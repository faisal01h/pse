import { Link } from '@inertiajs/inertia-react';
import RecordEntry from '@/Components/RecordEntry';
import RelationshipSections from '@/Components/RelationshipSections';

/**
 * Compact politician summary used if needed outside the full profile page.
 * The dedicated profile page lives in Pages/Politician.jsx.
 */
export default function Figure({ politician }) {
    const records = politician.records ?? [];
    const party = politician.party?.name ?? '';
    const unaffiliated = !party || party === 'independen' || party === 'independent';

    return (
        <article className="flex flex-col gap-6 border-2 border-ink border-l-8 border-l-brand bg-white p-5 sm:p-6">
            <div className="flex gap-4">
                {politician.photo_url ? (
                    <img
                        src={politician.photo_url}
                        alt=""
                        className="h-24 w-24 shrink-0 border-2 border-ink object-cover"
                    />
                ) : null}
                <div className="min-w-0">
                    <h3 className="text-2xl font-bold leading-tight">
                        <Link href={route('politicians.show', politician.id)}>{politician.name}</Link>
                    </h3>
                    {politician.position ? <p className="mt-1 text-muted">{politician.position}</p> : null}
                    {politician.institution ? <p className="text-muted">{politician.institution}</p> : null}
                    <p className="mt-2 flex flex-wrap items-center gap-2 text-base">
                        {politician.party?.logo_url ? (
                            <img
                                src={politician.party.logo_url}
                                alt=""
                                className="h-8 w-8 border-2 border-ink bg-white object-contain"
                            />
                        ) : null}
                        <span>
                            {unaffiliated ? (
                                'Tidak terafiliasi dengan partai politik'
                            ) : (
                                <>
                                    Terafiliasi dengan{' '}
                                    <Link href={route('parties.show', politician.party.id)}>{party}</Link>
                                </>
                            )}
                        </span>
                    </p>
                </div>
            </div>

            {records.length > 0 ? (
                <div>
                    {records.map((record) => (
                        <RecordEntry key={record.id} record={record} politician={politician} />
                    ))}
                </div>
            ) : null}

            <RelationshipSections
                tendencies={politician.tendencies}
                connections={politician.connections}
                stances={politician.issue_stances}
                affiliations={politician.business_affiliations}
                asSections={false}
            />
        </article>
    );
}
