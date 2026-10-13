import { useState } from 'react';
import Authenticated from '@/Layouts/Authenticated';
import ProposalList from '@/Components/ProposalList';
import { Inertia } from '@inertiajs/inertia';
import { Link, Head } from '@inertiajs/inertia-react';

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

export default function Dashboard(props) {
    const reports = props.reports ?? [];
    const decided = props.decided ?? [];
    const proposals = props.proposals ?? [];
    const disputeGroups = props.disputes ?? [];
    const disputeCount = disputeGroups.reduce((sum, group) => sum + group.disputes.length, 0);
    const tabs = [
        { id: 'pending', label: 'Menunggu', count: reports.length },
        { id: 'disputes', label: 'Sanggahan', count: disputeCount },
        { id: 'proposals', label: 'Usulan', count: proposals.length },
        { id: 'decided', label: 'Keputusan', count: decided.length },
    ];
    const [tab, setTab] = useState(
        reports.length > 0 ? 'pending' : disputeCount > 0 ? 'disputes' : proposals.length > 0 ? 'proposals' : 'decided',
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
                    {reports.length > 0 ? (
                        <table className="admin-table">
                            <thead>
                                <tr>
                                    <th>Catatan</th>
                                    <th>Jenis</th>
                                    <th>Sumber</th>
                                    <th>Masuk</th>
                                </tr>
                            </thead>
                            <tbody>
                                {reports.map((record) => (
                                    <tr key={record.id}>
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
                            </tbody>
                        </table>
                    ) : <p>Tidak ada catatan menunggu.</p>}
                </div>
            ) : null}

            {tab === 'disputes' ? (
                <div className="mt-4" role="tabpanel">
                    {disputeGroups.length > 0 ? disputeGroups.map((group) => (
                        <section key={group.record.id} className="mt-6 first:mt-0">
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
                    {decided.length > 0 ? (
                        <table className="admin-table">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Catatan</th>
                                    <th>Jenis</th>
                                    <th>Pemeriksa</th>
                                </tr>
                            </thead>
                            <tbody>
                                {decided.map((record) => (
                                    <tr key={record.id}>
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
                            </tbody>
                        </table>
                    ) : <p>Belum ada keputusan.</p>}
                </div>
            ) : null}
        </Authenticated>
    );
}
