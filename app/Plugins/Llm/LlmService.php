<?php

namespace App\Plugins\Llm;

use App\Settings\SettingsRepository;
use RuntimeException;

class LlmService
{
    public function __construct(private SettingsRepository $settings) {}

    public function migrateLegacySettings(): void
    {
        $map = [
            'autovet.llm.base_url' => 'llm.base_url',
            'autovet.llm.model' => 'llm.model',
            'autovet.timeout' => 'llm.timeout',
            'autovet.max_steps' => 'llm.max_steps',
        ];

        foreach ($map as $old => $new) {
            $current = $this->settings->get($new);
            if ($current !== null && $current !== '') {
                continue;
            }
            $legacy = $this->settings->get($old);
            if ($legacy !== null && $legacy !== '') {
                $this->settings->set($new, $legacy);
            }
        }

        if (! $this->settings->hasSecret('llm.api_key') && $this->settings->hasSecret('autovet.llm.api_key')) {
            $this->settings->set('llm.api_key', $this->settings->get('autovet.llm.api_key'));
        }

        // Enable LLM if credentials exist and flag unset.
        if ($this->settings->get('llm.enabled') === null && $this->isConfigured(ignoreEnabled: true)) {
            $this->settings->set('llm.enabled', true);
        }
    }

    public function isConfigured(bool $ignoreEnabled = false): bool
    {
        if (! $ignoreEnabled && ! $this->settings->getBool('llm.enabled', false)) {
            return false;
        }

        return $this->settings->getString('llm.base_url') !== ''
            && $this->settings->hasSecret('llm.api_key')
            && $this->settings->getString('llm.model') !== '';
    }

    public function makeClient(): LlmClient
    {
        if (! $this->isConfigured(ignoreEnabled: true)) {
            throw new RuntimeException('LLM is not configured.');
        }

        return new OpenAiCompatibleClient(
            $this->settings->getString('llm.base_url'),
            $this->settings->getDecrypted('llm.api_key'),
            $this->settings->getString('llm.model'),
            $this->settings->getInt('llm.timeout', 120),
        );
    }

    public function makeAgentRunner(?int $maxSteps = null, int $maxDepth = 1): AgentRunner
    {
        return new AgentRunner(
            $this->makeClient(),
            $maxSteps ?? $this->settings->getInt('llm.max_steps', 12),
            $maxDepth,
        );
    }
}
