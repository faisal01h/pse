import { Link, useForm } from '@inertiajs/inertia-react';
import ValidationErrors from '@/Components/ValidationErrors';

function ProposalCard({ proposal }) {
    const { data, setData, post, processing, errors } = useForm({ note: '' });

    const accept = (event) => {
        event.preventDefault();
        post(route('admin.proposals.accept', proposal.id), { preserveScroll: true });
    };

    return (
        <article className="grid gap-4 border-2 border-ink bg-white p-4 lg:grid-cols-[minmax(0,1fr)_14rem] lg:items-start">
            <div className="min-w-0">
                <p className="font-bold">{proposal.label}</p>
                <p className="mt-1">{proposal.excerpt}</p>
                <p className="mt-2 text-muted">
                    Dari catatan <Link href={proposal.record.href}>{proposal.record.politician_name}</Link>
                </p>
            </div>
            <form onSubmit={accept} className="flex flex-col gap-3">
                <ValidationErrors errors={errors} />
                {proposal.needs_note ? (
                    <div>
                        <label htmlFor={`proposal-note-${proposal.id}`} className="block font-bold mb-1">Catatan sumber</label>
                        <textarea
                            id={`proposal-note-${proposal.id}`}
                            value={data.note}
                            onChange={(event) => setData('note', event.target.value)}
                            className="gov-input"
                            rows="3"
                        />
                    </div>
                ) : null}
                <button className="gov-button" disabled={processing}>
                    {proposal.kind === 'axis' && proposal.replaces ? 'Gabungkan ke rata-rata' : proposal.replaces ? 'Ganti posisi' : 'Terima'}
                </button>
                <Link
                    href={route('admin.proposals.dismiss', proposal.id)}
                    method="post"
                    as="button"
                    className="underline text-left"
                    preserveScroll
                >
                    Abaikan
                </Link>
            </form>
        </article>
    );
}

export default function ProposalList({ proposals = [] }) {
    if (proposals.length === 0) {
        return <p>Tidak ada usulan.</p>;
    }

    return (
        <div className="flex flex-col gap-3">
            {proposals.map((proposal) => <ProposalCard key={proposal.id} proposal={proposal} />)}
        </div>
    );
}
