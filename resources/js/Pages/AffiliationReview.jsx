import Authenticated from '@/Layouts/Authenticated';
import PartyField from '@/Components/PartyField';
import BusinessField from '@/Components/BusinessField';
import RecordMedia from '@/Components/RecordMedia';
import ValidationErrors from '@/Components/ValidationErrors';
import { Head, Link, useForm } from '@inertiajs/inertia-react';

const statusLabel = {
    0: 'Menunggu',
    1: 'Terbit',
    2: 'Ditolak',
};

const checks = [
    ['check_source', 'Sumber terbuka dan memang menyebut tokoh serta bisnis yang diajukan.'],
    ['check_wording', 'Ringkasan tidak menambah fakta di luar sumber.'],
    ['check_category', 'Jenis afiliasi sesuai isi sumber: kepemilikan atau endorsemen.'],
    ['check_duplicate', 'Afiliasi ini belum terbit untuk pasangan tokoh, bisnis, dan jenis yang sama.'],
    ['check_legal_label', 'Kepemilikan dan endorsemen dibedakan sesuai bukti di sumber.'],
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

function typeLabel(affiliation) {
    if (affiliation.type === 'ownership') {
        if (affiliation.ownership_percent == null) {
            return 'Kepemilikan (jumlah tidak diketahui)';
        }
        if (Number(affiliation.ownership_percent) >= 100) {
            return 'Kepemilikan penuh (100%)';
        }
        return `Kepemilikan ${affiliation.ownership_percent}%`;
    }

    return 'Endorsemen';
}

export default function AffiliationReview({
    auth,
    affiliation,
    related,
    sameSource,
    duplicatePublished = false,
    parties = [],
    businesses = [],
    types = [],
}) {
    const politician = affiliation.politician;
    const unknown = affiliation.type === 'ownership' && affiliation.ownership_percent == null;
    const { data, setData, post, processing, errors } = useForm({
        decision: 'publish',
        name: politician?.name ?? '',
        position: politician?.position ?? '',
        department: politician?.institution ?? '',
        party_id: politician?.party?.id ? String(politician.party.id) : '',
        party_name: '',
        business_id: affiliation.business?.id ? String(affiliation.business.id) : '',
        business_name: '',
        type: affiliation.type,
        ownership_percent: affiliation.ownership_percent ?? '',
        ownership_unknown: unknown,
        summary: affiliation.summary ?? '',
        source_type: affiliation.source_type ?? '',
        source_url: affiliation.source_url ?? '',
        occurred_on: dateInput(affiliation.occurred_on),
        review_note: affiliation.review_note ?? '',
        check_source: Boolean(affiliation.check_source),
        check_wording: Boolean(affiliation.check_wording),
        check_category: Boolean(affiliation.check_category),
        check_duplicate: Boolean(affiliation.check_duplicate),
        check_legal_label: Boolean(affiliation.check_legal_label),
    });

    const onCheck = (event) => {
        setData(event.target.name, event.target.checked);
    };

    const submit = (event) => {
        event.preventDefault();
        post(route('affiliations.decide', affiliation.id));
    };

    return (
        <Authenticated
            auth={auth}
            wide
            header={<h1 className="text-4xl font-bold leading-tight">Periksa afiliasi bisnis</h1>}
        >
            <Head title={`Periksa afiliasi ${politician?.name ?? ''}`} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="font-bold">{statusLabel[affiliation.status] ?? affiliation.status}</p>
                    {affiliation.reviewed_at ? (
                        <p className="text-muted">
                            Terakhir diputus {formatDate(affiliation.reviewed_at)} oleh {affiliation.reviewer?.name ?? 'pemeriksa'}.
                        </p>
                    ) : null}
                    {duplicatePublished ? (
                        <p className="mt-2 border-l-8 border-brand bg-canvas px-4 py-2" role="alert">
                            Sudah ada afiliasi terbit dengan pasangan tokoh, bisnis, dan jenis yang sama.
                        </p>
                    ) : null}
                    {affiliation.dispute_retract_pending ? (
                        <p className="mt-2 border-l-8 border-brand bg-canvas px-4 py-2" role="status">
                            Ditarik otomatis karena sanggahan pending mencapai ambang {affiliation.dispute_threshold ?? 10}.
                            Jika Anda menolak sanggahan dan menerbitkan lagi, ambang berikutnya menjadi {(affiliation.dispute_threshold ?? 10) * 5}.
                        </p>
                    ) : null}
                </div>
                <Link href={route('dashboard')}>Kembali ke antrean</Link>
            </div>

            <div className="grid items-start gap-8 lg:grid-cols-2">
                <section>
                    <h2 className="text-2xl font-bold">Pengajuan</h2>
                    <div className="mt-3 grid grid-cols-2 gap-x-4 gap-y-3">
                        <Fact label="Nama">{politician?.name}</Fact>
                        <Fact label="Jabatan">{politician?.position}</Fact>
                        <Fact label="Instansi">{politician?.institution}</Fact>
                        <Fact label="Partai">{politician?.party?.name ?? 'Tidak terafiliasi'}</Fact>
                        <Fact label="Bisnis">{affiliation.business?.name}</Fact>
                        <Fact label="Jenis">{typeLabel(affiliation)}</Fact>
                        <Fact label="Tanggal">{formatDate(affiliation.occurred_on)}</Fact>
                        <Fact label="Masuk">{formatDate(affiliation.created_at)}</Fact>
                        <Fact label="Sumber">{affiliation.source_type}</Fact>
                    </div>
                    <p className="mt-3 break-all">
                        <a href={affiliation.source_url} target="_blank" rel="noreferrer">{affiliation.source_url}</a>
                    </p>
                    <p className="mt-3">{affiliation.summary || 'Tidak ada teks.'}</p>
                    {affiliation.attachments?.length ? <RecordMedia attachments={affiliation.attachments} compact /> : null}

                    <div className="mt-6 grid gap-4 sm:grid-cols-2">
                        <div>
                            <h2 className="font-bold">Afiliasi lain tokoh ini</h2>
                            {related.length > 0 ? (
                                <ul className="mt-2 flex flex-col gap-2">
                                    {related.map((item) => (
                                        <li key={item.id}>
                                            <Link href={route('affiliations.review', item.id)}>{item.business?.name ?? 'Bisnis'}</Link>
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
                                            <Link href={route('affiliations.review', item.id)}>{item.politician?.name ?? 'Profil lain'}</Link>
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
                            <PartyField parties={parties} partyId={data.party_id} partyName={data.party_name} onChange={setData} />
                        </div>
                    </div>

                    <div className="mt-4">
                        <BusinessField
                            businesses={businesses}
                            businessId={data.business_id}
                            businessName={data.business_name}
                            onChange={setData}
                        />
                    </div>

                    <div className="mt-4">
                        <label htmlFor="type" className="block font-bold mb-1">Jenis afiliasi</label>
                        <select id="type" value={data.type} onChange={(event) => setData('type', event.target.value)} className="gov-input">
                            {types.map((item) => (
                                <option key={item.key} value={item.key}>{item.label}</option>
                            ))}
                        </select>
                    </div>

                    {data.type === 'ownership' ? (
                        <div className="mt-4 border-2 border-ink p-4">
                            <label className="flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    checked={data.ownership_unknown}
                                    onChange={(event) => setData((current) => ({
                                        ...current,
                                        ownership_unknown: event.target.checked,
                                        ownership_percent: event.target.checked ? '' : current.ownership_percent,
                                    }))}
                                />
                                Jumlah kepemilikan tidak diketahui
                            </label>
                            {!data.ownership_unknown ? (
                                <div className="mt-4">
                                    <label htmlFor="ownership_percent" className="block font-bold mb-1">Persentase</label>
                                    <input
                                        id="ownership_percent"
                                        type="number"
                                        min="1"
                                        max="100"
                                        step="0.01"
                                        value={data.ownership_percent}
                                        onChange={(event) => setData('ownership_percent', event.target.value)}
                                        className="gov-input"
                                    />
                                </div>
                            ) : null}
                        </div>
                    ) : null}

                    <div className="mt-4">
                        <label htmlFor="summary" className="block font-bold mb-1">Ringkasan</label>
                        <textarea id="summary" value={data.summary} onChange={(event) => setData('summary', event.target.value)} className="gov-input min-h-[8rem]" />
                    </div>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label htmlFor="source_type" className="block font-bold mb-1">Jenis sumber</label>
                            <input id="source_type" value={data.source_type} onChange={(event) => setData('source_type', event.target.value)} className="gov-input" />
                        </div>
                        <div>
                            <label htmlFor="occurred_on" className="block font-bold mb-1">Tanggal</label>
                            <input id="occurred_on" type="date" value={data.occurred_on} onChange={(event) => setData('occurred_on', event.target.value)} className="gov-input" />
                        </div>
                    </div>
                    <div className="mt-4">
                        <label htmlFor="source_url" className="block font-bold mb-1">Tautan sumber</label>
                        <input id="source_url" value={data.source_url} onChange={(event) => setData('source_url', event.target.value)} className="gov-input" />
                    </div>

                    <h2 className="mt-8 text-2xl font-bold">Daftar periksa</h2>
                    <ul className="mt-4 flex flex-col gap-3">
                        {checks.map(([name, label]) => (
                            <li key={name}>
                                <label className="flex items-start gap-3">
                                    <input type="checkbox" name={name} checked={data[name]} onChange={onCheck} className="mt-1" />
                                    <span>{label}</span>
                                </label>
                            </li>
                        ))}
                    </ul>

                    <div className="mt-6">
                        <label htmlFor="review_note" className="block font-bold mb-1">Catatan pemeriksa</label>
                        <textarea id="review_note" value={data.review_note} onChange={(event) => setData('review_note', event.target.value)} className="gov-input min-h-[8rem]" />
                        <p className="mt-2 text-muted">Minimal 40 karakter.</p>
                    </div>

                    <div className="mt-6 flex flex-wrap gap-3">
                        <button
                            type="submit"
                            className="gov-button"
                            disabled={processing}
                            onClick={() => setData('decision', 'publish')}
                        >
                            Terbitkan
                        </button>
                        <button
                            type="submit"
                            className="underline"
                            disabled={processing}
                            onClick={() => setData('decision', 'reject')}
                        >
                            Tolak
                        </button>
                    </div>
                </form>
            </div>
        </Authenticated>
    );
}
