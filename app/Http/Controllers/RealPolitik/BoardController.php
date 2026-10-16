<?php

namespace App\Http\Controllers\RealPolitik;

use App\Http\Controllers\Controller;
use App\Models\PublicRecord;
use App\Plugins\PluginManager;
use App\Plugins\RealPolitik\CuiBonoAnalyzer;
use App\Plugins\RealPolitik\Models\WatchlistItem;
use App\Plugins\RealPolitik\RealPolitikService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BoardController extends Controller
{
    public function __construct(
        private RealPolitikService $service,
        private PluginManager $plugins,
        private CuiBonoAnalyzer $cuiBono,
    ) {}

    public function __invoke(Request $request): Response
    {
        abort_unless($this->plugins->isEnabled('realpolitik'), 404);

        $watchlist = [];
        if ($request->user()) {
            $watchlist = WatchlistItem::query()
                ->where('user_id', $request->user()->id)
                ->with('watchable')
                ->orderByDesc('id')
                ->get()
                ->map(fn (WatchlistItem $item) => [
                    'id' => $item->id,
                    'watchable_type' => $item->watchable_type,
                    'watchable_id' => $item->watchable_id,
                    'watchable_name' => $item->watchable?->name,
                ])
                ->all();
        }

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

        return Inertia::render('RealPolitik/Board', [
            'signals' => $this->service->presentSignals(),
            'briefs' => $this->service->presentBriefs(),
            'watchlist' => $watchlist,
            'cuiBono' => $cuiBono,
            'pollSeconds' => 20,
            'pluginEnabled' => true,
        ]);
    }
}
