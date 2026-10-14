import { useEffect, useRef, useState } from 'react';
import { Link, useForm } from '@inertiajs/inertia-react';
import ValidationErrors from '@/Components/ValidationErrors';

function SourceFields({ data, setData, idPrefix }) {
    return (
        <>
            <div className="mt-4">
                <label htmlFor={`${idPrefix}-source-type`} className="block font-bold mb-1">Jenis sumber</label>
                <input id={`${idPrefix}-source-type`} value={data.source_type} onChange={(event) => setData('source_type', event.target.value)} className="gov-input" />
            </div>
            <div className="mt-4">
                <label htmlFor={`${idPrefix}-source-url`} className="block font-bold mb-1">Tautan sumber</label>
                <input id={`${idPrefix}-source-url`} value={data.source_url} onChange={(event) => setData('source_url', event.target.value)} className="gov-input" placeholder="https://" />
            </div>
            <div className="mt-4">
                <label htmlFor={`${idPrefix}-date`} className="block font-bold mb-1">Tanggal <span className="font-normal text-muted">(opsional)</span></label>
                <input id={`${idPrefix}-date`} type="date" value={data.occurred_on} onChange={(event) => setData('occurred_on', event.target.value)} className="gov-input" />
            </div>
        </>
    );
}

function SubjectPicker({ url, except, onPick }) {
    const [query, setQuery] = useState('');
    const [items, setItems] = useState([]);
    const [open, setOpen] = useState(false);
    const root = useRef(null);

    useEffect(() => {
        const term = query.trim();
        if (term.length < 2) {
            setItems([]);
            setOpen(false);
            return undefined;
        }

        const timer = setTimeout(async () => {
            const response = await fetch(`${url}?q=${encodeURIComponent(term)}&except=${except}`, {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) {
                return;
            }
            const next = await response.json();
            setItems(next);
            setOpen(next.length > 0);
        }, 180);

        return () => clearTimeout(timer);
    }, [query, url, except]);

    useEffect(() => {
        const close = (event) => {
            if (!root.current?.contains(event.target)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, []);

    return (
        <div ref={root} className="relative">
            <input
                value={query}
                onChange={(event) => {
                    setQuery(event.target.value);
                    onPick(null);
                }}
                className="gov-input"
                autoComplete="off"
                placeholder="Ketik minimal 2 huruf, lalu pilih"
            />
            {open ? (
                <ul className="absolute z-10 mt-0 w-full border-2 border-ink border-t-0 bg-white">
                    {items.map((item) => (
                        <li key={item.id}>
                            <button
                                type="button"
                                className="block w-full px-2 py-2 text-left hover:bg-canvas"
                                onMouseDown={(event) => {
                                    event.preventDefault();
                                    setQuery(item.name);
                                    setOpen(false);
                                    onPick(item);
                                }}
                            >
                                <span className="font-bold">{item.name}</span>
                                {item.position ? <span className="block text-muted">{item.position}</span> : null}
                            </button>
                        </li>
                    ))}
                </ul>
            ) : null}
        </div>
    );
}

function AxisForm({ axis, saved, url, destroyUrl }) {
    const { data, setData, post, processing, errors } = useForm({
        axis: axis.key,
        score: saved?.score ?? 50,
        note: saved?.note ?? '',
        source_type: saved?.source_type ?? '',
        source_url: saved?.source_url ?? '',
        occurred_on: saved?.occurred_on ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        post(url, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="border-2 border-ink p-4">
            <h3 className="text-xl font-bold">{axis.label}</h3>
            <ValidationErrors errors={errors} />
            <div className="mt-4">
                <label htmlFor={`axis-${axis.key}`} className="block font-bold mb-1">Titik pada sumbu</label>
                <div className="flex justify-between gap-4 text-sm">
                    <span>{axis.left}</span>
                    <span className="text-right">{axis.right}</span>
                </div>
                <input
                    id={`axis-${axis.key}`}
                    type="range"
                    min="0"
                    max="100"
                    value={data.score}
                    onChange={(event) => setData('score', Number(event.target.value))}
                    className="mt-2 w-full accent-brand"
                />
                <p className="mt-1 font-bold">{data.score} dari 100</p>
                {saved?.samples > 1 ? (
                    <p className="text-muted">Rata-rata dari {saved.samples} catatan. Menyimpan mengganti rata-rata itu.</p>
                ) : null}
            </div>
            <div className="mt-4">
                <label htmlFor={`note-${axis.key}`} className="block font-bold mb-1">Apa yang dikatakan sumber</label>
                <textarea id={`note-${axis.key}`} value={data.note} onChange={(event) => setData('note', event.target.value)} className="gov-input min-h-[8rem]" />
            </div>
            <SourceFields data={data} setData={setData} idPrefix={`axis-${axis.key}`} />
            <button className="gov-button mt-6" disabled={processing}>Simpan posisi</button>
            {saved ? (
                <Link href={destroyUrl} method="delete" as="button" className="ml-4 underline" preserveScroll>Hapus posisi</Link>
            ) : null}
        </form>
    );
}

export default function GraphEditor({
    graph,
    subjectId,
    suggestUrl,
    tendencyUrl,
    tendencyDestroy,
    linkUrl,
    linkDestroy,
    stanceUrl,
    stanceDestroy,
    affiliationUrl,
    affiliationDestroy,
}) {
    const linkForm = useForm({
        other_id: '',
        type: graph.link_types[0]?.key ?? '',
        summary: '',
        source_type: '',
        source_url: '',
        occurred_on: '',
    });
    const stanceForm = useForm({
        issue_name: '',
        position: graph.stance_positions[0]?.key ?? '',
        note: '',
        source_type: '',
        source_url: '',
        occurred_on: '',
    });
    const affiliationForm = useForm({
        business_id: 'other',
        business_name: '',
        type: graph.affiliation_types?.[0]?.key ?? 'ownership',
        ownership_percent: '',
        ownership_unknown: true,
        summary: '',
        source_type: '',
        source_url: '',
        occurred_on: '',
    });
    const [pickerKey, setPickerKey] = useState(0);

    const submitLink = (event) => {
        event.preventDefault();
        linkForm.post(linkUrl, {
            preserveScroll: true,
            onSuccess: () => {
                linkForm.reset();
                setPickerKey((value) => value + 1);
            },
        });
    };

    const submitStance = (event) => {
        event.preventDefault();
        stanceForm.post(stanceUrl, {
            preserveScroll: true,
            onSuccess: () => stanceForm.reset('issue_name', 'note', 'source_type', 'source_url', 'occurred_on'),
        });
    };

    const submitAffiliation = (event) => {
        event.preventDefault();
        if (!affiliationUrl) {
            return;
        }
        affiliationForm.post(affiliationUrl, {
            preserveScroll: true,
            onSuccess: () => {
                affiliationForm.reset();
                affiliationForm.setData({
                    business_id: 'other',
                    business_name: '',
                    type: graph.affiliation_types?.[0]?.key ?? 'ownership',
                    ownership_percent: '',
                    ownership_unknown: true,
                    summary: '',
                    source_type: '',
                    source_url: '',
                    occurred_on: '',
                });
            },
        });
    };

    return (
        <div className="mt-12 max-w-xl flex flex-col gap-10">
            <section>
                <h2 className="text-2xl font-bold">Kecenderungan</h2>
                <p className="mt-2">Satu titik per sumbu, dari ujung kiri ke ujung kanan. Setiap sumbu wajib punya sumber publik.</p>
                <div className="mt-4 flex flex-col gap-4">
                    {graph.axes.map((axis) => {
                        const saved = graph.tendencies.find((item) => item.axis === axis.key);

                        return (
                            <AxisForm
                                key={`${axis.key}-${saved?.score ?? ''}-${saved?.source_url ?? ''}`}
                                axis={axis}
                                saved={saved}
                                url={tendencyUrl}
                                destroyUrl={tendencyDestroy(axis.key)}
                            />
                        );
                    })}
                </div>
            </section>
            <section>
                <h2 className="text-2xl font-bold">Hubungan</h2>
                {graph.connections.length > 0 ? (
                    <ul className="mt-4 flex flex-col gap-3">
                        {graph.connections.map((item) => (
                            <li key={item.id} className="border-2 border-ink p-4">
                                <p className="font-bold">{item.label} {item.other?.name}</p>
                                <p className="mt-1">{item.summary}</p>
                                <a href={item.source_url} className="font-bold" target="_blank" rel="noreferrer">Sumber: {item.source_type}</a>
                                <div>
                                    <Link href={linkDestroy(item.id)} method="delete" as="button" className="mt-3 underline" preserveScroll>Hapus hubungan</Link>
                                </div>
                            </li>
                        ))}
                    </ul>
                ) : <p className="mt-2">Belum ada hubungan.</p>}
                <form onSubmit={submitLink} className="mt-4 border-2 border-ink p-4">
                    <h3 className="text-xl font-bold">Tambah hubungan</h3>
                    <ValidationErrors errors={linkForm.errors} />
                    <div className="mt-4">
                        <label className="block font-bold mb-1">Nama</label>
                        <SubjectPicker
                            key={pickerKey}
                            url={suggestUrl}
                            except={subjectId}
                            onPick={(item) => linkForm.setData('other_id', item ? String(item.id) : '')}
                        />
                    </div>
                    <div className="mt-4">
                        <label htmlFor="link-type" className="block font-bold mb-1">Jenis</label>
                        <select id="link-type" value={linkForm.data.type} onChange={(event) => linkForm.setData('type', event.target.value)} className="gov-input">
                            {graph.link_types.map((type) => (
                                <option key={type.key} value={type.key}>{type.label}</option>
                            ))}
                        </select>
                    </div>
                    <div className="mt-4">
                        <label htmlFor="link-summary" className="block font-bold mb-1">Ringkasan dari sumber</label>
                        <textarea id="link-summary" value={linkForm.data.summary} onChange={(event) => linkForm.setData('summary', event.target.value)} className="gov-input min-h-[8rem]" />
                    </div>
                    <SourceFields data={linkForm.data} setData={linkForm.setData} idPrefix="link" />
                    <button className="gov-button mt-6" disabled={linkForm.processing}>Simpan hubungan</button>
                </form>
            </section>
            <section>
                <h2 className="text-2xl font-bold">Isu</h2>
                {graph.stances.length > 0 ? (
                    <ul className="mt-4 flex flex-col gap-3">
                        {graph.stances.map((item) => (
                            <li key={item.id} className="border-2 border-ink p-4">
                                <p className="font-bold">{item.issue.name}: {item.label}</p>
                                <p className="mt-1">{item.note}</p>
                                <a href={item.source_url} className="font-bold" target="_blank" rel="noreferrer">Sumber: {item.source_type}</a>
                                <div>
                                    <Link href={stanceDestroy(item.id)} method="delete" as="button" className="mt-3 underline" preserveScroll>Hapus posisi isu</Link>
                                </div>
                            </li>
                        ))}
                    </ul>
                ) : <p className="mt-2">Belum ada posisi isu.</p>}
                <form onSubmit={submitStance} className="mt-4 border-2 border-ink p-4">
                    <h3 className="text-xl font-bold">Tambah atau perbarui isu</h3>
                    <p className="mt-1">Nama yang sudah ada akan dipakai lagi. Menyimpan isu yang sama mengganti posisi sebelumnya.</p>
                    <ValidationErrors errors={stanceForm.errors} />
                    <div className="mt-4">
                        <label htmlFor="issue-name" className="block font-bold mb-1">Nama isu</label>
                        <input id="issue-name" value={stanceForm.data.issue_name} onChange={(event) => stanceForm.setData('issue_name', event.target.value)} className="gov-input" />
                    </div>
                    <div className="mt-4">
                        <label htmlFor="stance-position" className="block font-bold mb-1">Posisi</label>
                        <select id="stance-position" value={stanceForm.data.position} onChange={(event) => stanceForm.setData('position', event.target.value)} className="gov-input">
                            {graph.stance_positions.map((position) => (
                                <option key={position.key} value={position.key}>{position.label}</option>
                            ))}
                        </select>
                    </div>
                    <div className="mt-4">
                        <label htmlFor="stance-note" className="block font-bold mb-1">Apa yang dikatakan sumber</label>
                        <textarea id="stance-note" value={stanceForm.data.note} onChange={(event) => stanceForm.setData('note', event.target.value)} className="gov-input min-h-[8rem]" />
                    </div>
                    <SourceFields data={stanceForm.data} setData={stanceForm.setData} idPrefix="stance" />
                    <button className="gov-button mt-6" disabled={stanceForm.processing}>Simpan posisi isu</button>
                </form>
            </section>
            {affiliationUrl ? (
                <section>
                    <h2 className="text-2xl font-bold">Afiliasi bisnis</h2>
                    {(graph.affiliations ?? []).length > 0 ? (
                        <ul className="mt-4 flex flex-col gap-3">
                            {graph.affiliations.map((item) => (
                                <li key={item.id} className="border-2 border-ink p-4">
                                    <p className="font-bold">{item.label} {item.business?.name}</p>
                                    <p className="mt-1">{item.summary}</p>
                                    <a href={item.source_url} className="font-bold" target="_blank" rel="noreferrer">Sumber: {item.source_type}</a>
                                    <div>
                                        <Link href={affiliationDestroy(item.id)} method="delete" as="button" className="mt-3 underline" preserveScroll>Hapus afiliasi</Link>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    ) : <p className="mt-2">Belum ada afiliasi bisnis.</p>}
                    <form onSubmit={submitAffiliation} className="mt-4 border-2 border-ink p-4">
                        <h3 className="text-xl font-bold">Tambah afiliasi bisnis</h3>
                        <ValidationErrors errors={affiliationForm.errors} />
                        <div className="mt-4">
                            <label htmlFor="affiliation-business" className="block font-bold mb-1">Nama bisnis</label>
                            <input
                                id="affiliation-business"
                                value={affiliationForm.data.business_name}
                                onChange={(event) => affiliationForm.setData('business_name', event.target.value)}
                                className="gov-input"
                                placeholder="Nama usaha atau merek"
                            />
                        </div>
                        <div className="mt-4">
                            <label htmlFor="affiliation-type" className="block font-bold mb-1">Jenis</label>
                            <select
                                id="affiliation-type"
                                value={affiliationForm.data.type}
                                onChange={(event) => affiliationForm.setData('type', event.target.value)}
                                className="gov-input"
                            >
                                {(graph.affiliation_types ?? []).map((type) => (
                                    <option key={type.key} value={type.key}>{type.label}</option>
                                ))}
                            </select>
                        </div>
                        {affiliationForm.data.type === 'ownership' ? (
                            <div className="mt-4">
                                <label className="flex items-center gap-3">
                                    <input
                                        type="checkbox"
                                        checked={affiliationForm.data.ownership_unknown}
                                        onChange={(event) => affiliationForm.setData((current) => ({
                                            ...current,
                                            ownership_unknown: event.target.checked,
                                            ownership_percent: event.target.checked ? '' : current.ownership_percent,
                                        }))}
                                    />
                                    Jumlah kepemilikan tidak diketahui
                                </label>
                                {!affiliationForm.data.ownership_unknown ? (
                                    <div className="mt-3">
                                        <label htmlFor="affiliation-percent" className="block font-bold mb-1">Persentase</label>
                                        <input
                                            id="affiliation-percent"
                                            type="number"
                                            min="1"
                                            max="100"
                                            step="0.01"
                                            value={affiliationForm.data.ownership_percent}
                                            onChange={(event) => affiliationForm.setData('ownership_percent', event.target.value)}
                                            className="gov-input"
                                        />
                                    </div>
                                ) : null}
                            </div>
                        ) : null}
                        <div className="mt-4">
                            <label htmlFor="affiliation-summary" className="block font-bold mb-1">Ringkasan dari sumber</label>
                            <textarea
                                id="affiliation-summary"
                                value={affiliationForm.data.summary}
                                onChange={(event) => affiliationForm.setData('summary', event.target.value)}
                                className="gov-input min-h-[8rem]"
                            />
                        </div>
                        <SourceFields data={affiliationForm.data} setData={affiliationForm.setData} idPrefix="affiliation" />
                        <button className="gov-button mt-6" disabled={affiliationForm.processing}>Simpan afiliasi</button>
                    </form>
                </section>
            ) : null}
        </div>
    );
}
