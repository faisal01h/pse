import { Head, Link } from '@inertiajs/inertia-react';
import Public from '@/Layouts/Public';

export default function NotFound({ auth }) {
    return (
        <Public auth={auth}>
            <Head title="Halaman tidak ditemukan" />
            <div className="max-w-[960px] mx-auto px-4 py-16">
                <p className="text-6xl font-bold leading-none">404</p>
                <h1 className="mt-4 text-4xl font-bold leading-tight">Halaman tidak ditemukan</h1>
                <p className="mt-3 max-w-2xl text-xl">Alamat ini tidak ada di PolitiSEE.</p>
                <div className="mt-8 flex flex-wrap items-center gap-4">
                    <Link href="/" className="gov-button">Kembali ke beranda</Link>
                    <Link href={route('dispute.create')}>Dispute</Link>
                </div>
            </div>
        </Public>
    );
}
