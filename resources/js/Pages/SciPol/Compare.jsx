import { useEffect, useMemo, useRef, useState } from 'react';
import { Inertia } from '@inertiajs/inertia';
import { Head } from '@inertiajs/inertia-react';
import Spectrum from '@/Components/Spectrum';
import SciPol, { CategoryBadge, ExportButton, SciPolTable, SciPolTd, SciPolTh } from '@/Layouts/SciPol';

function SuggestField({ type, onPick }) {
    const [value, setValue] = useState('');
    const [items, setItems] = useState([]);
    const [open, setOpen] = useState(false);
    const root = useRef(null);
    const requestId = useRef(0);

    useEffect(() => {
        const close = (event) => {
            if (!root.current?.contains(event.target)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, []);

    useEffect(() => {
        const query = value.trim();
        if (query.length < 2) {
            setItems([]);
            setOpen(false);
            return undefined;
        }

        const id = requestId.current + 1;
        requestId.current = id;
        const timer = setTimeout(async () => {
            const response = await fetch(
                `${route('scipol.suggest')}?type=${encodeURIComponent(type)}&q=${encodeURIComponent(query)}`,
                { headers: { Accept: 'application/json' } },
            );
            if (requestId.current !== id || !response.ok) {
                return;
            }
            const next = await response.json();
            const list = (next || []).slice(0, 8).map((item) => ({
                id: String(item.id),
                name: item.name,
            }));
            setItems(list);
            setOpen(list.length > 0);
        }, 180);

        return () => clearTimeout(timer);
    }, [value, type]);

    const choose = (item) => {
        onPick(item);
        setValue('');
        setItems([]);
        setOpen(false);
    };

    return (
        <div ref={root} className="relative">
            <input
                value={value}
                onChange={(event) => setValue(event.target.value)}
                className="w-full border border-ink/30 bg-white px-2 py-1.5 text-sm"
                placeholder={type === 'party' ? 'Search parties…' : 'Search politicians…'}
                autoComplete="off"
            />
            {open && items.length > 0 ? (
                <ul className="absolute z-20 mt-0.5 max-h-48 w-full overflow-auto border border-ink/30 bg-white shadow">
                    {items.map((item) => (
                        <li key={item.id}>
                            <button
                                type="button"
                                className="block w-full px-2 py-1.5 text-left text-sm hover:bg-[#e8ecf0]"
                                onMouseDown={(event) => {
                                    event.preventDefault();
                                    choose(item);
                                }}
                            >
                                {item.name}
                            </button>
                        </li>
                    ))}
                </ul>
            ) : null}
        </div>
    );
}

export default function Compare({ type = 'politician', ids = [], subjects = [], export_url = null }) {
    const selected = useMemo(() => ids.map(String), [ids]);

    const apply = (nextType, nextIds) => {
        const unique = [...new Set(nextIds.map(String).filter(Boolean))].slice(0, 3);
        Inertia.get('/scipol/compare', {
            type: nextType,
            ids: unique.join(','),
        }, { preserveState: true, replace: true });
    };

    const add = (item) => {
        const id = String(item.id);
        if (selected.includes(id)) {
            return;
        }
        apply(type, [...selected, id]);
    };

    const remove = (id) => {
        apply(type, selected.filter((value) => value !== String(id)));
    };

    return (
        <SciPol title="compare">
            <Head title="SciPol · Compare" />

            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold">Compare</h1>
                    <p className="mt-1 text-sm text-muted">Select 2–3 subjects to compare evidence, not opinion.</p>
                </div>
                <ExportButton href={export_url} disabled={!export_url} />
            </div>

            <div className="mb-4 flex flex-wrap items-end gap-3 border border-ink/15 bg-white p-3">
                <div>
                    <label className="mb-1 block text-[11px] font-bold uppercase text-muted">Kind</label>
                    <select
                        className="border border-ink/30 bg-white px-2 py-1.5 text-sm"
                        value={type}
                        onChange={(event) => apply(event.target.value, [])}
                    >
                        <option value="politician">Politicians</option>
                        <option value="party">Parties</option>
                    </select>
                </div>
                <div className="min-w-[220px] flex-1">
                    <label className="mb-1 block text-[11px] font-bold uppercase text-muted">Add subject</label>
                    <SuggestField key={type} type={type} onPick={add} />
                </div>
                <div className="flex flex-wrap gap-1">
                    {subjects.map((subject) => (
                        <button
                            key={subject.id}
                            type="button"
                            className="border border-ink bg-[#f4f6f8] px-2 py-1 text-sm"
                            onClick={() => remove(subject.id)}
                            title="Remove from comparison"
                        >
                            {subject.name} ×
                        </button>
                    ))}
                </div>
            </div>

            {subjects.length === 0 ? (
                <p className="text-sm text-muted">No subjects yet. Search and add at least two for a useful matrix.</p>
            ) : (
                <div className="space-y-6">
                    <SciPolTable>
                        <thead>
                            <tr>
                                <SciPolTh className="w-40">Field</SciPolTh>
                                {subjects.map((subject) => (
                                    <SciPolTh key={subject.id}>
                                        <a href={subject.href} className="text-ink underline">{subject.name}</a>
                                    </SciPolTh>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <SciPolTd className="font-bold">Identity</SciPolTd>
                                {subjects.map((subject) => (
                                    <SciPolTd key={subject.id}>
                                        {subject.position ? <div>{subject.position}</div> : null}
                                        {subject.institution ? <div className="text-muted">{subject.institution}</div> : null}
                                        {subject.party?.name ? <div className="text-muted">{subject.party.name}</div> : null}
                                    </SciPolTd>
                                ))}
                            </tr>
                            <tr>
                                <SciPolTd className="font-bold">Records</SciPolTd>
                                {subjects.map((subject) => (
                                    <SciPolTd key={subject.id}>
                                        {subject.record_counts ? (
                                            <div className="flex flex-wrap gap-1">
                                                {Object.entries(subject.record_counts).map(([category, count]) => (
                                                    <span key={category} className="inline-flex items-center gap-1">
                                                        <CategoryBadge category={category} />
                                                        <span>{count}</span>
                                                    </span>
                                                ))}
                                            </div>
                                        ) : (
                                            <span className="text-muted">—</span>
                                        )}
                                    </SciPolTd>
                                ))}
                            </tr>
                        </tbody>
                    </SciPolTable>

                    <section>
                        <h2 className="mb-2 text-sm font-bold uppercase tracking-wide text-muted">Tendencies</h2>
                        <div className={`grid gap-3 ${subjects.length === 1 ? 'grid-cols-1' : subjects.length === 2 ? 'md:grid-cols-2' : 'md:grid-cols-3'}`}>
                            {subjects.map((subject) => (
                                <div key={subject.id} className="border border-ink/20 bg-white p-3">
                                    <h3 className="mb-3 font-bold">{subject.name}</h3>
                                    {(subject.tendencies || []).length === 0 ? (
                                        <p className="text-sm text-muted">No axes yet.</p>
                                    ) : (
                                        <div className="space-y-3">
                                            {subject.tendencies.map((item) => (
                                                <div key={item.axis}>
                                                    <Spectrum
                                                        compact
                                                        label={item.axis_label}
                                                        left={item.left}
                                                        right={item.right}
                                                        score={item.score}
                                                        scoreSuffix="of 100"
                                                        records={item.records}
                                                        basisLabel="Basis records"
                                                        basisCountLabel="records"
                                                    />
                                                    <p className="mt-1 text-[12px] text-muted">
                                                        samples={item.samples}
                                                        {(item.records?.length ?? 0) === 0 && item.source_url ? (
                                                            <>
                                                                {' · '}
                                                                <a href={item.source_url} target="_blank" rel="noreferrer">source</a>
                                                            </>
                                                        ) : null}
                                                    </p>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </section>

                    <section>
                        <h2 className="mb-2 text-sm font-bold uppercase tracking-wide text-muted">Issue stances</h2>
                        <SciPolTable>
                            <thead>
                                <tr>
                                    <SciPolTh>Subject</SciPolTh>
                                    <SciPolTh>Issue</SciPolTh>
                                    <SciPolTh>Position</SciPolTh>
                                    <SciPolTh>Source</SciPolTh>
                                </tr>
                            </thead>
                            <tbody>
                                {subjects.flatMap((subject) =>
                                    (subject.issue_stances || []).length === 0
                                        ? [(
                                            <tr key={`${subject.id}-empty`}>
                                                <SciPolTd>{subject.name}</SciPolTd>
                                                <SciPolTd colSpan={3} className="text-muted">No issue stances</SciPolTd>
                                            </tr>
                                        )]
                                        : subject.issue_stances.map((stance, index) => (
                                            <tr key={`${subject.id}-${index}`}>
                                                <SciPolTd>{subject.name}</SciPolTd>
                                                <SciPolTd>{stance.issue}</SciPolTd>
                                                <SciPolTd>{stance.position_label}</SciPolTd>
                                                <SciPolTd>
                                                    {stance.source_url ? (
                                                        <a href={stance.source_url} target="_blank" rel="noreferrer">
                                                            {stance.source_type || 'source'}
                                                        </a>
                                                    ) : '—'}
                                                </SciPolTd>
                                            </tr>
                                        ))
                                )}
                            </tbody>
                        </SciPolTable>
                    </section>

                    <section>
                        <h2 className="mb-2 text-sm font-bold uppercase tracking-wide text-muted">Business affiliations</h2>
                        <SciPolTable>
                            <thead>
                                <tr>
                                    <SciPolTh>Subject</SciPolTh>
                                    <SciPolTh>Type</SciPolTh>
                                    <SciPolTh>Business</SciPolTh>
                                    <SciPolTh>%</SciPolTh>
                                    <SciPolTh>Reliability</SciPolTh>
                                    <SciPolTh>Source</SciPolTh>
                                </tr>
                            </thead>
                            <tbody>
                                {subjects.flatMap((subject) =>
                                    (subject.business_affiliations || []).length === 0
                                        ? [(
                                            <tr key={`${subject.id}-aff-empty`}>
                                                <SciPolTd>{subject.name}</SciPolTd>
                                                <SciPolTd colSpan={5} className="text-muted">No published affiliations</SciPolTd>
                                            </tr>
                                        )]
                                        : subject.business_affiliations.map((row, index) => (
                                            <tr key={`${subject.id}-aff-${index}`}>
                                                <SciPolTd>{subject.name}</SciPolTd>
                                                <SciPolTd>{row.label}</SciPolTd>
                                                <SciPolTd>
                                                    <a href={row.business?.href}>{row.business?.name}</a>
                                                </SciPolTd>
                                                <SciPolTd>{row.ownership_percent ?? '—'}</SciPolTd>
                                                <SciPolTd>{row.reliability_level || '—'}</SciPolTd>
                                                <SciPolTd>
                                                    {row.source_url ? (
                                                        <a href={row.source_url} target="_blank" rel="noreferrer">
                                                            {row.source_type || 'source'}
                                                        </a>
                                                    ) : '—'}
                                                </SciPolTd>
                                            </tr>
                                        ))
                                )}
                            </tbody>
                        </SciPolTable>
                    </section>
                </div>
            )}
        </SciPol>
    );
}
