import { useRef } from 'react';
import Authenticated from '@/Layouts/Authenticated';
import PageEditor from '@/Components/PageEditor';
import ValidationErrors from '@/Components/ValidationErrors';
import { htmlFromMarkdown, markdownFromHTML } from '@/markdown';
import { Head, Link, useForm } from '@inertiajs/inertia-react';

function slugFromTitle(title) {
    return title
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 80);
}

export default function PageForm({ auth, page }) {
    const slugTouched = useRef(Boolean(page?.slug));
    const { data, setData, post, processing, errors } = useForm({
        title: page?.title ?? '',
        slug: page?.slug ?? '',
        format: page?.format ?? 'richtext',
        body: page?.body ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        post(page ? route('admin.pages.update', page.id) : route('admin.pages.store'));
    };

    const useFormat = (format) => {
        if (format === data.format) {
            return;
        }

        try {
            const body = format === 'markdown'
                ? markdownFromHTML(data.body)
                : htmlFromMarkdown(data.body);
            setData({ ...data, format, body });
        } catch (error) {
            setData('format', format);
        }
    };

    return (
        <Authenticated auth={auth} header={<h1 className="text-4xl font-bold leading-tight">{page ? 'Ubah halaman' : 'Halaman baru'}</h1>}>
            <Head title={page ? page.title : 'Halaman baru'} />
            <form onSubmit={submit} className="max-w-3xl">
                <ValidationErrors errors={errors} />
                <div>
                    <label htmlFor="title" className="block font-bold mb-1">Judul</label>
                    <input
                        id="title"
                        value={data.title}
                        onChange={(event) => {
                            const title = event.target.value;
                            const next = { ...data, title };

                            if (!page?.pinned && !slugTouched.current) {
                                next.slug = slugFromTitle(title);
                            }

                            setData(next);
                        }}
                        className="gov-input"
                    />
                </div>
                <div className="mt-4">
                    <label htmlFor="slug" className="block font-bold mb-1">Alamat</label>
                    <input
                        id="slug"
                        value={data.slug}
                        disabled={Boolean(page?.pinned)}
                        onChange={(event) => {
                            slugTouched.current = true;
                            setData('slug', event.target.value);
                        }}
                        className="gov-input disabled:bg-canvas"
                    />
                    <p className="mt-1 text-muted">/pages/{data.slug || 'alamat'}</p>
                </div>
                <fieldset className="mt-4">
                    <legend className="font-bold mb-2">Mode</legend>
                    <div className="flex gap-2">
                        <button
                            type="button"
                            aria-pressed={data.format === 'richtext'}
                            className={`border-2 border-ink px-3 py-1 font-bold ${data.format === 'richtext' ? 'bg-ink text-white' : 'bg-white'}`}
                            onClick={() => useFormat('richtext')}
                        >
                            Rich Text
                        </button>
                        <button
                            type="button"
                            aria-pressed={data.format === 'markdown'}
                            className={`border-2 border-ink px-3 py-1 font-bold ${data.format === 'markdown' ? 'bg-ink text-white' : 'bg-white'}`}
                            onClick={() => useFormat('markdown')}
                        >
                            Markdown
                        </button>
                    </div>
                </fieldset>
                <div className="mt-4">
                    <label htmlFor="body" className="block font-bold mb-1">Isi</label>
                    {data.format === 'richtext' ? (
                        <PageEditor html={data.body} onChange={(html) => setData('body', html)} />
                    ) : (
                        <textarea
                            id="body"
                            value={data.body}
                            onChange={(event) => setData('body', event.target.value)}
                            rows={16}
                            className="gov-input font-mono text-base"
                        />
                    )}
                </div>
                <button className="gov-button mt-6" disabled={processing}>Simpan</button>
            </form>
            {page && !page.pinned ? (
                <Link
                    href={route('admin.pages.destroy', page.id)}
                    method="delete"
                    as="button"
                    className="mt-8 underline"
                    onClick={(event) => {
                        if (!window.confirm('Hapus halaman ini?')) {
                            event.preventDefault();
                        }
                    }}
                >
                    Hapus halaman
                </Link>
            ) : null}
        </Authenticated>
    );
}
