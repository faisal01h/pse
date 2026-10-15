import { useState } from 'react';
import { Link } from '@inertiajs/inertia-react';
import RecordMedia from '@/Components/RecordMedia';
import RecordShare from '@/Components/RecordShare';
import ReliabilityBadge from '@/Components/ReliabilityBadge';
import TopicBadge from '@/Components/TopicBadge';
import { categoryClass, categoryLabel, formatRecordDate } from '@/Components/recordLabels';

function PoliticianAvatar({ politician }) {
    if (politician.photo_url) {
        return (
            <img
                src={politician.photo_url}
                alt=""
                className="h-11 w-11 shrink-0 border-2 border-ink object-cover"
                loading="lazy"
                decoding="async"
            />
        );
    }

    return (
        <div
            className="flex h-11 w-11 shrink-0 items-end border-2 border-ink bg-canvas p-1"
            aria-hidden="true"
        >
            <span className="text-lg font-bold leading-none text-brand">
                {String(politician.name || '?').trim().charAt(0).toUpperCase()}
            </span>
        </div>
    );
}

export default function FeedRecord({ report, index = 0 }) {
    const politician = report.politician;
    const href = report.href || route('records.show', report.id);
    const [shareOpen, setShareOpen] = useState(false);
    const meta = [politician.position, politician.institution].filter(Boolean).join(' · ');

    return (
        <article
            className={`feed-record relative border-2 border-ink border-l-4 border-l-brand bg-white ${
                shareOpen ? 'z-30' : 'z-0'
            }`}
            style={{ animationDelay: `${Math.min(index, 8) * 45}ms` }}
        >
            <div className="px-3 py-3.5 sm:px-4 sm:py-4">
                <div className="flex gap-3">
                    <Link href={route('politicians.show', politician.id)} className="shrink-0 self-start">
                        <PoliticianAvatar politician={politician} />
                    </Link>

                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <h3 className="text-lg font-bold leading-tight">
                                <Link href={route('politicians.show', politician.id)}>{politician.name}</Link>
                            </h3>
                            <span className="text-sm text-muted">{formatRecordDate(report.date)}</span>
                        </div>

                        {meta ? <p className="mt-0.5 text-sm text-muted">{meta}</p> : null}

                        {politician.party ? (
                            <p className="mt-1 flex flex-wrap items-center gap-1.5 text-sm">
                                {politician.party.logo_url ? (
                                    <img
                                        src={politician.party.logo_url}
                                        alt=""
                                        className="h-5 w-5 object-contain"
                                        loading="lazy"
                                        decoding="async"
                                    />
                                ) : null}
                                <Link href={route('parties.show', politician.party.id)} className="font-bold">
                                    {politician.party.name}
                                </Link>
                            </p>
                        ) : null}

                        <div className="mt-2 flex flex-wrap items-center gap-1.5">
                            <span className={`border-2 px-1.5 py-0.5 text-xs font-bold ${categoryClass[report.category] ?? 'border-ink text-ink'}`}>
                                {categoryLabel[report.category] ?? report.category}
                            </span>
                            <ReliabilityBadge level={report.reliability_level} className="text-xs" />
                            {(report.topics ?? []).slice(0, 3).map((topic) => (
                                <TopicBadge key={topic.id} topic={topic} />
                            ))}
                        </div>

                        {report.quote ? (
                            <blockquote className="mt-2.5 border-l-4 border-brand pl-3 text-base leading-snug sm:text-lg">
                                <Link href={href} className="record-body-link">
                                    “{report.quote}”
                                </Link>
                            </blockquote>
                        ) : null}

                        {!report.quote && report.summary ? (
                            <p className="mt-2 text-base leading-snug">
                                <Link href={href} className="record-body-link">
                                    {report.summary}
                                </Link>
                            </p>
                        ) : null}

                        {report.quote && report.summary ? (
                            <p className="mt-1.5 text-sm leading-snug text-muted">
                                <Link href={href} className="record-body-link">
                                    {report.summary}
                                </Link>
                            </p>
                        ) : null}

                        <RecordMedia attachments={report.attachments} compact />

                        <div className="mt-2.5 flex flex-wrap items-center justify-between gap-2">
                            <a href={report.source_url} className="text-sm font-bold" target="_blank" rel="noreferrer">
                                Sumber: {report.source_type}
                            </a>
                            <div className="flex flex-wrap items-center gap-2">
                                <Link href={href} className="text-sm font-bold">
                                    Baca
                                </Link>
                                <RecordShare
                                    record={report}
                                    menuPlacement="top"
                                    onOpenChange={setShareOpen}
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </article>
    );
}
