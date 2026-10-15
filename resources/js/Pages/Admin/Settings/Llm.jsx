import Authenticated from '@/Layouts/Authenticated';
import SettingsNav from '@/Components/SettingsNav';
import ValidationErrors from '@/Components/ValidationErrors';
import { Head, useForm, usePage } from '@inertiajs/inertia-react';
import { useState } from 'react';

export default function Llm({ auth, settings, llmReady }) {
    const { flash } = usePage().props;
    const { data, setData, post, processing, errors } = useForm({
        llm_enabled: Boolean(settings.llm_enabled),
        llm_base_url: settings.llm_base_url || '',
        llm_api_key: settings.llm_api_key_set ? '********' : '',
        llm_model: settings.llm_model || '',
        llm_max_steps: settings.llm_max_steps ?? 12,
        llm_timeout: settings.llm_timeout ?? 120,
    });

    const [models, setModels] = useState([]);
    const [modelsError, setModelsError] = useState('');
    const [loadingModels, setLoadingModels] = useState(false);

    async function loadModels() {
        setLoadingModels(true);
        setModelsError('');
        try {
            const response = await fetch(route('admin.llm.models'), {
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
        post(route('admin.settings.llm.update'));
    }

    return (
        <Authenticated auth={auth} header={<h1 className="text-4xl font-bold leading-tight">Pengaturan</h1>}>
            <Head title="Pengaturan LLM" />
            <p className="max-w-2xl">
                Konfigurasi plugin LLM bersama. AutoVet dan NewsRadar membutuhkan LLM yang aktif dan terkonfigurasi.
            </p>
            <SettingsNav />

            {flash?.success ? <p className="mt-4 border-l-8 border-brand bg-canvas px-4 py-3 font-bold">{flash.success}</p> : null}
            <ValidationErrors errors={errors} />

            <form onSubmit={submit} className="mt-8 max-w-2xl flex flex-col gap-6">
                <fieldset className="border-2 border-ink p-4">
                    <legend className="px-2 font-bold">LLM</legend>
                    <label className="flex items-center gap-3">
                        <input
                            type="checkbox"
                            checked={data.llm_enabled}
                            onChange={(event) => setData('llm_enabled', event.target.checked)}
                        />
                        Aktifkan plugin LLM
                    </label>
                    <p className="mt-2 text-sm text-muted">
                        {llmReady ? 'Status saat ini: siap.' : 'Status saat ini: belum siap.'}
                        {' '}
                        Menonaktifkan LLM juga menonaktifkan AutoVet dan NewsRadar.
                    </p>

                    <div className="mt-4">
                        <label htmlFor="llm_base_url" className="mb-1 block font-bold">Base URL</label>
                        <input
                            id="llm_base_url"
                            className="gov-input"
                            placeholder="https://api.openai.com/v1"
                            value={data.llm_base_url}
                            onChange={(event) => setData('llm_base_url', event.target.value)}
                        />
                    </div>
                    <div className="mt-4">
                        <label htmlFor="llm_api_key" className="mb-1 block font-bold">API Key</label>
                        <input
                            id="llm_api_key"
                            type="password"
                            className="gov-input"
                            autoComplete="off"
                            value={data.llm_api_key}
                            onChange={(event) => setData('llm_api_key', event.target.value)}
                        />
                        <p className="mt-1 text-muted">Biarkan ******** jika tidak ingin mengubah kunci yang tersimpan.</p>
                    </div>
                    <div className="mt-4">
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="min-w-0 flex-1">
                                <label htmlFor="llm_model" className="mb-1 block font-bold">Model</label>
                                {models.length > 0 ? (
                                    <select
                                        id="llm_model"
                                        className="gov-input"
                                        value={data.llm_model}
                                        onChange={(event) => setData('llm_model', event.target.value)}
                                    >
                                        <option value="">Pilih model</option>
                                        {models.map((id) => (
                                            <option key={id} value={id}>{id}</option>
                                        ))}
                                    </select>
                                ) : (
                                    <input
                                        id="llm_model"
                                        className="gov-input"
                                        value={data.llm_model}
                                        onChange={(event) => setData('llm_model', event.target.value)}
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
                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label htmlFor="llm_max_steps" className="mb-1 block font-bold">Maks. langkah agen</label>
                            <input
                                id="llm_max_steps"
                                type="number"
                                min="1"
                                max="40"
                                className="gov-input"
                                value={data.llm_max_steps}
                                onChange={(event) => setData('llm_max_steps', event.target.value)}
                            />
                        </div>
                        <div>
                            <label htmlFor="llm_timeout" className="mb-1 block font-bold">Timeout (detik)</label>
                            <input
                                id="llm_timeout"
                                type="number"
                                min="5"
                                max="600"
                                className="gov-input"
                                value={data.llm_timeout}
                                onChange={(event) => setData('llm_timeout', event.target.value)}
                            />
                        </div>
                    </div>
                </fieldset>

                <button type="submit" className="gov-button self-start" disabled={processing}>
                    Simpan pengaturan LLM
                </button>
            </form>
        </Authenticated>
    );
}
