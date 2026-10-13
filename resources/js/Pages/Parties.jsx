import { useState } from 'react';
import { Head, Link } from '@inertiajs/inertia-react';
import Public from '@/Layouts/Public';
import PageLinks from '@/Components/PageLinks';

export default function Parties({ auth, parties = [], meta = {}, query = '' }) {
    const [name, setName] = useState(query);

    return (
        <Public auth={auth}>
            <Head title="Partai" />
            <div className="max-w-[960px] mx-auto px-4 py-8">
                <h1 className="text-4xl font-bold leading-tight">Partai</h1>
                <form method="get" action="/parties" className="mt-6 flex flex-wrap items-end gap-2">
                    <div>
                        <label htmlFor="q" className="mb-1 block font-bold">Cari nama</label>
                        <input id="q" name="q" value={name} onChange={(event) => setName(event.target.value)} className="gov-input sm:w-72" />
                    </div>
                    <button className="gov-button">Cari</button>
                </form>
                {parties.length > 0 ? (
                    <ul className="mt-6 flex flex-col border-t-2 border-ink bg-white">
                        {parties.map((party) => (
                            <li key={party.id} className="border-b-2 border-ink">
                                <Link href={party.href} className="flex items-center gap-4 px-4 py-4 no-underline">
                                    {party.logo_url ? (
                                        <img src={party.logo_url} alt="" className="h-16 w-16 shrink-0 border-2 border-ink bg-white object-contain" />
                                    ) : null}
                                    <span>
                                        <span className="block text-2xl font-bold text-ink underline">{party.name}</span>
                                        <span className="block text-muted no-underline">
                                            {party.published_politicians_count} politisi terbit
                                        </span>
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                ) : <p className="mt-6">{query ? 'Tidak ada partai yang cocok.' : 'Belum ada partai.'}</p>}
                <PageLinks path="/parties" meta={meta} query={{ q: query }} />
            </div>
        </Public>
    );
}
