<?php

namespace App\Jobs;

use App\Services\DiscordService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class SendDiscordMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public ?bool $enabled = null;

    public function __construct(public string $content)
    {
        $this->enabled = (string) config('services.discord.webhook_url') !== '';
        $this->afterCommit();
    }

    public function handle(DiscordService $discord): void
    {
        if ($this->enabled === false || (string) config('services.discord.webhook_url') === '') {
            return;
        }

        if (! $discord->post($this->content)) {
            throw new RuntimeException('Failed to post Discord message');
        }
    }
}
