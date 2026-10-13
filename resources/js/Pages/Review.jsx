import Authenticated from '@/Layouts/Authenticated';
import PartyField from '@/Components/PartyField';
import TopicField from '@/Components/TopicField';
import ProposalList from '@/Components/ProposalList';
import RecordMedia from '@/Components/RecordMedia';
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
        <div className="min-w-0">
            <p className="text-muted">{label}</p>
            <p className="font-bold break-words">{children}</p>
        </div>
    );
}

export default function Review({ auth, record, related, sameSource, parties = [], topics = [], proposals = [] }) {
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
        topics: (record.topics ?? []).map((topic) => topic.name),
    });

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
            header={<h1 className="text-4xl font-bold leading-tight">Periksa catatan</h1>}
        >
            <Head title={`Periksa ${politician?.name ?? 'catatan'}`} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="font-bold">{statusLabel[record.status] ?? record.status}</p>
                    {record.reviewed_at ? (
                        <p className="text-muted">
                            Terakhir diputus {formatDate(record.reviewed_at)} oleh {record.reviewer?.name ?? 'pemeriksa'}.
                        </p>
                    ) : null}
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    {record.status === 1 ? (
                        <Link href={route('admin.audits.records.run', record.id)} method="post" as="button" className="gov-button" preserveScroll>
                            Jalankan inferensi
                        </Link>
                    ) : null}
                    <Link href={route('dashboard')}>Kembali ke antrean</Link>
                </div>
            </div>

            <div className="grid items-start gap-8 lg:grid-cols-2">
                <section>
                    <h2 className="text-2xl font-bold">Pengajuan</h2>
                    <div className="mt-3 grid grid-cols-2 gap-x-4 gap-y-3">
                        <Fact label="Nama">{politician?.name}</Fact>
                        <Fact label="Jabatan">{politician?.position}</Fact>
                        <Fact label="Instansi">{politician?.institution}</Fact>
                        <Fact label="Partai">{politician?.party?.name ?? 'Tidak terafiliasi'}</Fact>
                        <Fact label="Jenis">{categoryLabel[record.category] ?? record.category}</Fact>
                        <Fact label="Tanggal">{formatDate(record.occurred_on)}</Fact>
                        <Fact label="Masuk">{formatDate(record.created_at)}</Fact>
                        <Fact label="Sumber">{record.source_type}</Fact>
                    </div>
                    <p className="mt-3 break-all">
                        <a href={record.source_url} target="_blank" rel="noreferrer">{record.source_url}</a>
                    </p>
                    <p className="mt-3">{record.quote || record.summary || 'Tidak ada teks.'}</p>
                    {record.attachments?.length ? <RecordMedia attachments={record.attachments} compact /> : null}

                    <div className="mt-6 grid gap-4 sm:grid-cols-2">
                        <div>
                            <h2 className="font-bold">Catatan lain</h2>
                            {related.length > 0 ? (
                                <ul className="mt-2 flex flex-col gap-2">
                                    {related.map((item) => (
                                        <li key={item.id}>
                                            <Link href={route('reports.review', item.id)}>{categoryLabel[item.category] ?? item.category}</Link>
                                            <span className="text-muted"> · {statusLabel[item.status] ?? item.status}</span>
                                        </li>
                                    ))}
                                </ul>
                            ) : <p className="mt-2 text-muted">Tidak ada.</p>}
                        </div>
                        <div>
                            <h2 className="font-bold">Sumber yang sama</h2>
                            {sameSource.length > 0 ? (
                                <ul className="mt-2 flex flex-col gap-2">
                                    {sameSource.map((item) => (
                                        <li key={item.id}>
                                            <Link href={route('reports.review', item.id)}>{item.politician?.name ?? 'Profil lain'}</Link>
                                            <span className="text-muted"> · {statusLabel[item.status] ?? item.status}</span>
                                        </li>
                                    ))}
                                </ul>
                            ) : <p className="mt-2 text-muted">Tidak ada.</p>}
                        </div>
                    </div>
                </section>

            <form onSubmit={submit}>
                <ValidationErrors errors={errors} />

                <h2 className="text-2xl font-bold">Sunting sebelum diputus</h2>
                <p className="mt-2">Perubahan nama, jabatan, instansi, dan partai berlaku untuk profil ini.</p>
                <div className="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label htmlFor="name" className="block font-bold mb-1">Nama</label>
                        <input id="name" name="name" value={data.name} onChange={(event) => setData('name', event.target.value)} className="gov-input" />
                    </div>
                    <div>
                        <label htmlFor="position" className="block font-bold mb-1">Jabatan</label>
                        <input id="position" name="position" value={data.position} onChange={(event) => setData('position', event.target.value)} className="gov-input" />
                    </div>
                    <div>
                        <label htmlFor="department" className="block font-bold mb-1">Instansi</label>
                        <input id="department" name="department" value={data.department} onChange={(event) => setData('department', event.target.value)} className="gov-input" />
                    </div>
                    <div>
                        <label htmlFor="occurred_on" className="block font-bold mb-1">Tanggal</label>
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
                    <div>
                        <label htmlFor="source_type" className="block font-bold mb-1">Jenis sumber</label>
                        <input id="source_type" name="source_type" value={data.source_type} onChange={(event) => setData('source_type', event.target.value)} className="gov-input" />
                    </div>
                    <div>
                        <label htmlFor="category" className="block font-bold mb-1">Jenis catatan</label>
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
                        <label htmlFor="source_url" className="block font-bold mb-1">Tautan sumber</label>
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
                        <label htmlFor="quote" className="block font-bold mb-1">Kutipan persis</label>
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
                        <label htmlFor="summary" className="block font-bold mb-1">{data.category === 'conviction' ? 'Putusan' : 'Dugaan'}</label>
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

                <fieldset className="mt-6">
                    <legend className="text-xl font-bold">Daftar pemeriksaan</legend>
                    <div className="mt-3 flex flex-col gap-2">
                        {checks.map(([name, label]) => (
                            <label key={name} className="flex items-start gap-3">
                                <input
                                    type="checkbox"
                                    name={name}
                                    checked={data[name]}
                                    onChange={onCheck}
                                    className="mt-1 h-6 w-6 border-2 border-ink text-brand rounded-none"
                                />
                                <span>{label}</span>
                            </label>
                        ))}
                    </div>
                </fieldset>

                <div className="mt-6 grid gap-4 sm:grid-cols-[minmax(0,1fr)_10rem] sm:items-start">
                    <div>
                        <label htmlFor="review_note" className="block font-bold mb-1">Catatan pemeriksa</label>
                        <textarea
                            id="review_note"
                            name="review_note"
                            value={data.review_note}
                            onChange={(event) => setData('review_note', event.target.value)}
                            className="gov-input"
                            rows="3"
                        />
                    </div>
                    <fieldset>
                        <legend className="font-bold mb-2">Keputusan</legend>
                        <label className="flex items-center gap-2">
                            <input type="radio" name="decision" value="publish" checked={data.decision === 'publish'} onChange={() => setData('decision', 'publish')} />
                            Terbitkan
                        </label>
                        <label className="mt-2 flex items-center gap-2">
                            <input type="radio" name="decision" value="reject" checked={data.decision === 'reject'} onChange={() => setData('decision', 'reject')} />
                            Tolak
                        </label>
                    </fieldset>
                </div>

                <button className="gov-button mt-4" disabled={processing}>
                    Simpan keputusan
                </button>
            </form>
            </div>
            <h2 className="mt-10 text-2xl font-bold">Usulan dari catatan ini</h2>
            <p className="mt-2 max-w-3xl">Usulan muncul setelah data diterbitkan. Menerima satu usulan menyalin sumber catatan ini ke profil.</p>
            <ProposalList proposals={proposals} />
        </Authenticated>
    );
}
