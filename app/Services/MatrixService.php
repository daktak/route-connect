<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MatrixService
{
    public function post(string $content, ?string $txnId = null): bool
    {
        $homeserver = trim((string) config('services.matrix.homeserver_url'));
        $homeserver = preg_replace('#^//+#', '', rtrim($homeserver, '/'));
        if ($homeserver !== '' && ! preg_match('#^https?://#i', $homeserver)) {
            $homeserver = 'https://'.$homeserver;
        }

        $username = (string) config('services.matrix.username');
        $password = (string) config('services.matrix.password');
        $roomIds = (array) config('services.matrix.room_ids', []);
        $timeout = (int) config('services.matrix.timeout', 5);
        $txnId = $txnId ?: (string) Str::uuid();

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

            $roomTxnId = $txnId.'-'.md5($roomId);

            try {
                $response = $this->send($homeserver, $token, $roomId, $roomTxnId, $content, $timeout);
            } catch (\Throwable $e) {
                Log::warning('Matrix send request failed', ['room' => $roomId, 'error' => $e->getMessage()]);
                $ok = false;

                continue;
            }

            if ($response->status() === 401 || $response->status() === 403) {
                Cache::forget($this->tokenKey($username));
                $token = $this->accessToken($homeserver, $username, $password, $timeout);
                if ($token === null) {
                    $ok = false;

                    continue;
                }

                try {
                    $response = $this->send($homeserver, $token, $roomId, $roomTxnId, $content, $timeout);
                } catch (\Throwable $e) {
                    Log::warning('Matrix retry failed', ['room' => $roomId, 'error' => $e->getMessage()]);
                    $ok = false;

                    continue;
                }
            }

            $retries = max(0, (int) config('services.matrix.retry_attempts', 2));
            for ($attempt = 0; $attempt < $retries && $response->status() === 429; $attempt++) {
                $retryAfterMs = (int) ($response->json('retry_after_ms') ?? 1000);
                $maxDelay = max(250, (int) config('services.matrix.max_retry_delay_ms', 30000));
                usleep((min(max($retryAfterMs, 250), $maxDelay) + random_int(0, 250)) * 1000);

                try {
                    $response = $this->send($homeserver, $token, $roomId, $roomTxnId, $content, $timeout);
                } catch (\Throwable $e) {
                    Log::warning('Matrix retry after rate limit failed', ['room' => $roomId, 'error' => $e->getMessage()]);
                    $ok = false;

                    continue 2;
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

    private function send(string $homeserver, string $token, string $roomId, string $txnId, string $content, int $timeout): Response
    {
        return Http::timeout($timeout)
            ->withToken($token)
            ->acceptJson()
            ->put("{$homeserver}/_matrix/client/v3/rooms/".urlencode($roomId).'/send/m.room.message/'.rawurlencode($txnId), [
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
