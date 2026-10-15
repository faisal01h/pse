<?php

namespace App\Plugins\AutoVet\Llm;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiCompatibleClient
{
    public function __construct(
        private string $baseUrl,
        private string $apiKey,
        private string $model,
        private int $timeout = 60,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @return array<string, mixed>
     */
    public function chat(array $messages, array $tools = []): array
    {
        $payload = [
            'model' => $this->model,
            'temperature' => 0,
            'messages' => $messages,
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $response = Http::timeout($this->timeout)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->post($this->chatEndpoint(), $payload)
            ->throw()
            ->json();

        if (! is_array($response)) {
            throw new RuntimeException('LLM response was not JSON.');
        }

        return $response;
    }

    private function chatEndpoint(): string
    {
        $url = rtrim($this->baseUrl, '/');

        if (str_ends_with($url, '/chat/completions')) {
            return $url;
        }

        if (str_ends_with($url, '/v1')) {
            return $url.'/chat/completions';
        }

        return $url.'/v1/chat/completions';
    }
}
