import Authenticated from '@/Layouts/Authenticated';
import ValidationErrors from '@/Components/ValidationErrors';
import { Head, useForm, usePage } from '@inertiajs/inertia-react';
import { useState } from 'react';

export default function Settings({ auth, settings }) {
    const { flash } = usePage().props;
    const { data, setData, post, processing, errors } = useForm({
        autovet_enabled: Boolean(settings.autovet_enabled),
        autovet_mode: settings.autovet_mode || 'suggest_only',
        autovet_hybrid_min_confidence: settings.autovet_hybrid_min_confidence ?? 0.8,
        autovet_llm_base_url: settings.autovet_llm_base_url || '',
        autovet_llm_api_key: settings.autovet_llm_api_key_set ? '********' : '',
        autovet_llm_model: settings.autovet_llm_model || '',
        autovet_tools_terminal_enabled: Boolean(settings.autovet_tools_terminal_enabled),
        autovet_tools_browser_enabled: Boolean(settings.autovet_tools_browser_enabled),
        autovet_max_steps: settings.autovet_max_steps ?? 12,
        autovet_timeout: settings.autovet_timeout ?? 120,
    });

    const [models, setModels] = useState([]);
    const [modelsError, setModelsError] = useState('');
    const [loadingModels, setLoadingModels] = useState(false);

    async function loadModels() {
        setLoadingModels(true);
        setModelsError('');
        try {
            const response = await fetch(route('admin.autovet.models'), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const body = await response.json();
            if (!response.ok) {
                setModels([]);
                setModelsError(body.error || 'Gagal memuat model.');
                return;
            }
            setModels(body.models || []);
            if ((body.models || []).length === 0) {
                setModelsError('Tidak ada model yang dikembalikan.');
            }
        } catch (error) {
            setModelsError(error.message || 'Gagal memuat model.');
        } finally {
            setLoadingModels(false);
        }
    }

    function submit(event) {
        event.preventDefault();
        post(route('admin.settings.update'));
    }

    return (
        <Authenticated auth={auth} header={<h1 className="text-4xl font-bold leading-tight">Pengaturan</h1>}>
            <Head title="Pengaturan" />
            <p className="max-w-2xl">Konfigurasi plugin AutoVet: penyedia LLM, mode keputusan, dan alat yang diizinkan.</p>

            {flash?.success ? <p className="mt-4 border-l-8 border-brand bg-canvas px-4 py-3 font-bold">{flash.success}</p> : null}
            <ValidationErrors errors={errors} />

            <form onSubmit={submit} className="mt-8 max-w-2xl flex flex-col gap-6">
                <fieldset className="border-2 border-ink p-4">
                    <legend className="px-2 font-bold">AutoVet</legend>
                    <label className="flex items-center gap-3">
                        <input
                            type="checkbox"
                            checked={data.autovet_enabled}
                            onChange={(event) => setData('autovet_enabled', event.target.checked)}
                        />
                        Aktifkan AutoVet
                    </label>

                    <div className="mt-4">
                        <label htmlFor="autovet_mode" className="block font-bold mb-1">Mode keputusan</label>
                        <select
                            id="autovet_mode"
                            className="gov-input"
                            value={data.autovet_mode}
                            onChange={(event) => setData('autovet_mode', event.target.value)}
                        >
                            <option value="suggest_only">Hanya usulan (pemeriksa tetap memutuskan)</option>
                            <option value="auto_decide">Otomatis terbitkan/tolak</option>
                            <option value="hybrid">Hibrida (otomatis jika kepercayaan tinggi)</option>
                        </select>
                    </div>

                    {data.autovet_mode === 'hybrid' ? (
                        <div className="mt-4">
                            <label htmlFor="autovet_hybrid_min_confidence" className="block font-bold mb-1">
                                Ambang kepercayaan hibrida (0–1)
                            </label>
                            <input
                                id="autovet_hybrid_min_confidence"
                                type="number"
                                min="0"
                                max="1"
                                step="0.05"
                                className="gov-input"
                                value={data.autovet_hybrid_min_confidence}
                                onChange={(event) => setData('autovet_hybrid_min_confidence', event.target.value)}
                            />
                        </div>
                    ) : null}
                </fieldset>

                <fieldset className="border-2 border-ink p-4">
                    <legend className="px-2 font-bold">LLM (OpenAI / Anthropic compatible)</legend>
                    <div>
                        <label htmlFor="autovet_llm_base_url" className="block font-bold mb-1">Base URL</label>
                        <input
                            id="autovet_llm_base_url"
                            className="gov-input"
                            placeholder="https://api.openai.com/v1"
                            value={data.autovet_llm_base_url}
                            onChange={(event) => setData('autovet_llm_base_url', event.target.value)}
                        />
                    </div>
                    <div className="mt-4">
                        <label htmlFor="autovet_llm_api_key" className="block font-bold mb-1">API Key</label>
                        <input
                            id="autovet_llm_api_key"
                            type="password"
                            className="gov-input"
                            autoComplete="off"
                            value={data.autovet_llm_api_key}
                            onChange={(event) => setData('autovet_llm_api_key', event.target.value)}
                        />
                        <p className="mt-1 text-muted">Biarkan ******** jika tidak ingin mengubah kunci yang tersimpan.</p>
                    </div>
                    <div className="mt-4">
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="min-w-0 flex-1">
                                <label htmlFor="autovet_llm_model" className="block font-bold mb-1">Model</label>
                                {models.length > 0 ? (
                                    <select
                                        id="autovet_llm_model"
                                        className="gov-input"
                                        value={data.autovet_llm_model}
                                        onChange={(event) => setData('autovet_llm_model', event.target.value)}
                                    >
                                        <option value="">Pilih model</option>
                                        {models.map((id) => (
                                            <option key={id} value={id}>{id}</option>
                                        ))}
                                    </select>
                                ) : (
                                    <input
                                        id="autovet_llm_model"
                                        className="gov-input"
                                        value={data.autovet_llm_model}
                                        onChange={(event) => setData('autovet_llm_model', event.target.value)}
                                        placeholder="gpt-4o-mini"
                                    />
                                )}
                            </div>
                            <button type="button" className="gov-button" onClick={loadModels} disabled={loadingModels}>
                                {loadingModels ? 'Memuat…' : 'Muat model'}
                            </button>
                        </div>
                        {modelsError ? <p className="mt-2 text-brand">{modelsError}</p> : null}
                    </div>
                </fieldset>

                <fieldset className="border-2 border-ink p-4">
                    <legend className="px-2 font-bold">Alat & batas</legend>
                    <label className="flex items-center gap-3">
                        <input
                            type="checkbox"
                            checked={data.autovet_tools_terminal_enabled}
                            onChange={(event) => setData('autovet_tools_terminal_enabled', event.target.checked)}
                        />
                        Izinkan perintah terminal (allowlist)
                    </label>
                    <label className="mt-3 flex items-center gap-3">
                        <input
                            type="checkbox"
                            checked={data.autovet_tools_browser_enabled}
                            onChange={(event) => setData('autovet_tools_browser_enabled', event.target.checked)}
                        />
                        Izinkan headless browser
                    </label>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label htmlFor="autovet_max_steps" className="block font-bold mb-1">Maks. langkah agen</label>
                            <input
                                id="autovet_max_steps"
                                type="number"
                                min="1"
                                max="40"
                                className="gov-input"
                                value={data.autovet_max_steps}
                                onChange={(event) => setData('autovet_max_steps', event.target.value)}
                            />
                        </div>
                        <div>
                            <label htmlFor="autovet_timeout" className="block font-bold mb-1">Timeout (detik)</label>
                            <input
                                id="autovet_timeout"
                                type="number"
                                min="5"
                                max="600"
                                className="gov-input"
                                value={data.autovet_timeout}
                                onChange={(event) => setData('autovet_timeout', event.target.value)}
                            />
                        </div>
                    </div>
                </fieldset>

                <button type="submit" className="gov-button self-start" disabled={processing}>
                    Simpan pengaturan
                </button>
            </form>
        </Authenticated>
    );
}
