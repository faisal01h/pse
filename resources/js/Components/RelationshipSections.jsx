import { Link } from '@inertiajs/inertia-react';
import Spectrum from '@/Components/Spectrum';

export default function RelationshipSections({ tendencies = [], connections = [], stances = [], affiliations = [] }) {
    if (tendencies.length === 0 && connections.length === 0 && stances.length === 0 && affiliations.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-6 border-t border-ink/20 pt-4">
            {affiliations.length > 0 ? (
                <section>
                    <h3 className="text-xl font-bold">Afiliasi bisnis</h3>
                    <p className="mt-1 text-muted">Kepemilikan atau endorsemen yang dicatat dari sumber publik.</p>
                    <ul className="mt-3 flex flex-col gap-3">
                        {affiliations.map((item) => (
                            <li key={`${item.label}-${item.business.id}`}>
                                <p className="font-bold">{item.label} <Link href={item.business.href}>{item.business.name}</Link></p>
                                <p>{item.summary}</p>
                                <a href={item.source_url} className="font-bold" target="_blank" rel="noreferrer">Sumber: {item.source_type}</a>
                            </li>
                        ))}
                    </ul>
                </section>
            ) : null}
            {tendencies.length > 0 ? (
                <section>
                    <h3 className="text-xl font-bold">Kecenderungan</h3>
                    <p className="mt-1 text-muted">Titik pada sumbu dicatat dari sumber publik.</p>
                    <ul className="mt-3 flex flex-col gap-4">
                        {tendencies.map((item) => (
                            <li key={item.axis}>
                                <Spectrum label={item.axis_label} left={item.left} right={item.right} score={item.score} />
                                {item.samples > 1 ? <p className="mt-2 text-muted">Rata-rata dari {item.samples} catatan.</p> : null}
                                <p className="mt-2">{item.note}</p>
                                <a href={item.source_url} className="font-bold" target="_blank" rel="noreferrer">Sumber: {item.source_type}</a>
                            </li>
                        ))}
                    </ul>
                </section>
            ) : null}
            {connections.length > 0 ? (
                <section>
                    <h3 className="text-xl font-bold">Hubungan</h3>
                    <ul className="mt-3 flex flex-col gap-3">
                        {connections.map((item) => (
                            <li key={`${item.label}-${item.other.id}`}>
                                <p className="font-bold">{item.label} <Link href={item.other.href}>{item.other.name}</Link></p>
                                <p>{item.summary}</p>
                                <a href={item.source_url} className="font-bold" target="_blank" rel="noreferrer">Sumber: {item.source_type}</a>
                            </li>
                        ))}
                    </ul>
                </section>
            ) : null}
            {stances.length > 0 ? (
                <section>
                    <h3 className="text-xl font-bold">Isu</h3>
                    <ul className="mt-3 flex flex-col gap-3">
                        {stances.map((item) => (
                            <li key={`${item.issue}-${item.position_label}`}>
                                <p className="font-bold">{item.issue}: {item.position_label}</p>
                                <p>{item.note}</p>
                                <a href={item.source_url} className="font-bold" target="_blank" rel="noreferrer">Sumber: {item.source_type}</a>
                                {item.aligned?.length > 0 ? (
                                    <p className="mt-1">
                                        Selaras pada isu ini:{' '}
                                        {item.aligned.map((other, index) => (
                                            <span key={other.href}>
                                                {index > 0 ? ', ' : ''}
                                                <Link href={other.href}>{other.name}</Link>
                                            </span>
                                        ))}
                                    </p>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                </section>
            ) : null}
        </div>
    );
}
