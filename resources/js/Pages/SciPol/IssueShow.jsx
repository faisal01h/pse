import { Head, Link } from '@inertiajs/inertia-react';
import SciPol, { ExportButton, SciPolNotice, SciPolTable, SciPolTd, SciPolTh } from '@/Layouts/SciPol';

export default function IssueShow({ issue, groups = {}, position_labels = {}, export_url = null }) {
    const order = ['dukung', 'tolak', 'campuran'];

    return (
        <SciPol title="issue map">
            <Head title={`SciPol · ${issue.name}`} />

            <p className="mb-2 text-sm">
                <Link href="/scipol/issues" className="underline">← Issue map</Link>
            </p>

            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold">{issue.name}</h1>
                    {issue.description ? <p className="mt-1 text-sm text-muted">{issue.description}</p> : null}
                </div>
                <ExportButton href={export_url} disabled={!export_url} />
            </div>

            <SciPolNotice>
                Published subjects only. Each row includes a source link when available.
            </SciPolNotice>

            {order.map((key) => {
                const rows = groups[key] || [];
                return (
                    <section key={key} className="mb-6">
                        <h2 className="mb-2 text-sm font-bold uppercase tracking-wide text-muted">
                            {position_labels[key] || key}
                            <span className="ml-2 font-normal normal-case">({rows.length})</span>
                        </h2>
                        {rows.length === 0 ? (
                            <p className="text-sm text-muted">No subjects in this position.</p>
                        ) : (
                            <SciPolTable>
                                <thead>
                                    <tr>
                                        <SciPolTh>Subject</SciPolTh>
                                        <SciPolTh>Kind</SciPolTh>
                                        <SciPolTh>Note</SciPolTh>
                                        <SciPolTh>Source</SciPolTh>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((row, index) => (
                                        <tr key={`${key}-${index}`}>
                                            <SciPolTd>
                                                <a href={row.subject.href} className="underline">{row.subject.name}</a>
                                            </SciPolTd>
                                            <SciPolTd>{row.subject.kind}</SciPolTd>
                                            <SciPolTd className="max-w-md">{row.note || '—'}</SciPolTd>
                                            <SciPolTd>
                                                {row.source_url ? (
                                                    <a href={row.source_url} target="_blank" rel="noreferrer">
                                                        {row.source_type || 'source'}
                                                    </a>
                                                ) : '—'}
                                            </SciPolTd>
                                        </tr>
                                    ))}
                                </tbody>
                            </SciPolTable>
                        )}
                    </section>
                );
            })}
        </SciPol>
    );
}
