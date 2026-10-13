import { useEffect, useRef, useState } from 'react';
import { Inertia } from '@inertiajs/inertia';

function shortcutLabel() {
    if (typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform || '')) {
        return '⌘K';
    }

    return 'Ctrl K';
}

function HitBody({ hit }) {
    return (
        <span className="flex items-start gap-3 text-left">
            {hit.image_url ? (
                <img src={hit.image_url} alt="" className="mt-0.5 h-10 w-10 shrink-0 border-2 border-ink object-cover bg-white" />
            ) : null}
            <span className="min-w-0">
                <span className="block text-sm font-bold text-brand">{hit.label}</span>
                <span className="block text-lg font-bold text-ink leading-snug">{hit.title}</span>
                {hit.subtitle ? <span className="block text-base text-muted">{hit.subtitle}</span> : null}
            </span>
        </span>
    );
}

export default function CommandSearch() {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [active, setActive] = useState(0);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const inputRef = useRef(null);
    const requestRef = useRef(0);
    const shortcut = shortcutLabel();

    useEffect(() => {
        const onKey = (event) => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                setOpen(true);
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, []);

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        const frame = window.requestAnimationFrame(() => inputRef.current?.focus());

        return () => {
            document.body.style.overflow = previous;
            window.cancelAnimationFrame(frame);
        };
    }, [open]);

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const term = query.trim();

        if (term.length < 2) {
            setResults([]);
            setLoading(false);
            setFailed(false);
            return undefined;
        }

        const requestId = requestRef.current + 1;
        requestRef.current = requestId;
        const timer = window.setTimeout(async () => {
            setLoading(true);
            setFailed(false);

            try {
                const response = await window.axios.get('/search', {
                    params: { q: term },
                    headers: { Accept: 'application/json' },
                });

                if (requestRef.current !== requestId) {
                    return;
                }

                setResults(response.data?.results ?? []);
                setActive(0);
            } catch (error) {
                if (requestRef.current !== requestId) {
                    return;
                }

                setResults([]);
                setFailed(true);
            } finally {
                if (requestRef.current === requestId) {
                    setLoading(false);
                }
            }
        }, 180);

        return () => window.clearTimeout(timer);
    }, [open, query]);

    const close = () => setOpen(false);

    const openResult = (hit) => {
        if (!hit?.href) {
            return;
        }

        close();
        Inertia.visit(hit.href);
    };

    const onInputKey = (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive((current) => (results.length === 0 ? 0 : (current + 1) % results.length));
            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive((current) => (results.length === 0 ? 0 : (current - 1 + results.length) % results.length));
            return;
        }

        if (event.key === 'Enter' && results[active]) {
            event.preventDefault();
            openResult(results[active]);
        }
    };

    return (
        <>
            <button
                type="button"
                className="inline-flex items-center justify-center px-4 text-white"
                aria-label={`Cari, ${shortcut}`}
                onClick={() => setOpen(true)}
            >
                <svg aria-hidden="true" viewBox="0 0 24 24" className="h-6 w-6" fill="none" stroke="currentColor" strokeWidth="2">
                    <circle cx="10" cy="10" r="6" />
                    <path d="M20 20 14.5 14.5" />
                </svg>
            </button>
            {open ? (
                <div className="fixed inset-0 z-50 bg-ink/70 px-4 py-8" onMouseDown={close}>
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-label="Cari arsip"
                        className="mx-auto w-full max-w-2xl border-2 border-ink bg-white text-ink shadow-none"
                        onMouseDown={(event) => event.stopPropagation()}
                    >
                        <div className="border-b-2 border-ink p-3">
                            <label htmlFor="command-search" className="block text-sm font-bold mb-1">Cari politisi, partai, atau catatan</label>
                            <input
                                id="command-search"
                                ref={inputRef}
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                onKeyDown={onInputKey}
                                className="gov-input"
                                placeholder="Nama, partai, kutipan, atau topik"
                                autoComplete="off"
                                role="combobox"
                                aria-expanded="true"
                                aria-controls="command-search-results"
                                aria-activedescendant={results[active] ? `command-search-option-${active}` : undefined}
                            />
                        </div>
                        <ul id="command-search-results" role="listbox" className="max-h-[60vh] overflow-y-auto">
                            {results.map((hit, index) => (
                                <li key={`${hit.type}-${hit.href}`} role="presentation">
                                    <button
                                        type="button"
                                        id={`command-search-option-${index}`}
                                        role="option"
                                        aria-selected={index === active}
                                        className={`block w-full text-left border-0 border-b border-ink/15 px-3 py-3 ${index === active ? 'bg-canvas' : 'bg-white'}`}
                                        onMouseEnter={() => setActive(index)}
                                        onClick={() => openResult(hit)}
                                    >
                                        <HitBody hit={hit} />
                                    </button>
                                </li>
                            ))}
                        </ul>
                        <div className="px-3 py-2 text-sm text-muted border-t-2 border-ink">
                            {failed ? 'Pencarian gagal. Coba lagi.' : null}
                            {!failed && loading ? 'Mencari…' : null}
                            {!failed && !loading && query.trim().length < 2 ? 'Ketik minimal 2 huruf.' : null}
                            {!failed && !loading && query.trim().length >= 2 && results.length === 0 ? `Tidak ada hasil untuk “${query.trim()}”.` : null}
                            {!failed && !loading && results.length > 0 ? 'Panah untuk memilih, Enter membuka, Esc menutup.' : null}
                        </div>
                    </div>
                </div>
            ) : null}
        </>
    );
}
