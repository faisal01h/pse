import { Head, Link, useForm } from '@inertiajs/inertia-react';
import { Inertia } from '@inertiajs/inertia';
import Public from '@/Layouts/Public';
import { useEffect } from 'react';

function Severity({ value }) {
    const label = value === 'high' ? 'tinggi' : value === 'low' ? 'rendah' : 'sedang';
    return <span className="text-sm font-bold uppercase tracking-wide text-muted">{label}</span>;
}

export default function Board({
    auth,
    signals = [],
    briefs = [],
    watchlist = [],
    cuiBono = null,
    pollSeconds = 20,
}) {
    const watchForm = useForm({
        watchable_type: 'politician',
        watchable_name: '',
    });

    useEffect(() => {
        if (!pollSeconds || pollSeconds < 5) {
            return undefined;
        }
        const timer = setInterval(() => {
            Inertia.reload({ only: ['signals', 'briefs'], preserveScroll: true, preserveState: true });
        }, pollSeconds * 1000);
        return () => clearInterval(timer);
    }, [pollSeconds]);

    const submitWatch = (event) => {
        event.preventDefault();
        watchForm.post(route('realpolitik.watchlist.store'), {
            preserveScroll: true,
            onSuccess: () => watchForm.reset('watchable_name'),
        });
    };

    return (
        <Public auth={auth}>
            <Head title="REALPOLITIK" />
            <div className="max-w-[960px] mx-auto px-4 py-10">
                <p className="text-sm font-bold uppercase tracking-wide text-muted">PolitiSEE</p>
                <h1 className="mt-2 text-4xl font-bold leading-tight">REALPOLITIK</h1>
                <p className="mt-3 max-w-2xl text-lg text-muted">
                    Analisis kekuasaan hampir-realtime: lonjakan liputan, pergeseran sikap, perubahan koalisi, dan sitrep bersumber.
                </p>

                <section className="mt-10">
                    <h2 className="text-2xl font-bold">Sitrep terbaru</h2>
                    {briefs.length === 0 ? (
                        <p className="mt-3 text-muted">Belum ada sitrep. Jalankan tick dari pengaturan admin.</p>
                    ) : (
                        <ul className="mt-4 flex flex-col gap-4">
                            {briefs.map((brief) => (
                                <li key={brief.id} className="border-2 border-ink p-4">
                                    <p className="text-sm text-muted">
                                        {brief.window_start} → {brief.window_end}
                                        {brief.confidence ? ` · keyakinan ${brief.confidence}` : ''}
                                    </p>
                                    <div className="mt-3 whitespace-pre-wrap leading-relaxed">{brief.summary_md}</div>
                                    {(brief.payload?.power_vectors ?? []).length > 0 ? (
                                        <ul className="mt-4 list-disc pl-5 text-sm">
                                            {brief.payload.power_vectors.map((vector, index) => (
                                                <li key={`${vector.name}-${index}`}>
                                                    <span className="font-bold">{vector.name}</span>
                                                    {': '}
                                                    {vector.vector}
                                                </li>
                                            ))}
                                        </ul>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="mt-12">
                    <h2 className="text-2xl font-bold">Sinyal kekuasaan</h2>
                    {signals.length === 0 ? (
                        <p className="mt-3 text-muted">Belum ada sinyal terbuka.</p>
                    ) : (
                        <ul className="mt-4 divide-y-2 divide-ink/10 border-t-2 border-ink/10">
                            {signals.map((signal) => (
                                <li key={signal.id} className="py-4">
                                    <div className="flex flex-wrap items-baseline gap-3">
                                        <Severity value={signal.severity} />
                                        <p className="text-lg font-bold">{signal.title}</p>
                                    </div>
                                    {signal.body ? <p className="mt-2 leading-relaxed">{signal.body}</p> : null}
                                    <p className="mt-2 text-sm text-muted">
                                        {signal.type}
                                        {signal.politician ? (
                                            <>
                                                {' · '}
                                                <Link href={signal.politician.href} className="font-bold underline">
                                                    {signal.politician.name}
                                                </Link>
                                            </>
                                        ) : null}
                                        {signal.party ? (
                                            <>
                                                {' · '}
                                                <Link href={signal.party.href} className="font-bold underline">
                                                    {signal.party.name}
                                                </Link>
                                            </>
                                        ) : null}
                                    </p>
                                    {(signal.evidence?.public_record_ids ?? []).length > 0 ? (
                                        <p className="mt-2 text-sm">
                                            Bukti:{' '}
                                            {signal.evidence.public_record_ids.map((id, index) => (
                                                <span key={id}>
                                                    {index > 0 ? ', ' : ''}
                                                    <Link href={`/records/${id}`} className="underline">
                                                        #{id}
                                                    </Link>
                                                </span>
                                            ))}
                                            {' · '}
                                            <Link
                                                href={`/realpolitik?record_id=${signal.evidence.public_record_ids[0]}`}
                                                className="font-bold underline"
                                            >
                                                Cui bono
                                            </Link>
                                        </p>
                                    ) : null}
                                    {auth?.user ? (
                                        <Link
                                            href={route('admin.realpolitik.dismiss', signal.id)}
                                            method="post"
                                            as="button"
                                            className="mt-3 underline"
                                            preserveScroll
                                        >
                                            Tutup sinyal
                                        </Link>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {cuiBono ? (
                    <section className="mt-12 border-2 border-ink p-4">
                        <h2 className="text-2xl font-bold">Cui bono</h2>
                        <p className="mt-2 text-muted">
                            Peta kepentingan as-of {cuiBono.as_of} untuk{' '}
                            <Link href={cuiBono.record.href} className="font-bold underline">
                                catatan #{cuiBono.record.id}
                            </Link>
                            {cuiBono.record.politician ? ` (${cuiBono.record.politician})` : ''}.
                        </p>
                        <div className="mt-4 grid gap-6 sm:grid-cols-2">
                            <div>
                                <h3 className="font-bold">Berpotensi diuntungkan / terikat</h3>
                                <ul className="mt-2 list-disc pl-5">
                                    {cuiBono.beneficiaries.length === 0 ? (
                                        <li className="text-muted">Tidak ada kaitan aktif pada tanggal itu.</li>
                                    ) : (
                                        cuiBono.beneficiaries.map((item) => (
                                            <li key={`${item.kind}-${item.id}`}>
                                                <Link href={item.href} className="font-bold underline">{item.name}</Link>
                                                <span className="text-muted"> — {item.reason}</span>
                                            </li>
                                        ))
                                    )}
                                </ul>
                            </div>
                            <div>
                                <h3 className="font-bold">Terekspos / berlawanan</h3>
                                <ul className="mt-2 list-disc pl-5">
                                    {cuiBono.exposed.length === 0 ? (
                                        <li className="text-muted">Tidak ada oposisi aktif tercatat.</li>
                                    ) : (
                                        cuiBono.exposed.map((item) => (
                                            <li key={`${item.kind}-${item.id}`}>
                                                <Link href={item.href} className="font-bold underline">{item.name}</Link>
                                                <span className="text-muted"> — {item.reason}</span>
                                            </li>
                                        ))
                                    )}
                                </ul>
                            </div>
                        </div>
                    </section>
                ) : null}

                {auth?.user ? (
                    <section className="mt-12">
                        <h2 className="text-2xl font-bold">Watchlist</h2>
                        <form onSubmit={submitWatch} className="mt-4 grid gap-3 border-2 border-ink p-4 sm:grid-cols-3">
                            <select
                                className="gov-input"
                                value={watchForm.data.watchable_type}
                                onChange={(event) => watchForm.setData('watchable_type', event.target.value)}
                            >
                                <option value="politician">Politisi</option>
                                <option value="party">Partai</option>
                                <option value="topic">Topik</option>
                            </select>
                            <div className="flex flex-col gap-1">
                                <input
                                    className="gov-input"
                                    type="text"
                                    maxLength={255}
                                    placeholder="Nama politisi, partai, atau topik"
                                    value={watchForm.data.watchable_name}
                                    onChange={(event) => watchForm.setData('watchable_name', event.target.value)}
                                />
                                {watchForm.errors.watchable_name ? (
                                    <p className="text-sm text-red-700">{watchForm.errors.watchable_name}</p>
                                ) : null}
                            </div>
                            <button className="gov-button" disabled={watchForm.processing}>Tambah</button>
                        </form>
                        {watchlist.length > 0 ? (
                            <ul className="mt-4 flex flex-col gap-2">
                                {watchlist.map((item) => (
                                    <li key={item.id} className="flex items-center justify-between border-b border-ink/15 py-2">
                                        <span>
                                            {item.watchable_name ?? `${item.watchable_type} #${item.watchable_id}`}
                                            <span className="ml-2 text-sm text-muted">{item.watchable_type}</span>
                                        </span>
                                        <Link
                                            href={route('realpolitik.watchlist.destroy', item.id)}
                                            method="delete"
                                            as="button"
                                            className="underline"
                                            preserveScroll
                                        >
                                            Hapus
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="mt-3 text-muted">Belum ada item watchlist.</p>
                        )}
                    </section>
                ) : null}
            </div>
        </Public>
    );
}
