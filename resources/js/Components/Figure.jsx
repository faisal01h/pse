import { Link } from '@inertiajs/inertia-react';
import RecordMedia from '@/Components/RecordMedia';
import RelationshipSections from '@/Components/RelationshipSections';

const categoryLabel = {
    statement: 'Pernyataan publik',
    allegation: 'Dugaan pelanggaran',
    conviction: 'Vonis pengadilan',
};

const categoryClass = {
    statement: 'border-brand text-brand',
    allegation: 'border-ink text-ink',
    conviction: 'border-ink bg-ink text-white',
};

export default function Figure({ politician }) {
    const records = politician.records ?? [];
    const party = politician.party?.name ?? '';
    const unaffiliated = !party || party === 'independen' || party === 'independent';

    return (
        <article className="flex flex-col gap-4 border-2 border-ink border-t-8 border-t-brand bg-white p-5">
            <div className="flex gap-4">
                {politician.photo_url ? (
                    <img src={politician.photo_url} alt="" className="h-24 w-24 shrink-0 border-2 border-ink object-cover" />
                ) : null}
                <div>
                <h3 className="text-2xl font-bold leading-tight">
                    <Link href={route('politicians.show', politician.id)}>{politician.name}</Link>
                </h3>
                <p className="mt-1 text-muted">{politician.position}</p>
                <p className="text-muted">{politician.institution}</p>
                <p className="mt-2 flex items-center gap-2 text-base">
                    {politician.party?.logo_url ? (
                        <img src={politician.party.logo_url} alt="" className="h-8 w-8 border-2 border-ink object-contain bg-white" />
                    ) : null}
                    <span>
                        {unaffiliated ? 'Tidak terafiliasi dengan partai politik' : (
                            <>Terafiliasi dengan <Link href={route('parties.show', politician.party.id)}>{party}</Link></>
                        )}
                    </span>
                </p>
                </div>
            </div>
            <div className="flex flex-col gap-4">
                {records.map((record) => (
                    <div key={record.id} id={`record-${record.id}`} className="flex flex-col gap-2 border-t border-ink/20 pt-4 scroll-mt-6">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className={`text-sm font-bold w-fit border-2 px-2 py-0.5 ${categoryClass[record.category] ?? 'border-ink text-ink'}`}>
                                {categoryLabel[record.category] ?? record.category}
                            </span>
                            {(record.topics ?? []).map((topic) => (
                                <span key={topic.id} className="border-2 border-ink px-2 py-0.5 text-sm">{topic.name}</span>
                            ))}
                        </div>
                        {record.quote ? <blockquote className="border-l-4 border-ink pl-3 text-lg">"{record.quote}"</blockquote> : null}
                        {record.summary ? <p>{record.summary}</p> : null}
                        <RecordMedia attachments={record.attachments} />
                        <a href={record.source_url} className="w-fit font-bold" target="_blank" rel="noreferrer">
                            Sumber: {record.source_type}
                        </a>
                    </div>
                ))}
            </div>
            <RelationshipSections
                tendencies={politician.tendencies}
                connections={politician.connections}
                stances={politician.issue_stances}
                affiliations={politician.business_affiliations}
            />
        </article>
    );
}
