import { Head, Link } from '@inertiajs/inertia-react';
import Public from '@/Layouts/Public';
import RecordMedia from '@/Components/RecordMedia';

export default function Business({ auth, business }) {
    return (
        <Public auth={auth}>
            <Head title={business.name} />
            <div className="max-w-[960px] mx-auto px-4 py-8">
                <p className="mb-6"><Link href="/businesses">Bisnis</Link></p>
                <h1 className="text-4xl font-bold leading-tight">{business.name}</h1>
                {business.sector ? <p className="mt-2 text-lg text-muted">{business.sector}</p> : null}

                <h2 className="mt-10 text-2xl font-bold">Afiliasi dengan tokoh</h2>
                {business.affiliations?.length > 0 ? (
                    <ul className="mt-4 flex flex-col gap-6">
                        {business.affiliations.map((item) => (
                            <li key={item.id} className="border-2 border-ink border-t-8 border-t-brand bg-white p-5">
                                <p className="font-bold">
                                    {item.label}{' '}
                                    <Link href={item.politician.href}>{item.politician.name}</Link>
                                </p>
                                {item.politician.position ? <p className="text-muted">{item.politician.position}</p> : null}
                                <p className="mt-3">{item.summary}</p>
                                {item.attachments?.length ? <RecordMedia attachments={item.attachments} /> : null}
                                <a href={item.source_url} className="mt-2 inline-block font-bold" target="_blank" rel="noreferrer">
                                    Sumber: {item.source_type}
                                </a>
                            </li>
                        ))}
                    </ul>
                ) : <p className="mt-4">Belum ada afiliasi terbit.</p>}
            </div>
        </Public>
    );
}
