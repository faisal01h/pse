import { Head, Link } from '@inertiajs/inertia-react';
import Public from '@/Layouts/Public';
import ProfileHero, { ProfileCrumb } from '@/Components/ProfileHero';
import RecordMedia from '@/Components/RecordMedia';
import RecordShare from '@/Components/RecordShare';
import ReliabilityBadge from '@/Components/ReliabilityBadge';
import TopicBadge from '@/Components/TopicBadge';
import { categoryClass, categoryLabel, formatRecordDate } from '@/Components/recordLabels';

export default function PublicRecord({ record, auth }) {
    const politician = record.politician;
    const title = politician?.name
        ? `${politician.name} — ${categoryLabel[record.category] ?? 'Catatan'}`
        : 'Catatan publik';

    const meta = [
        politician?.position ? { node: politician.position } : null,
        politician?.institution ? { node: politician.institution, muted: true } : null,
        politician?.party
            ? {
                node: (
                    <span className="inline-flex flex-wrap items-center gap-2">
                        {politician.party.logo_url ? (
                            <img
                                src={politician.party.logo_url}
                                alt=""
                                className="h-7 w-7 border-2 border-ink bg-white object-contain"
                            />
                        ) : null}
                        <Link href={route('parties.show', politician.party.id)}>
                            {politician.party.name}
                        </Link>
                    </span>
                ),
            }
            : null,
        { node: formatRecordDate(record.date || record.occurred_on), muted: true },
    ].filter(Boolean);

    return (
        <Public auth={auth}>
            <Head title={title} />

            <ProfileHero
                breadcrumb={(
                    <ProfileCrumb
                        items={[
                            { href: '/', label: 'Beranda' },
                            politician
                                ? { href: politician.href || route('politicians.show', politician.id), label: politician.name }
                                : null,
                            { label: 'Catatan' },
                        ].filter(Boolean)}
                    />
                )}
                image={politician?.photo_url}
                imageAlt=""
                kicker="Catatan publik"
                title={politician?.name ?? 'Catatan'}
                meta={meta}
                action={<RecordShare record={record} />}
            />

            <div className="mx-auto max-w-[960px] px-4 py-10 sm:py-12">
                <article className="border-2 border-ink border-l-8 border-l-brand bg-white p-5 sm:p-8">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className={`border-2 px-2 py-0.5 text-sm font-bold ${categoryClass[record.category] ?? 'border-ink text-ink'}`}>
                            {categoryLabel[record.category] ?? record.category}
                        </span>
                        <ReliabilityBadge level={record.reliability_level} />
                        {(record.topics ?? []).map((topic) => (
                            <TopicBadge key={topic.id} topic={topic} className="border-2 border-ink/30 px-2 py-0.5 text-sm" />
                        ))}
                    </div>

                    {record.quote ? (
                        <blockquote className="mt-6 border-l-4 border-brand pl-4 text-2xl leading-snug sm:text-3xl">
                            “{record.quote}”
                        </blockquote>
                    ) : null}

                    {record.summary ? (
                        <p className="mt-6 text-lg leading-relaxed">{record.summary}</p>
                    ) : null}

                    <RecordMedia attachments={record.attachments} />

                    <div className="mt-8 flex flex-wrap items-center justify-between gap-4 border-t-2 border-ink/15 pt-6">
                        <a href={record.source_url} className="font-bold" target="_blank" rel="noreferrer">
                            Sumber: {record.source_type}
                        </a>
                        <RecordShare record={record} />
                    </div>
                </article>

                {politician ? (
                    <p className="mt-8">
                        <Link href={politician.href || route('politicians.show', politician.id)} className="font-bold">
                            Lihat profil {politician.name}
                        </Link>
                    </p>
                ) : null}
            </div>
        </Public>
    );
}
