<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Models\RouteComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    public function store(Request $request, Route $route)
    {
        $request->validate([
            'content' => 'required|string|max:5000',
            'parent_id' => 'nullable|exists:route_comments,id',
        ]);

        $comment = RouteComment::create([
            'route_id' => $route->id,
            'user_id' => Auth::id(),
            'parent_id' => $request->parent_id,
            'content' => $request->content,
        ]);

        $comment->load('user');

        // Notification logic for replies
        if ($request->parent_id) {
            $parent = RouteComment::with('user')->find($request->parent_id);
            if ($parent && $parent->user_id !== Auth::id()) {
                // Notify parent comment author
            }
        }

        // Notify route author
        if ($route->user_id !== Auth::id()) {
            // Notify route author
        }

        return response()->json([
            'success' => true,
            'comment' => $comment,
        ]);
    }

    public function update(Request $request, RouteComment $comment)
    {
        $this->authorize('update', $comment);

        $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $comment->update([
            'content' => $request->content,
            'is_edited' => true,
        ]);

        return response()->json(['success' => true, 'comment' => $comment]);
    }

    public function destroy(RouteComment $comment)
    {
        $this->authorize('delete', $comment);
        $comment->delete();

        return response()->json(['success' => true]);
    }
}
