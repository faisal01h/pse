<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Http;

trait FakesTurnstile
{
    protected function fakeTurnstile(string $action = 'report', string $hostname = 'localhost'): void
    {
        Http::fake([
            'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => true,
                'action' => $action,
                'hostname' => $hostname,
                'error-codes' => [],
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function withTurnstileToken(array $payload, string $token = 'test-turnstile-token'): array
    {
        $payload['cf-turnstile-response'] = $token;
        unset($payload['captcha']);

        return $payload;
    }
}
