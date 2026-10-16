<?php

namespace App\Http\Controllers\SciPol;

use App\Http\Controllers\Controller;
use App\Models\PublicRecord;
use App\Plugins\PluginManager;
use App\Plugins\RealPolitik\CuiBonoAnalyzer;
use App\Plugins\RealPolitik\RealPolitikService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RealPolitikController extends Controller
{
    public function __construct(
        private PluginManager $plugins,
        private RealPolitikService $service,
        private CuiBonoAnalyzer $cuiBono,
    ) {}

    public function __invoke(Request $request): Response
    {
        abort_unless($this->plugins->isEnabled('realpolitik'), 404);

        $cuiBono = null;
        $recordId = $request->integer('record_id');
        if ($recordId > 0) {
            $record = PublicRecord::query()
                ->whereKey($recordId)
                ->where('status', PublicRecord::STATUS_PUBLISHED)
                ->first();
            if ($record) {
                $cuiBono = $this->cuiBono->forRecord($record);
            }
        }

        return Inertia::render('SciPol/RealPolitik', [
            'signals' => $this->service->presentSignals(),
            'briefs' => $this->service->presentBriefs(),
            'cuiBono' => $cuiBono,
            'pollSeconds' => 30,
            'publicBoardUrl' => app(\App\Web\WebFeatures::class)->realpolitikEnabled()
                ? route('realpolitik.board')
                : null,
        ]);
    }
}
