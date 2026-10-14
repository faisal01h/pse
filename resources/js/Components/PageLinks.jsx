import { Link } from '@inertiajs/inertia-react';

export default function PageLinks({
    path,
    meta = {},
    query = {},
    labels = {
        prev: 'Sebelumnya',
        next: 'Berikutnya',
        page: (current, last) => `Halaman ${current} dari ${last}`,
        aria: 'Halaman',
    },
}) {
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
        <nav className="mt-6 flex flex-wrap items-center gap-4" aria-label={labels.aria}>
            {meta.prev ? <Link href={href(meta.prev)}>{labels.prev}</Link> : <span className="text-muted">{labels.prev}</span>}
            <span>{labels.page(meta.current, meta.last)}</span>
            {meta.next ? <Link href={href(meta.next)}>{labels.next}</Link> : <span className="text-muted">{labels.next}</span>}
        </nav>
    );
}
