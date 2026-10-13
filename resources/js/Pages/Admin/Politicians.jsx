import Authenticated from '@/Layouts/Authenticated';
import { Head, Link } from '@inertiajs/inertia-react';
import { useState } from 'react';

function listHref(query, status) {
    const params = new URLSearchParams();

    if (query) {
        params.set('q', query);
    }

    if (status) {
        params.set('status', status);
    }

    const search = params.toString();

    return route('admin.politicians.index') + (search ? `?${search}` : '');
}

export default function Politicians({ auth, politicians, filters, counts }) {
    const [query, setQuery] = useState(filters?.q ?? '');
    const rows = politicians?.data ?? [];
    const status = filters?.status ?? '';
    const chips = [
        { id: '', label: 'Semua', count: counts?.all },
        { id: '1', label: 'Terbit', count: counts?.published },
        { id: '0', label: 'Tidak terbit', count: counts?.hidden },
    ];

    return (
        <Authenticated auth={auth} header={<h1 className="text-4xl font-bold leading-tight">Politisi</h1>}>
            <Head title="Politisi" />
            <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <form method="get" action={route('admin.politicians.index')} className="flex flex-wrap items-end gap-2">
                    {status ? <input type="hidden" name="status" value={status} /> : null}
                    <div>
                        <label htmlFor="q" className="block font-bold mb-1">Cari nama</label>
                        <input id="q" name="q" value={query} onChange={(event) => setQuery(event.target.value)} className="gov-input sm:w-72" />
                    </div>
                    <button className="gov-button">Cari</button>
                </form>
                <Link href={route('admin.politicians.create')} className="gov-button">Tambah politisi</Link>
            </div>
            <div className="mt-4 flex flex-wrap gap-2">
                {chips.map((chip) => (
                    <Link
                        key={chip.label}
                        href={listHref(query, chip.id)}
                        aria-current={status === chip.id ? 'true' : undefined}
                        className={`chip border-2 border-ink px-3 py-1 font-bold ${status === chip.id ? 'chip-selected bg-ink' : 'bg-white hover:bg-canvas'}`}
                    >
                        {chip.label} <span className="font-normal">{chip.count ?? 0}</span>
                    </Link>
                ))}
            </div>
            <div className="mt-4 overflow-x-auto">
                {rows.length > 0 ? (
                    <table className="admin-table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Jabatan</th>
                                <th>Partai</th>
                                <th>Catatan</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((politician) => (
                                <tr key={politician.id}>
                                    <td>
                                        <Link href={route('admin.politicians.edit', politician.id)} className="row-link">
                                            {politician.name}
                                        </Link>
                                    </td>
                                    <td>
                                        {politician.position}
                                        <p className="text-muted">{politician.institution}</p>
                                    </td>
                                    <td>{politician.party?.name ?? 'Tidak terafiliasi'}</td>
                                    <td>{politician.records_count}</td>
                                    <td>
                                        <span className={`inline-block px-2 py-0.5 font-bold ${politician.status === 1 ? 'bg-ink text-white' : 'bg-canvas'}`}>
                                            {politician.status === 1 ? 'Terbit' : 'Tidak terbit'}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                ) : <p>Tidak ada profil.</p>}
            </div>
            {politicians?.links?.length > 3 ? (
                <nav className="mt-6 flex flex-wrap gap-3">
                    {politicians.links.map((link, index) => (
                        link.url ? (
                            <Link key={index} href={link.url} className={link.active ? 'font-bold' : undefined} dangerouslySetInnerHTML={{ __html: link.label }} />
                        ) : (
                            <span key={index} className="text-muted" dangerouslySetInnerHTML={{ __html: link.label }} />
                        )
                    ))}
                </nav>
            ) : null}
        </Authenticated>
    );
}
