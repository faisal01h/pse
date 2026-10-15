import Authenticated from '@/Layouts/Authenticated';
import PartyField from '@/Components/PartyField';
import TopicField from '@/Components/TopicField';
import ProposalList from '@/Components/ProposalList';
import RecordMedia from '@/Components/RecordMedia';
import ReliabilityField from '@/Components/ReliabilityField';
import AutoVetPanel from '@/Components/AutoVetPanel';
import ValidationErrors from '@/Components/ValidationErrors';
import { Head, Link, useForm } from '@inertiajs/inertia-react';

const categoryLabel = {
    statement: 'Pernyataan publik',
    allegation: 'Dugaan pelanggaran',
    conviction: 'Vonis pengadilan',
};

const statusLabel = {
    0: 'Menunggu',
    1: 'Terbit',
    2: 'Ditolak',
};

const statusTone = {
    0: 'border-ink bg-canvas text-ink',
    1: 'border-ink bg-ink text-white',
    2: 'border-brand bg-brand text-white',
};

const checks = [
    ['check_source', 'Sumber terbuka dan memang menyebut orang ini, jabatan, dan peristiwa yang diajukan.'],
    ['check_wording', 'Kutipan disalin dari sumber, atau ringkasan tidak menambah fakta di luar sumber.'],
    ['check_category', 'Jenis catatan sesuai isi sumber: pernyataan, dugaan, atau vonis.'],
    ['check_duplicate', 'Catatan ini belum terbit dari tautan sumber yang sama.'],
    ['check_legal_label', 'Dugaan tidak ditulis sebagai vonis, dan vonis merujuk putusan, bukan tuduhan.'],
];

function dateInput(value) {
    if (!value) {
        return '';
    }

    return String(value).slice(0, 10);
}

