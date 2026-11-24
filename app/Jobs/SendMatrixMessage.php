<?php

namespace App\Jobs;

use App\Services\MatrixService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use RuntimeException;

class SendMatrixMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public string $txnId;

    public ?bool $enabled = null;

    public function __construct(public string $content, ?string $txnId = null)
    {
        $this->txnId = $txnId ?: (string) Str::uuid();

        $homeserver = trim((string) config('services.matrix.homeserver_url'));
        $username = (string) config('services.matrix.username');
        $password = (string) config('services.matrix.password');
        $roomIds = (array) config('services.matrix.room_ids', []);

        $this->enabled = $homeserver !== '' && $username !== '' && $password !== '' && $roomIds !== [];
    }

    public function handle(MatrixService $matrix): void
    {
        $homeserver = trim((string) config('services.matrix.homeserver_url'));
        $username = (string) config('services.matrix.username');
        $password = (string) config('services.matrix.password');
        $roomIds = (array) config('services.matrix.room_ids', []);

        if ($this->enabled === false || $homeserver === '' || $username === '' || $password === '' || $roomIds === []) {
            return;
        }

        if (! $matrix->post($this->content, $this->txnId)) {
            throw new RuntimeException('Failed to post Matrix message');
        }
    }
}
