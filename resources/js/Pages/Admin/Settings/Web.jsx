import Authenticated from '@/Layouts/Authenticated';
import SettingsNav from '@/Components/SettingsNav';
import ValidationErrors from '@/Components/ValidationErrors';
import { Head, useForm, usePage } from '@inertiajs/inertia-react';

export default function Web({ auth, settings }) {
    const { flash } = usePage().props;
    const { data, setData, post, processing, errors } = useForm({
        web_submissions_enabled: Boolean(settings.web_submissions_enabled),
        web_tracking_enabled: Boolean(settings.web_tracking_enabled),
        web_disputes_enabled: Boolean(settings.web_disputes_enabled),
        web_scipol_enabled: Boolean(settings.web_scipol_enabled),
        web_turnstile_enabled: Boolean(settings.web_turnstile_enabled),
        web_site_notice: settings.web_site_notice || '',
    });

    function submit(event) {
        event.preventDefault();
        post(route('admin.settings.web.update'));
    }

    return (
        <Authenticated auth={auth} header={<h1 className="text-4xl font-bold leading-tight">Pengaturan</h1>}>
            <Head title="Pengaturan Web" />
            <p className="max-w-2xl">Fitur publik situs: pengajuan, pelacakan, dispute, SciPol, dan keamanan.</p>
            <SettingsNav />

            {flash?.success ? <p className="mt-4 border-l-8 border-brand bg-canvas px-4 py-3 font-bold">{flash.success}</p> : null}
            <ValidationErrors errors={errors} />

            <form onSubmit={submit} className="mt-8 max-w-2xl flex flex-col gap-6">
                <fieldset className="border-2 border-ink p-4">
                    <legend className="px-2 font-bold">Fitur publik</legend>
                    <label className="flex items-center gap-3">
                        <input
                            type="checkbox"
                            checked={data.web_submissions_enabled}
                            onChange={(event) => setData('web_submissions_enabled', event.target.checked)}
                        />
                        Izinkan buat pengajuan
                    </label>
                    <label className="mt-3 flex items-center gap-3">
                        <input
                            type="checkbox"
                            checked={data.web_tracking_enabled}
                            onChange={(event) => setData('web_tracking_enabled', event.target.checked)}
                        />
                        Izinkan lacak pengajuan
                    </label>
                    <label className="mt-3 flex items-center gap-3">
                        <input
                            type="checkbox"
                            checked={data.web_disputes_enabled}
                            onChange={(event) => setData('web_disputes_enabled', event.target.checked)}
                        />
                        Izinkan dispute / sanggahan
                    </label>
                    <label className="mt-3 flex items-center gap-3">
                        <input
                            type="checkbox"
                            checked={data.web_scipol_enabled}
                            onChange={(event) => setData('web_scipol_enabled', event.target.checked)}
                        />
                        Tampilkan SciPol
                    </label>
                    <label className="mt-3 flex items-center gap-3">
                        <input
                            type="checkbox"
                            checked={data.web_turnstile_enabled}
                            onChange={(event) => setData('web_turnstile_enabled', event.target.checked)}
                        />
                        Wajibkan Turnstile (verifikasi keamanan)
                    </label>
                </fieldset>

                <fieldset className="border-2 border-ink p-4">
                    <legend className="px-2 font-bold">Pengumuman situs</legend>
                    <label htmlFor="web_site_notice" className="mb-1 block font-bold">
                        Banner publik (opsional)
                    </label>
                    <textarea
                        id="web_site_notice"
                        className="gov-input min-h-[6rem]"
                        value={data.web_site_notice}
                        onChange={(event) => setData('web_site_notice', event.target.value)}
                        placeholder="Teks singkat yang tampil di bawah header publik."
                        maxLength={1000}
                    />
                    <p className="mt-1 text-sm text-muted">Kosongkan untuk menyembunyikan banner.</p>
                </fieldset>

                <button type="submit" className="gov-button self-start" disabled={processing}>
                    Simpan pengaturan web
                </button>
            </form>
        </Authenticated>
    );
}
