import { Fragment, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Transition } from '@headlessui/react';
import { usePage } from '@inertiajs/inertia-react';
import { buildAllStoryShareImages, SHARE_THEMES } from '@/lib/storyShareImage';

function recordUrl(record) {
    if (record.href) {
        if (record.href.startsWith('http')) {
            return record.href;
        }
        return `${window.location.origin}${record.href}`;
    }

    return `${window.location.origin}${route('records.show', record.id)}`;
}

function ShareIcon({ className = 'h-4 w-4' }) {
    return (
        <svg aria-hidden="true" viewBox="0 0 24 24" className={className} fill="none" stroke="currentColor" strokeWidth="2">
            <circle cx="18" cy="5" r="3" />
            <circle cx="6" cy="12" r="3" />
            <circle cx="18" cy="19" r="3" />
            <path d="M8.6 13.5 15.4 17.5M15.4 6.5 8.6 10.5" />
        </svg>
    );
}

function ThemeCarousel({ themes, index, onIndexChange }) {
    const startXRef = useRef(null);
    const draggingRef = useRef(false);
    const indexRef = useRef(index);
    const [dragging, setDragging] = useState(false);
    const [offset, setOffset] = useState(0);

    useEffect(() => {
        indexRef.current = index;
    }, [index]);

    const goTo = (next) => {
        const clamped = Math.max(0, Math.min(themes.length - 1, next));
        onIndexChange(clamped);
        setOffset(0);
        setDragging(false);
        draggingRef.current = false;
    };

    const onPointerDown = (event) => {
        if (event.button !== undefined && event.button !== 0) {
            return;
        }
        startXRef.current = event.clientX;
        draggingRef.current = true;
        setDragging(true);
        setOffset(0);
        event.currentTarget.setPointerCapture?.(event.pointerId);
    };

    const onPointerMove = (event) => {
        if (!draggingRef.current || startXRef.current === null) {
            return;
        }
        setOffset(event.clientX - startXRef.current);
    };

    const finishGesture = (event) => {
        if (!draggingRef.current || startXRef.current === null) {
            return;
        }

        const delta = event.clientX - startXRef.current;
        startXRef.current = null;
        draggingRef.current = false;
        setDragging(false);
        event.currentTarget.releasePointerCapture?.(event.pointerId);

        const threshold = Math.min(80, (event.currentTarget.clientWidth || 240) * 0.2);
        if (delta <= -threshold) {
            goTo(indexRef.current + 1);
            return;
        }
        if (delta >= threshold) {
            goTo(indexRef.current - 1);
            return;
        }
        setOffset(0);
    };

    const current = themes[index];

    return (
        <div className="mt-4">
            <div
                className="relative cursor-grab overflow-hidden border-2 border-ink bg-ink/5 active:cursor-grabbing"
                style={{ touchAction: 'none' }}
                onPointerDown={onPointerDown}
                onPointerMove={onPointerMove}
                onPointerUp={finishGesture}
                onPointerCancel={() => {
                    startXRef.current = null;
                    draggingRef.current = false;
                    setDragging(false);
                    setOffset(0);
                }}
            >
                <img
                    src={current?.dataUrl}
                    alt={`Tema ${current?.label ?? ''}`}
                    className="pointer-events-none w-full select-none"
                    draggable={false}
                    style={{
                        transform: `translateX(${offset}px)`,
                        transition: dragging ? 'none' : 'transform 180ms ease-out',
                    }}
                />
            </div>

            <div className="mt-3 flex items-center justify-between gap-3">
                <button
                    type="button"
                    className="border-2 border-ink bg-white px-3 py-1 text-xl font-bold leading-none disabled:opacity-40"
                    disabled={index <= 0}
                    onClick={() => goTo(index - 1)}
                    aria-label="Tema sebelumnya"
                >
                    ‹
                </button>
                <div className="flex flex-col items-center gap-1.5">
                    <p className="text-sm font-bold">{current?.label}</p>
                    <div className="flex items-center gap-1.5">
                        {themes.map((theme, themeIndex) => (
                            <button
                                key={theme.id}
                                type="button"
                                aria-label={theme.label}
                                aria-current={themeIndex === index ? 'true' : undefined}
                                className={`h-2.5 w-2.5 border-2 border-ink ${themeIndex === index ? 'bg-ink' : 'bg-white'}`}
                                onClick={() => goTo(themeIndex)}
                            />
                        ))}
                    </div>
                    <p className="text-sm text-muted">
                        Geser untuk mengganti tema · {index + 1}/{themes.length}
                    </p>
                </div>
                <button
                    type="button"
                    className="border-2 border-ink bg-white px-3 py-1 text-xl font-bold leading-none disabled:opacity-40"
                    disabled={index >= themes.length - 1}
                    onClick={() => goTo(index + 1)}
                    aria-label="Tema berikutnya"
                >
                    ›
                </button>
            </div>
        </div>
    );
}

