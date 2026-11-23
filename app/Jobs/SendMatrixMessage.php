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

    public function __construct(public string $content, ?string $txnId = null)
    {
        $this->txnId = $txnId ?: (string) Str::uuid();
    }

    public function handle(MatrixService $matrix): void
    {
        if (! $matrix->post($this->content, $this->txnId)) {
            throw new RuntimeException('Failed to post Matrix message');
        }
    }
}
