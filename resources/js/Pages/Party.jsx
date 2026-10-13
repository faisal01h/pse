import { Head, Link } from '@inertiajs/inertia-react';
import Public from '@/Layouts/Public';
import PageLinks from '@/Components/PageLinks';
import RelationshipSections from '@/Components/RelationshipSections';

export default function Party({ party, politicians = [], meta = {}, auth }) {
    return (
        <Public auth={auth}>
            <Head title={party.name} />
            <div className="max-w-[960px] mx-auto px-4 py-8">
                <p className="mb-4"><Link href="/">Beranda</Link></p>
                <article className="border-2 border-ink border-t-8 border-t-brand bg-white p-5">
                    <div className="flex gap-4">
                        {party.logo_url ? (
                            <img src={party.logo_url} alt="" className="h-24 w-24 shrink-0 border-2 border-ink object-contain bg-white" />
                        ) : null}
                        <div>
                            <h1 className="text-4xl font-bold leading-tight">{party.name}</h1>
                            <p className="mt-2"><Link href={`/?party=${party.id}`}>Lihat data yang terbit di beranda</Link></p>
                        </div>
                    </div>
                    <div className="mt-6">
                        <RelationshipSections
                            tendencies={party.tendencies}
                            connections={party.connections}
                            stances={party.issue_stances}
                        />
                    </div>
                </article>
                <h2 className="mt-10 text-3xl font-bold">Politisi terbit</h2>
                {politicians.length > 0 ? (
                    <ul className="mt-6 grid gap-4 md:grid-cols-2">
                        {politicians.map((politician) => (
                            <li key={politician.id} className="border-2 border-ink bg-white">
                                <Link href={politician.href} className="flex gap-4 p-4 no-underline">
                                    {politician.photo_url ? (
                                        <img src={politician.photo_url} alt="" className="h-20 w-20 shrink-0 border-2 border-ink object-cover" />
                                    ) : null}
                                    <span>
                                        <span className="block text-2xl font-bold text-ink underline">{politician.name}</span>
                                        {politician.position ? <span className="block text-muted no-underline">{politician.position}</span> : null}
                                        {politician.institution ? <span className="block text-muted no-underline">{politician.institution}</span> : null}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                ) : <p className="mt-6">Belum ada profil terbit dari partai ini.</p>}
                <PageLinks path={`/parties/${party.id}`} meta={meta} />
            </div>
        </Public>
    );
}
