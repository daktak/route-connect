<?php

namespace App\Notifications;

use App\Models\Route;
use App\Models\RouteComment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewComment extends Notification
{
    use Queueable;

    public function __construct(public Route $route, public RouteComment $comment, public User $author) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_comment',
            'route_id' => $this->route->id,
            'comment_id' => $this->comment->id,
            'title' => $this->route->name,
            'message' => "{$this->author->name} commented on \"{$this->route->name}\"",
            'url' => route('routes.show', $this->route).'#comment-'.$this->comment->id,
        ];
    }
}
