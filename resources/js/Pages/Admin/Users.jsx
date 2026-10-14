import Authenticated from '@/Layouts/Authenticated';
import { Head, Link } from '@inertiajs/inertia-react';

export default function Users({ auth, users }) {
    return (
        <Authenticated auth={auth} header={<h1 className="text-4xl font-bold leading-tight">Pengguna</h1>}>
            <Head title="Pengguna" />
            <div className="mt-4 overflow-x-auto">
                {users.length > 0 ? (
                    <table className="admin-table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Peran</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.map((user) => (
                                <tr key={user.id}>
                                    <td>
                                        <Link href={route('admin.users.edit', user.id)} className="row-link">
                                            {user.name}
                                        </Link>
                                    </td>
                                    <td>{user.email}</td>
                                    <td>{user.is_active ? 'Aktif' : 'Nonaktif'}</td>
                                    <td>{user.roles.length > 0 ? user.roles.join(', ') : '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                ) : <p>Belum ada pengguna.</p>}
            </div>
        </Authenticated>
    );
}
