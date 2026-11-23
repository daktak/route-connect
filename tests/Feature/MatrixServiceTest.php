<?php

namespace Tests\Feature;

use App\Jobs\SendMatrixMessage;
use App\Services\MatrixService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MatrixServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'services.matrix.homeserver_url' => 'matrix.example.com',
            'services.matrix.username' => '@bot:example.com',
            'services.matrix.password' => 'secret',
            'services.matrix.room_ids' => ['!room:example.com'],
            'services.matrix.timeout' => 5,
            'services.matrix.retry_attempts' => 2,
            'services.matrix.max_retry_delay_ms' => 1000,
        ]);
    }

    private function fakeLogin(): void
    {
        Http::fake([
            'https://matrix.example.com/_matrix/client/v3/login' => Http::response(['access_token' => 'token'], 200),
            'https://matrix.example.com/_matrix/client/v3/rooms/*' => Http::response(['event_id' => '$event'], 200),
        ]);
    }

    public function test_it_normalises_bare_homeserver_and_uses_a_transaction_id(): void
    {
        $this->fakeLogin();
        $txnId = 'txn-1-'.md5('!room:example.com');

        $this->assertTrue(app(MatrixService::class)->post('hello', 'txn-1'));

        Http::assertSent(fn (Request $request) => $request->url() === 'https://matrix.example.com/_matrix/client/v3/login');
        Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
            && str_contains($request->url(), '/send/m.room.message/'.$txnId)
            && $request['body'] === 'hello');
    }

    public function test_it_retries_after_a_rate_limit_response(): void
    {
        Http::fake([
            'https://matrix.example.com/_matrix/client/v3/login' => Http::response(['access_token' => 'token'], 200),
            'https://matrix.example.com/_matrix/client/v3/rooms/*' => Http::sequence([
                Http::response(['errcode' => 'M_LIMIT_EXCEEDED', 'retry_after_ms' => 1], 429),
                Http::response(['event_id' => '$event'], 200),
            ]),
        ]);

        $this->assertTrue(app(MatrixService::class)->post('hello', 'txn-2'));
        Http::assertSentCount(3);
    }

    public function test_job_fails_when_the_service_cannot_post(): void
    {
        Http::fake([
            'https://matrix.example.com/_matrix/client/v3/login' => Http::response(['access_token' => 'token'], 200),
            'https://matrix.example.com/_matrix/client/v3/rooms/*' => Http::response(['error' => 'nope'], 500),
        ]);

        $this->expectException(\RuntimeException::class);

        (new SendMatrixMessage('hello', 'txn-3'))->handle(app(MatrixService::class));
    }

    public function test_it_is_a_noop_without_configuration(): void
    {
        config(['services.matrix.homeserver_url' => null]);
        Http::fake();

        $this->assertFalse(app(MatrixService::class)->post('hello'));
        Http::assertNothingSent();
    }
}
