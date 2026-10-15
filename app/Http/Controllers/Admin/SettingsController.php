<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Plugins\Llm\LlmService;
use App\Plugins\PluginManager;
use App\Settings\SettingsRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function __construct(
        private SettingsRepository $settings,
        private LlmService $llm,
        private PluginManager $plugins,
    ) {}

    public function edit()
    {
        return redirect()->route('admin.settings.web');
    }

    public function editWeb()
    {
        return Inertia::render('Admin/Settings/Web', [
            'settings' => $this->webSettings(),
        ]);
    }

    public function updateWeb(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'web_submissions_enabled' => 'nullable|boolean',
            'web_tracking_enabled' => 'nullable|boolean',
            'web_disputes_enabled' => 'nullable|boolean',
            'web_scipol_enabled' => 'nullable|boolean',
            'web_turnstile_enabled' => 'nullable|boolean',
            'web_site_notice' => 'nullable|string|max:1000',
        ])->validate();

        $this->settings->setMany([
            'web.submissions_enabled' => $request->boolean('web_submissions_enabled'),
            'web.tracking_enabled' => $request->boolean('web_tracking_enabled'),
            'web.disputes_enabled' => $request->boolean('web_disputes_enabled'),
            'web.scipol_enabled' => $request->boolean('web_scipol_enabled'),
            'web.turnstile_enabled' => $request->boolean('web_turnstile_enabled'),
            'web.site_notice' => trim((string) ($validated['web_site_notice'] ?? '')),
        ]);

        return redirect()->route('admin.settings.web')->with('success', 'Pengaturan web disimpan.');
    }

    public function editLlm()
    {
        $this->llm->migrateLegacySettings();

        return Inertia::render('Admin/Settings/Llm', [
            'settings' => $this->llmSettings(),
            'llmReady' => $this->plugins->isEnabled('llm'),
        ]);
    }

    public function updateLlm(Request $request)
    {
        $this->llm->migrateLegacySettings();

        $validated = Validator::make($request->all(), [
            'llm_enabled' => 'nullable|boolean',
            'llm_base_url' => 'nullable|string|max:2048',
            'llm_api_key' => 'nullable|string|max:2048',
            'llm_model' => 'nullable|string|max:255',
            'llm_max_steps' => 'required|integer|min:1|max:40',
            'llm_timeout' => 'required|integer|min:5|max:600',
        ])->validate();

        $llmEnabled = $request->boolean('llm_enabled');
        $this->settings->setMany([
            'llm.enabled' => $llmEnabled,
            'llm.base_url' => rtrim((string) ($validated['llm_base_url'] ?? ''), '/'),
            'llm.model' => (string) ($validated['llm_model'] ?? ''),
            'llm.max_steps' => (int) $validated['llm_max_steps'],
            'llm.timeout' => (int) $validated['llm_timeout'],
        ]);

        if (! $llmEnabled) {
            $this->settings->setMany([
                'autovet.enabled' => false,
                'newsradar.enabled' => false,
            ]);
        }

        $apiKey = $validated['llm_api_key'] ?? null;
        if (is_string($apiKey) && $apiKey !== '' && $apiKey !== '********') {
            $this->settings->setEncrypted('llm.api_key', $apiKey);
        }

        return redirect()->route('admin.settings.llm')->with('success', 'Pengaturan LLM disimpan.');
    }

    public function editAutoVet()
    {
        $this->llm->migrateLegacySettings();

        return Inertia::render('Admin/Settings/AutoVet', [
            'settings' => $this->autoVetSettings(),
            'llmReady' => $this->plugins->isEnabled('llm'),
            'layaReady' => trim((string) config('laya.url', '')) !== '',
        ]);
    }

    public function updateAutoVet(Request $request)
    {
        $this->llm->migrateLegacySettings();

        $validated = Validator::make($request->all(), [
            'autovet_enabled' => 'nullable|boolean',
            'autovet_strategy' => ['required', Rule::in(['llm', 'laya'])],
            'autovet_mode' => ['required', Rule::in(['suggest_only', 'auto_decide', 'hybrid'])],
            'autovet_hybrid_min_confidence' => 'required|numeric|min:0|max:1',
            'autovet_laya_min_confidence' => 'nullable|numeric|min:0|max:1',
            'autovet_tools_terminal_enabled' => 'nullable|boolean',
            'autovet_tools_browser_enabled' => 'nullable|boolean',
            'autovet_max_steps' => 'nullable|integer|min:1|max:40',
            'autovet_timeout' => 'nullable|integer|min:5|max:600',
        ])->validate();

        $strategy = $validated['autovet_strategy'];
        $strategyReady = $strategy === 'laya'
            ? trim((string) config('laya.url', '')) !== ''
            : $this->plugins->isEnabled('llm');

        $this->settings->setMany([
            'autovet.enabled' => $strategyReady && $request->boolean('autovet_enabled'),
            'autovet.strategy' => $strategy,
            'autovet.mode' => $validated['autovet_mode'],
            'autovet.hybrid_min_confidence' => (float) $validated['autovet_hybrid_min_confidence'],
            'autovet.laya_min_confidence' => (float) ($validated['autovet_laya_min_confidence'] ?? 0.55),
            'autovet.tools.terminal_enabled' => $request->boolean('autovet_tools_terminal_enabled'),
            'autovet.tools.browser_enabled' => $request->boolean('autovet_tools_browser_enabled'),
            'autovet.max_steps' => (int) ($validated['autovet_max_steps'] ?? $this->settings->getInt('llm.max_steps', 12)),
            'autovet.timeout' => (int) ($validated['autovet_timeout'] ?? $this->settings->getInt('llm.timeout', 120)),
        ]);

        return redirect()->route('admin.settings.autovet')->with('success', 'Pengaturan AutoVet disimpan.');
    }

    public function editNewsRadar()
    {
        $this->llm->migrateLegacySettings();

        return Inertia::render('Admin/Settings/NewsRadar', [
            'settings' => $this->newsRadarSettings(),
            'llmReady' => $this->plugins->isEnabled('llm'),
        ]);
    }

    public function updateNewsRadar(Request $request)
    {
        $this->llm->migrateLegacySettings();

        $validated = Validator::make($request->all(), [
            'newsradar_enabled' => 'nullable|boolean',
            'newsradar_feeds' => 'nullable|string|max:20000',
            'newsradar_frequency' => ['required', Rule::in(['15m', '1h', '6h', 'daily', 'custom'])],
            'newsradar_custom_minutes' => 'nullable|integer|min:5|max:10080',
        ])->validate();

        $feeds = $this->parseFeeds((string) ($validated['newsradar_feeds'] ?? ''));
        $frequency = $validated['newsradar_frequency'];
        $customMinutes = (int) ($validated['newsradar_custom_minutes'] ?? 60);
        if ($frequency === 'custom' && $customMinutes < 5) {
            $customMinutes = 5;
        }

        $llmReady = $this->plugins->isEnabled('llm');

        $this->settings->setMany([
            'newsradar.enabled' => $llmReady && $request->boolean('newsradar_enabled'),
            'newsradar.feeds' => $feeds,
            'newsradar.frequency' => $frequency,
            'newsradar.custom_minutes' => $customMinutes,
        ]);

        return redirect()->route('admin.settings.newsradar')->with('success', 'Pengaturan NewsRadar disimpan.');
    }

    /**
     * @return list<string>
     */
    private function parseFeeds(string $raw): array
    {
        $parts = preg_split('/[\r\n,]+/', $raw) ?: [];

        return collect($parts)
            ->map(fn ($url) => trim((string) $url))
            ->filter(fn ($url) => $url !== '' && filter_var($url, FILTER_VALIDATE_URL))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function webSettings(): array
    {
        $has = fn (string $key) => array_key_exists($key, $this->settings->all());

        return [
            'web_submissions_enabled' => $has('web.submissions_enabled')
                ? $this->settings->getBool('web.submissions_enabled', true)
                : true,
            'web_tracking_enabled' => $has('web.tracking_enabled')
                ? $this->settings->getBool('web.tracking_enabled', true)
                : true,
            'web_disputes_enabled' => $has('web.disputes_enabled')
                ? $this->settings->getBool('web.disputes_enabled', true)
                : true,
            'web_scipol_enabled' => $has('web.scipol_enabled')
                ? $this->settings->getBool('web.scipol_enabled', true)
                : true,
            'web_turnstile_enabled' => $has('web.turnstile_enabled')
                ? $this->settings->getBool('web.turnstile_enabled', true)
                : true,
            'web_site_notice' => $this->settings->getString('web.site_notice', ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function llmSettings(): array
    {
        return [
            'llm_enabled' => $this->settings->getBool('llm.enabled', false),
            'llm_base_url' => $this->settings->getString('llm.base_url', ''),
            'llm_api_key_set' => $this->settings->hasSecret('llm.api_key'),
            'llm_model' => $this->settings->getString('llm.model', ''),
            'llm_max_steps' => $this->settings->getInt('llm.max_steps', 12),
            'llm_timeout' => $this->settings->getInt('llm.timeout', 120),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function autoVetSettings(): array
    {
        return [
            'autovet_enabled' => $this->settings->getBool('autovet.enabled', false),
            'autovet_strategy' => $this->settings->getString('autovet.strategy', 'llm'),
            'autovet_mode' => $this->settings->getString('autovet.mode', 'suggest_only'),
            'autovet_hybrid_min_confidence' => $this->settings->getFloat('autovet.hybrid_min_confidence', 0.8),
            'autovet_laya_min_confidence' => $this->settings->getFloat('autovet.laya_min_confidence', 0.55),
            'autovet_tools_terminal_enabled' => $this->settings->getBool('autovet.tools.terminal_enabled', false),
            'autovet_tools_browser_enabled' => $this->settings->getBool('autovet.tools.browser_enabled', false),
            'autovet_max_steps' => $this->settings->getInt('autovet.max_steps', $this->settings->getInt('llm.max_steps', 12)),
            'autovet_timeout' => $this->settings->getInt('autovet.timeout', $this->settings->getInt('llm.timeout', 120)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function newsRadarSettings(): array
    {
        $feeds = $this->settings->get('newsradar.feeds', []);
        if (! is_array($feeds)) {
            $feeds = [];
        }

        return [
            'newsradar_enabled' => $this->settings->getBool('newsradar.enabled', false),
            'newsradar_feeds' => implode("\n", $feeds),
            'newsradar_frequency' => $this->settings->getString('newsradar.frequency', '1h'),
            'newsradar_custom_minutes' => $this->settings->getInt('newsradar.custom_minutes', 60),
            'newsradar_last_run_at' => $this->settings->getString('newsradar.last_run_at', ''),
        ];
    }
}