export default function RecordShare({
    record,
    politician = null,
    className = '',
    menuPlacement = 'bottom',
    onOpenChange = null,
}) {
    const { appUrl, ziggy } = usePage().props;
    const resolvedAppUrl = appUrl || ziggy?.url || (typeof window !== 'undefined' ? window.location.origin : '');

    const payload = {
        ...record,
        politician: record.politician ?? politician ?? null,
    };

    const [open, setOpen] = useState(false);
    const [status, setStatus] = useState('');
    const [busy, setBusy] = useState(false);
    const [themes, setThemes] = useState(null);
    const [themeIndex, setThemeIndex] = useState(0);
    const [canNativeShare, setCanNativeShare] = useState(false);
    const rootRef = useRef(null);

    useEffect(() => {
        setCanNativeShare(typeof navigator !== 'undefined' && typeof navigator.share === 'function');
    }, []);

    useEffect(() => {
        onOpenChange?.(open || Boolean(themes));
        return () => onOpenChange?.(false);
    }, [open, themes, onOpenChange]);

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const onKey = (event) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };
        const onClick = (event) => {
            if (rootRef.current && !rootRef.current.contains(event.target)) {
                setOpen(false);
            }
        };

        document.addEventListener('keydown', onKey);
        document.addEventListener('mousedown', onClick);
        return () => {
            document.removeEventListener('keydown', onKey);
            document.removeEventListener('mousedown', onClick);
        };
    }, [open]);

    useEffect(() => {
        if (!themes) {
            return undefined;
        }

        const onKey = (event) => {
            if (event.key === 'Escape') {
                setThemes(null);
            }
            if (event.key === 'ArrowLeft') {
                setThemeIndex((current) => Math.max(0, current - 1));
            }
            if (event.key === 'ArrowRight') {
                setThemeIndex((current) => Math.min(SHARE_THEMES.length - 1, current + 1));
            }
        };

        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [themes]);

    useEffect(() => {
        if (!status) {
            return undefined;
        }
        const timer = window.setTimeout(() => setStatus(''), 2500);
        return () => window.clearTimeout(timer);
    }, [status]);

    const copyLink = async () => {
        const url = recordUrl(payload);
        try {
            await navigator.clipboard.writeText(url);
            setStatus('Tautan disalin');
            setOpen(false);
        } catch {
            window.prompt('Salin tautan catatan:', url);
        }
    };

    const shareLink = async () => {
        if (!navigator.share) {
            return;
        }

        const url = recordUrl(payload);
        try {
            await navigator.share({
                title: payload.politician?.name
                    ? `${payload.politician.name} — PolitiSEE`
                    : 'Catatan PolitiSEE',
                text: payload.quote || payload.summary || 'Catatan publik di PolitiSEE',
                url,
            });
            setOpen(false);
        } catch (error) {
            if (error?.name === 'AbortError') {
                return;
            }
        }
    };

    const openImagePreview = async () => {
        setBusy(true);
        setStatus('');
        try {
            const generated = await buildAllStoryShareImages(payload, resolvedAppUrl);
            setThemes(generated);
            setThemeIndex(0);
            setOpen(false);
        } catch {
            setStatus('Gagal membuat gambar');
        } finally {
            setBusy(false);
        }
    };

    const downloadImage = () => {
        const current = themes?.[themeIndex];
        if (!current) {
            return;
        }
        const link = document.createElement('a');
        link.href = current.dataUrl;
        link.download = `politisee-catatan-${payload.id}-${current.id}.png`;
        link.click();
        setStatus('Gambar diunduh');
    };

    const closePreview = () => {
        setThemes(null);
        setThemeIndex(0);
    };

    const menuAbove = menuPlacement === 'top';

    return (
        <div ref={rootRef} className={`relative inline-flex ${open ? 'z-50' : ''} ${className}`}>
            <button
                type="button"
                className="inline-flex items-center gap-2 border-2 border-ink/25 bg-white px-3 py-1.5 text-sm font-bold text-ink hover:border-ink"
                aria-expanded={open}
                aria-haspopup="menu"
                onClick={() => setOpen((value) => !value)}
            >
                <ShareIcon />
                Bagikan
            </button>

            {status ? (
                <span
                    className={`absolute left-0 whitespace-nowrap text-sm font-bold text-muted ${
                        menuAbove ? '-top-7' : '-bottom-7'
                    }`}
                    role="status"
                >
                    {status}
                </span>
            ) : null}

            <Transition
                as={Fragment}
                show={open}
                enter="transition ease-out duration-150"
                enterFrom={`opacity-0 ${menuAbove ? '-translate-y-1' : 'translate-y-1'}`}
                enterTo="opacity-100 translate-y-0"
                leave="transition ease-in duration-100"
                leaveFrom="opacity-100 translate-y-0"
                leaveTo={`opacity-0 ${menuAbove ? '-translate-y-1' : 'translate-y-1'}`}
            >
                <div
                    role="menu"
                    className={`absolute right-0 z-50 w-56 border-2 border-ink bg-white shadow-[0_2px_0_#0b0c0c] ${
                        menuAbove ? 'bottom-full mb-2' : 'top-full mt-2'
                    }`}
                >
                    {canNativeShare ? (
                        <button
                            type="button"
                            role="menuitem"
                            className="block w-full border-b-2 border-ink/10 px-4 py-3 text-left font-bold hover:bg-canvas"
                            onClick={shareLink}
                        >
                            Bagikan tautan
                        </button>
                    ) : null}
                    <button
                        type="button"
                        role="menuitem"
                        className="block w-full border-b-2 border-ink/10 px-4 py-3 text-left font-bold hover:bg-canvas"
                        onClick={copyLink}
                    >
                        Salin tautan
                    </button>
                    <button
                        type="button"
                        role="menuitem"
                        className="block w-full px-4 py-3 text-left font-bold hover:bg-canvas disabled:opacity-60"
                        disabled={busy}
                        onClick={openImagePreview}
                    >
                        {busy ? 'Menyiapkan…' : 'Bagikan sebagai gambar'}
                    </button>
                </div>
            </Transition>

            {themes && typeof document !== 'undefined'
                ? createPortal(
                    <div
                        className="fixed inset-0 z-[100] flex items-center justify-center bg-ink/70 p-4"
                        role="dialog"
                        aria-modal="true"
                        aria-label="Pratinjau gambar"
                        onClick={closePreview}
                    >
                        <div
                            className="max-h-[90vh] w-full max-w-sm overflow-auto border-2 border-ink bg-white p-4"
                            onClick={(event) => event.stopPropagation()}
                        >
                            <p className="text-lg font-bold">Bagikan sebagai gambar</p>
                            <p className="mt-1 text-sm text-muted">Pilih tema, lalu unduh PNG · 9:16</p>

                            <ThemeCarousel
                                themes={themes}
                                index={themeIndex}
                                onIndexChange={setThemeIndex}
                            />

                            <div className="mt-4 flex flex-wrap gap-3">
                                <button type="button" className="gov-button" onClick={downloadImage}>
                                    Unduh PNG
                                </button>
                                <button
                                    type="button"
                                    className="border-2 border-ink bg-white px-4 py-2 font-bold"
                                    onClick={closePreview}
                                >
                                    Tutup
                                </button>
                            </div>
                        </div>
                    </div>,
                    document.body,
                )
                : null}
        </div>
    );
}
