import { Head, Link } from '@inertiajs/inertia-react';
import Public from '@/Layouts/Public';
import Figure from '@/Components/Figure';

export default function Politician({ politician, auth }) {
    return (
        <Public auth={auth}>
            <Head title={politician.name} />
            <div className="max-w-[960px] mx-auto px-4 py-8">
                <p className="mb-4"><Link href="/">Beranda</Link></p>
                <h1 className="text-4xl font-bold leading-tight mb-6">Profil</h1>
                <Figure politician={politician} />
            </div>
        </Public>
    );
}
