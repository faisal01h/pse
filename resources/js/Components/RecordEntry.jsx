import { useState } from 'react';
import { Link } from '@inertiajs/inertia-react';
import RecordMedia from '@/Components/RecordMedia';
import RecordShare from '@/Components/RecordShare';
import ReliabilityBadge from '@/Components/ReliabilityBadge';
import TopicBadge from '@/Components/TopicBadge';
import { categoryClass, categoryLabel } from '@/Components/recordLabels';

export default function RecordEntry({ record, politician = null }) {
    const href = record.href || route('records.show', record.id);
    const [shareOpen, setShareOpen] = useState(false);

    return (
        <article
            id={`record-${record.id}`}
            className={`scroll-mt-8 relative border-b-2 border-ink/15 py-6 first:pt-0 last:border-b-0 last:pb-0 ${
                shareOpen ? 'z-30' : 'z-0'
            }`}
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex flex-wrap items-center gap-2">
                    <span className={`border-2 px-2 py-0.5 text-sm font-bold ${categoryClass[record.category] ?? 'border-ink text-ink'}`}>
                        {categoryLabel[record.category] ?? record.category}
                    </span>
                    <ReliabilityBadge level={record.reliability_level} />
                    {(record.topics ?? []).map((topic) => (
                        <TopicBadge key={topic.id} topic={topic} className="border-2 border-ink/30 px-2 py-0.5 text-sm" />
                    ))}
                </div>
                <RecordShare record={record} politician={politician} onOpenChange={setShareOpen} />
            </div>

            {record.quote ? (
                <blockquote className="mt-4 border-l-4 border-brand pl-4 text-xl leading-snug">
                    <Link href={href} className="record-body-link">
                        “{record.quote}”
                    </Link>
                </blockquote>
            ) : null}
            {record.summary ? (
                <p className="mt-4 text-lg leading-relaxed">
                    <Link href={href} className="record-body-link">
                        {record.summary}
                    </Link>
                </p>
            ) : null}

            <RecordMedia attachments={record.attachments} />

            <p className="mt-4">
                <a href={record.source_url} className="font-bold" target="_blank" rel="noreferrer">
                    Sumber: {record.source_type}
                </a>
            </p>
        </article>
    );
}
