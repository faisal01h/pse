import { Head, Link } from '@inertiajs/inertia-react';
import SciPol, { SciPolNotice, SciPolTable, SciPolTd, SciPolTh } from '@/Layouts/SciPol';

export default function Isu({ issues = [] }) {
    return (
        <SciPol title="peta isu">
            <Head title="SciPol · Peta isu" />

            <h1 className="text-2xl font-bold">Peta isu</h1>
            <p className="mt-1 mb-4 text-sm text-muted">
                Daftar isu dengan hitungan sikap dari politisi terbit dan partai. Buka isu untuk crosstab posisi.
            </p>

            <SciPolNotice>
                Posisi <strong>dukung / tolak / campuran</strong> adalah tendensi terkode dari sumber — bukan skor kebenaran.
            </SciPolNotice>

            {issues.length === 0 ? (
                <p className="text-sm text-muted">Belum ada isu dengan sikap terbit.</p>
            ) : (
                <SciPolTable>
                    <thead>
                        <tr>
                            <SciPolTh>Isu</SciPolTh>
                            <SciPolTh className="w-28 text-right">Politisi</SciPolTh>
                            <SciPolTh className="w-28 text-right">Partai</SciPolTh>
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
