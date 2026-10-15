import { reliabilityLabels } from '@/Components/ReliabilityBadge';

const options = [
    ['low', reliabilityLabels.low],
    ['medium', reliabilityLabels.medium],
    ['high', reliabilityLabels.high],
    ['officially_verified', reliabilityLabels.officially_verified],
];

export default function ReliabilityField({ value, onChange, required = false }) {
    return (
        <fieldset className="mt-8">
            <legend className="text-xl font-bold">
                Tingkat keandalan informasi
                {required ? <span className="text-brand"> *</span> : null}
            </legend>
            <p className="mt-1 text-sm text-muted">Wajib diisi saat menerbitkan. Ditampilkan ke publik.</p>
            <div className="mt-3 grid gap-2 sm:grid-cols-2">
                {options.map(([key, label]) => (
                    <label
                        key={key}
                        className={`flex cursor-pointer items-center gap-3 border-2 px-3 py-2.5 ${
                            value === key ? 'border-ink bg-canvas' : 'border-ink/20 bg-white'
                        }`}
                    >
                        <input
                            type="radio"
                            name="reliability_level"
                            value={key}
                            checked={value === key}
                            onChange={() => onChange(key)}
                            className="h-4 w-4 border-2 border-ink text-brand"
                        />
                        <span className="font-bold">{label}</span>
                    </label>
                ))}
            </div>
        </fieldset>
    );
}
