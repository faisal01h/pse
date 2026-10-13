import { Link, Head } from '@inertiajs/inertia-react';
import Public from '@/Layouts/Public';
import PageLinks from '@/Components/PageLinks';
import RecordMedia from '@/Components/RecordMedia';

const categoryLabel = {
    statement: 'Pernyataan publik',
    allegation: 'Dugaan pelanggaran',
    conviction: 'Vonis pengadilan',
};

const categoryClass = {
    statement: 'border-brand text-brand',
    allegation: 'border-ink text-ink',
    conviction: 'border-ink bg-ink text-white',
};

function formatDate(value) {
    if (!value) {
        return 'Tanggal tidak diisi';
    }

    return new Date(value).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

export default function Welcome(props) {
    const reports = props.reports ?? [];

    return (
        <Public auth={props.auth}>
            <Head title="Beranda" />
            <div className="max-w-[960px] mx-auto px-4 pt-8">
                <h1 className="text-4xl md:text-5xl font-bold leading-tight">PolitiSEE</h1>
                <p className="mt-3 max-w-2xl text-xl">Arsip publik tokoh publik, pejabat dan politisi.</p>
                <Link href="/reports" className="gov-button mt-6">Buat pengajuan</Link>
            </div>

            <section className="bg-canvas mt-10 py-8">
                <div className="max-w-[960px] mx-auto px-4">
                    {props.party ? (
                        <p className="mb-4">
                            Partai: <Link href={route('parties.show', props.party.id)}><strong>{props.party.name}</strong></Link>. <Link href="/">Tampilkan semua catatan</Link>
                        </p>
                    ) : null}
                    <h2 className="text-3xl font-bold">Data terbaru</h2>
                    {reports.length > 0 ? (
                        <div className="mt-4 bg-white">
                            {reports.map((report) => (
                                <article key={report.id} className="border-b-2 border-ink px-4 py-5">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <p className="text-muted">{formatDate(report.date)}</p>
                                        <span className="flex flex-wrap justify-end gap-2">
                                            <span className={`text-sm font-bold border-2 px-2 py-0.5 ${categoryClass[report.category] ?? 'border-ink text-ink'}`}>
                                                {categoryLabel[report.category] ?? report.category}
                                            </span>
                                            {(report.topics ?? []).map((topic) => (
                                                <span key={topic.id} className="border-2 border-ink px-2 py-0.5 text-sm">{topic.name}</span>
                                            ))}
                                        </span>
                                    </div>
                                    <div className="mt-3 flex gap-4">
                                        {report.politician.photo_url ? (
                                            <img src={report.politician.photo_url} alt="" className="h-20 w-20 shrink-0 border-2 border-ink object-cover" />
                                        ) : null}
                                        <div>
                                            <h3 className="text-2xl font-bold leading-tight">
                                                <Link href={report.href}>{report.politician.name}</Link>
                                            </h3>
                                            {report.politician.position ? <p className="text-muted">{report.politician.position}</p> : null}
                                            {report.politician.institution ? <p className="text-muted">{report.politician.institution}</p> : null}
                                            {report.politician.party ? (
                                                <p className="mt-1 flex items-center gap-2">
                                                    {report.politician.party.logo_url ? (
                                                        <img src={report.politician.party.logo_url} alt="" className="h-8 w-8 border-2 border-ink bg-white object-contain" />
                                                    ) : null}
                                                    <Link href={route('parties.show', report.politician.party.id)}>{report.politician.party.name}</Link>
                                                </p>
                                            ) : null}
                                        </div>
                                    </div>
                                    {report.quote ? <blockquote className="mt-3 border-l-4 border-ink pl-3 text-lg">"{report.quote}"</blockquote> : null}
                                    {report.summary ? <p className="mt-3">{report.summary}</p> : null}
                                    <RecordMedia attachments={report.attachments} compact />
                                    <a href={report.source_url} className="mt-3 inline-block font-bold" target="_blank" rel="noreferrer">
                                        Sumber: {report.source_type}
                                    </a>
                                </article>
                            ))}
                        </div>
                    ) : <p className="mt-4">Belum ada data yang terbit.</p>}
                    <PageLinks
                        path="/"
                        meta={props.meta}
                        query={props.party ? { party: props.party.id } : {}}
                    />
                </div>
            </section>
        </Public>
    );
}
