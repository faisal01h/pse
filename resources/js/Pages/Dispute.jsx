import { useEffect, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/inertia-react';
import Public from '@/Layouts/Public';
import Label from '@/Components/Label';
import Input from '@/Components/Input';
import ValidationErrors from '@/Components/ValidationErrors';

const categoryLabel = {
    statement: 'Pernyataan publik',
    allegation: 'Dugaan pelanggaran',
    conviction: 'Vonis pengadilan',
};

export default function Dispute({ auth }) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [selected, setSelected] = useState(null);
    const [captchaKey, setCaptchaKey] = useState(() => Date.now());
    const { data, setData, post, processing, errors } = useForm({
        public_record_id: '',
        reason: '',
        source_url: '',
        source_type: '',
        captcha: '',
    });

    useEffect(() => {
        if (query.trim().length < 2) {
            setResults([]);
            return undefined;
        }

        const timer = window.setTimeout(async () => {
            const response = await fetch(`${route('dispute.records')}?q=${encodeURIComponent(query.trim())}`, {
                headers: { Accept: 'application/json' },
            });
            if (response.ok) {
                setResults(await response.json());
            }
        }, 250);

        return () => window.clearTimeout(timer);
    }, [query]);

    useEffect(() => {
        if (errors.captcha) {
            setCaptchaKey(Date.now());
            setData('captcha', '');
        }
    }, [errors.captcha]);

    const choose = (record) => {
        setSelected(record);
        setData('public_record_id', String(record.id));
        setQuery('');
        setResults([]);
    };

    const submit = (event) => {
        event.preventDefault();
        post(route('dispute.store'));
    };

    return (
        <Public auth={auth}>
            <Head title="Dispute" />
            <div className="max-w-[960px] mx-auto px-4 py-8">
                <h1 className="text-4xl font-bold leading-tight">Dispute</h1>
                <p className="mt-3 max-w-2xl text-xl">Sanggah arsip yang sudah terbit jika isinya tidak sesuai sumber.</p>
                <form onSubmit={submit} className="mt-8 flex max-w-2xl flex-col gap-6">
                    <ValidationErrors errors={errors} />
                    <div>
                        <Label forInput="record-search" value="Arsip yang disanggah" />
                        {selected ? (
                            <div className="mt-2 border-2 border-ink bg-white p-4">
                                <p className="font-bold">{selected.politician}</p>
                                <p className="text-muted">{categoryLabel[selected.category] ?? selected.category}</p>
                                <p className="mt-2">{selected.excerpt}</p>
                                <button
                                    type="button"
                                    className="mt-3 underline"
                                    onClick={() => {
                                        setSelected(null);
                                        setData('public_record_id', '');
                                    }}
                                >
                                    Ganti arsip
                                </button>
                            </div>
                        ) : (
                            <div className="relative mt-2">
                                <Input
                                    type="search"
                                    name="record-search"
                                    value={query}
                                    placeholder="Ketik nama atau cuplikan arsip"
                                    handleChange={(event) => setQuery(event.target.value)}
                                />
                                {results.length > 0 ? (
                                    <ul className="absolute z-10 mt-1 w-full border-2 border-ink bg-white">
                                        {results.map((record) => (
                                            <li key={record.id}>
                                                <button type="button" className="block w-full px-3 py-2 text-left hover:bg-canvas" onClick={() => choose(record)}>
                                                    <span className="font-bold">{record.politician}</span>
                                                    <span className="mt-1 block text-muted">{record.excerpt}</span>
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                ) : null}
                            </div>
                        )}
                    </div>
                    <div>
                        <Label forInput="reason" value="Apa yang tidak sesuai?" />
                        <textarea
                            id="reason"
                            value={data.reason}
                            onChange={(event) => setData('reason', event.target.value)}
                            className="gov-input mt-1 min-h-[8rem]"
                        />
                    </div>
                    <div>
                        <Label forInput="source_type" value="Jenis sumber pembanding" />
                        <Input
                            type="text"
                            name="source_type"
                            value={data.source_type}
                            placeholder="cth: berita / putusan / transkrip"
                            handleChange={(event) => setData('source_type', event.target.value)}
                        />
                    </div>
                    <div>
                        <Label forInput="source_url" value="Tautan sumber pembanding" />
                        <Input
                            type="url"
                            name="source_url"
                            value={data.source_url}
                            placeholder="https://"
                            handleChange={(event) => setData('source_url', event.target.value)}
                        />
                    </div>
                    <div>
                        <Label forInput="captcha" value="Kode pada gambar" />
                        <img src={`${route('captcha')}?k=${captchaKey}`} alt="Kode captcha" width="180" height="56" className="mt-1 border-2 border-ink bg-white" />
                        <button type="button" className="mt-2 underline" onClick={() => setCaptchaKey(Date.now())}>Minta kode baru</button>
                        <Input
                            type="text"
                            name="captcha"
                            value={data.captcha}
                            placeholder="Ketik 5 huruf pada gambar"
                            className="mt-3 uppercase"
                            handleChange={(event) => setData('captcha', event.target.value)}
                        />
                    </div>
                    <button className="gov-button w-fit" disabled={processing}>Kirim sanggahan</button>
                    <p className="text-muted">
                        Belum menemukan arsipnya? <Link href="/">Lihat catatan terbaru</Link>.
                    </p>
                </form>
            </div>
        </Public>
    );
}
