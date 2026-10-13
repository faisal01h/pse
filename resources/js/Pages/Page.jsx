import Public from '@/Layouts/Public';
import { Head } from '@inertiajs/inertia-react';

export default function PageView({ auth, page }) {
    return (
        <Public auth={auth}>
            <Head title={page.title} />
            <article className="max-w-[960px] mx-auto px-4 pt-8">
                <h1 className="text-4xl font-bold leading-tight">{page.title}</h1>
                <div className="page-body mt-6 max-w-3xl text-lg" dangerouslySetInnerHTML={{ __html: page.html }} />
            </article>
        </Public>
    );
}
