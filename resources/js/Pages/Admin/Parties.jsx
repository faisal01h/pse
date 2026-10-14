import Authenticated from '@/Layouts/Authenticated';
import { Head, Link } from '@inertiajs/inertia-react';
import { useMemo, useState } from 'react';

export default function Parties({ auth, parties }) {
    const [query, setQuery] = useState('');
    const [sort, setSort] = useState('name');
    const rows = useMemo(() => {
        const needle = query.trim().toLowerCase();
        const filtered = parties.filter((party) => party.name.toLowerCase().includes(needle));

        return [...filtered].sort((left, right) => {
            if (sort === 'profiles') {
                return right.politicians_count - left.politicians_count || left.name.localeCompare(right.name, 'id');
            }

            return left.name.localeCompare(right.name, 'id');
        });
    }, [parties, query, sort]);

    const sortBy = (next) => {
        setSort((current) => (current === next ? 'name' : next));
    };

    return (
        <Authenticated auth={auth} header={<h1 className="text-4xl font-bold leading-tight">Partai</h1>}>
            <Head title="Partai" />
            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div className="sm:w-72">
                    <label htmlFor="party-q" className="block font-bold mb-1">Cari partai</label>
                    <input
                        id="party-q"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        className="gov-input"
                    />
                </div>
                {auth?.permissions?.includes('parties.add') ? (
                    <Link href={route('admin.parties.create')} className="gov-button">Tambah partai</Link>
                ) : null}
            </div>
            <div className="mt-4 overflow-x-auto">
                {rows.length > 0 ? (
                    <table className="admin-table">
                        <thead>
                            <tr>
                                <th>
                                    <button type="button" className="font-bold" onClick={() => sortBy('name')}>
                                        Nama{sort === 'name' ? ' ↑' : ''}
                                    </button>
                                </th>
                                <th>
                                    <button type="button" className="font-bold" onClick={() => sortBy('profiles')}>
                                        Profil{sort === 'profiles' ? ' ↓' : ''}
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((party) => (
                                <tr key={party.id}>
                                    <td>
                                        <Link href={route('admin.parties.edit', party.id)} className="row-link inline-flex items-center gap-3">
                                            {party.logo_url ? (
                                                <img src={party.logo_url} alt="" className="h-8 w-8 border border-ink object-contain bg-white" />
                                            ) : (
                                                <span className="inline-block h-8 w-8 border border-ink bg-canvas" />
                                            )}
                                            {party.name}
                                        </Link>
                                    </td>
                                    <td>{party.politicians_count}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                ) : <p>{query.trim() ? 'Tidak ada partai yang cocok.' : 'Belum ada partai.'}</p>}
            </div>
        </Authenticated>
    );
}
