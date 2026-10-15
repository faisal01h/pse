import { Link } from '@inertiajs/inertia-react';

const categoryLabel = {
    statement: 'Pernyataan',
    allegation: 'Dugaan',
    conviction: 'Vonis',
};

export default function Spectrum({
    label,
    left,
    right,
    score,
    scoreSuffix = 'dari 100',
    compact = false,
    records = [],
    basisLabel = 'Dasar perhitungan',
    basisCountLabel = 'catatan',
}) {
    const value = Math.min(100, Math.max(0, Number(score) || 0));
    const basis = Array.isArray(records) ? records : [];
    const aria = label
        ? `${label}: ${value} ${scoreSuffix}. ${left} hingga ${right}.`
        : `${value} ${scoreSuffix}. ${left} hingga ${right}.`;

    return (
        <div className={compact ? 'spectrum spectrum--compact' : 'spectrum'}>
            <div className="flex items-baseline justify-between gap-3">
                {label ? (
                    <p className={`min-w-0 truncate font-bold ${compact ? 'text-sm' : 'text-base'}`}>
                        {label}
                    </p>
                ) : <span />}
                <span
                    className={`shrink-0 tabular-nums font-bold ${
                        compact
                            ? 'text-xs text-muted'
                            : 'border-2 border-ink bg-white px-1.5 py-0.5 text-sm'
                    }`}
                    title={`${value} ${scoreSuffix}`}
                >
                    {value}
                    <span className="font-normal text-muted">/100</span>
                </span>
            </div>

            <div
                className={`grid grid-cols-[minmax(0,1fr)_minmax(4.5rem,1.6fr)_minmax(0,1fr)] items-center gap-2 ${
                    compact ? 'mt-1.5' : 'mt-2'
                }`}
            >
                <span
                    className={`truncate text-right text-muted ${compact ? 'text-[11px] leading-tight' : 'text-xs sm:text-sm'}`}
                    title={left}
                >
                    {left}
                </span>

                <div
                    className="spectrum-track relative h-2.5 overflow-visible border-2 border-ink bg-canvas"
                    role="meter"
                    aria-label={aria}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={value}
                >
                    <span
                        className="spectrum-fill absolute inset-y-0 left-0 bg-brand/35"
                        style={{ width: `${value}%` }}
                    />
                    <span
                        className="spectrum-marker absolute top-1/2 z-10 h-3.5 w-3.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-ink bg-brand shadow-[0_0_0_2px_#fff]"
                        style={{ left: `${value}%` }}
                    />
                </div>

                <span
                    className={`truncate text-muted ${compact ? 'text-[11px] leading-tight' : 'text-xs sm:text-sm'}`}
                    title={right}
                >
                    {right}
                </span>
            </div>

            {basis.length > 0 ? (
                <div className={compact ? 'mt-2' : 'mt-3'}>
                    <p className={`font-bold text-muted ${compact ? 'text-[11px]' : 'text-xs'}`}>
                        {basisLabel} ({basis.length} {basisCountLabel})
                    </p>
                    <ol className={`mt-1.5 flex flex-col ${compact ? 'gap-1' : 'gap-1.5'}`}>
                        {basis.map((record, index) => (
                            <li key={record.id} className={compact ? 'text-[11px] leading-snug' : 'text-sm leading-snug'}>
                                <span className="text-muted">{index + 1}. </span>
                                {record.href ? (
                                    <Link href={record.href} className="font-bold">
                                        {categoryLabel[record.category] ?? record.category}
                                    </Link>
                                ) : (
                                    <span className="font-bold">{categoryLabel[record.category] ?? record.category}</span>
                                )}
                                {record.excerpt ? (
                                    <span className="text-muted"> — {record.excerpt}</span>
                                ) : null}
                                {record.source_url ? (
                                    <>
                                        {' · '}
                                        <a href={record.source_url} target="_blank" rel="noreferrer" className="font-bold">
                                            {record.source_type || 'Sumber'}
                                        </a>
                                    </>
                                ) : null}
                            </li>
                        ))}
                    </ol>
                </div>
            ) : null}
        </div>
    );
}
