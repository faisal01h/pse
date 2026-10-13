<?php

namespace App\Search;

interface Embedder
{
    public function model(): string;

    /**
     * @return array<int, float>
     */
    public function embed(string $text): array;

    public function semanticThreshold(): float;
}
