import { Head, Link } from '@inertiajs/inertia-react';
import Public from '@/Layouts/Public';
import PageLinks from '@/Components/PageLinks';
import ProfileHero, { ProfileCrumb } from '@/Components/ProfileHero';
import ProfileSection from '@/Components/ProfileSection';
import RelationshipSections from '@/Components/RelationshipSections';

export default function Party({ party, politicians = [], meta = {}, auth }) {
    const hasRelations = Boolean(
        (party.tendencies ?? []).length
        || (party.connections ?? []).length
        || (party.issue_stances ?? []).length
        || (party.stance_timeline ?? []).length
        || (party.connection_timeline ?? []).length,
    );

    return (
        <Public auth={auth}>
            <Head title={party.name} />

            <ProfileHero
                breadcrumb={(
                    <ProfileCrumb
                        items={[
                            { href: '/', label: 'Beranda' },
                            { href: '/parties', label: 'Partai' },
                            { label: party.name },
                        ]}
                    />
                )}
                image={party.logo_url}
                imageAlt=""
                imageContain
                kicker="Partai politik"
                title={party.name}
                meta={[
                    {
                        node: politicians.length > 0
                            ? `${politicians.length} politisi terbit ditampilkan di halaman ini`
                            : 'Belum ada politisi terbit dari partai ini',
                        muted: true,
                    },
                ]}
                action={(
                    <Link href={`/?party=${party.id}`} className="gov-button">
                        Lihat catatan di beranda
                    </Link>
                )}
            />

            <div className="mx-auto max-w-[960px] px-4 py-10 sm:py-12">
                <ProfileSection
                    id="politisi"
                    title="Politisi terbit"
                    lead="Profil tokoh yang terafiliasi dengan partai ini dan sudah diterbitkan."
                >
                    {politicians.length > 0 ? (
                        <ul className="divide-y-2 divide-ink border-y-2 border-ink bg-white">
                            {politicians.map((politician) => (
                                <li key={politician.id}>
                                    <Link
                                        href={politician.href}
                                        className="group flex gap-4 px-0 py-5 no-underline sm:px-1"
                                    >
                                        {politician.photo_url ? (
                                            <img
                                                src={politician.photo_url}
                                                alt=""
                                                className="h-20 w-20 shrink-0 border-2 border-ink object-cover"
                                            />
                                        ) : (
                                            <span
                                                className="flex h-20 w-20 shrink-0 items-end border-2 border-ink bg-canvas p-2 text-2xl font-bold text-brand"
                                                aria-hidden="true"
                                            >
                                                {politician.name.charAt(0)}
                                            </span>
                                        )}
                                        <span className="min-w-0 self-center">
                                            <span className="block text-2xl font-bold text-ink underline decoration-transparent transition group-hover:decoration-brand">
                                                {politician.name}
                                            </span>
                                            {politician.position ? (
                                                <span className="mt-1 block text-muted no-underline">{politician.position}</span>
                                            ) : null}
                                            {politician.institution ? (
                                                <span className="block text-muted no-underline">{politician.institution}</span>
                                            ) : null}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p>Belum ada profil terbit dari partai ini.</p>
                    )}
                    <PageLinks path={`/parties/${party.id}`} meta={meta} />
                </ProfileSection>

                {hasRelations ? (
                    <RelationshipSections
                        tendencies={party.tendencies}
                        connections={party.connections}
                        stances={party.issue_stances}
                        stanceTimeline={party.stance_timeline}
                        connectionTimeline={party.connection_timeline}
                    />
                ) : null}
            </div>
        </Public>
    );
}
