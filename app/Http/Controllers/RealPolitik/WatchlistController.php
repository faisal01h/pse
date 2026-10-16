<?php

namespace App\Http\Controllers\RealPolitik;

use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Models\Politician;
use App\Models\Topic;
use App\Plugins\PluginManager;
use App\Plugins\RealPolitik\Models\WatchlistItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WatchlistController extends Controller
{
    public function __construct(private PluginManager $plugins) {}

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->plugins->isEnabled('realpolitik'), 404);

        $validated = $request->validate([
            'watchable_type' => ['required', Rule::in(['politician', 'party', 'topic'])],
            'watchable_id' => 'required|integer|min:1',
        ]);

        $type = match ($validated['watchable_type']) {
            'politician' => Politician::class,
            'party' => Party::class,
            'topic' => Topic::class,
        };

        abort_unless($type::query()->whereKey($validated['watchable_id'])->exists(), 404);

        WatchlistItem::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'watchable_type' => $type,
            'watchable_id' => $validated['watchable_id'],
        ]);

        return back();
    }

    public function destroy(Request $request, WatchlistItem $watchlist): RedirectResponse
    {
        abort_unless($this->plugins->isEnabled('realpolitik'), 404);
        abort_unless($watchlist->user_id === $request->user()->id, 404);
        $watchlist->delete();

        return back();
    }
}
