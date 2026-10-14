import Authenticated from '@/Layouts/Authenticated';
import ValidationErrors from '@/Components/ValidationErrors';
import { Head, Link, useForm } from '@inertiajs/inertia-react';

export default function UserAccessForm({
    auth,
    user,
    roleOptions = [],
    permissionOptions = [],
}) {
    const { data, setData, post, processing, errors } = useForm({
        is_active: Boolean(user.is_active),
        roles: user.roles ?? [],
        permissions: user.permissions ?? [],
    });

    const toggle = (field, value) => {
        setData(field, data[field].includes(value)
            ? data[field].filter((item) => item !== value)
            : [...data[field], value]);
    };

    const submit = (event) => {
        event.preventDefault();
        post(route('admin.users.update', user.id));
    };

    const canManage = auth?.permissions?.includes('users.manage');

    return (
        <Authenticated
            auth={auth}
            header={<h1 className="text-4xl font-bold leading-tight">Akses pengguna</h1>}
        >
            <Head title={`Akses ${user.name}`} />
            <form onSubmit={submit} className="max-w-xl">
                <ValidationErrors errors={errors} />
                <div className="grid gap-2">
                    <p><span className="font-bold">Nama:</span> {user.name}</p>
                    <p><span className="font-bold">Email:</span> {user.email}</p>
                </div>
                <label className="mt-6 inline-flex items-center gap-2">
                    <input
                        type="checkbox"
                        checked={data.is_active}
                        disabled={!canManage}
                        onChange={(event) => setData('is_active', event.target.checked)}
                    />
                    <span className="font-bold">Aktif</span>
                </label>
                <fieldset className="mt-6">
                    <legend className="font-bold mb-2">Peran</legend>
                    <ul className="flex flex-col gap-2">
                        {roleOptions.map((name) => (
                            <li key={name}>
                                <label className="inline-flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        checked={data.roles.includes(name)}
                                        disabled={!canManage}
                                        onChange={() => toggle('roles', name)}
                                    />
                                    <span>{name}</span>
                                </label>
                            </li>
                        ))}
                    </ul>
                </fieldset>
                <fieldset className="mt-6">
                    <legend className="font-bold mb-2">Izin langsung</legend>
                    <p className="mb-2 text-muted">Izin tambahan di luar peran.</p>
                    <ul className="flex flex-col gap-2">
                        {permissionOptions.map((option) => (
                            <li key={option.key}>
                                <label className="inline-flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        checked={data.permissions.includes(option.key)}
                                        disabled={!canManage}
                                        onChange={() => toggle('permissions', option.key)}
                                    />
                                    <span>{option.label}</span>
                                </label>
                            </li>
                        ))}
                    </ul>
                </fieldset>
                <div className="mt-8 flex flex-wrap gap-3">
                    {canManage ? (
                        <button type="submit" className="gov-button" disabled={processing}>Simpan</button>
                    ) : null}
                    <Link href={route('admin.users.index')}>Kembali</Link>
                </div>
            </form>
        </Authenticated>
    );
}