function formatDate(value) {
    if (!value) {
        return 'Tidak diisi';
    }

    return new Date(value).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function Fact({ label, children }) {
    return (
        <div className="min-w-0 border-b border-ink/10 py-2.5 last:border-b-0">
            <dt className="text-xs font-bold uppercase tracking-wide text-muted">{label}</dt>
            <dd className="mt-0.5 font-bold break-words">{children || '—'}</dd>
        </div>
    );
}

function SideList({ title, empty, children }) {
    return (
        <div>
            <h3 className="text-sm font-bold uppercase tracking-wide text-muted">{title}</h3>
            {children ? (
                <ul className="mt-2 divide-y divide-ink/10 border-y border-ink/10">{children}</ul>
            ) : (
                <p className="mt-2 text-sm text-muted">{empty}</p>
            )}
        </div>
    );
}

export default function Review({
    auth,
    record,
    related,
    sameSource,
    parties = [],
    topics = [],
    proposals = [],
    autovetEnabled = false,
    autovetRun = null,
}) {
    const politician = record.politician;
    const { data, setData, post, processing, errors } = useForm({
        decision: 'publish',
        name: politician?.name ?? '',
        position: politician?.position ?? '',
        department: politician?.institution ?? '',
        party_id: politician?.party?.id ? String(politician.party.id) : '',
        party_name: '',
        category: record.category,
        summary: record.summary ?? '',
        quote: record.quote ?? '',
        source_type: record.source_type ?? '',
        source_url: record.source_url ?? '',
        occurred_on: dateInput(record.occurred_on),
        review_note: record.review_note ?? '',
        check_source: Boolean(record.check_source),
        check_wording: Boolean(record.check_wording),
        check_category: Boolean(record.check_category),
        check_duplicate: Boolean(record.check_duplicate),
        check_legal_label: Boolean(record.check_legal_label),
        reliability_level: record.reliability_level ?? '',
        topics: (record.topics ?? []).map((topic) => topic.name),
    });

    const checkedCount = checks.filter(([name]) => data[name]).length;

    function applyAutoVetDraft(draft) {
        setData((current) => ({
            ...current,
            decision: draft.decision === 'reject' ? 'reject' : 'publish',
            review_note: draft.review_note || current.review_note,
            reliability_level: draft.reliability_level || current.reliability_level,
            check_source: Boolean(draft.check_source),
            check_wording: Boolean(draft.check_wording),
            check_category: Boolean(draft.check_category),
            check_duplicate: Boolean(draft.check_duplicate),
            check_legal_label: Boolean(draft.check_legal_label),
        }));
    }

    const onCheck = (event) => {
        setData(event.target.name, event.target.checked);
    };

    const submit = (event) => {
        event.preventDefault();
        post(route('reports.decide', record.id));
    };

    return (
        <Authenticated
            auth={auth}
            wide
            header={(
                <div className="review-hero border-2 border-ink border-l-8 border-l-brand bg-canvas px-4 py-5 sm:px-6">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div className="min-w-0">
                            <p className="text-sm">
                                <Link href={route('dashboard')} className="text-ink underline">Antrean</Link>
                                <span className="text-muted"> / Periksa catatan</span>
                            </p>
                            <h1 className="mt-2 text-3xl font-bold leading-tight sm:text-4xl">
                                {politician?.name ?? 'Catatan'}
                            </h1>
                            <p className="mt-1 text-muted">
                                {categoryLabel[record.category] ?? record.category}
                                {politician?.position ? ` · ${politician.position}` : ''}
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <span className={`border-2 px-2.5 py-1 text-sm font-bold ${statusTone[record.status] ?? statusTone[0]}`}>
                                {statusLabel[record.status] ?? record.status}
                            </span>
                            {record.status === 1 ? (
                                <Link
                                    href={route('admin.audits.records.run', record.id)}
                                    method="post"
                                    as="button"
                                    className="gov-button"
                                    preserveScroll
                                >
                                    Jalankan inferensi
                                </Link>
                            ) : null}
                        </div>
                    </div>
                    {record.reviewed_at ? (
                        <p className="mt-4 border-t border-ink/15 pt-3 text-sm text-muted">
                            Terakhir diputus {formatDate(record.reviewed_at)} oleh {record.reviewer?.name ?? 'pemeriksa'}.
                        </p>
                    ) : null}
                </div>
            )}
        >
            <Head title={`Periksa ${politician?.name ?? 'catatan'}`} />

            <div className="grid items-start gap-8 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
                <aside className="flex flex-col gap-6 xl:sticky xl:top-4">
                    <section className="border-2 border-ink bg-white">
                        <div className="border-b-2 border-ink bg-canvas px-4 py-3 sm:px-5">
                            <h2 className="text-xl font-bold">Bukti pengajuan</h2>
                            <p className="mt-1 text-sm text-muted">Baca sumber sebelum mengubah atau memutus.</p>
                        </div>

                        <div className="px-4 py-4 sm:px-5">
                            <a
                                href={record.source_url}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex max-w-full items-center gap-2 break-all font-bold"
                            >
                                Buka sumber
                                <span className="text-muted no-underline">({record.source_type})</span>
                            </a>
                            <p className="mt-1 break-all text-sm text-muted">{record.source_url}</p>

                            <blockquote className="mt-5 border-l-4 border-brand bg-canvas/60 py-3 pl-4 pr-3 text-lg leading-snug">
                                {record.quote || record.summary || 'Tidak ada teks.'}
                            </blockquote>

                            {record.attachments?.length ? (
                                <div className="mt-4">
                                    <RecordMedia attachments={record.attachments} compact />
                                </div>
                            ) : null}
                        </div>

                        <dl className="border-t-2 border-ink px-4 py-2 sm:px-5 sm:columns-2 sm:gap-x-8">
                            <Fact label="Nama">{politician?.name}</Fact>
                            <Fact label="Jabatan">{politician?.position}</Fact>
                            <Fact label="Instansi">{politician?.institution}</Fact>
                            <Fact label="Partai">{politician?.party?.name ?? 'Tidak terafiliasi'}</Fact>
                            <Fact label="Jenis">{categoryLabel[record.category] ?? record.category}</Fact>
                            <Fact label="Tanggal peristiwa">{formatDate(record.occurred_on)}</Fact>
                            <Fact label="Masuk antrean">{formatDate(record.created_at)}</Fact>
                        </dl>
                    </section>

                    <section className="grid gap-6 border-2 border-ink bg-white p-4 sm:grid-cols-2 sm:p-5">
                        <SideList title="Catatan lain tokoh ini" empty="Tidak ada catatan lain.">
                            {related.length > 0
                                ? related.map((item) => (
                                    <li key={item.id} className="py-2 text-sm">
                                        <Link href={route('reports.review', item.id)} className="font-bold">
                                            {categoryLabel[item.category] ?? item.category}
                                        </Link>
                                        <span className="text-muted"> · {statusLabel[item.status] ?? item.status}</span>
                                    </li>
                                ))
                                : null}
                        </SideList>
                        <SideList title="Sumber yang sama" empty="Tidak ada duplikat sumber.">
                            {sameSource.length > 0
                                ? sameSource.map((item) => (
                                    <li key={item.id} className="py-2 text-sm">
                                        <Link href={route('reports.review', item.id)} className="font-bold">
                                            {item.politician?.name ?? 'Profil lain'}
                                        </Link>
                                        <span className="text-muted"> · {statusLabel[item.status] ?? item.status}</span>
                                    </li>
                                ))
                                : null}
                        </SideList>
                    </section>
                </aside>

                <div className="flex flex-col gap-6">
                    <AutoVetPanel
                        enabled={autovetEnabled}
                        run={autovetRun}
                        draft={record.autovet_draft}
                        runHref={route('reports.autovet', record.id)}
                        onApplyDraft={applyAutoVetDraft}
                    />

                    <form onSubmit={submit} className="border-2 border-ink bg-white">
                        <div className="border-b-2 border-ink bg-canvas px-4 py-3 sm:px-5">
                            <h2 className="text-xl font-bold">Sunting & putuskan</h2>
                            <p className="mt-1 text-sm text-muted">
                                Perubahan nama, jabatan, instansi, dan partai berlaku untuk profil tokoh.
                            </p>
                        </div>

                        <div className="px-4 py-5 sm:px-5">
                            <ValidationErrors errors={errors} />

                            <h3 className="text-sm font-bold uppercase tracking-wide text-muted">Profil tokoh</h3>
                            <div className="mt-3 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label htmlFor="name" className="mb-1 block font-bold">Nama</label>
                                    <input id="name" name="name" value={data.name} onChange={(event) => setData('name', event.target.value)} className="gov-input" />
                                </div>
                                <div>
                                    <label htmlFor="position" className="mb-1 block font-bold">Jabatan</label>
                                    <input id="position" name="position" value={data.position} onChange={(event) => setData('position', event.target.value)} className="gov-input" />
                                </div>
                                <div>
                                    <label htmlFor="department" className="mb-1 block font-bold">Instansi</label>
                                    <input id="department" name="department" value={data.department} onChange={(event) => setData('department', event.target.value)} className="gov-input" />
                                </div>
                                <div>
                                    <label htmlFor="occurred_on" className="mb-1 block font-bold">Tanggal</label>
                                    <input id="occurred_on" name="occurred_on" type="date" value={data.occurred_on} onChange={(event) => setData('occurred_on', event.target.value)} className="gov-input" />
                                </div>
                                <div className="sm:col-span-2">
                                    <PartyField
                                        parties={parties}
                                        partyId={data.party_id}
                                        partyName={data.party_name}
                                        onChange={setData}
                                    />
                                </div>
                            </div>

                            <h3 className="mt-8 text-sm font-bold uppercase tracking-wide text-muted">Isi catatan</h3>
                            <div className="mt-3 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label htmlFor="source_type" className="mb-1 block font-bold">Jenis sumber</label>
                                    <input id="source_type" name="source_type" value={data.source_type} onChange={(event) => setData('source_type', event.target.value)} className="gov-input" />
                                </div>
                                <div>
                                    <label htmlFor="category" className="mb-1 block font-bold">Jenis catatan</label>
                                    <select
                                        id="category"
                                        name="category"
                                        value={data.category}
                                        onChange={(event) => setData('category', event.target.value)}
                                        className="gov-input"
                                    >
                                        <option value="statement">Pernyataan publik</option>
                                        <option value="allegation">Dugaan pelanggaran hukum</option>
                                        <option value="conviction">Vonis pengadilan</option>
                                    </select>
                                </div>
                                <div className="sm:col-span-2">
                                    <label htmlFor="source_url" className="mb-1 block font-bold">Tautan sumber</label>
                                    <input id="source_url" name="source_url" type="url" value={data.source_url} onChange={(event) => setData('source_url', event.target.value)} className="gov-input" />
                                </div>
                            </div>

                            {data.category === 'allegation' ? (
                                <p className="mt-4 border-l-8 border-brand bg-canvas px-4 py-3">
                                    Dugaan bukan vonis. Teks yang terbit harus tetap menyebut ini sebagai dugaan.
                                </p>
                            ) : null}

                            {data.category === 'statement' ? (
                                <div className="mt-4">
                                    <label htmlFor="quote" className="mb-1 block font-bold">Kutipan persis</label>
                                    <textarea
                                        id="quote"
                                        name="quote"
                                        value={data.quote}
                                        onChange={(event) => setData('quote', event.target.value)}
                                        className="gov-input"
                                        rows="3"
                                    />
                                </div>
                            ) : (
                                <div className="mt-4">
                                    <label htmlFor="summary" className="mb-1 block font-bold">
                                        {data.category === 'conviction' ? 'Putusan' : 'Dugaan'}
                                    </label>
                                    <textarea
                                        id="summary"
                                        name="summary"
                                        value={data.summary}
                                        onChange={(event) => setData('summary', event.target.value)}
                                        className="gov-input"
                                        rows="3"
                                    />
                                </div>
                            )}

                            <div className="mt-6">
                                <TopicField topics={topics} selected={data.topics} onChange={(next) => setData('topics', next)} />
                            </div>

                            <fieldset className="mt-8">
                                <legend className="flex flex-wrap items-baseline gap-2">
                                    <span className="text-xl font-bold">Daftar pemeriksaan</span>
                                    <span className="text-sm text-muted">{checkedCount}/{checks.length} dicentang</span>
                                </legend>
                                <div className="mt-3 flex flex-col gap-2">
                                    {checks.map(([name, label]) => (
                                        <label
                                            key={name}
                                            className={`flex items-start gap-3 border-2 px-3 py-2.5 ${
                                                data[name] ? 'border-ink bg-canvas' : 'border-ink/20 bg-white'
                                            }`}
                                        >
                                            <input
                                                type="checkbox"
                                                name={name}
                                                checked={data[name]}
                                                onChange={onCheck}
                                                className="mt-0.5 h-5 w-5 border-2 border-ink text-brand rounded-none"
                                            />
                                            <span>{label}</span>
                                        </label>
                                    ))}
                                </div>
                            </fieldset>

                            <ReliabilityField
                                value={data.reliability_level}
                                onChange={(next) => setData('reliability_level', next)}
                                required={data.decision === 'publish'}
                            />

                            <div className="mt-6">
                                <label htmlFor="review_note" className="mb-1 block font-bold">Catatan pemeriksa</label>
                                <textarea
                                    id="review_note"
                                    name="review_note"
                                    value={data.review_note}
                                    onChange={(event) => setData('review_note', event.target.value)}
                                    className="gov-input"
                                    rows="3"
                                />
                                <p className="mt-1 text-sm text-muted">Minimal 40 karakter.</p>
                            </div>
                        </div>

                        <div className="sticky bottom-0 border-t-2 border-ink bg-canvas px-4 py-4 sm:px-5">
                            <fieldset>
                                <legend className="sr-only">Keputusan</legend>
                                <div className="flex flex-wrap gap-3">
                                    <label className={`flex cursor-pointer items-center gap-2 border-2 px-3 py-2 font-bold ${
                                        data.decision === 'publish' ? 'border-ink bg-ink text-white' : 'border-ink bg-white'
                                    }`}
                                    >
                                        <input
                                            type="radio"
                                            name="decision"
                                            value="publish"
                                            className="sr-only"
                                            checked={data.decision === 'publish'}
                                            onChange={() => setData('decision', 'publish')}
                                        />
                                        Terbitkan
                                    </label>
                                    <label className={`flex cursor-pointer items-center gap-2 border-2 px-3 py-2 font-bold ${
                                        data.decision === 'reject' ? 'border-brand bg-brand text-white' : 'border-ink bg-white'
                                    }`}
                                    >
                                        <input
                                            type="radio"
                                            name="decision"
                                            value="reject"
                                            className="sr-only"
                                            checked={data.decision === 'reject'}
                                            onChange={() => setData('decision', 'reject')}
                                        />
                                        Tolak
                                    </label>
                                </div>
                            </fieldset>
                            <div className="mt-3 flex flex-wrap items-center gap-3">
                                <button className="gov-button" disabled={processing}>
                                    Simpan keputusan
                                </button>
                                <Link href={route('dashboard')} className="text-ink underline">
                                    Kembali ke antrean
                                </Link>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <section className="mt-12 border-t-2 border-ink pt-8">
                <h2 className="text-2xl font-bold">Usulan dari catatan ini</h2>
                <p className="mt-2 max-w-3xl text-muted">
                    Usulan muncul setelah data diterbitkan. Menerima satu usulan menyalin sumber catatan ini ke profil.
                </p>
                <div className="mt-4">
                    <ProposalList proposals={proposals} />
                </div>
            </section>
        </Authenticated>
    );
}
