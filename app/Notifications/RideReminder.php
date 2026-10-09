<?php

namespace App\Notifications;

use App\Models\GroupRide;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RideReminder extends Notification
{
    use Queueable;

    public function __construct(public GroupRide $ride) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ride_reminder',
            'ride_id' => $this->ride->id,
            'title' => $this->ride->title ?: $this->ride->route?->name,
            'message' => 'Reminder: "'.($this->ride->title ?: $this->ride->route?->name).'" starts '.$this->ride->ride_date->diffForHumans(),
            'url' => route('rides.show', $this->ride),
        ];
    }
}
