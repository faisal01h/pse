import { useEffect, useState } from 'react';
import { Link } from '@inertiajs/inertia-react';
import AdminNav from '@/Components/AdminNav';
import CommandSearch from '@/Components/CommandSearch';
import Toast from '@/Components/Toast';

function MenuLink({ href, method, children }) {
    const className = 'text-brand underline font-bold text-lg';

    if (method) {
        return (
            <Link href={href} method={method} as="button" className={`${className} bg-transparent border-0 p-0 text-left`}>
                {children}
            </Link>
        );
    }

    return (
        <Link href={href} className={className}>
            {children}
        </Link>
    );
}

export default function Public({ children, auth }) {
    const [menuOpen, setMenuOpen] = useState(false);

    useEffect(() => {
        const close = () => setMenuOpen(false);
        document.addEventListener('inertia:start', close);
        return () => document.removeEventListener('inertia:start', close);
    }, []);

    const links = [
        { href: '/', label: 'Beranda' },
        { href: '/politicians', label: 'Politisi' },
        { href: '/parties', label: 'Partai' },
        { href: '/reports', label: 'Buat pengajuan' },
        { href: '/reports/track', label: 'Lacak pengajuan' },
        { href: '/dispute', label: 'Dispute' },
    ];

    if (auth?.user) {
        links.push({ href: route('logout'), label: 'Keluar', method: 'post' });
    } else {
        links.push({ href: route('login'), label: 'Masuk' });
    }

    return (
        <div className="min-h-screen flex flex-col bg-white text-ink">
            <header>
                <div className="h-2 bg-brand" />
                <div className="bg-ink text-white">
                    <div className="max-w-[960px] mx-auto px-4 flex items-stretch justify-between">
                        <Link href="/" className="text-white text-2xl font-bold no-underline hover:underline self-center py-3">
                            PolitiSEE
                        </Link>
                        <div className="flex items-stretch">
                            <CommandSearch />
                            <button
                                type="button"
                                className={`inline-flex items-center justify-center gap-2 px-4 font-bold ${menuOpen ? 'bg-white text-ink' : 'bg-transparent text-white'}`}
                                aria-expanded={menuOpen}
                                aria-controls="navigation"
                                onClick={() => setMenuOpen((open) => !open)}
                            >
                                <svg
                                    aria-hidden="true"
                                    viewBox="0 0 16 10"
                                    className={`h-2.5 w-4 ${menuOpen ? 'rotate-180' : ''}`}
                                    fill="currentColor"
                                >
                                    <path d="M1 1.5 8 8.5 15 1.5" fill="none" stroke="currentColor" strokeWidth="2" />
                                </svg>
                                Menu
                            </button>
                        </div>
                    </div>
                </div>
                {auth?.user ? <AdminNav /> : null}
                {menuOpen ? (
                    <div className="bg-canvas border-b-2 border-ink">
                        <nav id="navigation" aria-label="Menu" className="max-w-[960px] mx-auto px-4 py-6">
                            <ul className="grid gap-y-3 sm:grid-cols-2 sm:gap-x-8">
                                {links.map((item) => (
                                    <li key={item.label}>
                                        <MenuLink href={item.href} method={item.method}>{item.label}</MenuLink>
                                    </li>
                                ))}
                            </ul>
                        </nav>
                    </div>
                ) : null}
            </header>

            <main className="flex-1">{children}</main>

            <Toast />

            <footer className="mt-12 border-t-[10px] border-brand bg-canvas text-ink">
                <div className="max-w-[960px] mx-auto px-4 pt-8 pb-6">
                    <h2 className="sr-only">Tautan pendukung</h2>
                    <nav aria-label="Kaki halaman">
                        <ul className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:gap-x-6 sm:gap-y-3">
                            <li>
                                <Link href={route('pages.show', 'legal')} className="text-ink underline">Legal</Link>
                            </li>
                            <li>
                                <Link href={route('pages.show', 'terms')} className="text-ink underline">Ketentuan Layanan</Link>
                            </li>
                            <li>
                                <Link href={route('pages.show', 'privacy')} className="text-ink underline">Kebijakan Privasi</Link>
                            </li>
                        </ul>
                    </nav>
                    <hr className="my-8 border-ink/30" />
                    <div className="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
                        <div className="max-w-xl">
                            <p className="text-base">
                                Arsip data profil tokoh publik, pejabat, dan politisi.
                            </p>
                            <p className="mt-4 text-base">
                                Semua konten berada di bawah ketentuan situs ini, kecuali dinyatakan lain pada halaman yang bersangkutan.
                            </p>
                        </div>
                        <p className="inline-block self-start bg-ink px-2 py-1 font-bold text-white md:self-end">
                            © PolitiSEE
                        </p>
                    </div>
                </div>
            </footer>
        </div>
    );
}
