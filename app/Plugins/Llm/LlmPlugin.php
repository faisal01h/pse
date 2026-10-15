<?php

namespace App\Plugins\Llm;

use App\Plugins\Plugin;

class LlmPlugin implements Plugin
{
    public function __construct(private LlmService $llm) {}

    public function id(): string
    {
        return 'llm';
    }

    public function name(): string
    {
        return 'LLM';
    }

    public function boot(): void
    {
        $this->llm->migrateLegacySettings();
    }

    public function isConfigured(): bool
    {
        return $this->llm->isConfigured();
    }
}
