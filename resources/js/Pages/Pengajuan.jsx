import { Head, Link, useForm } from '@inertiajs/inertia-react';
import { useEffect, useState } from 'react';
import Public from '@/Layouts/Public';
import Label from '@/Components/Label';
import Input from '@/Components/Input';
import PartyField from '@/Components/PartyField';
import BusinessField from '@/Components/BusinessField';
import NameField from '@/Components/NameField';
import TurnstileField from '@/Components/TurnstileField';
import ValidationErrors from '@/Components/ValidationErrors';

const recordSteps = [
    { title: 'Siapa yang dicatat?', hint: 'Ketik namanya. Jika profilnya sudah ada, pilih dari daftar.' },
    { title: 'Di mana ia bertugas?', hint: 'Jabatan, instansi, dan partai. Bagian ini sudah terisi jika Anda memilih profil yang tersimpan.' },
    { title: 'Catatan ini jenisnya apa?', hint: 'Pilih satu. Dugaan dan vonis tidak boleh tertukar.' },
    { title: 'Apa yang tertulis di sumber?', hint: 'Salin kutipan untuk pernyataan. Ringkas perkara untuk dugaan atau vonis.' },
    { title: 'Dari mana sumbernya?', hint: 'Tautan wajib. Berkas foto, video, atau PDF boleh ditambahkan.' },
    { title: 'Cek sekali lagi, lalu kirim.', hint: 'Verifikasi keamanan menjaga formulir dari kiriman otomatis.' },
];

const affiliationSteps = [
    { title: 'Siapa tokohnya?', hint: 'Ketik namanya. Jika profilnya sudah ada, pilih dari daftar.' },
    { title: 'Di mana ia bertugas?', hint: 'Jabatan, instansi, dan partai. Bagian ini sudah terisi jika Anda memilih profil yang tersimpan.' },
    { title: 'Bisnis apa yang terkait?', hint: 'Pilih dari daftar atau ketik nama bisnis baru.' },
    { title: 'Jenis afiliasi apa?', hint: 'Kepemilikan mencakup saham penuh, sebagian, atau jumlah tidak diketahui. Endorsemen adalah dukungan publik terhadap merek atau usaha.' },
    { title: 'Apa yang tertulis di sumber?', hint: 'Ringkas hubungan tokoh dengan bisnis menurut sumber.' },
    { title: 'Dari mana sumbernya?', hint: 'Tautan wajib. Berkas foto, video, atau PDF boleh ditambahkan.' },
    { title: 'Cek sekali lagi, lalu kirim.', hint: 'Verifikasi keamanan menjaga formulir dari kiriman otomatis.' },
];

const categories = [
    { id: 'statement', title: 'Pernyataan publik', hint: 'Kalimat yang ia ucapkan atau tulis, disalin persis.' },
    { id: 'allegation', title: 'Dugaan pelanggaran', hint: 'Tuduhan yang belum menjadi putusan pengadilan.' },
    { id: 'conviction', title: 'Vonis pengadilan', hint: 'Putusan yang sudah dijatuhkan, bukan sekadar tuduhan.' },
];

const affiliationTypes = [
    { id: 'ownership', title: 'Kepemilikan', hint: 'Tokoh memiliki bisnis ini, penuh atau sebagian. Jumlah boleh tidak diketahui.' },
    { id: 'endorsement', title: 'Endorsemen', hint: 'Tokoh mendukung atau mempromosikan bisnis ini di ruang publik.' },
];

const recordFieldStep = {
    name: 0,
    position: 1,
    department: 1,
    party_id: 1,
    party_name: 1,
    category: 2,
    quote: 3,
    summary: 3,
    occurred_on: 3,
    evtype: 4,
    link: 4,
    media: 4,
    'cf-turnstile-response': 5,
};

