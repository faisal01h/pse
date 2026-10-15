import { Link, Head } from '@inertiajs/inertia-react';
import Public from '@/Layouts/Public';
import FeedRecord from '@/Components/FeedRecord';
import useInfiniteReports from '@/hooks/useInfiniteReports';

export default function Welcome(props) {
    const {
        reports,
        total,
        loading,
        error,
        hasMore,
        sentinelRef,
        loadMore,
    } = useInfiniteReports({
        initialReports: props.reports ?? [],
        initialMeta: props.meta ?? {},
        initialTotal: props.total ?? 0,
        partyId: props.party?.id ?? null,
    });

    return (
        <Public auth={props.auth}>
            <Head title="Beranda" />
            <div className="mx-auto max-w-[960px] px-4 pt-8">
                <h1 className="text-4xl font-bold leading-tight md:text-5xl">PolitiSEE</h1>
                <p className="mt-3 max-w-2xl text-xl">Arsip publik tokoh publik, pejabat dan politisi.</p>
                <Link href="/reports" className="gov-button mt-6">Buat pengajuan</Link>
            </div>

            <section className="feed-section mt-10 border-t-2 border-ink">
                <div className="mx-auto max-w-[960px] px-4 py-8 sm:py-10">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p className="text-sm font-bold uppercase tracking-wide text-muted">Arsip publik</p>
                            <h2 className="mt-1 text-3xl font-bold leading-tight sm:text-4xl">Data terbaru</h2>
                            <p className="mt-2 max-w-xl text-muted">
                                Catatan yang baru diterbitkan setelah diperiksa, dengan tautan ke sumber aslinya.
                            </p>
                        </div>
                        {total > 0 ? (
                            <p className="text-sm font-bold text-muted sm:text-right">
                                {reports.length} dari {total} catatan
                            </p>
                        ) : null}
                    </div>

                    {props.party ? (
                        <div className="mt-6 flex flex-wrap items-center gap-3 border-2 border-ink bg-white px-4 py-3">
                            {props.party.logo_url ? (
                                <img
                                    src={props.party.logo_url}
                                    alt=""
                                    className="h-8 w-8 border border-ink object-contain"
                                    loading="lazy"
                                />
                            ) : null}
                            <p className="flex-1 font-bold">
                                Menampilkan partai {props.party.name}
                            </p>
                            <Link href="/" className="font-bold">
                                Tampilkan semua
                            </Link>
                        </div>
                    ) : null}

                    {reports.length > 0 ? (
                        <div className="mt-5 flex flex-col gap-3">
                            {reports.map((report, index) => (
                                <FeedRecord key={report.id} report={report} index={index} />
                            ))}
                        </div>
                    ) : (
                        <div className="mt-6 border-2 border-dashed border-ink/30 bg-white px-4 py-10 text-center">
                            <p className="text-lg font-bold">Belum ada data yang terbit.</p>
                            <p className="mt-2 text-muted">Pengajuan yang lolos pemeriksaan akan muncul di sini.</p>
                            <Link href="/reports" className="gov-button mt-5">
                                Buat pengajuan
                            </Link>
                        </div>
                    )}

                    {reports.length > 0 ? (
                        <div className="mt-6 flex flex-col items-center gap-3" aria-live="polite">
                            {error ? (
                                <div className="flex flex-wrap items-center justify-center gap-3">
                                    <p className="font-bold text-brand">{error}</p>
                                    <button type="button" className="font-bold underline" onClick={loadMore}>
                                        Coba lagi
                                    </button>
                                </div>
                            ) : null}

                            {loading ? (
                                <p className="text-sm font-bold text-muted">Memuat catatan…</p>
                            ) : null}

                            {!hasMore && !loading ? (
                                <p className="text-sm font-bold text-muted">Semua catatan sudah ditampilkan.</p>
                            ) : null}

                            {hasMore ? (
                                <div ref={sentinelRef} className="h-8 w-full" aria-hidden="true" />
                            ) : null}

                            {hasMore && !loading ? (
                                <button
                                    type="button"
                                    className="border-2 border-ink bg-white px-4 py-2 font-bold"
                                    onClick={loadMore}
                                >
                                    Muat lebih banyak
                                </button>
                            ) : null}
                        </div>
                    ) : null}
                </div>
            </section>
        </Public>
    );
}
