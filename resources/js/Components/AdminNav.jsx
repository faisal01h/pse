import { Link } from '@inertiajs/inertia-react';

const items = [
    {
        label: 'Pemeriksaan',
        match: ['dashboard', 'reports.review'],
        href: () => route('dashboard'),
    },
    {
        label: 'Politisi',
        match: ['admin.politicians.*'],
        href: () => route('admin.politicians.index'),
    },
    {
        label: 'Partai',
        match: ['admin.parties.*'],
        href: () => route('admin.parties.index'),
    },
    {
        label: 'Halaman',
        match: ['admin.pages.*'],
        href: () => route('admin.pages.index'),
    },
    {
        label: 'Audit',
        match: ['admin.audits.*'],
        href: () => route('admin.audits.index'),
    },
];

function isCurrent(match) {
    if (typeof route !== 'function' || typeof route().current !== 'function') {
        return false;
    }

    return match.some((name) => route().current(name));
}

export default function AdminNav() {
    return (
        <nav aria-label="Menu kelola" className="border-b-2 border-ink bg-canvas">
            <ul className="mx-auto flex max-w-[960px] gap-1 overflow-x-auto px-2">
                {items.map((item) => {
                    const current = isCurrent(item.match);

                    return (
                        <li key={item.label} className="flex">
                            <Link
                                href={item.href()}
                                aria-current={current ? 'page' : undefined}
                                className={`admin-bar-link whitespace-nowrap border-b-4 px-3 py-3 font-bold ${
                                    current
                                        ? 'border-brand bg-white text-ink'
                                        : 'border-transparent text-ink hover:bg-white'
                                }`}
                            >
                                {item.label}
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
