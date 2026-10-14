import { Head } from '@inertiajs/inertia-react';
import PageLinks from '@/Components/PageLinks';
import SciPol, { CategoryBadge, ExportButton, SciPolNotice, SciPolTable, SciPolTd, SciPolTh } from '@/Layouts/SciPol';

export default function Topik({
    items = [],
    meta = {},
    filters = {},
    topics = [],
    categories = [],
    export_url = null,
}) {
    return (
        <SciPol title="topik">
            <Head title="SciPol · Arsip topik" />

            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold">Arsip topik</h1>
                    <p className="mt-1 text-sm text-muted">
                        Korpus rekaman terbit untuk tugas dan studi kasus. Perhatikan kategori.
                    </p>
                </div>
                <ExportButton href={export_url} disabled={!export_url} />
            </div>

            <SciPolNotice>
                <strong>Dugaan</strong> bukan <strong>vonis</strong>. Cantumkan kategori saat mengutip untuk makalah.
            </SciPolNotice>

            <form method="get" action="/scipol/topik" className="mb-4 grid gap-2 border border-ink/15 bg-white p-3 sm:grid-cols-4">
                <div>
                    <label htmlFor="q" className="mb-1 block text-[11px] font-bold uppercase text-muted">Cari</label>
                    <input id="q" name="q" defaultValue={filters.q || ''} className="w-full border border-ink/30 px-2 py-1.5 text-sm" />
                </div>
                <div>
                    <label htmlFor="topic" className="mb-1 block text-[11px] font-bold uppercase text-muted">Topik</label>
                    <select id="topic" name="topic" defaultValue={filters.topic || ''} className="w-full border border-ink/30 px-2 py-1.5 text-sm">
                        <option value="">Semua</option>
                        {topics.map((topic) => (
                            <option key={topic.id} value={topic.id}>{topic.name}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label htmlFor="category" className="mb-1 block text-[11px] font-bold uppercase text-muted">Kategori</label>
                    <select id="category" name="category" defaultValue={filters.category || ''} className="w-full border border-ink/30 px-2 py-1.5 text-sm">
                        <option value="">Semua</option>
                        {categories.map((item) => (
                            <option key={item.key} value={item.key}>{item.label}</option>
                        ))}
                    </select>
                </div>
                <div className="flex items-end">
                    <button type="submit" className="bg-ink px-3 py-1.5 text-sm font-bold text-white">Terapkan</button>
                </div>
            </form>

            {items.length === 0 ? (
                <p className="text-sm text-muted">Tidak ada rekaman yang cocok.</p>
            ) : (
                <SciPolTable>
                    <thead>
                        <tr>
                            <SciPolTh>Tanggal</SciPolTh>
                            <SciPolTh>Kategori</SciPolTh>
                            <SciPolTh>Politisi</SciPolTh>
                            <SciPolTh>Isi</SciPolTh>
                            <SciPolTh>Topik</SciPolTh>
                            <SciPolTh>Sumber</SciPolTh>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((row) => (
                            <tr key={row.id}>
                                <SciPolTd className="whitespace-nowrap">{row.date || '—'}</SciPolTd>
                                <SciPolTd><CategoryBadge category={row.category} /></SciPolTd>
                                <SciPolTd>
                                    <a href={row.politician.href} className="underline">{row.politician.name}</a>
                                </SciPolTd>
                                <SciPolTd className="max-w-md">
                                    <a href={row.href} className="text-ink no-underline hover:underline">
                                        {row.quote || row.summary || '—'}
                                    </a>
                                </SciPolTd>
                                <SciPolTd className="text-[12px]">
                                    {(row.topics || []).map((topic) => topic.name).join(', ') || '—'}
                                </SciPolTd>
                                <SciPolTd>
                                    {row.source_url ? (
                                        <a href={row.source_url} target="_blank" rel="noreferrer">
                                            {row.source_type || 'sumber'}
                                        </a>
                                    ) : '—'}
                                </SciPolTd>
                            </tr>
                        ))}
                    </tbody>
                </SciPolTable>
            )}

            <PageLinks
                path="/scipol/topik"
                meta={meta}
                query={{
                    q: filters.q || '',
                    topic: filters.topic || '',
                    category: filters.category || '',
                }}
            />
        </SciPol>
    );
}
