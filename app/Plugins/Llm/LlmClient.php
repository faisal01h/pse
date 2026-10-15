<?php

namespace App\Plugins\Llm;

interface LlmClient
{
    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @return array<string, mixed>
     */
    public function chat(array $messages, array $tools = []): array;
}
