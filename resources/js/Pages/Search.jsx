import { Head, Link } from '@inertiajs/inertia-react';
import { Inertia } from '@inertiajs/inertia';
import { useState } from 'react';
import Public from '@/Layouts/Public';

export default function Search({ auth, query, results }) {
    const [term, setTerm] = useState(query ?? '');
    const hits = results ?? [];

    const submit = (event) => {
        event.preventDefault();
        Inertia.get('/search', { q: term }, { preserveState: true });
    };

    return (
        <Public auth={auth}>
            <Head title="Cari" />
            <div className="max-w-[960px] mx-auto px-4 py-8">
                <h1 className="text-4xl font-bold leading-tight">Cari arsip</h1>
                <p className="mt-3 max-w-3xl">Cari politisi, partai, dan catatan yang sudah terbit. Tekan Ctrl+K atau ⌘K di halaman mana pun untuk membuka pencarian cepat.</p>
                <form onSubmit={submit} className="mt-6 flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div className="flex-1">
                        <label htmlFor="search-q" className="block font-bold mb-1">Kata kunci</label>
                        <input
                            id="search-q"
                            value={term}
                            onChange={(event) => setTerm(event.target.value)}
                            className="gov-input"
                            name="q"
                        />
                    </div>
                    <button type="submit" className="gov-button">Cari</button>
                </form>
                <div className="mt-8 flex flex-col gap-3">
                    {hits.length === 0 && (query ?? '').trim().length >= 2 ? (
                        <p>Tidak ada hasil untuk “{query.trim()}”.</p>
                    ) : null}
                    {hits.map((hit) => (
                        <Link
                            key={`${hit.type}-${hit.href}`}
                            href={hit.href}
                            className="block border-2 border-ink p-4 no-underline hover:bg-canvas"
                        >
                            <span className="block text-sm font-bold">{hit.label}</span>
                            <span className="block text-2xl font-bold text-ink mt-1">{hit.title}</span>
                            {hit.subtitle ? <span className="block text-muted mt-1">{hit.subtitle}</span> : null}
                        </Link>
                    ))}
                </div>
            </div>
        </Public>
    );
}
