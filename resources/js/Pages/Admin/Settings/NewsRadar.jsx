import Authenticated from '@/Layouts/Authenticated';
import SettingsNav from '@/Components/SettingsNav';
import ValidationErrors from '@/Components/ValidationErrors';
import { Inertia } from '@inertiajs/inertia';
import { Head, useForm, usePage } from '@inertiajs/inertia-react';
import { useState } from 'react';

export default function NewsRadar({ auth, settings, llmReady }) {
    const { flash } = usePage().props;
    const [hunting, setHunting] = useState(false);
    const { data, setData, post, processing, errors } = useForm({
        newsradar_enabled: Boolean(settings.newsradar_enabled),
        newsradar_feeds: settings.newsradar_feeds || '',
        newsradar_frequency: settings.newsradar_frequency || '1h',
        newsradar_custom_minutes: settings.newsradar_custom_minutes ?? 60,
    });

    const newsRadarReady = Boolean(llmReady && settings.newsradar_enabled);

    function submit(event) {
        event.preventDefault();
        post(route('admin.settings.newsradar.update'));
    }

    function runHunt(event) {
        event.preventDefault();
        setHunting(true);
        Inertia.post(route('admin.newsradar.hunt'), {}, {
            preserveScroll: true,
            onFinish: () => setHunting(false),
        });
    }

    return (
        <Authenticated auth={auth} header={<h1 className="text-4xl font-bold leading-tight">Pengaturan</h1>}>
            <Head title="Pengaturan NewsRadar" />
            <p className="max-w-2xl">Feed RSS dan frekuensi pencarian berita untuk pengajuan otomatis.</p>
            <SettingsNav />

            {flash?.success ? <p className="mt-4 border-l-8 border-brand bg-canvas px-4 py-3 font-bold">{flash.success}</p> : null}
            <ValidationErrors errors={errors} />

            <form onSubmit={submit} className={`mt-8 max-w-2xl flex flex-col gap-6 ${llmReady ? '' : 'opacity-60'}`}>
                <fieldset className="border-2 border-ink p-4">
                    <legend className="px-2 font-bold">NewsRadar</legend>
                    <label className="flex items-center gap-3">
                        <input
                            type="checkbox"
                            checked={data.newsradar_enabled}
                            disabled={!llmReady}
                            onChange={(event) => setData('newsradar_enabled', event.target.checked)}
                        />
                        Aktifkan NewsRadar
                    </label>
                    {!llmReady ? (
                        <p className="mt-2 text-sm text-muted">NewsRadar hanya tersedia saat LLM aktif dan siap.</p>
                    ) : null}

                    <div className="mt-4">
                        <label htmlFor="newsradar_feeds" className="mb-1 block font-bold">URL feed RSS (satu per baris)</label>
                        <textarea
                            id="newsradar_feeds"
                            className="gov-input min-h-[8rem]"
                            disabled={!llmReady}
                            value={data.newsradar_feeds}
                            onChange={(event) => setData('newsradar_feeds', event.target.value)}
                            placeholder="https://example.com/rss"
                        />
                    </div>

                    <div className="mt-4">
                        <label htmlFor="newsradar_frequency" className="mb-1 block font-bold">Frekuensi pencarian</label>
                        <select
                            id="newsradar_frequency"
                            className="gov-input"
                            disabled={!llmReady}
                            value={data.newsradar_frequency}
                            onChange={(event) => setData('newsradar_frequency', event.target.value)}
                        >
                            <option value="15m">Setiap 15 menit</option>
                            <option value="1h">Setiap jam</option>
                            <option value="6h">Setiap 6 jam</option>
                            <option value="daily">Harian</option>
                            <option value="custom">Kustom (menit)</option>
                        </select>
                    </div>

                    {data.newsradar_frequency === 'custom' ? (
                        <div className="mt-4">
                            <label htmlFor="newsradar_custom_minutes" className="mb-1 block font-bold">
                                Interval kustom (menit, min. 5)
                            </label>
                            <input
                                id="newsradar_custom_minutes"
                                type="number"
                                min="5"
                                max="10080"
                                className="gov-input"
                                disabled={!llmReady}
                                value={data.newsradar_custom_minutes}
                                onChange={(event) => setData('newsradar_custom_minutes', event.target.value)}
                            />
                        </div>
                    ) : null}

                    {settings.newsradar_last_run_at ? (
                        <p className="mt-4 text-sm text-muted">
                            Terakhir dijalankan: {settings.newsradar_last_run_at}
                        </p>
                    ) : (
                        <p className="mt-4 text-sm text-muted">Belum pernah dijalankan.</p>
                    )}

                    <div className="mt-4">
                        <button
                            type="button"
                            className="gov-button"
                            disabled={!newsRadarReady || hunting || processing}
                            onClick={runHunt}
                        >
                            {hunting ? 'Mengantrekan…' : 'Jalankan pencarian sekarang'}
                        </button>
                        {newsRadarReady ? (
                            <p className="mt-2 text-sm text-muted">
                                Pencarian dijalankan di latar belakang melalui antrean kerja.
                            </p>
                        ) : (
                            <p className="mt-2 text-sm text-muted">
                                Simpan pengaturan dengan NewsRadar aktif (dan LLM siap) untuk menjalankan pencarian manual.
                            </p>
                        )}
                    </div>
                </fieldset>

                <button type="submit" className="gov-button self-start" disabled={processing || !llmReady}>
                    Simpan pengaturan NewsRadar
                </button>
            </form>
        </Authenticated>
    );
}
