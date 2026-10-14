import { Head, Link, useForm } from '@inertiajs/inertia-react';
import { useState } from 'react';
import Public from '@/Layouts/Public';
import Label from '@/Components/Label';
import Input from '@/Components/Input';
import ValidationErrors from '@/Components/ValidationErrors';

const kinds = [
    {
        id: 'submission',
        title: 'Catatan atau afiliasi',
        hint: 'Kode dari pengajuan catatan publik atau afiliasi bisnis.',
    },
    {
        id: 'dispute',
        title: 'Sanggahan',
        hint: 'Kode dari formulir Dispute setelah sanggahan terkirim.',
    },
];

export default function Lacak({ auth, kind: initialKind = null, progress }) {
    const [kind, setKind] = useState(initialKind);
    const { data, setData, post, processing, errors } = useForm({
        kind: initialKind ?? '',
        tracking_code: '',
    });

    const chooseKind = (nextKind) => {
        setKind(nextKind);
        setData('kind', nextKind);
    };

    const submit = (event) => {
        event.preventDefault();
        post(route('reports.track.submit'), {
            preserveScroll: true,
            onSuccess: () => setData('tracking_code', ''),
        });
    };

    if (!kind) {
        return (
            <Public auth={auth}>
                <Head title="Lacak pengajuan" />
                <div className="max-w-[960px] mx-auto px-4 py-8">
                    <p className="mb-6"><Link href="/">Beranda</Link></p>
                    <h1 className="text-4xl font-bold leading-tight">Jenis pelacakan</h1>
                    <p className="mt-3 text-lg">Pilih apakah Anda melacak pengajuan catatan/afiliasi atau sanggahan.</p>
                    <div className="mt-8 flex max-w-xl flex-col gap-3">
                        {kinds.map((item) => (
                            <button
                                key={item.id}
                                type="button"
                                onClick={() => chooseKind(item.id)}
                                className="border-2 border-ink bg-white px-4 py-4 text-left hover:bg-canvas"
                            >
                                <span className="block text-2xl font-bold">{item.title}</span>
                                <span className="mt-1 block">{item.hint}</span>
                            </button>
                        ))}
                    </div>
                </div>
            </Public>
        );
    }

    const kindLabel = kinds.find((item) => item.id === kind)?.title ?? kind;

    return (
        <Public auth={auth}>
            <Head title="Lacak pengajuan" />
            <div className="max-w-[960px] mx-auto px-4 py-8">
                <p className="mb-6"><Link href="/">Beranda</Link></p>
                <button
                    type="button"
                    className="mb-4 underline"
                    onClick={() => {
                        setKind(null);
                        setData({ kind: '', tracking_code: '' });
                    }}
                >
                    Ubah jenis pelacakan
                </button>
                <h1 className="text-4xl font-bold leading-tight">Lacak {kindLabel.toLowerCase()}</h1>
                <p className="mt-4 max-w-3xl">
                    {kind === 'dispute'
                        ? 'Masukkan kode yang ditampilkan setelah sanggahan terkirim.'
                        : 'Masukkan kode yang ditampilkan setelah catatan atau afiliasi terkirim. Kode itu tidak disimpan dalam bentuk yang bisa dibaca server.'}
                </p>
                <ValidationErrors errors={errors} />
                <form onSubmit={submit} className="mt-6 max-w-xl">
                    <input type="hidden" name="kind" value={data.kind} />
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
