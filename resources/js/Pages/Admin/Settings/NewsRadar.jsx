import Authenticated from '@/Layouts/Authenticated';
import HeadlessBrowserModal from '@/Components/HeadlessBrowserModal';
import SettingsNav from '@/Components/SettingsNav';
import ValidationErrors from '@/Components/ValidationErrors';
import { Inertia } from '@inertiajs/inertia';
import { Head, useForm, usePage } from '@inertiajs/inertia-react';
import { useCallback, useEffect, useState } from 'react';

function xsrfToken() {
    const cookie = document.cookie.split('; ').find((row) => row.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

async function postJson(url, body) {
    let response;
    try {
        response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify(body),
        });
    } catch (e) {
        throw new Error('Tidak dapat menghubungi server. Coba lagi.');
    }

    const payload = await response.json().catch(() => ({}));

    return { ok: response.ok && Boolean(payload.ok), payload };
}

export default function NewsRadar({ auth, settings, llmReady }) {
    const { flash } = usePage().props;
    const [hunting, setHunting] = useState(false);
    const [testUrl, setTestUrl] = useState('');
    const [testing, setTesting] = useState(false);
    const [testModalOpen, setTestModalOpen] = useState(false);
    const [testResult, setTestResult] = useState(null);
    const [testError, setTestError] = useState(null);
    const [testedUrl, setTestedUrl] = useState('');
    const [playwright, setPlaywright] = useState(null);
    const [configuring, setConfiguring] = useState(false);
    const [setupError, setSetupError] = useState(null);

    useEffect(() => {
        let active = true;

        fetch(route('admin.newsradar.playwright.status'), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((response) => response.json())
            .then((payload) => active && setPlaywright(payload))
            .catch(() => active && setPlaywright({ ready: false, package: false, browser: false }));

        return () => {
            active = false;
        };
    }, []);
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

    const closeTestModal = useCallback(() => setTestModalOpen(false), []);

    async function configurePlaywright() {
        setConfiguring(true);
        setSetupError(null);

        try {
            const { ok, payload } = await postJson(route('admin.newsradar.playwright.configure'), {});
            if (!ok) {
                throw new Error(payload.message || 'Konfigurasi Playwright gagal.');
            }

            setPlaywright(payload);
        } finally {
            setConfiguring(false);
        }
    }

    function handleConfigure() {
        configurePlaywright().catch((e) => setSetupError(e.message));
    }

    async function runBrowserTest(event) {
        event.preventDefault();
        const url = testUrl.trim();

        setTestedUrl(url);
        setTestResult(null);
        setTestError(null);
        setTestModalOpen(true);
        setTesting(true);

        try {
            let response = await postJson(route('admin.newsradar.browser-test'), { url });

            // Playwright is missing on the server: configure it once, then retry the test.
            if (!response.ok && response.payload.needs_setup) {
                await configurePlaywright();
                response = await postJson(route('admin.newsradar.browser-test'), { url });
            }

            if (!response.ok) {
                throw new Error(response.payload.message || response.payload.errors?.url?.[0] || 'Tes headless browser gagal.');
            }

            setTestResult(response.payload);
        } catch (e) {
            setTestError(e.message || 'Tes headless browser gagal.');
        } finally {
            setTesting(false);
        }
    }

    const playwrightLabel = playwright === null
        ? 'Memeriksa…'
        : playwright.ready
            ? 'Siap'
            : `Belum siap (paket: ${playwright.package ? 'ada' : 'belum'}, Chromium: ${playwright.browser ? 'ada' : 'belum'})`;

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

            <section className="mt-12 max-w-2xl border-2 border-ink p-4">
                <h2 className="text-2xl font-bold">Tes headless browser</h2>
                <p className="mt-2 text-muted">
                    Buka URL dengan headless browser (Playwright) untuk memastikan halaman bisa dirender sebelum NewsRadar membacanya.
                </p>
                <p className="mt-4 font-bold">Status Playwright: {playwrightLabel}</p>
                <div className="mt-2 flex flex-col gap-2">
                    <button
                        type="button"
                        className="gov-button self-start"
                        disabled={configuring || testing || playwright?.ready}
                        onClick={handleConfigure}
                    >
                        {configuring ? 'Mengonfigurasi…' : 'Konfigurasi Playwright otomatis'}
                    </button>
                    {configuring ? (
                        <p className="text-sm text-muted">Memasang paket dan Chromium. Ini bisa memakan beberapa menit.</p>
                    ) : null}
                    {setupError ? (
                        <p className="border-l-8 border-red-700 bg-canvas px-4 py-3 font-bold text-red-700">{setupError}</p>
                    ) : null}
                </div>
                <form onSubmit={runBrowserTest} className="mt-4 flex flex-col gap-3 sm:flex-row">
                    <label htmlFor="browser_test_url" className="sr-only">URL yang akan diuji</label>
                    <input
                        id="browser_test_url"
                        type="url"
                        required
                        maxLength={2048}
                        className="gov-input flex-1"
                        placeholder="https://example.com/berita"
                        value={testUrl}
                        onChange={(event) => setTestUrl(event.target.value)}
                    />
                    <button type="submit" className="gov-button" disabled={testing || testUrl.trim() === ''}>
                        {testing ? 'Menguji…' : 'Uji URL'}
                    </button>
                </form>
            </section>

            <HeadlessBrowserModal
                open={testModalOpen}
                onClose={closeTestModal}
                loading={testing}
                result={testResult}
                error={testError}
                url={testedUrl}
            />
        </Authenticated>
    );
}
