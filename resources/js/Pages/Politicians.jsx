import { useState } from 'react';
import { Head, Link } from '@inertiajs/inertia-react';
import Public from '@/Layouts/Public';
import PageLinks from '@/Components/PageLinks';

export default function Politicians({ auth, politicians = [], meta = {}, query = '' }) {
    const [name, setName] = useState(query);

    return (
        <Public auth={auth}>
            <Head title="Politisi" />
            <div className="max-w-[960px] mx-auto px-4 py-8">
                <h1 className="text-4xl font-bold leading-tight">Politisi</h1>
                <form method="get" action="/politicians" className="mt-6 flex flex-wrap items-end gap-2">
                    <div>
                        <label htmlFor="q" className="mb-1 block font-bold">Cari nama</label>
                        <input id="q" name="q" value={name} onChange={(event) => setName(event.target.value)} className="gov-input sm:w-72" />
                    </div>
                    <button className="gov-button">Cari</button>
                </form>
                {politicians.length > 0 ? (
                    <ul className="mt-6 flex flex-col border-t-2 border-ink bg-white">
                        {politicians.map((politician) => (
                            <li key={politician.id} className="border-b-2 border-ink">
                                <Link href={politician.href} className="flex gap-4 px-4 py-4 no-underline">
                                    {politician.photo_url ? (
                                        <img src={politician.photo_url} alt="" className="h-20 w-20 shrink-0 border-2 border-ink object-cover" />
                                    ) : null}
                                    <span>
                                        <span className="block text-2xl font-bold text-ink underline">{politician.name}</span>
                                        {politician.position ? <span className="block text-muted no-underline">{politician.position}</span> : null}
                                        {politician.institution ? <span className="block text-muted no-underline">{politician.institution}</span> : null}
                                        {politician.party ? (
                                            <span className="mt-1 flex items-center gap-2 text-ink no-underline">
                                                {politician.party.logo_url ? (
                                                    <img src={politician.party.logo_url} alt="" className="h-8 w-8 border-2 border-ink bg-white object-contain" />
                                                ) : null}
                                                {politician.party.name}
                                            </span>
                                        ) : null}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                ) : <p className="mt-6">{query ? 'Tidak ada politisi yang cocok.' : 'Belum ada politisi terbit.'}</p>}
                <PageLinks path="/politicians" meta={meta} query={{ q: query }} />
            </div>
        </Public>
    );
}
