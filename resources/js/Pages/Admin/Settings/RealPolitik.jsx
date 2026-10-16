import Authenticated from '@/Layouts/Authenticated';
import SettingsNav from '@/Components/SettingsNav';
import ValidationErrors from '@/Components/ValidationErrors';
import { Inertia } from '@inertiajs/inertia';
import { Head, useForm, usePage } from '@inertiajs/inertia-react';
import { useState } from 'react';

export default function RealPolitik({ auth, settings, pluginReady }) {
    const { flash } = usePage().props;
    const [ticking, setTicking] = useState(false);
    const { data, setData, post, processing, errors } = useForm({
        realpolitik_enabled: Boolean(settings.realpolitik_enabled),
        realpolitik_sitrep_enabled: Boolean(settings.realpolitik_sitrep_enabled),
        realpolitik_window_hours: settings.realpolitik_window_hours ?? 24,
        realpolitik_tick_minutes: settings.realpolitik_tick_minutes ?? 15,
        realpolitik_shock_ratio: settings.realpolitik_shock_ratio ?? 3,
        realpolitik_shock_min_count: settings.realpolitik_shock_min_count ?? 3,
        realpolitik_baseline_days: settings.realpolitik_baseline_days ?? 30,
        realpolitik_sitrep_max_records: settings.realpolitik_sitrep_max_records ?? 20,
    });

    function submit(event) {
        event.preventDefault();
        post(route('admin.settings.realpolitik.update'));
    }

    function runTick(event) {
        event.preventDefault();
        setTicking(true);
        Inertia.post(route('admin.realpolitik.tick'), {}, {
            preserveScroll: true,
            onFinish: () => setTicking(false),
        });
    }

    return (
        <Authenticated auth={auth} header={<h1 className="text-4xl font-bold leading-tight">Pengaturan</h1>}>
            <Head title="Pengaturan REALPOLITIK" />
            <p className="max-w-2xl">Analisis kekuasaan hampir-realtime di atas arsip dan linimasa hubungan.</p>
            <SettingsNav />

            {flash?.success ? <p className="mt-4 border-l-8 border-brand bg-canvas px-4 py-3 font-bold">{flash.success}</p> : null}
            <ValidationErrors errors={errors} />

            <form onSubmit={submit} className="mt-8 max-w-2xl flex flex-col gap-6">
                <fieldset className="border-2 border-ink p-4">
                    <legend className="px-2 font-bold">REALPOLITIK</legend>
                    <label className="flex items-center gap-3">
                        <input
                            type="checkbox"
                            checked={data.realpolitik_enabled}
                            onChange={(event) => setData('realpolitik_enabled', event.target.checked)}
                        />
                        Aktifkan plugin REALPOLITIK
                    </label>
                    <label className="mt-3 flex items-center gap-3">
                        <input
                            type="checkbox"
                            checked={data.realpolitik_sitrep_enabled}
                            onChange={(event) => setData('realpolitik_sitrep_enabled', event.target.checked)}
                        />
                        Hasilkan sitrep LLM (membutuhkan LLM siap)
                    </label>
                    {!pluginReady ? (
                        <p className="mt-2 text-sm text-muted">Plugin nonaktif sampai kotak di atas dicentang.</p>
                    ) : null}

                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label htmlFor="window_hours" className="mb-1 block font-bold">Jendela analisis (jam)</label>
                            <input
                                id="window_hours"
                                type="number"
                                min="1"
                                max="168"
                                className="gov-input"
                                value={data.realpolitik_window_hours}
                                onChange={(event) => setData('realpolitik_window_hours', event.target.value)}
                            />
                        </div>
                        <div>
                            <label htmlFor="tick_minutes" className="mb-1 block font-bold">Interval tick (menit)</label>
                            <input
                                id="tick_minutes"
                                type="number"
                                min="5"
                                max="1440"
                                className="gov-input"
                                value={data.realpolitik_tick_minutes}
                                onChange={(event) => setData('realpolitik_tick_minutes', event.target.value)}
                            />
                        </div>
                        <div>
                            <label htmlFor="shock_ratio" className="mb-1 block font-bold">Ambang rasio shock</label>
                            <input
                                id="shock_ratio"
                                type="number"
                                min="1.5"
                                step="0.1"
                                className="gov-input"
                                value={data.realpolitik_shock_ratio}
                                onChange={(event) => setData('realpolitik_shock_ratio', event.target.value)}
                            />
                        </div>
                        <div>
                            <label htmlFor="shock_min" className="mb-1 block font-bold">Min. catatan untuk shock</label>
                            <input
                                id="shock_min"
                                type="number"
                                min="2"
                                className="gov-input"
                                value={data.realpolitik_shock_min_count}
                                onChange={(event) => setData('realpolitik_shock_min_count', event.target.value)}
                            />
                        </div>
                        <div>
                            <label htmlFor="baseline_days" className="mb-1 block font-bold">Baseline (hari)</label>
                            <input
                                id="baseline_days"
                                type="number"
                                min="7"
                                max="180"
                                className="gov-input"
                                value={data.realpolitik_baseline_days}
                                onChange={(event) => setData('realpolitik_baseline_days', event.target.value)}
                            />
                        </div>
                        <div>
                            <label htmlFor="sitrep_max" className="mb-1 block font-bold">Max catatan sitrep</label>
                            <input
                                id="sitrep_max"
                                type="number"
                                min="5"
                                max="40"
                                className="gov-input"
                                value={data.realpolitik_sitrep_max_records}
                                onChange={(event) => setData('realpolitik_sitrep_max_records', event.target.value)}
                            />
                        </div>
                    </div>

                    {settings.realpolitik_last_run_at ? (
                        <p className="mt-4 text-sm text-muted">Tick terakhir: {settings.realpolitik_last_run_at}</p>
                    ) : null}
                </fieldset>

                <div className="flex flex-wrap gap-3">
                    <button className="gov-button" disabled={processing}>Simpan</button>
                    <button
                        type="button"
                        className="gov-button"
                        disabled={!data.realpolitik_enabled || ticking}
                        onClick={runTick}
                    >
                        {ticking ? 'Mengantre…' : 'Jalankan tick sekarang'}
                    </button>
                </div>
            </form>
        </Authenticated>
    );
}
