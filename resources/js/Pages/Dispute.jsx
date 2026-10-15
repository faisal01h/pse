import { useEffect, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/inertia-react';
import Public from '@/Layouts/Public';
import Label from '@/Components/Label';
import Input from '@/Components/Input';
import TurnstileField from '@/Components/TurnstileField';
import ValidationErrors from '@/Components/ValidationErrors';

const categoryLabel = {
    statement: 'Pernyataan publik',
    allegation: 'Dugaan pelanggaran',
    conviction: 'Vonis pengadilan',
};

const targets = [
    {
        id: 'record',
        title: 'Catatan publik',
        hint: 'Sanggah pernyataan, dugaan, atau vonis yang sudah terbit.',
    },
    {
        id: 'affiliation',
        title: 'Afiliasi bisnis',
        hint: 'Sanggah kepemilikan atau endorsemen tokoh terhadap suatu bisnis.',
    },
];

export default function Dispute({ auth, turnstile }) {
    const sitekey = turnstile?.sitekey ?? '';
    const turnstileEnabled = turnstile?.enabled !== false;
    const [target, setTarget] = useState(null);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [selected, setSelected] = useState(null);
    const [turnstileReset, setTurnstileReset] = useState(0);
    const { data, setData, post, processing, errors } = useForm({
        target: '',
        public_record_id: '',
        business_affiliation_id: '',
        reason: '',
        source_url: '',
        source_type: '',
        'cf-turnstile-response': '',
    });

    useEffect(() => {
        if (!target || query.trim().length < 2) {
            setResults([]);
            return undefined;
        }

        const url = target === 'affiliation'
            ? `${route('dispute.affiliations')}?q=${encodeURIComponent(query.trim())}`
            : `${route('dispute.records')}?q=${encodeURIComponent(query.trim())}`;

        const timer = window.setTimeout(async () => {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (response.ok) {
                setResults(await response.json());
            }
        }, 250);

        return () => window.clearTimeout(timer);
    }, [query, target]);

    useEffect(() => {
        if (errors['cf-turnstile-response']) {
            setTurnstileReset((value) => value + 1);
            setData('cf-turnstile-response', '');
        }
    }, [errors['cf-turnstile-response']]);

    const chooseTarget = (next) => {
        setTarget(next);
        setSelected(null);
        setQuery('');
        setResults([]);
        setData({
            ...data,
            target: next,
            public_record_id: '',
            business_affiliation_id: '',
        });
    };

    const choose = (item) => {
        setSelected(item);
        if (target === 'affiliation') {
            setData('business_affiliation_id', String(item.id));
            setData('public_record_id', '');
        } else {
            setData('public_record_id', String(item.id));
            setData('business_affiliation_id', '');
        }
        setQuery('');
        setResults([]);
    };

    const submit = (event) => {
        event.preventDefault();
        post(route('dispute.store'), {
            onFinish: () => {
                setTurnstileReset((value) => value + 1);
                setData('cf-turnstile-response', '');
            },
        });
    };

    if (!target) {
        return (
            <Public auth={auth}>
                <Head title="Dispute" />
                <div className="max-w-[960px] mx-auto px-4 py-8">
                    <p className="mb-6"><Link href="/">Beranda</Link></p>
                    <h1 className="text-4xl font-bold leading-tight">Jenis sanggahan</h1>
                    <p className="mt-3 text-lg">Pilih apakah Anda menyanggah catatan publik atau afiliasi bisnis.</p>
                    <div className="mt-8 flex max-w-xl flex-col gap-3">
                        {targets.map((item) => (
                            <button
                                key={item.id}
                                type="button"
                                onClick={() => chooseTarget(item.id)}
                                className="border-2 border-ink bg-white px-4 py-4 text-left hover:bg-canvas"
                            >
                                <span className="block text-2xl font-bold">{item.title}</span>
                                <span className="mt-1 block">{item.hint}</span>
                            </button>
                        ))}
                    </div>
                </div>
            </Public>
        );
    }

    return (
        <Public auth={auth}>
            <Head title="Dispute" />
            <div className="max-w-[960px] mx-auto px-4 py-8">
                <button type="button" className="mb-4 underline" onClick={() => { setTarget(null); setSelected(null); }}>
                    Ubah jenis sanggahan
                </button>
                <h1 className="text-4xl font-bold leading-tight">Dispute</h1>
                <p className="mt-3 max-w-2xl text-xl">
                    {target === 'affiliation'
                        ? 'Sanggah afiliasi bisnis yang sudah terbit jika isinya tidak sesuai sumber.'
                        : 'Sanggah arsip yang sudah terbit jika isinya tidak sesuai sumber.'}
                </p>
                <form onSubmit={submit} className="mt-8 flex max-w-2xl flex-col gap-6">
                    <ValidationErrors errors={errors} />
                    <input type="hidden" name="target" value={data.target} />
                    <div>
                        <Label forInput="record-search" value={target === 'affiliation' ? 'Afiliasi yang disanggah' : 'Arsip yang disanggah'} />
                        {selected ? (
                            <div className="mt-2 border-2 border-ink bg-white p-4">
                                <p className="font-bold">{selected.politician}</p>
                                {selected.business ? <p className="font-bold">{selected.business}</p> : null}
                                <p className="text-muted">{categoryLabel[selected.category] ?? selected.category}</p>
                                <p className="mt-2">{selected.excerpt}</p>
                                {target === 'affiliation' && selected.threshold ? (
                                    <p className="mt-2 text-muted">
                                        Ambang penarikan otomatis: {selected.pending_count ?? 0}/{selected.threshold} sanggahan pending.
                                    </p>
                                ) : null}
                                <button
                                    type="button"
                                    className="mt-3 underline"
                                    onClick={() => {
                                        setSelected(null);
                                        setData('public_record_id', '');
                                        setData('business_affiliation_id', '');
                                    }}
                                >
                                    Ganti pilihan
                                </button>
                            </div>
                        ) : (
                            <div className="relative mt-2">
                                <Input
                                    type="search"
                                    name="record-search"
                                    value={query}
                                    placeholder={target === 'affiliation' ? 'Ketik nama tokoh atau bisnis' : 'Ketik nama atau cuplikan arsip'}
                                    handleChange={(event) => setQuery(event.target.value)}
                                />
                                {results.length > 0 ? (
                                    <ul className="absolute z-10 mt-1 w-full border-2 border-ink bg-white">
                                        {results.map((item) => (
                                            <li key={item.id}>
                                                <button type="button" className="block w-full px-3 py-2 text-left hover:bg-canvas" onClick={() => choose(item)}>
                                                    <span className="font-bold">{item.politician}</span>
                                                    {item.business ? <span className="mt-1 block font-bold">{item.business}</span> : null}
                                                    <span className="mt-1 block text-muted">{item.excerpt}</span>
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
                    {turnstileEnabled ? (
                        <div>
                            <p className="font-bold mb-2">Verifikasi keamanan</p>
                            <TurnstileField
                                sitekey={sitekey}
                                action="dispute"
                                resetKey={turnstileReset}
                                onToken={(token) => setData('cf-turnstile-response', token)}
                            />
                        </div>
                    ) : null}
                    <button
                        className="gov-button w-fit"
                        disabled={processing || (turnstileEnabled && !data['cf-turnstile-response'])}
                    >
                        Kirim sanggahan
                    </button>
                    <p className="text-muted">
                        Belum menemukan arsipnya? <Link href="/">Lihat catatan terbaru</Link>
                        {' · '}
                        <Link href="/businesses">Lihat bisnis</Link>.
                    </p>
                </form>
            </div>
        </Public>
    );
}
