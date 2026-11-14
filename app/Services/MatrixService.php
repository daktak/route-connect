<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MatrixService
{
    public function post(string $content): bool
    {
        $homeserver = rtrim((string) config('services.matrix.homeserver_url'), '/');
        if ($homeserver !== '' && ! preg_match('#^https?://#i', $homeserver)) {
            $homeserver = 'https://'.$homeserver;
        }

        $username = (string) config('services.matrix.username');
        $password = (string) config('services.matrix.password');
        $roomIds = (array) config('services.matrix.room_ids', []);
        $timeout = (int) config('services.matrix.timeout', 5);

        if ($homeserver === '' || $username === '' || $password === '' || $roomIds === []) {
            return false;
        }

        $token = $this->accessToken($homeserver, $username, $password, $timeout);
        if ($token === null) {
            return false;
        }

        $ok = true;
        foreach ($roomIds as $roomId) {
            $roomId = trim((string) $roomId);
            if ($roomId === '') {
                continue;
            }

            try {
                $response = $this->send($homeserver, $token, $roomId, $content, $timeout);
            } catch (\Throwable $e) {
                Log::warning('Matrix send request failed', ['room' => $roomId, 'error' => $e->getMessage()]);
                $ok = false;
                continue;
            }

            if ($response->status() === 401 || $response->status() === 403) {
                Cache::forget($this->tokenKey($username));
                $token = $this->accessToken($homeserver, $username, $password, $timeout);
                if ($token !== null) {
                    try {
                        $response = $this->send($homeserver, $token, $roomId, $content, $timeout);
                    } catch (\Throwable $e) {
                        Log::warning('Matrix retry failed', ['room' => $roomId, 'error' => $e->getMessage()]);
                    }
                }
            }

            if ($response->status() === 429) {
                $retryAfterMs = (int) ($response->json('retry_after_ms') ?? 1000);
                usleep(min(max($retryAfterMs, 250), 5000) * 1000);
                try {
                    $response = $this->send($homeserver, $token, $roomId, $content, $timeout);
                } catch (\Throwable $e) {
                    Log::warning('Matrix retry after rate limit failed', ['room' => $roomId, 'error' => $e->getMessage()]);
                }
            }

            if (! $response->successful()) {
                Log::warning('Matrix send returned an error', [
                    'room' => $roomId,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);
                $ok = false;
            }
        }

        return $ok;
    }

    private function send(string $homeserver, string $token, string $roomId, string $content, int $timeout): Response
    {
        return Http::timeout($timeout)
            ->withToken($token)
            ->acceptJson()
            ->post("{$homeserver}/_matrix/client/v3/rooms/".urlencode($roomId)."/send/m.room.message", [
                'msgtype' => 'm.text',
                'body' => $content,
                'format' => 'org.matrix.custom.html',
                'formatted_body' => $this->formattedBody($content),
            ]);
    }

    private function formattedBody(string $content): string
    {
        $html = htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = str_replace(['\\\\', '\\[', '\\]'], ['\\', '[', ']'], $html);
        $html = preg_replace('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/', '<a href="$2">$1</a>', $html);

        return nl2br($html);
    }

    private function accessToken(string $homeserver, string $username, string $password, int $timeout): ?string
    {
        $key = $this->tokenKey($username);
        $cached = Cache::get($key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->post("{$homeserver}/_matrix/client/v3/login", [
                    'type' => 'm.login.password',
                    'user' => $username,
                    'password' => $password,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Matrix login request failed', ['error' => $e->getMessage()]);
            return null;
        }

        if (! $response->successful()) {
            Log::warning('Matrix login returned an error', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);
            return null;
        }

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            return null;
        }

        Cache::put($key, $token, now()->addHour());

        return $token;
    }

    private function tokenKey(string $username): string
    {
        return 'matrix.access_token.'.md5($username);
    }
}
