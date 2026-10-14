import Authenticated from '@/Layouts/Authenticated';
import { Head, Link } from '@inertiajs/inertia-react';

export default function Roles({ auth, roles, permissionLabels = {} }) {
    return (
        <Authenticated auth={auth} header={<h1 className="text-4xl font-bold leading-tight">Peran</h1>}>
            <Head title="Peran" />
            <div className="flex justify-end">
                {auth?.permissions?.includes('roles.manage') ? (
                    <Link href={route('admin.roles.create')} className="gov-button">Tambah peran</Link>
                ) : null}
            </div>
            <div className="mt-4 overflow-x-auto">
                {roles.length > 0 ? (
                    <table className="admin-table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Pengguna</th>
                                <th>Izin</th>
                            </tr>
                        </thead>
                        <tbody>
                            {roles.map((role) => (
                                <tr key={role.id}>
                                    <td>
                                        <Link href={route('admin.roles.edit', role.id)} className="row-link">
                                            {role.name}
                                            {role.protected ? ' (dilindungi)' : ''}
                                        </Link>
                                    </td>
                                    <td>{role.users_count}</td>
                                    <td>
                                        {role.permissions.length > 0
                                            ? role.permissions.map((key) => permissionLabels[key] ?? key).join(', ')
                                            : '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                ) : <p>Belum ada peran.</p>}
            </div>
        </Authenticated>
    );
}
