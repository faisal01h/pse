import Authenticated from '@/Layouts/Authenticated';
import ValidationErrors from '@/Components/ValidationErrors';
import { Head, Link, useForm } from '@inertiajs/inertia-react';

export default function BusinessForm({ auth, business }) {
    const { data, setData, post, processing, errors } = useForm({
        name: business?.name ?? '',
        sector: business?.sector ?? '',
        status: business?.status ?? 0,
    });

    const submit = (event) => {
        event.preventDefault();
        post(business ? route('admin.businesses.update', business.id) : route('admin.businesses.store'));
    };

    return (
        <Authenticated
            auth={auth}
            header={<h1 className="text-4xl font-bold leading-tight">{business ? 'Ubah bisnis' : 'Bisnis baru'}</h1>}
        >
            <Head title={business ? business.name : 'Bisnis baru'} />
            <form onSubmit={submit} className="max-w-xl">
                <ValidationErrors errors={errors} />
                <div>
                    <label htmlFor="business-name" className="block font-bold mb-1">Nama</label>
                    <input
                        id="business-name"
                        value={data.name}
                        onChange={(event) => setData('name', event.target.value)}
                        className="gov-input"
                    />
                </div>
                <div className="mt-4">
                    <label htmlFor="business-sector" className="block font-bold mb-1">Sektor</label>
                    <input
                        id="business-sector"
                        value={data.sector}
                        onChange={(event) => setData('sector', event.target.value)}
                        className="gov-input"
                    />
                </div>
                <fieldset className="mt-4">
                    <legend className="font-bold mb-2">Status</legend>
                    <div className="flex flex-wrap gap-4">
                        <label className="inline-flex items-center gap-2">
                            <input
                                type="radio"
                                name="status"
                                checked={Number(data.status) === 0}
                                onChange={() => setData('status', 0)}
                            />
                            <span>Draf</span>
                        </label>
                        <label className="inline-flex items-center gap-2">
                            <input
                                type="radio"
                                name="status"
                                checked={Number(data.status) === 1}
                                onChange={() => setData('status', 1)}
                            />
                            <span>Terbit</span>
                        </label>
                    </div>
                </fieldset>
                {business ? (
                    <p className="mt-4 text-muted">Afiliasi terkait: {business.affiliations_count ?? 0}</p>
                ) : null}
                <button className="gov-button mt-6" disabled={processing}>Simpan</button>
            </form>
            {business && auth?.permissions?.includes('businesses.edit') ? (
                <Link
                    href={route('admin.businesses.destroy', business.id)}
                    method="delete"
                    as="button"
                    className="mt-8 underline"
                    onClick={(event) => {
                        if (!window.confirm('Hapus bisnis ini?')) {
                            event.preventDefault();
                        }
                    }}
                >
                    Hapus bisnis
                </Link>
            ) : null}
        </Authenticated>
    );
}
