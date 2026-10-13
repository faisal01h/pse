import { Link } from '@inertiajs/inertia-react';

export default function PageLinks({ path, meta = {}, query = {} }) {
    if (!meta.last || meta.last <= 1) {
        return null;
    }

    const href = (page) => {
        const params = new URLSearchParams();

        Object.entries(query).forEach(([key, value]) => {
            if (value !== undefined && value !== null && String(value) !== '') {
                params.set(key, String(value));
            }
        });

        params.set('page', String(page));

        return `${path}?${params.toString()}`;
    };

    return (
        <nav className="mt-6 flex flex-wrap items-center gap-4" aria-label="Halaman">
            {meta.prev ? <Link href={href(meta.prev)}>Sebelumnya</Link> : <span className="text-muted">Sebelumnya</span>}
            <span>Halaman {meta.current} dari {meta.last}</span>
            {meta.next ? <Link href={href(meta.next)}>Berikutnya</Link> : <span className="text-muted">Berikutnya</span>}
        </nav>
    );
}
