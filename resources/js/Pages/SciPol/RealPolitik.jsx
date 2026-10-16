import { Head, Link } from '@inertiajs/inertia-react';
import { Inertia } from '@inertiajs/inertia';
import SciPol, { SciPolNotice, SciPolTable, SciPolTd, SciPolTh } from '@/Layouts/SciPol';
import { useEffect } from 'react';

function severityLabel(value) {
    if (value === 'high') return 'high';
    if (value === 'low') return 'low';
    return 'medium';
}

export default function RealPolitik({
    signals = [],
    briefs = [],
    cuiBono = null,
    pollSeconds = 30,
    publicBoardUrl = null,
}) {
    useEffect(() => {
        if (!pollSeconds || pollSeconds < 5) {
            return undefined;
        }
        const timer = setInterval(() => {
            Inertia.reload({ only: ['signals', 'briefs'], preserveScroll: true, preserveState: true });
        }, pollSeconds * 1000);
        return () => clearInterval(timer);
    }, [pollSeconds]);

    return (
        <SciPol title="REALPOLITIK">
            <Head title="SciPol · REALPOLITIK" />

            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">REALPOLITIK</h1>
                    <p className="mt-1 max-w-2xl text-sm text-muted">
                        Near-real-time power signals over the published archive and temporal stance/coalition graph.
                        Treat every signal as a hypothesis until you read the cited records.
                    </p>
                </div>
                {publicBoardUrl ? (
                    <a href={publicBoardUrl} className="text-sm font-bold underline">
                        Open public board
                    </a>
                ) : null}
            </div>

            <SciPolNotice>
                <strong>Method:</strong> record stream + interval history → shock / drift / coalition change → optional
                LLM sitrep with mandatory citations. SciPol exposes the same evidence layer for research reading.
            </SciPolNotice>

            <section className="mb-8">
                <h2 className="mb-2 text-sm font-bold uppercase tracking-wide text-muted">Situation briefs</h2>
                {briefs.length === 0 ? (
                    <p className="text-sm text-muted">No sitrep yet. Admins can run a REALPOLITIK tick from settings.</p>
                ) : (
                    <div className="space-y-3">
                        {briefs.map((brief) => (
                            <article key={brief.id} className="border border-ink/20 bg-white p-3">
                                <p className="text-[12px] text-muted">
                                    {brief.window_start} → {brief.window_end}
                                    {brief.confidence ? ` · confidence ${brief.confidence}` : ''}
                                    {brief.status === 'low_confidence' ? ' · low evidence' : ''}
                                </p>
                                <div className="mt-2 whitespace-pre-wrap text-sm leading-relaxed">{brief.summary_md}</div>
                                {(brief.payload?.power_vectors ?? []).length > 0 ? (
                                    <ul className="mt-3 list-disc pl-5 text-sm">
                                        {brief.payload.power_vectors.map((vector, index) => (
                                            <li key={`${vector.name}-${index}`}>
                                                <span className="font-bold">{vector.name}</span>
                                                {': '}
                                                {vector.vector}
                                                {(vector.public_record_ids ?? []).length > 0 ? (
                                                    <span className="text-muted">
                                                        {' '}
                                                        (
                                                        {vector.public_record_ids.map((id, i) => (
                                                            <span key={id}>
                                                                {i > 0 ? ', ' : ''}
                                                                <Link href={`/records/${id}`} className="underline">
                                                                    #{id}
                                                                </Link>
                                                            </span>
                                                        ))}
                                                        )
                                                    </span>
                                                ) : null}
                                            </li>
                                        ))}
                                    </ul>
                                ) : null}
                                {(brief.payload?.watch_next ?? []).length > 0 ? (
                                    <div className="mt-3">
                                        <p className="text-[11px] font-bold uppercase tracking-wide text-muted">Watch next</p>
                                        <ul className="mt-1 list-disc pl-5 text-sm">
                                            {brief.payload.watch_next.map((item) => (
                                                <li key={item}>{item}</li>
                                            ))}
                                        </ul>
                                    </div>
                                ) : null}
                            </article>
                        ))}
                    </div>
                )}
            </section>

            <section className="mb-8">
                <h2 className="mb-2 text-sm font-bold uppercase tracking-wide text-muted">Power signals</h2>
                {signals.length === 0 ? (
                    <p className="text-sm text-muted">No open signals.</p>
                ) : (
                    <SciPolTable>
                        <thead>
                            <tr>
                                <SciPolTh>Severity</SciPolTh>
                                <SciPolTh>Type</SciPolTh>
                                <SciPolTh>Signal</SciPolTh>
                                <SciPolTh>Actor</SciPolTh>
                                <SciPolTh>Evidence</SciPolTh>
                            </tr>
                        </thead>
                        <tbody>
                            {signals.map((signal) => (
                                <tr key={signal.id}>
                                    <SciPolTd className="font-bold uppercase text-[11px]">
                                        {severityLabel(signal.severity)}
                                    </SciPolTd>
                                    <SciPolTd className="font-mono text-[12px]">{signal.type}</SciPolTd>
                                    <SciPolTd>
                                        <p className="font-bold">{signal.title}</p>
                                        {signal.body ? <p className="mt-1 text-muted">{signal.body}</p> : null}
                                    </SciPolTd>
                                    <SciPolTd>
                                        {signal.politician ? (
                                            <Link href={signal.politician.href} className="underline">
                                                {signal.politician.name}
                                            </Link>
                                        ) : null}
                                        {signal.party ? (
                                            <Link href={signal.party.href} className="underline">
                                                {signal.party.name}
                                            </Link>
                                        ) : null}
                                        {!signal.politician && !signal.party ? '—' : null}
                                    </SciPolTd>
                                    <SciPolTd className="text-[12px]">
                                        {(signal.evidence?.public_record_ids ?? []).length > 0 ? (
                                            <>
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
                                                    href={`/scipol/realpolitik?record_id=${signal.evidence.public_record_ids[0]}`}
                                                    className="font-bold underline"
                                                >
                                                    cui bono
                                                </Link>
                                            </>
                                        ) : (
                                            '—'
                                        )}
                                    </SciPolTd>
                                </tr>
                            ))}
                        </tbody>
                    </SciPolTable>
                )}
            </section>

            {cuiBono ? (
                <section>
                    <h2 className="mb-2 text-sm font-bold uppercase tracking-wide text-muted">Cui bono (as-of)</h2>
                    <p className="mb-3 text-sm text-muted">
                        Interest map as of {cuiBono.as_of} for{' '}
                        <Link href={cuiBono.record.href} className="font-bold underline">
                            record #{cuiBono.record.id}
                        </Link>
                        {cuiBono.record.politician ? ` (${cuiBono.record.politician})` : ''}. Reasons come from
                        then-active links and affiliations—not speculation.
                    </p>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="border border-ink/20 bg-white p-3">
                            <h3 className="font-bold">Tied / potentially benefited</h3>
                            <ul className="mt-2 list-disc pl-5 text-sm">
                                {cuiBono.beneficiaries.length === 0 ? (
                                    <li className="text-muted">No active ties on that date.</li>
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
                        <div className="border border-ink/20 bg-white p-3">
                            <h3 className="font-bold">Opposed / exposed</h3>
                            <ul className="mt-2 list-disc pl-5 text-sm">
                                {cuiBono.exposed.length === 0 ? (
                                    <li className="text-muted">No active opposition recorded.</li>
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
        </SciPol>
    );
}
