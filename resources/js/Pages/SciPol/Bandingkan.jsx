import { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/inertia-react';
import Spectrum from '@/Components/Spectrum';
import SciPol, { CategoryBadge, ExportButton, SciPolNotice, SciPolTable, SciPolTd, SciPolTh } from '@/Layouts/SciPol';

function SuggestField({ type, onPick }) {
    const [value, setValue] = useState('');
    const [items, setItems] = useState([]);
    const [open, setOpen] = useState(false);

    const search = async (query) => {
        setValue(query);
        if (query.trim().length < 2) {
            setItems([]);
            setOpen(false);
            return;
        }

        const response = await fetch(
            `${route('scipol.suggest')}?type=${encodeURIComponent(type)}&q=${encodeURIComponent(query.trim())}`,
            { headers: { Accept: 'application/json' } },
        );
        if (!response.ok) {
            return;
        }
        const next = await response.json();
        const list = (next || []).slice(0, 8).map((item) => ({ id: item.id, name: item.name }));
        setItems(list);
        setOpen(list.length > 0);
    };

    return (
        <div className="relative">
            <input
                value={value}
                onChange={(event) => search(event.target.value)}
                className="w-full border border-ink/30 bg-white px-2 py-1.5 text-sm"
                placeholder={type === 'party' ? 'Cari partai…' : 'Cari politisi…'}
                autoComplete="off"
            />
            {open ? (
                <ul className="absolute z-10 mt-0.5 max-h-48 w-full overflow-auto border border-ink/30 bg-white shadow">
                    {items.map((item) => (
                        <li key={item.id}>
                            <button
                                type="button"
                                className="block w-full px-2 py-1.5 text-left text-sm hover:bg-[#e8ecf0]"
                                onClick={() => {
                                    onPick(item);
                                    setValue('');
                                    setItems([]);
                                    setOpen(false);
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

export default function Bandingkan({ type = 'politician', ids = [], subjects = [], export_url = null }) {
    const selected = useMemo(() => ids.map(Number), [ids]);

    const apply = (nextType, nextIds) => {
        const unique = [...new Set(nextIds)].filter(Boolean).slice(0, 3);
        router.get('/scipol/bandingkan', {
            type: nextType,
            ids: unique.join(','),
        }, { preserveState: true, replace: true });
    };

    const add = (item) => {
        if (selected.includes(item.id)) {
            return;
        }
        apply(type, [...selected, item.id]);
    };

    const remove = (id) => {
        apply(type, selected.filter((value) => value !== id));
    };

    return (
        <SciPol title="bandingkan">
            <Head title="SciPol · Bandingkan" />

            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold">Bandingkan</h1>
                    <p className="mt-1 text-sm text-muted">Pilih 2–3 subjek untuk membandingkan bukti, bukan opini.</p>
                </div>
                <ExportButton href={export_url} disabled={!export_url} />
            </div>

            <SciPolNotice>
                Matriks menampilkan <strong>tendensi terkode</strong>, sikap isu, tautan, afiliasi, dan hitungan rekaman per kategori.
            </SciPolNotice>

            <div className="mb-4 flex flex-wrap items-end gap-3 border border-ink/15 bg-white p-3">
                <div>
                    <label className="mb-1 block text-[11px] font-bold uppercase text-muted">Jenis</label>
                    <select
                        className="border border-ink/30 bg-white px-2 py-1.5 text-sm"
                        value={type}
                        onChange={(event) => apply(event.target.value, [])}
                    >
                        <option value="politician">Politisi</option>
                        <option value="party">Partai</option>
                    </select>
                </div>
                <div className="min-w-[220px] flex-1">
                    <label className="mb-1 block text-[11px] font-bold uppercase text-muted">Tambah subjek</label>
                    <SuggestField type={type} onPick={add} />
                </div>
                <div className="flex flex-wrap gap-1">
                    {subjects.map((subject) => (
                        <button
                            key={subject.id}
                            type="button"
                            className="border border-ink bg-[#f4f6f8] px-2 py-1 text-sm"
                            onClick={() => remove(subject.id)}
                            title="Hapus dari perbandingan"
                        >
                            {subject.name} ×
                        </button>
                    ))}
                </div>
            </div>

            {subjects.length === 0 ? (
                <p className="text-sm text-muted">Belum ada subjek. Cari dan tambahkan minimal dua untuk matriks yang bermakna.</p>
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
                                <SciPolTd className="font-bold">Identitas</SciPolTd>
                                {subjects.map((subject) => (
                                    <SciPolTd key={subject.id}>
                                        {subject.position ? <div>{subject.position}</div> : null}
                                        {subject.institution ? <div className="text-muted">{subject.institution}</div> : null}
                                        {subject.party?.name ? <div className="text-muted">{subject.party.name}</div> : null}
                                    </SciPolTd>
                                ))}
                            </tr>
                            <tr>
                                <SciPolTd className="font-bold">Rekaman</SciPolTd>
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
                        <h2 className="mb-2 text-sm font-bold uppercase tracking-wide text-muted">Kecenderungan (tendensi terkode)</h2>
                        <div className={`grid gap-3 ${subjects.length === 1 ? 'grid-cols-1' : subjects.length === 2 ? 'md:grid-cols-2' : 'md:grid-cols-3'}`}>
                            {subjects.map((subject) => (
                                <div key={subject.id} className="border border-ink/20 bg-white p-3">
                                    <h3 className="mb-3 font-bold">{subject.name}</h3>
                                    {(subject.tendencies || []).length === 0 ? (
                                        <p className="text-sm text-muted">Belum ada sumbu.</p>
                                    ) : (
                                        <div className="space-y-4">
                                            {subject.tendencies.map((item) => (
                                                <div key={item.axis}>
                                                    <Spectrum
                                                        label={item.axis_label}
                                                        left={item.left}
                                                        right={item.right}
                                                        score={item.score}
                                                    />
                                                    <p className="mt-1 text-[12px] text-muted">
                                                        samples={item.samples}
                                                        {item.source_url ? (
                                                            <>
                                                                {' · '}
                                                                <a href={item.source_url} target="_blank" rel="noreferrer">sumber</a>
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
                        <h2 className="mb-2 text-sm font-bold uppercase tracking-wide text-muted">Sikap isu</h2>
                        <SciPolTable>
                            <thead>
                                <tr>
                                    <SciPolTh>Subjek</SciPolTh>
                                    <SciPolTh>Isu</SciPolTh>
                                    <SciPolTh>Posisi</SciPolTh>
                                    <SciPolTh>Sumber</SciPolTh>
                                </tr>
                            </thead>
                            <tbody>
                                {subjects.flatMap((subject) =>
                                    (subject.issue_stances || []).length === 0
                                        ? [(
                                            <tr key={`${subject.id}-empty`}>
                                                <SciPolTd>{subject.name}</SciPolTd>
                                                <SciPolTd colSpan={3} className="text-muted">Belum ada sikap isu</SciPolTd>
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
                                                            {stance.source_type || 'sumber'}
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
                        <h2 className="mb-2 text-sm font-bold uppercase tracking-wide text-muted">Afiliasi bisnis</h2>
                        <SciPolTable>
                            <thead>
                                <tr>
                                    <SciPolTh>Subjek</SciPolTh>
                                    <SciPolTh>Jenis</SciPolTh>
                                    <SciPolTh>Bisnis</SciPolTh>
                                    <SciPolTh>%</SciPolTh>
                                    <SciPolTh>Sumber</SciPolTh>
                                </tr>
                            </thead>
                            <tbody>
                                {subjects.flatMap((subject) =>
                                    (subject.business_affiliations || []).length === 0
                                        ? [(
                                            <tr key={`${subject.id}-aff-empty`}>
                                                <SciPolTd>{subject.name}</SciPolTd>
                                                <SciPolTd colSpan={4} className="text-muted">Tidak ada afiliasi terbit</SciPolTd>
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
                                                <SciPolTd>
                                                    {row.source_url ? (
                                                        <a href={row.source_url} target="_blank" rel="noreferrer">
                                                            {row.source_type || 'sumber'}
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