const affiliationFieldStep = {
    name: 0,
    position: 1,
    department: 1,
    party_id: 1,
    party_name: 1,
    business_id: 2,
    business_name: 2,
    type: 3,
    ownership_percent: 3,
    ownership_unknown: 3,
    summary: 4,
    occurred_on: 4,
    evtype: 5,
    link: 5,
    media: 5,
    'cf-turnstile-response': 6,
};

function categoryLabel(id) {
    return categories.find((item) => item.id === id)?.title ?? id;
}

function affiliationTypeLabel(id) {
    return affiliationTypes.find((item) => item.id === id)?.title ?? id;
}

export default function Pengajuan(props) {
    const parties = props.parties ?? [];
    const businesses = props.businesses ?? [];
    const sitekey = props.turnstile?.sitekey ?? '';
    const turnstileEnabled = props.turnstile?.enabled !== false;
    const [kind, setKind] = useState(null);
    const [step, setStep] = useState(0);
    const [stepError, setStepError] = useState('');
    const [turnstileReset, setTurnstileReset] = useState(0);
    const isAffiliation = kind === 'business_affiliation';
    const steps = isAffiliation ? affiliationSteps : recordSteps;
    const fieldStep = isAffiliation ? affiliationFieldStep : recordFieldStep;

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        position: '',
        department: '',
        party_id: '',
        party_name: '',
        business_id: '',
        business_name: '',
        category: 'statement',
        type: 'ownership',
        ownership_percent: '',
        ownership_unknown: false,
        summary: '',
        quote: '',
        evtype: '',
        link: '',
        occurred_on: '',
        'cf-turnstile-response': '',
        media: [],
    });
    const errorStamp = Object.keys(errors).sort().join('|');

    useEffect(() => {
        if (!errorStamp || !kind) {
            return;
        }

        const nextStep = Object.keys(errors).reduce((lowest, field) => {
            const index = fieldStep[field] ?? fieldStep[field.split('.')[0]];
            return index === undefined ? lowest : Math.min(lowest, index);
        }, steps.length - 1);
        setStep(nextStep);
        setTurnstileReset((value) => value + 1);
        setData('cf-turnstile-response', '');
    }, [errorStamp]);

    const onHandleChange = (event) => {
        setStepError('');
        setData(event.target.name, event.target.type === 'checkbox' ? event.target.checked : event.target.value);
    };

    const onPickPolitician = (politician) => {
        setData((current) => ({
            ...current,
            name: politician.name,
            position: politician.position ?? '',
            department: politician.institution ?? '',
            party_id: politician.party_id ? String(politician.party_id) : '',
            party_name: '',
        }));
        setStepError('');
    };

    const partyLabel = () => {
        if (data.party_id === 'other') {
            return data.party_name || 'Partai baru';
        }
        if (!data.party_id) {
            return 'Tidak terafiliasi';
        }
        return parties.find((party) => String(party.id) === String(data.party_id))?.name ?? 'Partai';
    };

    const businessLabel = () => {
        if (data.business_id === 'other') {
            return data.business_name || 'Bisnis baru';
        }
        return businesses.find((business) => String(business.id) === String(data.business_id))?.name ?? 'Bisnis';
    };

    const ownershipLabel = () => {
        if (data.type !== 'ownership') {
            return affiliationTypeLabel(data.type);
        }
        if (data.ownership_unknown || data.ownership_percent === '') {
            return 'Kepemilikan (jumlah tidak diketahui)';
        }
        if (Number(data.ownership_percent) >= 100) {
            return 'Kepemilikan penuh (100%)';
        }
        return `Kepemilikan ${data.ownership_percent}%`;
    };

    const validate = (index) => {
        if (index === 0 && data.name.trim() === '') {
            return 'Nama wajib diisi.';
        }
        if (index === 1 && data.position.trim() === '') {
            return 'Jabatan wajib diisi.';
        }
        if (index === 1 && data.department.trim() === '') {
            return 'Instansi wajib diisi.';
        }
        if (index === 1 && data.party_id === 'other' && data.party_name.trim() === '') {
            return 'Ketik nama partai jika memilih lainnya.';
        }

        if (isAffiliation) {
            if (index === 2 && data.business_id === '') {
                return 'Pilih bisnis, atau ketik nama baru.';
            }
            if (index === 2 && data.business_id === 'other' && data.business_name.trim() === '') {
                return 'Ketik nama bisnis jika memilih lainnya.';
            }
            if (index === 3 && data.type === 'ownership' && !data.ownership_unknown && data.ownership_percent !== '') {
                const value = Number(data.ownership_percent);
                if (Number.isNaN(value) || value < 1 || value > 100) {
                    return 'Persentase harus antara 1 dan 100, atau pilih jumlah tidak diketahui.';
                }
            }
            if (index === 4 && data.summary.trim() === '') {
                return 'Ringkasan afiliasi wajib diisi.';
            }
            if (index === 5 && data.evtype.trim() === '') {
                return 'Jenis sumber wajib diisi.';
            }
            if (index === 5 && !/^https?:\/\/\S+$/i.test(data.link.trim())) {
                return 'Tautan sumber harus diawali http:// atau https://.';
            }
            if (index === 6 && turnstileEnabled && !data['cf-turnstile-response']) {
                return 'Selesaikan verifikasi keamanan terlebih dahulu.';
            }
            return '';
        }

        if (index === 3 && data.category === 'statement' && data.quote.trim() === '') {
            return 'Kutipan wajib diisi untuk pernyataan publik.';
        }
        if (index === 3 && data.category !== 'statement' && data.summary.trim() === '') {
            return 'Ringkasan wajib diisi untuk dugaan atau vonis.';
        }
        if (index === 4 && data.evtype.trim() === '') {
            return 'Jenis sumber wajib diisi.';
        }
        if (index === 4 && !/^https?:\/\/\S+$/i.test(data.link.trim())) {
            return 'Tautan sumber harus diawali http:// atau https://.';
        }
        if (index === 5 && turnstileEnabled && !data['cf-turnstile-response']) {
            return 'Selesaikan verifikasi keamanan terlebih dahulu.';
        }
        return '';
    };

    const goNext = () => {
        const message = validate(step);
        setStepError(message);
        if (message) {
            return;
        }
        setStep((current) => Math.min(current + 1, steps.length - 1));
    };

    const chooseCategory = (id) => {
        setData('category', id);
        setStepError('');
        setStep(3);
    };

    const chooseAffiliationType = (id) => {
        setData((current) => ({
            ...current,
            type: id,
            ownership_percent: id === 'ownership' ? current.ownership_percent : '',
            ownership_unknown: id === 'ownership' ? current.ownership_unknown : false,
        }));
        setStepError('');
    };

    const chooseKind = (nextKind) => {
        setKind(nextKind);
        setStep(0);
        setStepError('');
    };

    const onSubmit = (event) => {
        event.preventDefault();
        if (step < steps.length - 1) {
            goNext();
            return;
        }
        const message = validate(step);
        setStepError(message);
        if (message) {
            return;
        }
        post(isAffiliation ? route('reports.affiliations.store') : route('newReport'), { forceFormData: true });
    };

    if (!kind) {
        return (
            <Public auth={props.auth}>
                <Head title="Pengajuan" />
                <div className="max-w-[960px] mx-auto px-4 py-8">
                    <p className="mb-6"><Link href="/">Beranda</Link></p>
                    <h1 className="text-4xl font-bold leading-tight">Jenis pengajuan</h1>
                    <p className="mt-3 text-lg">Pilih apakah Anda mengirim catatan publik atau afiliasi bisnis tokoh.</p>
                    <div className="mt-8 flex max-w-xl flex-col gap-3">
                        <button
                            type="button"
                            onClick={() => chooseKind('public_record')}
                            className="border-2 border-ink bg-white px-4 py-4 text-left hover:bg-canvas"
                        >
                            <span className="block text-2xl font-bold">Catatan publik</span>
                            <span className="mt-1 block">Pernyataan, dugaan, atau vonis yang sudah ada sumbernya.</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => chooseKind('business_affiliation')}
                            className="border-2 border-ink bg-white px-4 py-4 text-left hover:bg-canvas"
                        >
                            <span className="block text-2xl font-bold">Afiliasi bisnis</span>
                            <span className="mt-1 block">Kepemilikan atau endorsemen tokoh terhadap suatu bisnis.</span>
                        </button>
                    </div>
                </div>
            </Public>
        );
    }

    return (
        <Public auth={props.auth}>
            <Head title={isAffiliation ? 'Afiliasi bisnis' : 'Catatan'} />
            <div className="max-w-[960px] mx-auto px-4 py-8">
                <p className="mb-6"><Link href="/">Beranda</Link></p>
                <button type="button" className="mb-4 underline" onClick={() => { setKind(null); setStep(0); setStepError(''); }}>
                    Ubah jenis pengajuan
                </button>
                <p className="font-bold">Langkah {step + 1} dari {steps.length}</p>
                <ol className="mt-3 flex gap-2">
                    {steps.map((item, index) => (
                        <li key={item.title} className="flex-1">
                            <button
                                type="button"
                                className={`block h-2 w-full border-2 border-ink ${index <= step ? 'bg-brand' : 'bg-white'}`}
                                aria-label={item.title}
                                disabled={index > step}
                                onClick={() => index < step && setStep(index)}
                            />
                        </li>
                    ))}
                </ol>

                <form onSubmit={onSubmit} className="mt-8 max-w-xl">
                    <div key={`${kind}-${step}`} className="step-in">
                        <h1 className="text-4xl font-bold leading-tight">{steps[step].title}</h1>
                        <p className="mt-3 text-lg">{steps[step].hint}</p>
                        <ValidationErrors errors={errors} />
                        {stepError ? <p className="mt-4 border-l-8 border-brand bg-canvas px-4 py-3" role="alert">{stepError}</p> : null}

                        {step === 0 ? (
                            <div className="mt-6">
                                <Label forInput="name" value="Nama" />
                                <NameField
                                    value={data.name}
                                    onChange={(name) => {
                                        setStepError('');
                                        setData('name', name);
                                    }}
                                    onPick={onPickPolitician}
                                />
                            </div>
                        ) : null}

                        {step === 1 ? (
                            <div className="mt-6 flex flex-col gap-6">
                                <div>
                                    <Label forInput="position" value="Jabatan" />
                                    <Input type="text" name="position" value={data.position} isFocused placeholder="cth: Anggota DPR" handleChange={onHandleChange} />
                                </div>
                                <div>
                                    <Label forInput="department" value="Instansi" />
                                    <Input type="text" name="department" value={data.department} placeholder="cth: Dewan Perwakilan Rakyat" handleChange={onHandleChange} />
                                </div>
                                <PartyField parties={parties} partyId={data.party_id} partyName={data.party_name} onChange={setData} />
                            </div>
                        ) : null}

                        {!isAffiliation && step === 2 ? (
                            <div className="mt-6 flex flex-col gap-3">
                                {categories.map((item) => (
                                    <button
                                        key={item.id}
                                        type="button"
                                        onClick={() => chooseCategory(item.id)}
                                        className={`border-2 px-4 py-4 text-left ${data.category === item.id ? 'border-brand bg-canvas' : 'border-ink bg-white'}`}
                                    >
                                        <span className="block text-2xl font-bold">{item.title}</span>
                                        <span className="mt-1 block">{item.hint}</span>
                                    </button>
                                ))}
                            </div>
                        ) : null}

                        {isAffiliation && step === 2 ? (
                            <div className="mt-6">
                                <BusinessField
                                    businesses={businesses}
                                    businessId={data.business_id}
                                    businessName={data.business_name}
                                    onChange={(field, value) => {
                                        setStepError('');
                                        setData(field, value);
                                    }}
                                />
                            </div>
                        ) : null}

                        {isAffiliation && step === 3 ? (
                            <div className="mt-6 flex flex-col gap-3">
                                {affiliationTypes.map((item) => (
                                    <button
                                        key={item.id}
                                        type="button"
                                        onClick={() => chooseAffiliationType(item.id)}
                                        className={`border-2 px-4 py-4 text-left ${data.type === item.id ? 'border-brand bg-canvas' : 'border-ink bg-white'}`}
                                    >
                                        <span className="block text-2xl font-bold">{item.title}</span>
                                        <span className="mt-1 block">{item.hint}</span>
                                    </button>
                                ))}
                                {data.type === 'ownership' ? (
                                    <div className="mt-4 border-2 border-ink p-4">
                                        <label className="flex items-center gap-3">
                                            <input
                                                type="checkbox"
                                                checked={data.ownership_unknown}
                                                onChange={(event) => {
                                                    setStepError('');
                                                    setData((current) => ({
                                                        ...current,
                                                        ownership_unknown: event.target.checked,
                                                        ownership_percent: event.target.checked ? '' : current.ownership_percent,
                                                    }));
                                                }}
                                            />
                                            Jumlah kepemilikan tidak diketahui
                                        </label>
                                        {!data.ownership_unknown ? (
                                            <div className="mt-4">
                                                <Label forInput="ownership_percent" value="Persentase kepemilikan" />
                                                <Input
                                                    type="number"
                                                    name="ownership_percent"
                                                    value={data.ownership_percent}
                                                    handleChange={onHandleChange}
                                                    placeholder="100 untuk kepemilikan penuh"
                                                />
                                                <p className="mt-2 text-muted">Isi 1–100, atau biarkan kosong jika jumlah tidak diketahui.</p>
                                            </div>
                                        ) : null}
                                    </div>
                                ) : null}
                            </div>
                        ) : null}

                        {!isAffiliation && step === 3 ? (
                            <div className="mt-6 flex flex-col gap-6">
                                {data.category === 'statement' ? (
                                    <div>
                                        <Label forInput="quote" value="Kutipan persis" />
                                        <textarea id="quote" name="quote" value={data.quote} onChange={onHandleChange} className="gov-input" rows="5" autoFocus placeholder="Salin kalimat dari sumber, tanpa parafrase" />
                                    </div>
                                ) : (
                                    <div>
                                        <Label forInput="summary" value={data.category === 'conviction' ? 'Putusan' : 'Dugaan'} />
                                        <textarea id="summary" name="summary" value={data.summary} onChange={onHandleChange} className="gov-input" rows="5" autoFocus placeholder={data.category === 'conviction' ? 'Perkara, pasal, dan putusan yang tertulis di sumber' : 'Apa yang diduga, dan bahwa ini belum vonis'} />
                                    </div>
                                )}
                                <div>
                                    <Label forInput="occurred_on" value="Tanggal kejadian atau putusan" />
                                    <Input type="date" name="occurred_on" value={data.occurred_on} handleChange={onHandleChange} />
                                    <p className="mt-2 text-muted">Boleh dikosongkan jika sumber tidak menyebut tanggal.</p>
                                </div>
                            </div>
                        ) : null}

                        {isAffiliation && step === 4 ? (
                            <div className="mt-6 flex flex-col gap-6">
                                <div>
                                    <Label forInput="summary" value="Ringkasan dari sumber" />
                                    <textarea id="summary" name="summary" value={data.summary} onChange={onHandleChange} className="gov-input" rows="5" autoFocus placeholder="Jelaskan hubungan tokoh dengan bisnis menurut sumber" />
                                </div>
                                <div>
                                    <Label forInput="occurred_on" value="Tanggal di sumber" />
                                    <Input type="date" name="occurred_on" value={data.occurred_on} handleChange={onHandleChange} />
                                    <p className="mt-2 text-muted">Boleh dikosongkan jika sumber tidak menyebut tanggal.</p>
                                </div>
                            </div>
                        ) : null}

                        {((!isAffiliation && step === 4) || (isAffiliation && step === 5)) ? (
                            <div className="mt-6 flex flex-col gap-6">
                                <div>
                                    <Label forInput="evtype" value="Jenis sumber" />
                                    <Input type="text" name="evtype" value={data.evtype} isFocused placeholder="cth: berita / video / putusan pengadilan" handleChange={onHandleChange} />
                                </div>
                                <div>
                                    <Label forInput="link" value="Tautan sumber" />
                                    <Input type="url" name="link" value={data.link} placeholder="https://" handleChange={onHandleChange} />
                                </div>
                                <div>
                                    <Label forInput="media" value="Berkas pendukung" />
                                    <input
                                        id="media"
                                        name="media"
                                        type="file"
                                        multiple
                                        accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,application/pdf"
                                        onChange={(event) => setData('media', Array.from(event.target.files ?? []))}
                                    />
                                    <p className="mt-2 text-muted">Opsional. Maksimal 3 berkas, masing-masing 2 MB.</p>
                                    {data.media?.length > 0 ? (
                                        <ul className="mt-2 list-disc pl-5">
                                            {data.media.map((file) => <li key={file.name}>{file.name}</li>)}
                                        </ul>
                                    ) : null}
                                </div>
                            </div>
                        ) : null}

                        {((!isAffiliation && step === 5) || (isAffiliation && step === 6)) ? (
                            <div className="mt-6">
                                <dl className="border-2 border-ink">
                                    {(isAffiliation ? [
                                        ['Nama', data.name, 0],
                                        ['Jabatan', data.position, 1],
                                        ['Instansi', data.department, 1],
                                        ['Partai', partyLabel(), 1],
                                        ['Bisnis', businessLabel(), 2],
                                        ['Jenis', ownershipLabel(), 3],
                                        ['Teks', data.summary, 4],
                                        ['Sumber', `${data.evtype} · ${data.link}`, 5],
                                    ] : [
                                        ['Nama', data.name, 0],
                                        ['Jabatan', data.position, 1],
                                        ['Instansi', data.department, 1],
                                        ['Partai', partyLabel(), 1],
                                        ['Jenis', categoryLabel(data.category), 2],
                                        ['Teks', data.category === 'statement' ? data.quote : data.summary, 3],
                                        ['Sumber', `${data.evtype} · ${data.link}`, 4],
                                    ]).map(([label, value, index]) => (
                                        <div key={label} className="grid gap-1 border-b border-ink/20 px-4 py-3 last:border-b-0 md:grid-cols-3">
                                            <dt className="font-bold">{label}</dt>
                                            <dd className="md:col-span-2">
                                                {value}
                                                <button type="button" className="ml-3 underline" onClick={() => setStep(index)}>Ubah</button>
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                                {turnstileEnabled ? (
                                    <div className="mt-6">
                                        <p className="font-bold mb-2">Verifikasi keamanan</p>
                                        <TurnstileField
                                            sitekey={sitekey}
                                            action={isAffiliation ? 'affiliation' : 'report'}
                                            resetKey={turnstileReset}
                                            onToken={(token) => setData('cf-turnstile-response', token)}
                                        />
                                    </div>
                                ) : null}
                            </div>
                        ) : null}
                    </div>

                    <div className="mt-8 flex items-center gap-6">
                        {step > 0 ? (
                            <button type="button" className="underline" onClick={() => { setStepError(''); setStep((current) => current - 1); }}>
                                Kembali
                            </button>
                        ) : null}
                        {step < steps.length - 1 && !(!isAffiliation && step === 2) ? (
                            <button className="gov-button" type="submit">Lanjutkan</button>
                        ) : null}
                        {step === steps.length - 1 ? (
                            <button className="gov-button" disabled={processing}>Kirim untuk diperiksa</button>
                        ) : null}
                    </div>
                </form>
            </div>
        </Public>
    );
}
