import { useState } from 'react';
import { Head, Link } from '@inertiajs/inertia-react';
import Public from '@/Layouts/Public';
import PageLinks from '@/Components/PageLinks';

export default function Businesses({ auth, businesses = [], meta = {}, query = '' }) {
    const [name, setName] = useState(query);

    return (
        <Public auth={auth}>
            <Head title="Bisnis" />
            <div className="max-w-[960px] mx-auto px-4 py-8">
                <h1 className="text-4xl font-bold leading-tight">Bisnis</h1>
                <p className="mt-3 text-lg">Usaha yang tercatat terkait tokoh publik lewat kepemilikan atau endorsemen.</p>
                <form method="get" action="/businesses" className="mt-6 flex flex-wrap items-end gap-2">
                    <div>
                        <label htmlFor="q" className="mb-1 block font-bold">Cari nama</label>
                        <input id="q" name="q" value={name} onChange={(event) => setName(event.target.value)} className="gov-input sm:w-72" />
                    </div>
                    <button className="gov-button">Cari</button>
                </form>
                {businesses.length > 0 ? (
                    <ul className="mt-6 flex flex-col border-t-2 border-ink bg-white">
                        {businesses.map((business) => (
                            <li key={business.id} className="border-b-2 border-ink">
                                <Link href={business.href} className="block px-4 py-4 no-underline">
                                    <span className="block text-2xl font-bold text-ink underline">{business.name}</span>
                                    {business.sector ? <span className="block text-muted no-underline">{business.sector}</span> : null}
                                </Link>
                            </li>
                        ))}
                    </ul>
                ) : <p className="mt-6">{query ? 'Tidak ada bisnis yang cocok.' : 'Belum ada bisnis terbit.'}</p>}
                <PageLinks path="/businesses" meta={meta} query={{ q: query }} />
            </div>
        </Public>
    );
}
