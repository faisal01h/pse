import { Head, Link } from '@inertiajs/inertia-react';
import SciPol, { SciPolTable, SciPolTd, SciPolTh } from '@/Layouts/SciPol';

export default function Isu({ issues = [] }) {
    return (
        <SciPol title="issue map">
            <Head title="SciPol · Issue map" />

            <h1 className="text-2xl font-bold">Issue map</h1>
            <p className="mt-1 mb-4 text-sm text-muted">
                Issues with stance counts from published politicians and parties. Open an issue for a position crosstab.
            </p>

            {issues.length === 0 ? (
                <p className="text-sm text-muted">No issues with published stances yet.</p>
            ) : (
                <SciPolTable>
                    <thead>
                        <tr>
                            <SciPolTh>Issue</SciPolTh>
                            <SciPolTh className="w-28 text-right">Politicians</SciPolTh>
                            <SciPolTh className="w-28 text-right">Parties</SciPolTh>
                        </tr>
                    </thead>
                    <tbody>
                        {issues.map((issue) => (
                            <tr key={issue.id}>
                                <SciPolTd>
                                    <Link href={issue.href} className="font-bold text-ink underline">
                                        {issue.name}
                                    </Link>
                                    {issue.description ? (
                                        <div className="mt-0.5 text-[12px] text-muted">{issue.description}</div>
                                    ) : null}
                                </SciPolTd>
                                <SciPolTd className="text-right">{issue.politician_stance_count}</SciPolTd>
                                <SciPolTd className="text-right">{issue.party_stance_count}</SciPolTd>
                            </tr>
                        ))}
                    </tbody>
                </SciPolTable>
            )}
        </SciPol>
    );
}
