<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Settings\SettingsRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function __construct(private SettingsRepository $settings) {}

    public function edit()
    {
        return Inertia::render('Admin/Settings', [
            'settings' => $this->publicSettings(),
        ]);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'autovet_enabled' => 'nullable|boolean',
            'autovet_mode' => ['required', Rule::in(['suggest_only', 'auto_decide', 'hybrid'])],
            'autovet_hybrid_min_confidence' => 'required|numeric|min:0|max:1',
            'autovet_llm_base_url' => 'nullable|string|max:2048',
            'autovet_llm_api_key' => 'nullable|string|max:2048',
            'autovet_llm_model' => 'nullable|string|max:255',
            'autovet_tools_terminal_enabled' => 'nullable|boolean',
            'autovet_tools_browser_enabled' => 'nullable|boolean',
            'autovet_max_steps' => 'required|integer|min:1|max:40',
            'autovet_timeout' => 'required|integer|min:5|max:600',
        ]);

        $validated = $validator->validate();

        $this->settings->setMany([
            'autovet.enabled' => $request->boolean('autovet_enabled'),
            'autovet.mode' => $validated['autovet_mode'],
            'autovet.hybrid_min_confidence' => (float) $validated['autovet_hybrid_min_confidence'],
            'autovet.llm.base_url' => rtrim((string) ($validated['autovet_llm_base_url'] ?? ''), '/'),
            'autovet.llm.model' => (string) ($validated['autovet_llm_model'] ?? ''),
            'autovet.tools.terminal_enabled' => $request->boolean('autovet_tools_terminal_enabled'),
            'autovet.tools.browser_enabled' => $request->boolean('autovet_tools_browser_enabled'),
            'autovet.max_steps' => (int) $validated['autovet_max_steps'],
            'autovet.timeout' => (int) $validated['autovet_timeout'],
        ]);

        $apiKey = $validated['autovet_llm_api_key'] ?? null;
        if (is_string($apiKey) && $apiKey !== '' && $apiKey !== '********') {
            $this->settings->setEncrypted('autovet.llm.api_key', $apiKey);
        }

        return redirect()->route('admin.settings.edit')->with('success', 'Pengaturan disimpan.');
    }

    /**
     * @return array<string, mixed>
     */
    private function publicSettings(): array
    {
        return [
            'autovet_enabled' => $this->settings->getBool('autovet.enabled', false),
            'autovet_mode' => $this->settings->getString('autovet.mode', 'suggest_only'),
            'autovet_hybrid_min_confidence' => $this->settings->getFloat('autovet.hybrid_min_confidence', 0.8),
            'autovet_llm_base_url' => $this->settings->getString('autovet.llm.base_url', ''),
            'autovet_llm_api_key_set' => $this->settings->hasSecret('autovet.llm.api_key'),
            'autovet_llm_model' => $this->settings->getString('autovet.llm.model', ''),
            'autovet_tools_terminal_enabled' => $this->settings->getBool('autovet.tools.terminal_enabled', false),
            'autovet_tools_browser_enabled' => $this->settings->getBool('autovet.tools.browser_enabled', false),
            'autovet_max_steps' => $this->settings->getInt('autovet.max_steps', 12),
            'autovet_timeout' => $this->settings->getInt('autovet.timeout', 120),
        ];
    }
}
