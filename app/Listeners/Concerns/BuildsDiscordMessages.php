<?php

namespace App\Listeners\Concerns;

use App\Jobs\SendDiscordMessage;
use App\Models\GroupRide;

trait BuildsDiscordMessages
{
    protected function rideName(GroupRide $ride): string
    {
        return $ride->title ?: $ride->route?->name ?: 'Ride #'.$ride->id;
    }

    protected function rideWhen(GroupRide $ride): string
    {
        return $ride->ride_date->timezone($ride->timezoneName())->format('D, M j, Y g:i A T');
    }

    /**
     * Build a Discord masked link: [label](url).
     */
    protected function link(string $label, string $url): string
    {
        return '['.$this->escapeLabel($label).']('.$url.')';
    }

    /**
     * Remove line breaks and escape Markdown characters in link labels.
     */
    protected function escapeLabel(string $value): string
    {
        $value = str_replace(["\r", "\n"], ' ', $value);

        return str_replace(['\\', '[', ']'], ['\\\\', '\[', '\]'], $value);
    }

    protected function sendToDiscord(string $content): void
    {
        SendDiscordMessage::dispatch($content);
    }
}
