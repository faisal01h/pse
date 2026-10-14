<?php

namespace App\Security;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Validator;

class Turnstile
{
    public function assert(Request $request, Validator $validator, string $action): void
    {
        $token = (string) $request->input('cf-turnstile-response', '');
        $secret = (string) config('turnstile.secret');
        $hostnames = config('turnstile.hostnames', []);

        if (
            $token === ''
            || strlen($token) > 2048
            || $secret === ''
            || ! is_array($hostnames)
            || $hostnames === []
        ) {
            $validator->errors()->add('cf-turnstile-response', 'Verifikasi keamanan gagal. Muat ulang dan coba lagi.');

            return;
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);
        } catch (ConnectionException) {
            $validator->errors()->add('cf-turnstile-response', 'Verifikasi keamanan gagal. Muat ulang dan coba lagi.');

            return;
        }

        if (! $response->ok()) {
            $validator->errors()->add('cf-turnstile-response', 'Verifikasi keamanan gagal. Muat ulang dan coba lagi.');

            return;
        }

        $result = $response->json();

        if (
            ! is_array($result)
            || ($result['success'] ?? false) !== true
            || ($result['action'] ?? null) !== $action
            || ! in_array((string) ($result['hostname'] ?? ''), $hostnames, true)
        ) {
            $validator->errors()->add('cf-turnstile-response', 'Verifikasi keamanan gagal. Muat ulang dan coba lagi.');
        }
    }
}
