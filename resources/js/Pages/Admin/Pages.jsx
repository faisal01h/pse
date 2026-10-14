import Authenticated from '@/Layouts/Authenticated';
import { Head, Link } from '@inertiajs/inertia-react';

export default function Pages({ auth, pages }) {
    return (
        <Authenticated auth={auth} header={<h1 className="text-4xl font-bold leading-tight">Halaman</h1>}>
            <Head title="Halaman" />
            {auth?.permissions?.includes('pages.add') ? (
                <Link href={route('admin.pages.create')} className="gov-button">Tambah halaman</Link>
            ) : null}
            <div className="mt-4 overflow-x-auto">
                <table className="admin-table">
                    <thead>
                        <tr>
                            <th>Judul</th>
                            <th>Alamat</th>
                            <th>Mode</th>
                        </tr>
                    </thead>
                    <tbody>
                        {pages.map((page) => (
                            <tr key={page.id}>
                                <td>
                                    <Link href={route('admin.pages.edit', page.id)} className="row-link">
                                        {page.title}
                                    </Link>
                                </td>
                                <td>/pages/{page.slug}</td>
                                <td>
                                    {page.format === 'richtext' ? 'Rich Text' : 'Markdown'}
                                    {page.pinned ? ' · kaki halaman' : ''}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </Authenticated>
    );
}
