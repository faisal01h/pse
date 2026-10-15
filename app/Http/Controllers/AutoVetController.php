<?php

namespace App\Http\Controllers;

use App\Models\BusinessAffiliation;
use App\Models\PublicRecord;
use App\Plugins\AutoVet\Jobs\AutoVetJob;
use App\Plugins\PluginManager;
use App\Settings\SettingsRepository;
use Illuminate\Http\Request;

class AutoVetController extends Controller
{
    public function __construct(
        private PluginManager $plugins,
        private SettingsRepository $settings,
    ) {}

    public function runRecord(Request $request, PublicRecord $record)
    {
        return $this->dispatchRun($record);
    }

    public function runAffiliation(Request $request, BusinessAffiliation $affiliation)
    {
        return $this->dispatchRun($affiliation);
    }

    private function dispatchRun(PublicRecord|BusinessAffiliation $subject)
    {
        if (! $this->plugins->isEnabled('autovet')) {
            return back()->withErrors(['autovet' => 'AutoVet belum diaktifkan.']);
        }

        if ($this->settings->getString('autovet.llm.base_url') === ''
            || ! $this->settings->hasSecret('autovet.llm.api_key')
            || $this->settings->getString('autovet.llm.model') === '') {
            return back()->withErrors(['autovet' => 'Konfigurasi LLM AutoVet belum lengkap.']);
        }

        AutoVetJob::dispatch($subject->getMorphClass(), $subject->id);

        return back()->with('success', 'AutoVet dijalankan.');
    }
}
