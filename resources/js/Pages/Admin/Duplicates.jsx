import Authenticated from '@/Layouts/Authenticated';
import ValidationErrors from '@/Components/ValidationErrors';
import { Head, Link } from '@inertiajs/inertia-react';

const kindLabel = {
    exact: 'Nama sama setelah dinormalkan',
    similar: 'Nama mirip',
};

function Entry({ item }) {
    return (
        <div>
            <p className="font-bold">{item.name}</p>
            {item.detail ? <p className="text-sm">{item.detail}</p> : null}
            {item.party ? <p className="text-sm">{item.party}</p> : null}
            <p className="text-sm">
                {item.records !== undefined ? `${item.records} catatan` : null}
                {item.members !== undefined ? `${item.members} politisi` : null}
                {item.affiliations !== undefined ? `${item.affiliations} afiliasi` : null}
                {` · #${item.id}`}
            </p>
        </div>
    );
}

export default function Duplicates({ auth, errors, type, types, pairs, history }) {
    return (
        <Authenticated auth={auth} header={<h1 className="text-4xl font-bold leading-tight">Data ganda</h1>}>
            <Head title="Data ganda" />
            <ValidationErrors errors={errors} />
            <p className="max-w-2xl">
                Pasangan data dengan nama yang sama atau mirip. Pilih satu pasangan untuk menggabungkannya: semua catatan, afiliasi,
                sikap, dan daftar pantau dipindahkan ke data utama, lalu data lainnya dihapus.
            </p>
            <nav className="mt-4 flex gap-2" aria-label="Jenis data">
                {types.map((item) => (
                    <Link
                        key={item.key}
                        href={route('admin.duplicates.index', { type: item.key })}
                        className={item.key === type ? 'gov-button' : 'underline self-center'}
                    >
                        {item.label}
                    </Link>
                ))}
            </nav>

            <div className="mt-6 overflow-x-auto">
                {pairs.length > 0 ? (
                    <table className="admin-table">
                        <thead>
                            <tr>
                                <th>Data A</th>
                                <th>Data B</th>
                                <th>Kecocokan</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {pairs.map((pair) => (
                                <tr key={`${pair.a.id}-${pair.b.id}`}>
                                    <td><Entry item={pair.a} /></td>
                                    <td><Entry item={pair.b} /></td>
                                    <td>
                                        {kindLabel[pair.kind]}
                                        <br />
                                        <span className="text-sm">{Math.round(pair.score * 100)}%</span>
                                    </td>
                                    <td>
                                        <Link href={route('admin.duplicates.show', { type, a: pair.a.id, b: pair.b.id })} className="gov-button">
                                            Tinjau
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                ) : (
                    <p>Tidak ada pasangan data ganda.</p>
                )}
            </div>

            {history.length > 0 ? (
                <>
                    <h2 className="mt-8 text-2xl font-bold">Penggabungan terakhir</h2>
                    <ul className="mt-3 list-disc pl-6">
                        {history.map((item) => (
                            <li key={item.id}>
                                {item.source_name} (#{item.source_id}) digabung ke #{item.canonical_id}
                            </li>
                        ))}
                    </ul>
                </>
            ) : null}
        </Authenticated>
    );
}
