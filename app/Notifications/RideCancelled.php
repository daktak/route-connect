<?php

namespace App\Notifications;

use App\Models\GroupRide;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RideCancelled extends Notification
{
    use Queueable;

    public function __construct(public GroupRide $ride, public string $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ride_cancelled',
            'ride_id' => $this->ride->id,
            'title' => $this->ride->title ?: $this->ride->route?->name,
            'message' => $this->message,
            'url' => route('rides.index'),
        ];
    }
}
