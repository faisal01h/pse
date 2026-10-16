import { useEffect } from 'react';

export default function HeadlessBrowserModal({ open, onClose, loading = false, result = null, error = null, url = '' }) {
    useEffect(() => {
        if (!open) {
            return undefined;
        }

        function onKeyDown(event) {
            if (event.key === 'Escape') {
                onClose();
            }
        }

        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [open, onClose]);

    if (!open) {
        return null;
    }

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
            onClick={onClose}
            role="presentation"
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="headless-browser-modal-title"
                className="max-h-full w-full max-w-4xl overflow-y-auto border-2 border-ink bg-canvas p-4"
                onClick={(event) => event.stopPropagation()}
            >
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h2 id="headless-browser-modal-title" className="text-2xl font-bold">Tes headless browser</h2>
                        <p className="mt-1 break-all text-sm text-muted">{url}</p>
                    </div>
                    <button type="button" className="gov-button" onClick={onClose}>
                        Tutup
                    </button>
                </div>

                {loading ? (
                    <p className="mt-6 font-bold">Membuka halaman di headless browser…</p>
                ) : null}

                {error ? (
                    <p className="mt-6 border-l-8 border-red-700 bg-canvas px-4 py-3 font-bold text-red-700">{error}</p>
                ) : null}

                {result ? (
                    <div className="mt-6 flex flex-col gap-4">
                        {result.screenshot ? (
                            <img
                                src={result.screenshot}
                                alt="Tangkapan layar halaman dari headless browser"
                                className="w-full border-2 border-ink"
                            />
                        ) : (
                            <p className="text-muted">Tangkapan layar tidak tersedia.</p>
                        )}

                        <dl className="grid gap-2 text-sm sm:grid-cols-[10rem_1fr]">
                            <dt className="font-bold">URL akhir</dt>
                            <dd className="break-all">{result.final_url}</dd>
                            <dt className="font-bold">Judul halaman</dt>
                            <dd>{result.title || '—'}</dd>
                            <dt className="font-bold">Panjang teks</dt>
                            <dd>{result.text_length.toLocaleString('id-ID')} karakter</dd>
                        </dl>

                        <div>
                            <p className="font-bold">Cuplikan teks</p>
                            <p className="mt-1 leading-relaxed text-muted">{result.excerpt || 'Tidak ada teks terbaca.'}</p>
                        </div>
                    </div>
                ) : null}
            </div>
        </div>
    );
}
