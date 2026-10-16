import { Link } from '@inertiajs/inertia-react';
import Spectrum from '@/Components/Spectrum';
import ReliabilityBadge from '@/Components/ReliabilityBadge';
import ProfileSection from '@/Components/ProfileSection';

function SourceLink({ url, type }) {
    if (!url) {
        return null;
    }

    return (
        <a href={url} className="mt-2 inline-block font-bold" target="_blank" rel="noreferrer">
            Sumber: {type}
        </a>
    );
}

function EntryList({ children }) {
    return <ul className="divide-y-2 divide-ink/10 border-t-2 border-ink/10">{children}</ul>;
}

function Entry({ children }) {
    return <li className="py-5 first:pt-4">{children}</li>;
}

function TimelineList({ items, emptyLabel }) {
    if (!items?.length) {
        return <p className="text-muted">{emptyLabel}</p>;
    }

    return (
        <EntryList>
            {items.map((item, index) => (
                <Entry key={`${item.issue || item.label}-${item.effective_from}-${index}`}>
                    <p className="text-sm text-muted">
                        {item.effective_from || '—'}
                        {' → '}
                        {item.active ? 'sekarang' : (item.ended_at || '—')}
                        {item.active ? <span className="ml-2 font-bold text-ink">aktif</span> : null}
                    </p>
                    <p className="mt-1 text-lg font-bold">
                        {item.issue ? (
                            <>
                                {item.issue}
                                <span className="text-muted"> · {item.position_label}</span>
                            </>
                        ) : (
                            <>
                                {item.label}{' '}
                                {item.other ? <Link href={item.other.href}>{item.other.name}</Link> : null}
                            </>
                        )}
                    </p>
                    {item.note || item.summary ? (
                        <p className="mt-2 leading-relaxed">{item.note || item.summary}</p>
                    ) : null}
                    <SourceLink url={item.source_url} type={item.source_type} />
                </Entry>
            ))}
        </EntryList>
    );
}

export default function RelationshipSections({
    tendencies = [],
    connections = [],
    stances = [],
    affiliations = [],
    stanceTimeline = [],
    connectionTimeline = [],
    asSections = true,
}) {
    const hasTimeline = stanceTimeline.length > 0 || connectionTimeline.length > 0;
    if (tendencies.length === 0 && connections.length === 0 && stances.length === 0 && affiliations.length === 0 && !hasTimeline) {
        return null;
    }

    const wrap = (id, title, lead, body) => {
        if (!asSections) {
            return (
                <div key={id} className="mt-8 first:mt-0">
                    <h3 className="text-xl font-bold">{title}</h3>
                    {lead ? <p className="mt-1 text-muted">{lead}</p> : null}
                    <div className="mt-4">{body}</div>
                </div>
            );
        }

        return (
            <ProfileSection key={id} id={id} title={title} lead={lead} className="mt-14">
                {body}
            </ProfileSection>
        );
    };

    return (
        <div className="flex flex-col">
            {affiliations.length > 0
                ? wrap(
                    'afiliasi',
                    'Afiliasi bisnis',
                    'Kepemilikan atau endorsemen yang dicatat dari sumber publik.',
                    <EntryList>
                        {affiliations.map((item) => (
                            <Entry key={`${item.label}-${item.business.id}`}>
                                <div className="flex flex-wrap items-center gap-2">
                                    <p className="text-lg font-bold">
                                        {item.label}{' '}
                                        <Link href={item.business.href}>{item.business.name}</Link>
                                    </p>
                                    <ReliabilityBadge level={item.reliability_level} />
                                </div>
                                {item.summary ? <p className="mt-2 leading-relaxed">{item.summary}</p> : null}
                                <SourceLink url={item.source_url} type={item.source_type} />
                            </Entry>
                        ))}
                    </EntryList>,
                )
                : null}

            {tendencies.length > 0
                ? wrap(
                    'kecenderungan',
                    'Kecenderungan',
                    'Titik pada sumbu politik dicatat dari sumber publik.',
                    <EntryList>
                        {tendencies.map((item) => (
                            <Entry key={item.axis}>
                                <Spectrum
                                    label={item.axis_label}
                                    left={item.left}
                                    right={item.right}
                                    score={item.score}
                                    records={item.records}
                                />
                                {(item.records?.length ?? 0) === 0 && item.samples > 1 ? (
                                    <p className="mt-1.5 text-sm text-muted">Rata-rata dari {item.samples} catatan.</p>
                                ) : null}
                                {item.note ? <p className="mt-1.5 text-sm leading-relaxed">{item.note}</p> : null}
                                {(item.records?.length ?? 0) === 0 ? (
                                    <SourceLink url={item.source_url} type={item.source_type} />
                                ) : null}
                            </Entry>
                        ))}
                    </EntryList>,
                )
                : null}

            {connections.length > 0
                ? wrap(
                    'hubungan',
                    'Hubungan',
                    'Kaitan dengan tokoh atau partai lain yang tercatat dari sumber.',
                    <EntryList>
                        {connections.map((item) => (
                            <Entry key={`${item.label}-${item.other.id}`}>
                                <p className="text-lg font-bold">
                                    {item.label} <Link href={item.other.href}>{item.other.name}</Link>
                                </p>
                                {item.summary ? <p className="mt-2 leading-relaxed">{item.summary}</p> : null}
                                <SourceLink url={item.source_url} type={item.source_type} />
                            </Entry>
                        ))}
                    </EntryList>,
                )
                : null}

            {stances.length > 0
                ? wrap(
                    'isu',
                    'Posisi isu',
                    'Sikap terhadap isu publik yang tercatat dari sumber (posisi aktif).',
                    <EntryList>
                        {stances.map((item) => (
                            <Entry key={`${item.issue}-${item.position_label}`}>
                                <p className="text-lg font-bold">
                                    {item.issue}
                                    <span className="text-muted"> · {item.position_label}</span>
                                </p>
                                {item.note ? <p className="mt-2 leading-relaxed">{item.note}</p> : null}
                                <SourceLink url={item.source_url} type={item.source_type} />
                                {item.aligned?.length > 0 ? (
                                    <p className="mt-2 text-muted">
                                        Selaras pada isu ini:{' '}
                                        {item.aligned.map((other, index) => (
                                            <span key={other.href}>
                                                {index > 0 ? ', ' : ''}
                                                <Link href={other.href}>{other.name}</Link>
                                            </span>
                                        ))}
                                    </p>
                                ) : null}
                            </Entry>
                        ))}
                    </EntryList>,
                )
                : null}

            {stanceTimeline.length > 1
                ? wrap(
                    'linimasa-isu',
                    'Linimasa posisi isu',
                    'Perubahan sikap dari waktu ke waktu, termasuk posisi yang sudah berakhir.',
                    <TimelineList items={stanceTimeline} emptyLabel="Belum ada riwayat posisi isu." />,
                )
                : null}

            {connectionTimeline.length > 1
                ? wrap(
                    'linimasa-hubungan',
                    'Linimasa hubungan',
                    'Koalisi, oposisi, atau kaitan lain beserta masa berlakunya.',
                    <TimelineList items={connectionTimeline} emptyLabel="Belum ada riwayat hubungan." />,
                )
                : null}
        </div>
    );
}
