<?php

namespace App\Listeners;

use App\Events\CommentCreated;
use App\Listeners\Concerns\BuildsDiscordMessages;
use App\Models\RouteComment;
use App\Notifications\CommentReply as CommentReplyNotification;
use App\Notifications\NewComment as NewCommentNotification;

class NotifyCommentCreated
{
    use BuildsDiscordMessages;

    public function handle(CommentCreated $event): void
    {
        $route = $event->route;
        $comment = $event->comment;
        $author = $event->author;

        $notified = [$author->id];

        if ($comment->parent_id) {
            $parent = RouteComment::with('user')->find($comment->parent_id);

            if ($parent?->user && ! in_array($parent->user_id, $notified)) {
                $parent->user->notify(new CommentReplyNotification($route, $comment, $author));
                $notified[] = $parent->user_id;
            }
        }

        if ($route->user && ! in_array($route->user_id, $notified)) {
            $route->user->notify(new NewCommentNotification($route, $comment, $author));
        }

        $action = $comment->parent_id
            ? "{$author->name} replied to a comment on"
            : "{$author->name} commented on";

        $this->sendToDiscord(
            $action.' '.$this->link($route->name, route('routes.show', $route).'#comment-'.$comment->id).'.'
        );
    }
}
