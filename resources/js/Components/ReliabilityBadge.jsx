const labels = {
    low: 'Reliabilitas Rendah',
    medium: 'Reliabilitas Medium',
    high: 'Terpercaya',
    officially_verified: 'Terverifikasi',
};

const tones = {
    low: 'border border-orange-500 bg-white text-orange-600',
    medium: 'border border-yellow-500 bg-white text-yellow-700',
    high: 'border-0 bg-green-100 text-green-800',
    officially_verified: 'border-0 bg-sky-100 text-sky-800',
};

function CheckIcon({ className = '' }) {
    return (
        <svg
            aria-hidden="true"
            viewBox="0 0 16 16"
            className={`h-3.5 w-3.5 shrink-0 ${className}`}
            fill="none"
            stroke="currentColor"
            strokeWidth="2.25"
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            <path d="M3.5 8.5 6.5 11.5 12.5 4.5" />
        </svg>
    );
}

export default function ReliabilityBadge({ level, className = '' }) {
    if (!level || !labels[level]) {
        return null;
    }

    const showCheck = level === 'high' || level === 'officially_verified';

    return (
        <span
            className={`inline-flex items-center gap-1 px-2 py-0.5 text-sm font-bold ${tones[level] ?? 'border border-ink text-ink'} ${className}`}
            title="Tingkat keandalan informasi"
        >
            {showCheck ? <CheckIcon /> : null}
            {labels[level]}
        </span>
    );
}

export { labels as reliabilityLabels };
