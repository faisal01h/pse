import Authenticated from '@/Layouts/Authenticated';
import GraphEditor from '@/Components/GraphEditor';
import ValidationErrors from '@/Components/ValidationErrors';
import { Head, Link, useForm } from '@inertiajs/inertia-react';

export default function PartyForm({ auth, party, graph }) {
    const { data, setData, post, processing, errors } = useForm({
        name: party?.name ?? '',
        logo: null,
        remove_logo: false,
    });

    const submit = (event) => {
        event.preventDefault();
        post(party ? route('admin.parties.update', party.id) : route('admin.parties.store'), {
            forceFormData: true,
        });
    };

    return (
        <Authenticated auth={auth} header={<h1 className="text-4xl font-bold leading-tight">{party ? 'Ubah partai' : 'Partai baru'}</h1>}>
            <Head title={party ? party.name : 'Partai baru'} />
            <form onSubmit={submit} className="max-w-xl">
                <ValidationErrors errors={errors} />
                <div>
                    <label htmlFor="name" className="block font-bold mb-1">Nama</label>
                    <input id="name" value={data.name} onChange={(event) => setData('name', event.target.value)} className="gov-input" />
                </div>
                <div className="mt-4">
                    <label htmlFor="logo" className="block font-bold mb-1">Logo</label>
                    {party?.logo_url ? (
                        <img src={party.logo_url} alt="" className="mb-3 h-24 w-24 border-2 border-ink object-contain bg-white" />
                    ) : null}
                    <input
                        id="logo"
                        type="file"
                        accept="image/jpeg,image/png,image/webp,image/gif"
                        onChange={(event) => setData('logo', event.target.files[0] ?? null)}
                    />
                    <p className="mt-2 text-muted">JPEG, PNG, WebP, atau GIF. Maksimal 2 MB.</p>
                    {party?.logo_url ? (
                        <label className="mt-3 flex items-center gap-3">
                            <input type="checkbox" checked={data.remove_logo} onChange={(event) => setData('remove_logo', event.target.checked)} />
                            Hapus logo yang ada
                        </label>
                    ) : null}
                </div>
                <button className="gov-button mt-6" disabled={processing}>Simpan</button>
            </form>
            {party && graph ? (
                <GraphEditor
                    graph={graph}
                    subjectId={party.id}
                    suggestUrl={route('admin.parties.suggest')}
                    tendencyUrl={route('admin.parties.tendencies.store', party.id)}
                    tendencyDestroy={(axis) => route('admin.parties.tendencies.destroy', [party.id, axis])}
                    linkUrl={route('admin.parties.links.store', party.id)}
                    linkDestroy={(id) => route('admin.parties.links.destroy', [party.id, id])}
                    stanceUrl={route('admin.parties.stances.store', party.id)}
                    stanceDestroy={(id) => route('admin.parties.stances.destroy', [party.id, id])}
                />
            ) : null}
            {party ? (
                <Link
                    href={route('admin.parties.destroy', party.id)}
                    method="delete"
                    as="button"
                    className="mt-8 underline"
                    onClick={(event) => {
                        if (!window.confirm('Hapus partai ini?')) {
                            event.preventDefault();
                        }
                    }}
                >
                    Hapus partai
                </Link>
            ) : null}
        </Authenticated>
    );
}
