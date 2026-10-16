<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordService
{
    public function post(string $content): bool
    {
        $url = (string) config('services.discord.webhook_url');

        if ($url === '') {
            return false;
        }

        try {
            $response = Http::timeout((int) config('services.discord.timeout', 5))
                ->acceptJson()
                ->post($url, ['content' => $content]);
        } catch (\Throwable $e) {
            Log::warning('Discord webhook request failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('Discord webhook request returned an error', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            return false;
        }

        return true;
    }
}
