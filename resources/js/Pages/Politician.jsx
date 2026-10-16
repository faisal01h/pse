import { Head, Link } from '@inertiajs/inertia-react';
import Public from '@/Layouts/Public';
import ProfileHero, { ProfileCrumb } from '@/Components/ProfileHero';
import ProfileSection from '@/Components/ProfileSection';
import RecordEntry from '@/Components/RecordEntry';
import RelationshipSections from '@/Components/RelationshipSections';

export default function Politician({ politician, auth }) {
    const records = politician.records ?? [];
    const party = politician.party?.name ?? '';
    const unaffiliated = !party || party === 'independen' || party === 'independent';
    const hasRelations = Boolean(
        (politician.business_affiliations ?? []).length
        || (politician.tendencies ?? []).length
        || (politician.connections ?? []).length
        || (politician.issue_stances ?? []).length
        || (politician.stance_timeline ?? []).length
        || (politician.connection_timeline ?? []).length,
    );

    const meta = [
        politician.position ? { node: politician.position } : null,
        politician.institution ? { node: politician.institution, muted: true } : null,
        {
            node: unaffiliated ? (
                'Tidak terafiliasi dengan partai politik'
            ) : (
                <span className="inline-flex flex-wrap items-center gap-2">
                    {politician.party?.logo_url ? (
                        <img
                            src={politician.party.logo_url}
                            alt=""
                            className="h-7 w-7 border-2 border-ink bg-white object-contain"
                        />
                    ) : null}
                    <span>
                        Partai{' '}
                        <Link href={route('parties.show', politician.party.id)}>{party}</Link>
                    </span>
                </span>
            ),
            muted: unaffiliated,
        },
    ].filter(Boolean);

    return (
        <Public auth={auth}>
            <Head title={politician.name} />

            <ProfileHero
                breadcrumb={(
                    <ProfileCrumb
                        items={[
                            { href: '/', label: 'Beranda' },
                            { href: '/politicians', label: 'Politisi' },
                            { label: politician.name },
                        ]}
                    />
                )}
                image={politician.photo_url}
                imageAlt=""
                kicker="Profil tokoh"
                title={politician.name}
                meta={meta}
            />

            <div className="mx-auto max-w-[960px] px-4 py-10 sm:py-12">
                <ProfileSection
                    id="catatan"
                    title="Catatan publik"
                    lead={
                        records.length > 0
                            ? `${records.length} catatan terbit dari sumber yang telah diperiksa.`
                            : 'Belum ada catatan publik yang terbit untuk tokoh ini.'
                    }
                >
                    {records.length > 0 ? (
                        <div>
                            {records.map((record) => (
                                <RecordEntry key={record.id} record={record} politician={politician} />
                            ))}
                        </div>
                    ) : null}
                </ProfileSection>

                {(politician.power_signals ?? []).length > 0 ? (
                    <ProfileSection id="realpolitik" title="Sinyal REALPOLITIK" lead="Lonjakan liputan atau perubahan sikap yang terdeteksi baru-baru ini." className="mt-14">
                        <ul className="divide-y-2 divide-ink/10 border-t-2 border-ink/10">
                            {politician.power_signals.map((signal) => (
                                <li key={signal.id} className="py-4">
                                    <p className="font-bold">{signal.title}</p>
                                    {signal.body ? <p className="mt-2 text-muted">{signal.body}</p> : null}
                                </li>
                            ))}
                        </ul>
                        <p className="mt-4">
                            <Link href="/realpolitik" className="font-bold underline">Buka papan REALPOLITIK</Link>
                        </p>
                    </ProfileSection>
                ) : null}

                {hasRelations ? (
                    <RelationshipSections
                        tendencies={politician.tendencies}
                        connections={politician.connections}
                        stances={politician.issue_stances}
                        affiliations={politician.business_affiliations}
                        stanceTimeline={politician.stance_timeline}
                        connectionTimeline={politician.connection_timeline}
                    />
                ) : null}
            </div>
        </Public>
    );
}
