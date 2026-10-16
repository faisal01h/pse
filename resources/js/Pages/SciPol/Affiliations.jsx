import { Head } from '@inertiajs/inertia-react';
import PageLinks from '@/Components/PageLinks';
import SciPol, { ExportButton, SciPolNotice, SciPolTable, SciPolTd, SciPolTh } from '@/Layouts/SciPol';

export default function Affiliations({
    items = [],
    meta = {},
    filters = {},
    sectors = [],
    types = [],
    export_url = null,
}) {
    return (
        <SciPol title="affiliations">
            <Head title="SciPol · Affiliations" />

            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold">Affiliation network</h1>
                    <p className="mt-1 text-sm text-muted">
                        Published ownership and endorsement ties to businesses.
                    </p>
                </div>
                <ExportButton href={export_url} disabled={!export_url} />
            </div>

            <SciPolNotice>
                Each row includes a source. Filters support political-economy work by sector.
            </SciPolNotice>

            <form method="get" action="/scipol/affiliations" className="mb-4 grid gap-2 border border-ink/15 bg-white p-3 sm:grid-cols-4">
                <div>
                    <label htmlFor="q" className="mb-1 block text-[11px] font-bold uppercase text-muted">Search</label>
                    <input id="q" name="q" defaultValue={filters.q || ''} className="w-full border border-ink/30 px-2 py-1.5 text-sm" />
                </div>
                <div>
                    <label htmlFor="sector" className="mb-1 block text-[11px] font-bold uppercase text-muted">Sector</label>
                    <select id="sector" name="sector" defaultValue={filters.sector || ''} className="w-full border border-ink/30 px-2 py-1.5 text-sm">
                        <option value="">All</option>
                        {sectors.map((sector) => (
                            <option key={sector} value={sector}>{sector}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label htmlFor="type" className="mb-1 block text-[11px] font-bold uppercase text-muted">Type</label>
                    <select id="type" name="type" defaultValue={filters.type || ''} className="w-full border border-ink/30 px-2 py-1.5 text-sm">
                        <option value="">All</option>
                        {types.map((item) => (
                            <option key={item.key} value={item.key}>{item.label}</option>
                        ))}
                    </select>
                </div>
                <div className="flex items-end">
                    <button type="submit" className="bg-ink px-3 py-1.5 text-sm font-bold text-white">Apply</button>
                </div>
            </form>

            {items.length === 0 ? (
                <p className="text-sm text-muted">No affiliations match.</p>
            ) : (
                <SciPolTable>
                    <thead>
                        <tr>
                            <SciPolTh>Politician</SciPolTh>
                            <SciPolTh>Business</SciPolTh>
                            <SciPolTh>Sector</SciPolTh>
                            <SciPolTh>Type</SciPolTh>
                            <SciPolTh>%</SciPolTh>
                            <SciPolTh>Reliability</SciPolTh>
                            <SciPolTh>Source</SciPolTh>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((row) => (
                            <tr key={row.id}>
                                <SciPolTd>
                                    <a href={row.politician.href} className="underline">{row.politician.name}</a>
                                </SciPolTd>
                                <SciPolTd>
                                    <a href={row.business.href} className="underline">{row.business.name}</a>
                                </SciPolTd>
                                <SciPolTd>{row.business.sector || '—'}</SciPolTd>
                                <SciPolTd>{row.type_label}</SciPolTd>
                                <SciPolTd>{row.ownership_percent ?? '—'}</SciPolTd>
                                <SciPolTd>{row.reliability_level || '—'}</SciPolTd>
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

            <PageLinks
                path="/scipol/affiliations"
                meta={meta}
                query={{
                    q: filters.q || '',
                    sector: filters.sector || '',
                    type: filters.type || '',
                    politician: filters.politician || '',
                }}
                labels={{
                    prev: 'Previous',
                    next: 'Next',
                    page: (current, last) => `Page ${current} of ${last}`,
                    aria: 'Pagination',
                }}
            />
        </SciPol>
    );
}
