import { Link } from '@inertiajs/inertia-react';

export default function TopicBadge({ topic, className = '' }) {
    if (!topic?.id) {
        return null;
    }

    return (
        <Link
            href={route('topics.show', topic.id)}
            className={`topic-badge border border-ink/25 px-1.5 py-0.5 text-xs text-muted no-underline hover:border-ink hover:text-ink ${className}`}
        >
            {topic.name}
        </Link>
    );
}
