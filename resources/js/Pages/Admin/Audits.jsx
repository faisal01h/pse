import Authenticated from '@/Layouts/Authenticated';
import { Head, Link } from '@inertiajs/inertia-react';

const categoryLabel = {
    statement: 'Pernyataan publik',
    allegation: 'Dugaan pelanggaran',
    conviction: 'Vonis pengadilan',
};

const actionLabel = {
    created: 'Dibuat',
    updated: 'Diubah',
    deleted: 'Dihapus',
    inference: 'Inferensi',
};

function formatDate(value) {
    if (!value) {
        return 'Belum pernah';
    }

    return new Date(value).toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function Audits({ auth, records, audits }) {
    return (
        <Authenticated auth={auth} header={<h1 className="text-4xl font-bold leading-tight">Audit inferensi</h1>}>
            <Head title="Audit" />
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="max-w-2xl">Inferensi membaca catatan terbit dan membuat usulan. Usulan tidak masuk ke profil sampai diterima.</p>
                {auth?.permissions?.includes('audits.rerun') ? (
                    <Link href={route('admin.audits.run')} method="post" as="button" className="gov-button">
                        Jalankan semua
                    </Link>
                ) : null}
            </div>

            <h2 className="mt-8 text-2xl font-bold">Catatan terbit</h2>
            <div className="mt-3 overflow-x-auto">
                {records.length > 0 ? (
                    <table className="admin-table">
                        <thead>
                            <tr>
                                <th>Catatan</th>
                                <th>Jenis</th>
                                <th>Terakhir</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {records.map((record) => (
                                <tr key={record.id}>
                                    <td>
                                        <Link href={route('reports.review', record.id)}>{record.politician}</Link>
                                        {record.excerpt ? <p className="max-w-md text-muted">{record.excerpt}</p> : null}
                                    </td>
                                    <td>{categoryLabel[record.category] ?? record.category}</td>
                                    <td className="whitespace-nowrap">{formatDate(record.last_run_at)}</td>
                                    <td>
                                        {auth?.permissions?.includes('audits.rerun') ? (
                                            <Link
                                                href={route('admin.audits.records.run', record.id)}
                                                method="post"
                                                as="button"
                                                className="gov-button"
                                                preserveScroll
                                            >
                                                Jalankan
                                            </Link>
                                        ) : null}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                ) : <p>Belum ada catatan terbit.</p>}
            </div>

            <h2 className="mt-10 text-2xl font-bold">Riwayat perubahan</h2>
            <div className="mt-3 overflow-x-auto">
                {audits.length > 0 ? (
                    <table className="admin-table">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Perubahan</th>
                                <th>Rincian</th>
                            </tr>
                        </thead>
                        <tbody>
                            {audits.map((audit) => (
                                <tr key={audit.id}>
                                    <td className="whitespace-nowrap">
                                        {formatDate(audit.created_at)}
                                        <p className="text-muted">{audit.user ?? 'Pengunjung'}</p>
                                    </td>
                                    <td>
                                        {audit.href ? <Link href={audit.href}>{audit.subject}</Link> : audit.subject}
                                        <p className="text-muted">{actionLabel[audit.action] ?? audit.action}</p>
                                    </td>
                                    <td className="max-w-md">{audit.detail}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                ) : <p>Belum ada audit.</p>}
            </div>
        </Authenticated>
    );
}
