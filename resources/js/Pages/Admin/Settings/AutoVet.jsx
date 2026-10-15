import Authenticated from '@/Layouts/Authenticated';
import SettingsNav from '@/Components/SettingsNav';
import ValidationErrors from '@/Components/ValidationErrors';
import { Head, useForm, usePage } from '@inertiajs/inertia-react';

export default function AutoVet({ auth, settings, llmReady, layaReady }) {
    const { flash } = usePage().props;
    const { data, setData, post, processing, errors } = useForm({
        autovet_enabled: Boolean(settings.autovet_enabled),
        autovet_strategy: settings.autovet_strategy || 'llm',
        autovet_mode: settings.autovet_mode || 'suggest_only',
        autovet_hybrid_min_confidence: settings.autovet_hybrid_min_confidence ?? 0.8,
        autovet_laya_min_confidence: settings.autovet_laya_min_confidence ?? 0.55,
        autovet_tools_terminal_enabled: Boolean(settings.autovet_tools_terminal_enabled),
        autovet_tools_browser_enabled: Boolean(settings.autovet_tools_browser_enabled),
        autovet_max_steps: settings.autovet_max_steps ?? 12,
        autovet_timeout: settings.autovet_timeout ?? 120,
    });

    const strategyReady = data.autovet_strategy === 'laya' ? Boolean(layaReady) : Boolean(llmReady);
    const usingLlm = data.autovet_strategy === 'llm';

    function submit(event) {
        event.preventDefault();
        post(route('admin.settings.autovet.update'));
    }

    return (
        <Authenticated auth={auth} header={<h1 className="text-4xl font-bold leading-tight">Pengaturan</h1>}>
            <Head title="Pengaturan AutoVet" />
            <p className="max-w-2xl">Strategi pemeriksaan, mode keputusan, dan alat AutoVet.</p>
            <SettingsNav />

            {flash?.success ? <p className="mt-4 border-l-8 border-brand bg-canvas px-4 py-3 font-bold">{flash.success}</p> : null}
            <ValidationErrors errors={errors} />

            <form onSubmit={submit} className="mt-8 max-w-2xl flex flex-col gap-6">
                <fieldset className="border-2 border-ink p-4">
                    <legend className="px-2 font-bold">AutoVet</legend>

                    <div>
                        <label htmlFor="autovet_strategy" className="mb-1 block font-bold">Strategi pemeriksaan</label>
                        <select
                            id="autovet_strategy"
                            className="gov-input"
                            value={data.autovet_strategy}
                            onChange={(event) => setData('autovet_strategy', event.target.value)}
                        >
                            <option value="llm">LLM (agen dengan alat)</option>
                            <option value="laya">Laya (pertanyaan bertipe)</option>
                        </select>
                        <p className="mt-2 text-sm text-muted">
                            {usingLlm
                                ? (llmReady ? 'LLM siap.' : 'Strategi LLM membutuhkan plugin LLM yang aktif dan terkonfigurasi.')
                                : (layaReady ? 'Laya siap (LAYA_URL terisi).' : 'Strategi Laya membutuhkan LAYA_URL di konfigurasi server.')}
                        </p>
                    </div>

                    <label className={`mt-4 flex items-center gap-3 ${strategyReady ? '' : 'opacity-60'}`}>
                        <input
                            type="checkbox"
                            checked={data.autovet_enabled}
                            disabled={!strategyReady}
                            onChange={(event) => setData('autovet_enabled', event.target.checked)}
                        />
                        Aktifkan AutoVet
                    </label>
                    {!strategyReady ? (
                        <p className="mt-2 text-sm text-muted">Siapkan backend strategi yang dipilih sebelum mengaktifkan AutoVet.</p>
                    ) : null}

                    <div className="mt-4">
                        <label htmlFor="autovet_mode" className="mb-1 block font-bold">Mode keputusan</label>
                        <select
                            id="autovet_mode"
                            className="gov-input"
                            value={data.autovet_mode}
                            disabled={!strategyReady}
                            onChange={(event) => setData('autovet_mode', event.target.value)}
                        >
                            <option value="suggest_only">Hanya usulan (pemeriksa tetap memutuskan)</option>
                            <option value="auto_decide">Otomatis terbitkan/tolak</option>
                            <option value="hybrid">Hibrida (otomatis jika kepercayaan tinggi)</option>
                        </select>
                    </div>

                    {data.autovet_mode === 'hybrid' ? (
                        <div className="mt-4">
                            <label htmlFor="autovet_hybrid_min_confidence" className="mb-1 block font-bold">
                                Ambang kepercayaan hibrida (0–1)
                            </label>
                            <input
                                id="autovet_hybrid_min_confidence"
                                type="number"
                                min="0"
                                max="1"
                                step="0.05"
                                className="gov-input"
                                disabled={!strategyReady}
                                value={data.autovet_hybrid_min_confidence}
                                onChange={(event) => setData('autovet_hybrid_min_confidence', event.target.value)}
                            />
                        </div>
                    ) : null}

                    {!usingLlm ? (
                        <div className="mt-4">
                            <label htmlFor="autovet_laya_min_confidence" className="mb-1 block font-bold">
                                Ambang kepercayaan Laya (0–1)
                            </label>
                            <input
                                id="autovet_laya_min_confidence"
                                type="number"
                                min="0"
                                max="1"
                                step="0.05"
                                className="gov-input"
                                disabled={!strategyReady}
                                value={data.autovet_laya_min_confidence}
                                onChange={(event) => setData('autovet_laya_min_confidence', event.target.value)}
                            />
                        </div>
                    ) : null}

                    {usingLlm ? (
                        <>
                            <label className="mt-4 flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    checked={data.autovet_tools_terminal_enabled}
                                    disabled={!strategyReady}
                                    onChange={(event) => setData('autovet_tools_terminal_enabled', event.target.checked)}
                                />
                                Izinkan perintah terminal (allowlist)
                            </label>
                            <label className="mt-3 flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    checked={data.autovet_tools_browser_enabled}
                                    disabled={!strategyReady}
                                    onChange={(event) => setData('autovet_tools_browser_enabled', event.target.checked)}
                                />
                                Izinkan headless browser
                            </label>
                        </>
                    ) : null}
                </fieldset>

                <button type="submit" className="gov-button self-start" disabled={processing}>
                    Simpan pengaturan AutoVet
                </button>
            </form>
        </Authenticated>
    );
}
