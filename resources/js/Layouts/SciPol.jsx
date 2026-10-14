import { Link, usePage } from '@inertiajs/inertia-react';

const NAV = [
    { href: '/scipol', label: 'Hub', match: (url) => url === '/scipol' || url === '/scipol/' },
    { href: '/scipol/bandingkan', label: 'Compare', match: (url) => url.startsWith('/scipol/bandingkan') },
    { href: '/scipol/isu', label: 'Issue map', match: (url) => url.startsWith('/scipol/isu') },
    { href: '/scipol/afiliasi', label: 'Affiliations', match: (url) => url.startsWith('/scipol/afiliasi') },
    { href: '/scipol/topik', label: 'Topics', match: (url) => url.startsWith('/scipol/topik') },
    { href: '/scipol/latihan', label: 'Practice', match: (url) => url.startsWith('/scipol/latihan') },
];

export default function SciPol({ children, title }) {
    const { url } = usePage();
    const path = url.split('?')[0];

    return (
        <div className="min-h-screen bg-[#eef1f4] text-ink text-[15px] leading-snug">
            <header className="border-b border-ink/20 bg-[#1b242c] text-white">
                <div className="mx-auto flex max-w-[1280px] flex-wrap items-center justify-between gap-3 px-4 py-2.5">
                    <div className="flex min-w-0 items-baseline gap-3">
                        <Link href="/scipol" className="shrink-0 text-lg font-bold text-white no-underline hover:underline">
                            SciPol
                        </Link>
                        <span className="truncate text-xs text-white/70">
                            research workspace · {title || 'archive-based analysis'}
                        </span>
                    </div>
                    <Link href="/" className="shrink-0 text-xs font-bold text-white/90 underline">
                        Back to PolitiSEE
                    </Link>
                </div>
            </header>

            <div className="mx-auto flex max-w-[1280px] flex-col gap-0 lg:flex-row">
                <aside className="border-b border-ink/15 bg-white lg:w-52 lg:shrink-0 lg:border-b-0 lg:border-r">
                    <nav aria-label="SciPol tools" className="sticky top-0 p-3">
                        <p className="mb-2 px-2 text-[11px] font-bold uppercase tracking-wide text-muted">Tools</p>
                        <ul className="flex flex-wrap gap-1 lg:flex-col lg:gap-0.5">
                            {NAV.map((item) => {
                                const active = item.match(path);
                                return (
                                    <li key={item.href}>
                                        <Link
                                            href={item.href}
                                            className={`block rounded-sm px-2 py-1.5 text-sm no-underline ${
                                                active
                                                    ? 'bg-ink font-bold text-white'
                                                    : 'text-ink hover:bg-[#e8ecf0]'
                                            }`}
                                        >
                                            {item.label}
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                        <p className="mt-4 border-t border-ink/10 px-2 pt-3 text-[11px] text-muted">
                            Analysis over published PolitiSEE archive data.
                        </p>
                    </nav>
                </aside>

                <main className="min-w-0 flex-1 px-4 py-5 lg:px-6">{children}</main>
            </div>
        </div>
    );
}

export function SciPolNotice({ children }) {
    return (
        <div className="mb-4 border border-ink/20 bg-[#fff8e8] px-3 py-2 text-sm text-ink">
            {children}
        </div>
    );
}

export function SciPolTable({ children, className = '' }) {
    return (
        <div className={`overflow-auto border border-ink/25 bg-white ${className}`}>
            <table className="w-full min-w-[640px] border-collapse text-left text-[13px]">{children}</table>
        </div>
    );
}

export function SciPolTh({ children, className = '' }) {
    return (
        <th className={`sticky top-0 border-b border-ink/20 bg-[#f4f6f8] px-2 py-1.5 font-bold ${className}`}>
            {children}
        </th>
    );
}

export function SciPolTd({ children, className = '', colSpan }) {
    return (
        <td colSpan={colSpan} className={`border-b border-ink/10 px-2 py-1.5 align-top ${className}`}>
            {children}
        </td>
    );
}

export function CategoryBadge({ category }) {
    const labels = {
        statement: 'Statement',
        allegation: 'Allegation',
        conviction: 'Conviction',
    };
    const tones = {
        statement: 'bg-[#e8f1ff] text-[#1a3a6b]',
        allegation: 'bg-[#fff3cd] text-[#6b5200]',
        conviction: 'bg-[#fde2e2] text-[#7a1f1f]',
    };

    return (
        <span className={`inline-block px-1.5 py-0.5 text-[11px] font-bold ${tones[category] || 'bg-canvas'}`}>
            {labels[category] || category}
        </span>
    );
}

export function ExportButton({ href, disabled }) {
    if (disabled) {
        return (
            <span className="inline-block cursor-not-allowed bg-ink/20 px-3 py-1.5 text-sm font-bold text-ink/50">
                Export XLSX
            </span>
        );
    }

    return (
        <a href={href} className="inline-block bg-ink px-3 py-1.5 text-sm font-bold text-white no-underline hover:bg-brand">
            Export XLSX
        </a>
    );
}
