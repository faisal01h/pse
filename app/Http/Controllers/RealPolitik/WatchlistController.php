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
use Illuminate\Validation\ValidationException;

class WatchlistController extends Controller
{
    public function __construct(private PluginManager $plugins) {}

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->plugins->isEnabled('realpolitik'), 404);

        $validated = $request->validate([
            'watchable_type' => ['required', Rule::in(['politician', 'party', 'topic'])],
            'watchable_name' => 'required|string|max:255',
        ]);

        $type = match ($validated['watchable_type']) {
            'politician' => Politician::class,
            'party' => Party::class,
            'topic' => Topic::class,
        };

        $name = trim($validated['watchable_name']);
        $matches = $type::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->limit(2)
            ->get();

        if ($matches->isEmpty()) {
            throw ValidationException::withMessages([
                'watchable_name' => 'Nama tidak ditemukan. Periksa ejaan atau jenis yang dipilih.',
            ]);
        }

        if ($matches->count() > 1) {
            throw ValidationException::withMessages([
                'watchable_name' => 'Ada lebih dari satu data dengan nama tersebut.',
            ]);
        }

        WatchlistItem::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'watchable_type' => $type,
            'watchable_id' => $matches->first()->getKey(),
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
