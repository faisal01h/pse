<?php

namespace App\Search;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class HttpEmbedder implements Embedder
{
    public function __construct(
        private string $url,
        private string $key,
        private string $modelName,
        private ?int $dimensions = null,
        private int $timeout = 8,
    ) {}

    public function model(): string
    {
        $dimensions = $this->dimensions ? ':'.$this->dimensions : '';

        return $this->modelName.$dimensions;
    }

    public function semanticThreshold(): float
    {
        return 0.25;
    }

    public function embed(string $text): array
    {
        $payload = [
            'model' => $this->modelName,
            'input' => mb_substr($text, 0, 8000),
        ];

        if ($this->dimensions) {
            $payload['dimensions'] = $this->dimensions;
        }

        $response = Http::timeout($this->timeout)
            ->withToken($this->key)
            ->acceptJson()
            ->post($this->url, $payload);

        $response->throw();

        $embedding = $response->json('data.0.embedding');

        if (! is_array($embedding) || $embedding === []) {
            throw new RuntimeException('Embedding response did not include a vector.');
        }

        $vector = array_map(fn ($value) => (float) $value, array_values($embedding));

        return $this->normalize($vector);
    }

    /**
     * @param  array<int, float>  $vector
     * @return array<int, float>
     */
    private function normalize(array $vector): array
    {
        $sum = 0.0;

        foreach ($vector as $value) {
            $sum += $value * $value;
        }

        if ($sum <= 0.0) {
            return $vector;
        }

        $norm = sqrt($sum);

        foreach ($vector as $index => $value) {
            $vector[$index] = $value / $norm;
        }

        return $vector;
    }
}
