import { Link } from '@inertiajs/inertia-react';

const statusLabel = {
    queued: 'Antri',
    running: 'Berjalan',
    completed: 'Selesai',
    failed: 'Gagal',
};

const statusTone = {
    queued: 'border-ink/30 bg-white text-muted',
    running: 'border-ink bg-canvas text-ink',
    completed: 'border-ink bg-ink text-white',
    failed: 'border-brand bg-brand text-white',
};

export default function AutoVetPanel({
    enabled,
    run,
    draft,
    runHref,
    onApplyDraft,
}) {
    if (!enabled && !draft && !run) {
        return null;
    }

    return (
        <section className="border-2 border-ink border-l-8 border-l-brand bg-white">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b-2 border-ink bg-canvas px-4 py-3 sm:px-5">
                <div>
                    <h2 className="text-xl font-bold">AutoVet</h2>
                    <p className="text-sm text-muted">Bantuan pemeriksaan otomatis terhadap sumber.</p>
                </div>
                {run ? (
                    <span className={`border-2 px-2 py-0.5 text-sm font-bold ${statusTone[run.status] ?? statusTone.queued}`}>
                        {statusLabel[run.status] ?? run.status}
                        {run.mode ? ` · ${run.mode}` : ''}
                    </span>
                ) : null}
            </div>

            <div className="px-4 py-4 sm:px-5">
                {!enabled ? (
                    <p className="text-muted">Plugin AutoVet nonaktif. Aktifkan di Pengaturan.</p>
                ) : (
                    <Link href={runHref} method="post" as="button" className="gov-button" preserveScroll>
                        Jalankan AutoVet
                    </Link>
                )}

                {run?.error ? <p className="mt-3 text-brand">{run.error}</p> : null}

                {draft ? (
                    <div className="mt-4 border-t border-ink/20 pt-4">
                        <p className="font-bold">Usulan siap diterapkan</p>
                        <p className="mt-1 text-sm text-muted">
                            Keputusan: <span className="font-bold text-ink">{draft.decision}</span>
                            {' · '}
                            Kepercayaan: <span className="font-bold text-ink">{draft.confidence}</span>
                            {draft.reliability_level ? (
                                <>
                                    {' · '}
                                    Keandalan: <span className="font-bold text-ink">{draft.reliability_level}</span>
                                </>
                            ) : null}
                        </p>
                        {draft.rationale ? <p className="mt-2 text-sm">{draft.rationale}</p> : null}
                        {draft.review_note ? (
                            <p className="mt-2 text-sm">
                                <span className="font-bold">Catatan:</span> {draft.review_note}
                            </p>
                        ) : null}
                        {typeof onApplyDraft === 'function' ? (
                            <button type="button" className="gov-button mt-3" onClick={() => onApplyDraft(draft)}>
                                Terapkan usulan ke formulir
                            </button>
                        ) : null}
                    </div>
                ) : null}
            </div>
        </section>
    );
}
