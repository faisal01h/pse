import { Link } from '@inertiajs/inertia-react';

const items = [
    { label: 'Web', route: 'admin.settings.web' },
    { label: 'LLM', route: 'admin.settings.llm' },
    { label: 'AutoVet', route: 'admin.settings.autovet' },
    { label: 'NewsRadar', route: 'admin.settings.newsradar' },
    { label: 'REALPOLITIK', route: 'admin.settings.realpolitik' },
];

export default function SettingsNav() {
    return (
        <nav aria-label="Submenu pengaturan" className="mt-6 border-b-2 border-ink">
            <ul className="flex gap-1 overflow-x-auto">
                {items.map((item) => {
                    const current = typeof route === 'function' && route().current(item.route);
                    const href = route(item.route);

                    return (
                        <li key={item.route}>
                            <Link
                                href={href}
                                aria-current={current ? 'page' : undefined}
                                className={`inline-block whitespace-nowrap border-b-4 px-3 py-2 font-bold ${
                                    current
                                        ? 'border-brand text-ink'
                                        : 'border-transparent text-muted hover:text-ink'
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
