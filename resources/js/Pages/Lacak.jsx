import { Head, Link, useForm } from '@inertiajs/inertia-react';
import Public from '@/Layouts/Public';
import Label from '@/Components/Label';
import Input from '@/Components/Input';
import ValidationErrors from '@/Components/ValidationErrors';

export default function Lacak({ auth, progress }) {
    const { data, setData, post, processing, errors } = useForm({
        tracking_code: '',
    });

    const submit = (event) => {
        event.preventDefault();
        post(route('reports.track.submit'), {
            preserveScroll: true,
            onSuccess: () => setData('tracking_code', ''),
        });
    };

    return (
        <Public auth={auth}>
            <Head title="Lacak pengajuan" />
            <div className="max-w-[960px] mx-auto px-4 py-8">
                <h1 className="text-4xl font-bold leading-tight">Lacak pengajuan</h1>
                <p className="mt-4 max-w-3xl">
                    Masukkan kode yang ditampilkan setelah catatan terkirim. Kode itu tidak disimpan dalam bentuk yang bisa dibaca server.
                </p>
                <ValidationErrors errors={errors} />
                <form onSubmit={submit} className="mt-6 max-w-xl">
                    <Label forInput="tracking_code" value="Kode pelacakan" />
                    <Input
                        type="text"
                        name="tracking_code"
                        value={data.tracking_code}
                        placeholder="ABCD-EFGH-JKLM-NPQR"
                        className="mt-1 uppercase font-mono tracking-widest"
                        handleChange={(event) => setData('tracking_code', event.target.value)}
                        autoComplete="off"
                    />
                    <button className="gov-button mt-4" disabled={processing}>Lihat progres</button>
                </form>
                {progress ? (
                    <section className="mt-8 max-w-xl border-2 border-ink border-t-8 border-t-brand p-5">
                        <p className="text-sm font-bold">{progress.category}</p>
                        <h2 className="mt-1 text-3xl font-bold">{progress.label}</h2>
                        <p className="mt-2">{progress.detail}</p>
                        {progress.politician ? <p className="mt-4">Profil: {progress.politician}</p> : null}
                        {progress.submitted_at ? <p>Dikirim: {progress.submitted_at}</p> : null}
                        {progress.reviewed_at ? <p>Diperiksa: {progress.reviewed_at}</p> : null}
                        {progress.href ? (
                            <p className="mt-4"><Link href={progress.href}>Buka di profil</Link></p>
                        ) : null}
                    </section>
                ) : null}
            </div>
        </Public>
    );
}
