<?php

namespace App\Inference;

use App\Models\Issue;
use App\Models\Politician;

interface ReportReader
{
    /**
     * @param  iterable<int, Politician>  $politicians
     * @param  iterable<int, Issue>  $issues
     * @return array<int, array{kind: string, payload: array<string, mixed>}>
     */
    public function read(string $text, int $subjectId, iterable $politicians, iterable $issues): array;

    /**
     * The reader that produced the last result: "local", or "http:{model}".
     */
    public function method(): ?string;
}
