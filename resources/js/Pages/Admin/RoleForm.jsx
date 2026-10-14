import Authenticated from '@/Layouts/Authenticated';
import ValidationErrors from '@/Components/ValidationErrors';
import { Head, Link, useForm } from '@inertiajs/inertia-react';

export default function RoleForm({ auth, role, permissionOptions = [] }) {
    const protectedRole = Boolean(role?.protected);
    const { data, setData, post, delete: destroy, processing, errors } = useForm({
        name: role?.name ?? '',
        permissions: role?.permissions ?? [],
    });

    const togglePermission = (key) => {
        setData('permissions', data.permissions.includes(key)
            ? data.permissions.filter((item) => item !== key)
            : [...data.permissions, key]);
    };

    const submit = (event) => {
        event.preventDefault();
        post(role ? route('admin.roles.update', role.id) : route('admin.roles.store'));
    };

    const remove = () => {
        if (!role || protectedRole) {
            return;
        }
        if (!window.confirm(`Hapus peran ${role.name}?`)) {
            return;
        }
        destroy(route('admin.roles.destroy', role.id));
    };

    return (
        <Authenticated
            auth={auth}
            header={<h1 className="text-4xl font-bold leading-tight">{role ? 'Ubah peran' : 'Peran baru'}</h1>}
        >
            <Head title={role ? role.name : 'Peran baru'} />
            <form onSubmit={submit} className="max-w-xl">
                <ValidationErrors errors={errors} />
                <div>
                    <label htmlFor="role-name" className="block font-bold mb-1">Nama</label>
                    <input
                        id="role-name"
                        value={data.name}
                        disabled={protectedRole}
                        onChange={(event) => setData('name', event.target.value)}
                        className="gov-input disabled:bg-canvas"
                    />
                    {protectedRole ? (
                        <p className="mt-1 text-muted">Nama peran dilindungi tidak dapat diubah.</p>
                    ) : null}
                </div>
                <fieldset className="mt-6">
                    <legend className="font-bold mb-2">Izin</legend>
                    <ul className="flex flex-col gap-2">
                        {permissionOptions.map((option) => (
                            <li key={option.key}>
                                <label className="inline-flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        checked={data.permissions.includes(option.key)}
                                        onChange={() => togglePermission(option.key)}
                                    />
                                    <span>{option.label}</span>
                                </label>
                            </li>
                        ))}
                    </ul>
                </fieldset>
                <div className="mt-8 flex flex-wrap gap-3">
                    <button type="submit" className="gov-button" disabled={processing || !auth?.permissions?.includes('roles.manage')}>
                        Simpan
                    </button>
                    <Link href={route('admin.roles.index')}>Batal</Link>
                    {role && !protectedRole && auth?.permissions?.includes('roles.manage') ? (
                        <button type="button" className="underline" onClick={remove} disabled={processing}>
                            Hapus peran
                        </button>
                    ) : null}
                </div>
            </form>
        </Authenticated>
    );
}
