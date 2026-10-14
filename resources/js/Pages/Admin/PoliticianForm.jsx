import Authenticated from '@/Layouts/Authenticated';
import GraphEditor from '@/Components/GraphEditor';
import ProposalList from '@/Components/ProposalList';
import ValidationErrors from '@/Components/ValidationErrors';
import { Head, Link, useForm } from '@inertiajs/inertia-react';

const categoryLabel = {
    statement: 'Pernyataan publik',
    allegation: 'Dugaan pelanggaran',
    conviction: 'Vonis pengadilan',
};

const statusLabel = {
    0: 'Menunggu',
    1: 'Terbit',
    2: 'Ditolak',
};

export default function PoliticianForm({ auth, politician, parties, records, graph, proposals = [] }) {
    const { data, setData, post, processing, errors } = useForm({
        name: politician?.name ?? '',
        position: politician?.position ?? '',
        institution: politician?.institution ?? '',
        party_id: politician?.party_id ? String(politician.party_id) : '',
        status: politician ? String(politician.status) : '0',
        photo: null,
        remove_photo: false,
    });

    const submit = (event) => {
        event.preventDefault();
        post(politician ? route('admin.politicians.update', politician.id) : route('admin.politicians.store'), {
            forceFormData: true,
        });
    };

    return (
        <Authenticated auth={auth} header={<h1 className="text-4xl font-bold leading-tight">{politician ? 'Ubah politisi' : 'Politisi baru'}</h1>}>
            <Head title={politician ? politician.name : 'Politisi baru'} />
            <form onSubmit={submit} className="max-w-xl">
                <ValidationErrors errors={errors} />
                <div>
                    <label htmlFor="name" className="block font-bold mb-1">Nama</label>
                    <input id="name" value={data.name} onChange={(event) => setData('name', event.target.value)} className="gov-input" />
                </div>
                <div className="mt-4">
                    <label htmlFor="position" className="block font-bold mb-1">Jabatan</label>
                    <input id="position" value={data.position} onChange={(event) => setData('position', event.target.value)} className="gov-input" />
                </div>
                <div className="mt-4">
                    <label htmlFor="institution" className="block font-bold mb-1">Instansi</label>
                    <input id="institution" value={data.institution} onChange={(event) => setData('institution', event.target.value)} className="gov-input" />
                </div>
                <div className="mt-4">
                    <label htmlFor="party_id" className="block font-bold mb-1">Partai</label>
                    <select id="party_id" value={data.party_id} onChange={(event) => setData('party_id', event.target.value)} className="gov-input">
                        <option value="">Tidak terafiliasi</option>
                        {parties.map((party) => (
                            <option key={party.id} value={String(party.id)}>{party.name}</option>
                        ))}
                    </select>
                </div>
                <div className="mt-4">
                    <label htmlFor="status" className="block font-bold mb-1">Tampil di beranda</label>
                    <select id="status" value={data.status} onChange={(event) => setData('status', event.target.value)} className="gov-input">
                        <option value="0">Tidak terbit</option>
                        <option value="1">Terbit</option>
                    </select>
                </div>
                <div className="mt-4">
                    <label htmlFor="photo" className="block font-bold mb-1">Foto</label>
                    {politician?.photo_url ? (
                        <img src={politician.photo_url} alt="" className="mb-3 h-32 w-32 border-2 border-ink object-cover" />
                    ) : null}
                    <input
                        id="photo"
                        type="file"
                        accept="image/jpeg,image/png,image/webp,image/gif"
                        onChange={(event) => setData('photo', event.target.files[0] ?? null)}
                    />
                    <p className="mt-2 text-muted">JPEG, PNG, WebP, atau GIF. Maksimal 2 MB.</p>
                    {politician?.photo_url ? (
                        <label className="mt-3 flex items-center gap-3">
                            <input type="checkbox" checked={data.remove_photo} onChange={(event) => setData('remove_photo', event.target.checked)} />
                            Hapus foto yang ada
                        </label>
                    ) : null}
                </div>
                <button className="gov-button mt-6" disabled={processing}>Simpan</button>
            </form>
            {politician ? (
                <>
                    <h2 className="mt-10 text-2xl font-bold">Catatan pada profil ini</h2>
                    {records.length > 0 ? (
                        <ul className="mt-3 list-disc pl-5">
                            {records.map((record) => (
                                <li key={record.id}>
                                    <Link href={route('reports.review', record.id)}>{categoryLabel[record.category] ?? record.category}</Link>
                                    {' · '}{statusLabel[record.status] ?? record.status}
                                    {' · '}{record.quote || record.summary}
                                </li>
                            ))}
                        </ul>
                    ) : <p className="mt-2">Belum ada catatan.</p>}
                    {graph ? (
                        <>
                            <h2 className="mt-10 text-2xl font-bold">Usulan dari data yang terbit</h2>
                            <ProposalList proposals={proposals} />
                            <GraphEditor
                            graph={graph}
                            subjectId={politician.id}
                            suggestUrl={route('admin.politicians.suggest')}
                            tendencyUrl={route('admin.politicians.tendencies.store', politician.id)}
                            tendencyDestroy={(axis) => route('admin.politicians.tendencies.destroy', [politician.id, axis])}
                            linkUrl={route('admin.politicians.links.store', politician.id)}
                            linkDestroy={(id) => route('admin.politicians.links.destroy', [politician.id, id])}
                            stanceUrl={route('admin.politicians.stances.store', politician.id)}
                            stanceDestroy={(id) => route('admin.politicians.stances.destroy', [politician.id, id])}
                            affiliationUrl={route('admin.politicians.affiliations.store', politician.id)}
                            affiliationDestroy={(id) => route('admin.politicians.affiliations.destroy', [politician.id, id])}
                        />
                        </>
                    ) : null}
                    <Link
                        href={route('admin.politicians.destroy', politician.id)}
                        method="delete"
                        as="button"
                        className="mt-8 underline"
                        onClick={(event) => {
                            if (!window.confirm('Hapus profil ini beserta seluruh catatannya?')) {
                                event.preventDefault();
                            }
                        }}
                    >
                        Hapus profil dan catatannya
                    </Link>
                </>
            ) : null}
        </Authenticated>
    );
}
