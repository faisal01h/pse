import { Head, Link } from '@inertiajs/inertia-react';
import SciPol, { SciPolNotice, SciPolTable, SciPolTd, SciPolTh } from '@/Layouts/SciPol';

export default function Index({ tools = [], codebook = [] }) {
    return (
        <SciPol title="hub">
            <Head title="SciPol" />

            <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">SciPol</h1>
                    <p className="mt-1 max-w-2xl text-sm text-muted">
                        Ruang kerja riset untuk mahasiswa dan akademisi: baca bukti sebelum menarik kesimpulan.
                        Data di sini adalah arsip terbit PolitiSEE — tendensi terkode, bukan peringkat atau vonis.
                    </p>
                </div>
            </div>

            <SciPolNotice>
                <strong>Metode:</strong> sumber → klaim terdokumentasi → struktur (sumbu / sikap / tautan) → interpretasi Anda.
                Allegation bukan conviction; skor spektrum bukan kebenaran mutlak.
            </SciPolNotice>

            <section className="mb-8">
                <h2 className="mb-2 text-sm font-bold uppercase tracking-wide text-muted">Alat</h2>
                <div className="grid gap-2 sm:grid-cols-2">
                    {tools.map((tool) => (
                        <Link
                            key={tool.href}
                            href={tool.href}
                            className="block border border-ink/20 bg-white p-3 no-underline hover:border-ink"
                        >
                            <span className="block font-bold text-ink">{tool.title}</span>
                            <span className="mt-1 block text-sm text-muted">{tool.blurb}</span>
                        </Link>
                    ))}
                </div>
            </section>

            <section>
                <h2 className="mb-2 text-sm font-bold uppercase tracking-wide text-muted">Codebook</h2>
                <p className="mb-2 text-sm text-muted">
                    Definisi variabel untuk ekstrak XLSX dan pembacaan matriks. Ekspor selalu menyertakan lembar Codebook.
                </p>
                <SciPolTable>
                    <thead>
                        <tr>
                            <SciPolTh>Field</SciPolTh>
                            <SciPolTh>Tipe</SciPolTh>
                            <SciPolTh>Nilai</SciPolTh>
                            <SciPolTh>Deskripsi</SciPolTh>
                        </tr>
                    </thead>
                    <tbody>
                        {codebook.map((row) => (
                            <tr key={row.field}>
                                <SciPolTd className="font-mono text-[12px]">{row.field}</SciPolTd>
                                <SciPolTd>{row.type}</SciPolTd>
                                <SciPolTd className="max-w-xs break-words text-[12px] text-muted">{row.values || '—'}</SciPolTd>
                                <SciPolTd>{row.description}</SciPolTd>
                            </tr>
                        ))}
                    </tbody>
                </SciPolTable>
            </section>
        </SciPol>
    );
}
