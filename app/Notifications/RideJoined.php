<?php

namespace App\Notifications;

use App\Models\GroupRide;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RideJoined extends Notification
{
    use Queueable;

    public function __construct(public GroupRide $ride, public User $attendee) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ride_joined',
            'ride_id' => $this->ride->id,
            'title' => $this->ride->title ?: $this->ride->route?->name,
            'message' => "{$this->attendee->name} joined your ride \"".($this->ride->title ?: $this->ride->route?->name).'"',
            'url' => route('rides.show', $this->ride),
        ];
    }
}
