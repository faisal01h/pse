import { Link, usePage } from '@inertiajs/inertia-react';

function hasAny(permissions, keys) {
    return keys.some((key) => permissions.includes(key));
}

const items = [
    {
        label: 'Pemeriksaan',
        match: ['dashboard', 'reports.review', 'affiliations.review'],
        href: () => route('dashboard'),
        show: (permissions) => permissions.includes('checks.perform'),
    },
    {
        label: 'Politisi',
        match: ['admin.politicians.*'],
        href: () => route('admin.politicians.index'),
        show: (permissions) => hasAny(permissions, ['figures.add', 'figures.edit']),
    },
    {
        label: 'Partai',
        match: ['admin.parties.*'],
        href: () => route('admin.parties.index'),
        show: (permissions) => hasAny(permissions, ['parties.add', 'parties.edit']),
    },
    {
        label: 'Bisnis',
        match: ['admin.businesses.*'],
        href: () => route('admin.businesses.index'),
        show: (permissions) => hasAny(permissions, ['businesses.add', 'businesses.edit']),
    },
    {
        label: 'Halaman',
        match: ['admin.pages.*'],
        href: () => route('admin.pages.index'),
        show: (permissions) => hasAny(permissions, ['pages.add', 'pages.edit']),
    },
    {
        label: 'Audit',
        match: ['admin.audits.*'],
        href: () => route('admin.audits.index'),
        show: (permissions) => permissions.includes('audits.view'),
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
    {
        label: 'Pengaturan',
        match: ['admin.settings.*', 'admin.llm.*', 'admin.autovet.*', 'admin.newsradar.*'],
        href: () => route('admin.settings.web'),
        show: (permissions) => permissions.includes('settings.manage'),
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

    if (visible.length === 0) {
        return null;
    }

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
