import Authenticated from '@/Layouts/Authenticated';
import { Head, Link } from '@inertiajs/inertia-react';
import { useMemo, useState } from 'react';

const statusLabel = {
    0: 'Draf',
    1: 'Terbit',
};

export default function Businesses({ auth, businesses }) {
    const [query, setQuery] = useState('');
    const [sort, setSort] = useState('name');
    const canEdit = auth?.permissions?.includes('businesses.edit');
    const rows = useMemo(() => {
        const needle = query.trim().toLowerCase();
        const filtered = businesses.filter((item) => {
            const haystack = `${item.name} ${item.sector ?? ''}`.toLowerCase();

            return haystack.includes(needle);
        });

        return [...filtered].sort((left, right) => {
            if (sort === 'affiliations') {
                return right.affiliations_count - left.affiliations_count || left.name.localeCompare(right.name, 'id');
            }

            return left.name.localeCompare(right.name, 'id');
        });
    }, [businesses, query, sort]);

    const sortBy = (next) => {
        setSort((current) => (current === next ? 'name' : next));
    };

    return (
        <Authenticated auth={auth} header={<h1 className="text-4xl font-bold leading-tight">Bisnis</h1>}>
            <Head title="Bisnis" />
            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div className="sm:w-72">
                    <label htmlFor="business-q" className="block font-bold mb-1">Cari bisnis</label>
                    <input
                        id="business-q"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        className="gov-input"
                    />
                </div>
                {auth?.permissions?.includes('businesses.add') ? (
                    <Link href={route('admin.businesses.create')} className="gov-button">Tambah bisnis</Link>
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
                                <th>Sektor</th>
                                <th>Status</th>
                                <th>
                                    <button type="button" className="font-bold" onClick={() => sortBy('affiliations')}>
                                        Afiliasi{sort === 'affiliations' ? ' ↓' : ''}
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((business) => (
                                <tr key={business.id}>
                                    <td>
                                        {canEdit ? (
                                            <Link href={route('admin.businesses.edit', business.id)} className="row-link">
                                                {business.name}
                                            </Link>
                                        ) : business.name}
                                    </td>
                                    <td>{business.sector || '—'}</td>
                                    <td>{statusLabel[business.status] ?? business.status}</td>
                                    <td>{business.affiliations_count}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                ) : <p>{query.trim() ? 'Tidak ada bisnis yang cocok.' : 'Belum ada bisnis.'}</p>}
            </div>
        </Authenticated>
    );
}
