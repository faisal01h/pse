import { Link, usePage } from '@inertiajs/inertia-react';

const items = [
    {
        label: 'Pemeriksaan',
        match: ['dashboard', 'reports.review', 'affiliations.review'],
        href: () => route('dashboard'),
        show: () => true,
    },
    {
        label: 'Politisi',
        match: ['admin.politicians.*'],
        href: () => route('admin.politicians.index'),
        show: () => true,
    },
    {
        label: 'Partai',
        match: ['admin.parties.*'],
        href: () => route('admin.parties.index'),
        show: () => true,
    },
    {
        label: 'Halaman',
        match: ['admin.pages.*'],
        href: () => route('admin.pages.index'),
        show: () => true,
    },
    {
        label: 'Audit',
        match: ['admin.audits.*'],
        href: () => route('admin.audits.index'),
        show: () => true,
    },
    {
        label: 'Peran',
        match: ['admin.roles.*'],
        href: () => route('admin.roles.index'),
        show: (permissions) => permissions.includes('roles.view'),
    },
    {
        label: 'Pengguna',
        match: ['admin.users.*'],
        href: () => route('admin.users.index'),
        show: (permissions) => permissions.includes('users.view'),
    },
];

function isCurrent(match) {
    if (typeof route !== 'function' || typeof route().current !== 'function') {
        return false;
    }

    return match.some((name) => route().current(name));
}

export default function AdminNav() {
    const { auth } = usePage().props;
    const permissions = auth?.permissions ?? [];
    const visible = items.filter((item) => item.show(permissions));

    return (
        <nav aria-label="Menu kelola" className="border-b-2 border-ink bg-canvas">
            <ul className="mx-auto flex max-w-[960px] gap-1 overflow-x-auto px-2">
                {visible.map((item) => {
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
