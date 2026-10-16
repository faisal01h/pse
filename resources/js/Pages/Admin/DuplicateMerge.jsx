import Authenticated from '@/Layouts/Authenticated';
import ValidationErrors from '@/Components/ValidationErrors';
import { Head, Link, useForm } from '@inertiajs/inertia-react';

const tableLabel = {
    public_records: 'Catatan publik',
    business_affiliations: 'Afiliasi bisnis',
    issue_stances: 'Sikap isu',
    ideology_positions: 'Kecenderungan ideologi',
    politician_links: 'Hubungan politisi',
    party_links: 'Hubungan partai',
    politicians: 'Profil politisi',
    realpolitik_signals: 'Sinyal REALPOLITIK',
    realpolitik_watchlists: 'Daftar pantau',
    autovet_runs: 'Proses AutoVet',
};

export default function DuplicateMerge({ auth, errors, type, typeLabel, candidates }) {
    const { data, setData, post, processing } = useForm({
        type,
        canonical_id: String(candidates[0].id),
        source_id: String(candidates[1].id),
    });

    const choose = (id) => {
        const other = candidates.find((item) => item.id !== id);
        setData({ ...data, canonical_id: String(id), source_id: String(other.id) });
    };

    const source = candidates.find((item) => String(item.id) === data.source_id);

    const submit = (event) => {
        event.preventDefault();
        if (window.confirm(`Gabungkan "${source.name}" ke data utama? Data ini akan dihapus dan tidak bisa dikembalikan.`)) {
            post(route('admin.duplicates.merge'));
        }
    };

    return (
        <Authenticated auth={auth} header={<h1 className="text-4xl font-bold leading-tight">Gabungkan {typeLabel.toLowerCase()}</h1>}>
            <Head title="Gabungkan data" />
            <ValidationErrors errors={errors} />
            <form onSubmit={submit}>
                <fieldset>
                    <legend className="font-bold mb-2">Pilih data utama</legend>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {candidates.map((item) => (
                            <label key={item.id} className="block border p-4">
                                <input
                                    type="radio"
                                    name="canonical"
                                    checked={data.canonical_id === String(item.id)}
                                    onChange={() => choose(item.id)}
                                />{' '}
                                <span className="font-bold">{item.name}</span> (#{item.id})
                                {item.detail ? <p className="text-sm">{item.detail}</p> : null}
                                {item.party ? <p className="text-sm">{item.party}</p> : null}
                                <ul className="mt-2 text-sm">
                                    {Object.entries(item.moves ?? {}).map(([table, count]) => (
                                        <li key={table}>{tableLabel[table] ?? table}: {count}</li>
                                    ))}
                                </ul>
                            </label>
                        ))}
                    </div>
                </fieldset>
                <p className="mt-4 max-w-2xl">
                    Semua data milik <strong>{source.name}</strong> dipindahkan ke data utama. Jika sikap atau kecenderungan yang
                    sama masih terbuka di kedua sisi, yang lebih lama ditutup sehari sebelum yang baru dimulai. Tautan lama diarahkan ke data utama.
                </p>
                <div className="mt-4 flex gap-3">
                    <button type="submit" className="gov-button" disabled={processing}>Gabungkan</button>
                    <Link href={route('admin.duplicates.index', { type })} className="underline self-center">Batal</Link>
                </div>
            </form>
        </Authenticated>
    );
}
