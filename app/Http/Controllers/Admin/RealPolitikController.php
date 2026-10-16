<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Plugins\PluginManager;
use App\Plugins\RealPolitik\Jobs\RealPolitikTickJob;
use App\Plugins\RealPolitik\Models\PowerSignal;

class RealPolitikController extends Controller
{
    public function __construct(private PluginManager $plugins) {}

    public function tick()
    {
        if (! $this->plugins->isEnabled('realpolitik')) {
            return back()->withErrors([
                'realpolitik' => 'REALPOLITIK belum diaktifkan.',
            ]);
        }

        RealPolitikTickJob::dispatch(force: true);

        return back()->with('success', 'Tick REALPOLITIK dimasukkan antrean.');
    }

    public function dismiss(PowerSignal $signal)
    {
        if (! $this->plugins->isEnabled('realpolitik')) {
            abort(404);
        }

        $signal->dismissed_at = now();
        $signal->save();

        return back()->with('success', 'Sinyal ditutup.');
    }
}
