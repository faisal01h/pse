export default function Spectrum({ label, left, right, score, scoreSuffix = 'dari 100' }) {
    const value = Math.min(100, Math.max(0, Number(score) || 0));

    return (
        <div>
            {label ? <p className="font-bold">{label}</p> : null}
            <div className="mt-2 flex justify-between gap-4 text-sm">
                <span>{left}</span>
                <span className="text-right">{right}</span>
            </div>
            <div
                className="relative mt-2 h-3 border-2 border-ink bg-canvas"
                role="meter"
                aria-label={label ? `${label}: ${value} ${scoreSuffix}` : `${value} ${scoreSuffix}`}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={value}
            >
                <span
                    className="absolute top-1/2 h-5 w-1 -translate-x-1/2 -translate-y-1/2 bg-brand"
                    style={{ left: `${value}%` }}
                />
            </div>
            <p className="mt-2 text-muted">{value} {scoreSuffix}</p>
        </div>
    );
}
