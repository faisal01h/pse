import { useState } from 'react';
import Authenticated from '@/Layouts/Authenticated';
import ProposalList from '@/Components/ProposalList';
import { Inertia } from '@inertiajs/inertia';
import { Link, Head } from '@inertiajs/inertia-react';

const categoryLabel = {
    statement: 'Pernyataan publik',
    allegation: 'Dugaan pelanggaran',
    conviction: 'Vonis pengadilan',
    ownership: 'Kepemilikan',
    endorsement: 'Endorsemen',
};

const statusLabel = {
    0: 'Menunggu',
    1: 'Terbit',
    2: 'Ditolak',
};

function formatDate(value) {
    if (!value) {
        return 'Tidak diisi';
    }

    return new Date(value).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function excerpt(record) {
    const text = record.quote || record.summary || '';

    return text.length > 90 ? `${text.slice(0, 90)}…` : text;
}

function affiliationLabel(item) {
    if (item.type === 'ownership') {
        if (item.ownership_percent == null) {
            return 'Kepemilikan (jumlah tidak diketahui)';
        }
        if (Number(item.ownership_percent) >= 100) {
            return 'Kepemilikan penuh (100%)';
        }
        return `Kepemilikan ${item.ownership_percent}%`;
    }

    return categoryLabel[item.type] ?? item.type;
}

export default function Dashboard(props) {
    const reports = props.reports ?? [];
    const affiliations = props.affiliations ?? [];
    const decided = props.decided ?? [];
    const decidedAffiliations = props.decidedAffiliations ?? [];
    const proposals = props.proposals ?? [];
    const disputeGroups = props.disputes ?? [];
    const disputeCount = disputeGroups.reduce((sum, group) => sum + group.disputes.length, 0);
    const pendingCount = reports.length + affiliations.length;
    const decidedCount = decided.length + decidedAffiliations.length;
    const tabs = [
        { id: 'pending', label: 'Menunggu', count: pendingCount },
        { id: 'disputes', label: 'Sanggahan', count: disputeCount },
        { id: 'proposals', label: 'Usulan', count: proposals.length },
        { id: 'decided', label: 'Keputusan', count: decidedCount },
    ];
    const [tab, setTab] = useState(
        pendingCount > 0 ? 'pending' : disputeCount > 0 ? 'disputes' : proposals.length > 0 ? 'proposals' : 'decided',
    );

    return (
        <Authenticated
            auth={props.auth}
            header={<h1 className="text-4xl font-bold leading-tight">Pemeriksaan sumber</h1>}
        >
            <Head title="Pemeriksaan" />

            <div role="tablist" aria-label="Antrean pemeriksaan" className="flex flex-wrap gap-2">
                {tabs.map((item) => {
                    const selected = tab === item.id;

                    return (
                        <button
                            key={item.id}
                            type="button"
                            role="tab"
                            aria-selected={selected}
                            className={`border-2 border-ink px-3 py-2 font-bold ${selected ? 'bg-ink text-white' : 'bg-white hover:bg-canvas'}`}
                            onClick={() => setTab(item.id)}
                        >
                            {item.label} <span className="font-normal">{item.count}</span>
                        </button>
                    );
                })}
            </div>

            {tab === 'pending' ? (
                <div className="mt-4 overflow-x-auto" role="tabpanel">
                    {pendingCount > 0 ? (
                        <table className="admin-table">
                            <thead>
                                <tr>
                                    <th>Pengajuan</th>
                                    <th>Jenis</th>
                                    <th>Sumber</th>
                                    <th>Masuk</th>
                                </tr>
                            </thead>
                            <tbody>
                                {reports.map((record) => (
                                    <tr key={`record-${record.id}`}>
                                        <td>
                                            <Link href={route('reports.review', record.id)} className="row-link">
                                                {record.politician?.name}
                                            </Link>
                                            <p className="max-w-md text-muted">{excerpt(record)}</p>
                                        </td>
                                        <td>{categoryLabel[record.category] ?? record.category}</td>
                                        <td>{record.source_type}</td>
                                        <td className="whitespace-nowrap">{formatDate(record.created_at)}</td>
                                    </tr>
                                ))}
                                {affiliations.map((item) => (
                                    <tr key={`affiliation-${item.id}`}>
                                        <td>
                                            <Link href={route('affiliations.review', item.id)} className="row-link">
                                                {item.politician?.name}
                                            </Link>
                                            <p className="max-w-md text-muted">
                                                {item.business?.name ? `${item.business.name} · ` : ''}
                                                {excerpt(item)}
                                            </p>
                                        </td>
                                        <td>{affiliationLabel(item)}</td>
                                        <td>{item.source_type}</td>
                                        <td className="whitespace-nowrap">{formatDate(item.created_at)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    ) : <p>Tidak ada pengajuan menunggu.</p>}
                </div>
            ) : null}

            {tab === 'disputes' ? (
                <div className="mt-4" role="tabpanel">
                    {disputeGroups.length > 0 ? disputeGroups.map((group) => (
                        <section key={`${group.kind ?? 'record'}-${group.record.id}`} className="mt-6 first:mt-0">
                            <p className="text-sm font-bold text-muted">
                                {group.kind === 'affiliation' ? 'Afiliasi bisnis' : 'Catatan publik'}
                                {group.kind === 'affiliation' && group.record.threshold ? (
                                    <> · pending {group.record.pending_count ?? group.disputes.length}/{group.record.threshold}{group.record.retracted ? ' · sudah ditarik otomatis' : ''}</>
                                ) : null}
                            </p>
                            <h2 className="text-2xl font-bold">
                                {group.record.href ? (
                                    <Link href={group.record.href} className="row-link">{group.record.politician}</Link>
                                ) : group.record.politician}
                            </h2>
                            <p className="mt-1 max-w-3xl text-muted">{group.record.excerpt}</p>
                            <div className="mt-3 overflow-x-auto">
                                <table className="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Kode</th>
                                            <th>Sanggahan</th>
                                            <th>Keputusan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {group.disputes.map((dispute) => (
                                            <tr key={dispute.id}>
                                                <td className="whitespace-nowrap font-mono">{dispute.tracking_code}</td>
                                                <td>
                                                    <p className="max-w-md">{excerpt({ quote: dispute.reason })}</p>
                                                    <a href={dispute.source_url} className="underline">{dispute.source_type}</a>
                                                </td>
                                                <td className="whitespace-nowrap">
                                                    <button type="button" className="gov-button" onClick={() => Inertia.post(route('admin.disputes.decide', dispute.id), { decision: 'uphold' })}>
                                                        Tarik arsip
                                                    </button>
                                                    <button type="button" className="ml-3 underline" onClick={() => Inertia.post(route('admin.disputes.decide', dispute.id), { decision: 'dismiss' })}>
                                                        Tolak
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    )) : <p>Tidak ada sanggahan menunggu.</p>}
                </div>
            ) : null}

            {tab === 'proposals' ? (
                <div className="mt-4" role="tabpanel">
                    <p className="mb-3 max-w-3xl">Usulan ini dibaca dari kutipan atau ringkasan yang sudah terbit. Profil publik tidak berubah sampai usulan diterima.</p>
                    <ProposalList proposals={proposals} />
                </div>
            ) : null}

            {tab === 'decided' ? (
                <div className="mt-4 overflow-x-auto" role="tabpanel">
                    {decidedCount > 0 ? (
                        <table className="admin-table">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Pengajuan</th>
                                    <th>Jenis</th>
                                    <th>Pemeriksa</th>
                                </tr>
                            </thead>
                            <tbody>
                                {decided.map((record) => (
                                    <tr key={`decided-record-${record.id}`}>
                                        <td>
                                            <span className={`inline-block px-2 py-0.5 font-bold ${record.status === 1 ? 'bg-ink text-white' : 'bg-canvas'}`}>
                                                {statusLabel[record.status] ?? record.status}
                                            </span>
                                        </td>
                                        <td>
                                            <Link href={route('reports.review', record.id)} className="row-link">
                                                {record.politician?.name}
                                            </Link>
                                            {record.review_note ? <p className="max-w-md text-muted">{excerpt({ quote: record.review_note })}</p> : null}
                                        </td>
                                        <td>{categoryLabel[record.category] ?? record.category}</td>
                                        <td>
                                            {record.reviewer?.name ?? 'Pemeriksa'}
                                            <p className="text-muted whitespace-nowrap">{formatDate(record.reviewed_at)}</p>
                                        </td>
                                    </tr>
                                ))}
                                {decidedAffiliations.map((item) => (
                                    <tr key={`decided-affiliation-${item.id}`}>
                                        <td>
                                            <span className={`inline-block px-2 py-0.5 font-bold ${item.status === 1 ? 'bg-ink text-white' : 'bg-canvas'}`}>
                                                {statusLabel[item.status] ?? item.status}
                                            </span>
                                        </td>
                                        <td>
                                            <Link href={route('affiliations.review', item.id)} className="row-link">
                                                {item.politician?.name}
                                            </Link>
                                            <p className="max-w-md text-muted">
                                                {item.business?.name ? `${item.business.name} · ` : ''}
                                                {item.review_note ? excerpt({ quote: item.review_note }) : excerpt(item)}
                                            </p>
                                        </td>
                                        <td>{affiliationLabel(item)}</td>
                                        <td>
                                            {item.reviewer?.name ?? 'Pemeriksa'}
                                            <p className="text-muted whitespace-nowrap">{formatDate(item.reviewed_at)}</p>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    ) : <p>Belum ada keputusan.</p>}
                </div>
            ) : null}
        </Authenticated>
    );
}
