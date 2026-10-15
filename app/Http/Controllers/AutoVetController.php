<?php

namespace App\Http\Controllers;

use App\Models\BusinessAffiliation;
use App\Models\PublicRecord;
use App\Plugins\AutoVet\AutoVetService;
use App\Plugins\AutoVet\Jobs\AutoVetJob;
use App\Plugins\PluginManager;
use Illuminate\Http\Request;

class AutoVetController extends Controller
{
    public function __construct(
        private PluginManager $plugins,
        private AutoVetService $autoVet,
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
            return back()->withErrors([
                'autovet' => 'AutoVet belum diaktifkan atau strategi pemeriksaannya belum siap.',
            ]);
        }

        if (! $this->autoVet->isConfigured()) {
            $message = $this->autoVet->strategy() === AutoVetService::STRATEGY_LAYA
                ? 'Konfigurasi Laya belum lengkap (LAYA_URL).'
                : 'Konfigurasi LLM belum lengkap.';

            return back()->withErrors(['autovet' => $message]);
        }

        AutoVetJob::dispatch($subject->getMorphClass(), $subject->id);

        return back()->with('success', 'AutoVet dijalankan.');
    }
}
