import { Head, Link } from '@inertiajs/inertia-react';
import Public from '@/Layouts/Public';
import RecordMedia from '@/Components/RecordMedia';
import ReliabilityBadge from '@/Components/ReliabilityBadge';
import ProfileHero, { ProfileCrumb } from '@/Components/ProfileHero';
import ProfileSection from '@/Components/ProfileSection';

export default function Business({ auth, business }) {
    const affiliations = business.affiliations ?? [];

    return (
        <Public auth={auth}>
            <Head title={business.name} />

            <ProfileHero
                breadcrumb={(
                    <ProfileCrumb
                        items={[
                            { href: '/', label: 'Beranda' },
                            { href: '/businesses', label: 'Bisnis' },
                            { label: business.name },
                        ]}
                    />
                )}
                kicker="Usaha / organisasi"
                title={business.name}
                meta={[
                    business.sector
                        ? { node: business.sector, muted: true }
                        : { node: 'Sektor tidak dicantumkan', muted: true },
                    {
                        node: affiliations.length > 0
                            ? `${affiliations.length} afiliasi tokoh terbit`
                            : 'Belum ada afiliasi tokoh yang terbit',
                        muted: true,
                    },
                ]}
            />

            <div className="mx-auto max-w-[960px] px-4 py-10 sm:py-12">
                <ProfileSection
                    id="afiliasi"
                    title="Afiliasi dengan tokoh"
                    lead="Kepemilikan atau endorsemen yang menghubungkan usaha ini dengan tokoh publik."
                >
                    {affiliations.length > 0 ? (
                        <ul className="divide-y-2 divide-ink/15">
                            {affiliations.map((item) => (
                                <li key={item.id} className="py-7 first:pt-0">
                                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:gap-6">
                                        {item.politician.photo_url ? (
                                            <img
                                                src={item.politician.photo_url}
                                                alt=""
                                                className="h-20 w-20 shrink-0 border-2 border-ink object-cover"
                                            />
                                        ) : (
                                            <span
                                                className="flex h-20 w-20 shrink-0 items-end border-2 border-ink bg-canvas p-2 text-2xl font-bold text-brand"
                                                aria-hidden="true"
                                            >
                                                {item.politician.name.charAt(0)}
                                            </span>
                                        )}
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="text-lg font-bold">
                                                    {item.label}{' '}
                                                    <Link href={item.politician.href}>{item.politician.name}</Link>
                                                </p>
                                                <ReliabilityBadge level={item.reliability_level} />
                                            </div>
                                            {item.politician.position ? (
                                                <p className="mt-1 text-muted">{item.politician.position}</p>
                                            ) : null}
                                            {item.politician.institution ? (
                                                <p className="text-muted">{item.politician.institution}</p>
                                            ) : null}
                                            <p className="mt-3 text-lg leading-relaxed">{item.summary}</p>
                                            {item.attachments?.length ? (
                                                <RecordMedia attachments={item.attachments} />
                                            ) : null}
                                            <a
                                                href={item.source_url}
                                                className="mt-3 inline-block font-bold"
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                Sumber: {item.source_type}
                                            </a>
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p>Belum ada afiliasi terbit.</p>
                    )}
                </ProfileSection>
            </div>
        </Public>
    );
}
