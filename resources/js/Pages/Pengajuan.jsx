import { Head, Link, useForm } from '@inertiajs/inertia-react';
import { useEffect, useState } from 'react';
import Public from '@/Layouts/Public';
import Label from '@/Components/Label';
import Input from '@/Components/Input';
import PartyField from '@/Components/PartyField';
import NameField from '@/Components/NameField';
import ValidationErrors from '@/Components/ValidationErrors';

const steps = [
    { title: 'Siapa yang dicatat?', hint: 'Ketik namanya. Jika profilnya sudah ada, pilih dari daftar.' },
    { title: 'Di mana ia bertugas?', hint: 'Jabatan, instansi, dan partai. Bagian ini sudah terisi jika Anda memilih profil yang tersimpan.' },
    { title: 'Catatan ini jenisnya apa?', hint: 'Pilih satu. Dugaan dan vonis tidak boleh tertukar.' },
    { title: 'Apa yang tertulis di sumber?', hint: 'Salin kutipan untuk pernyataan. Ringkas perkara untuk dugaan atau vonis.' },
    { title: 'Dari mana sumbernya?', hint: 'Tautan wajib. Berkas foto, video, atau PDF boleh ditambahkan.' },
    { title: 'Cek sekali lagi, lalu kirim.', hint: 'Kode pada gambar menjaga formulir dari kiriman otomatis.' },
];

const categories = [
    { id: 'statement', title: 'Pernyataan publik', hint: 'Kalimat yang ia ucapkan atau tulis, disalin persis.' },
    { id: 'allegation', title: 'Dugaan pelanggaran', hint: 'Tuduhan yang belum menjadi putusan pengadilan.' },
    { id: 'conviction', title: 'Vonis pengadilan', hint: 'Putusan yang sudah dijatuhkan, bukan sekadar tuduhan.' },
];

const fieldStep = {
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
    captcha: 5,
};

function categoryLabel(id) {
    return categories.find((item) => item.id === id)?.title ?? id;
}

export default function Pengajuan(props) {
    const parties = props.parties ?? [];
    const [step, setStep] = useState(0);
    const [stepError, setStepError] = useState('');
    const [captchaKey, setCaptchaKey] = useState(() => Date.now());
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        position: '',
        department: '',
        party_id: '',
        party_name: '',
        category: 'statement',
        summary: '',
        quote: '',
        evtype: '',
        link: '',
        occurred_on: '',
        captcha: '',
        media: [],
    });
    const errorStamp = Object.keys(errors).sort().join('|');

    useEffect(() => {
        if (!errorStamp) {
            return;
        }

        const nextStep = Object.keys(errors).reduce((lowest, field) => {
            const index = fieldStep[field] ?? fieldStep[field.split('.')[0]];
            return index === undefined ? lowest : Math.min(lowest, index);
        }, steps.length - 1);
        setStep(nextStep);
        setCaptchaKey(Date.now());
        setData('captcha', '');
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
        if (index === 5 && data.captcha.trim().length < 5) {
            return 'Ketik kode 5 huruf pada gambar.';
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
        post(route('newReport'), { forceFormData: true });
    };

    return (
        <Public auth={props.auth}>
            <Head title="Catatan" />
            <div className="max-w-[960px] mx-auto px-4 py-8">
                <p className="mb-6"><Link href="/">Beranda</Link></p>
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
                    <div key={step} className="step-in">
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

                        {step === 2 ? (
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

                        {step === 3 ? (
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

                        {step === 4 ? (
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

                        {step === 5 ? (
                            <div className="mt-6">
                                <dl className="border-2 border-ink">
                                    {[
                                        ['Nama', data.name, 0],
                                        ['Jabatan', data.position, 1],
                                        ['Instansi', data.department, 1],
                                        ['Partai', partyLabel(), 1],
                                        ['Jenis', categoryLabel(data.category), 2],
                                        ['Teks', data.category === 'statement' ? data.quote : data.summary, 3],
                                        ['Sumber', `${data.evtype} · ${data.link}`, 4],
                                    ].map(([label, value, index]) => (
                                        <div key={label} className="grid gap-1 border-b border-ink/20 px-4 py-3 last:border-b-0 md:grid-cols-3">
                                            <dt className="font-bold">{label}</dt>
                                            <dd className="md:col-span-2">
                                                {value}
                                                <button type="button" className="ml-3 underline" onClick={() => setStep(index)}>Ubah</button>
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                                <div className="mt-6">
                                    <Label forInput="captcha" value="Kode pada gambar" />
                                    <img src={`${route('captcha')}?k=${captchaKey}`} alt="Kode captcha" width="180" height="56" className="mt-1 border-2 border-ink bg-white" />
                                    <button type="button" className="mt-2 underline" onClick={() => setCaptchaKey(Date.now())}>Minta kode baru</button>
                                    <Input type="text" name="captcha" value={data.captcha} placeholder="Ketik 5 huruf pada gambar" handleChange={onHandleChange} className="mt-3 uppercase" />
                                </div>
                            </div>
                        ) : null}
                    </div>

                    <div className="mt-8 flex items-center gap-6">
                        {step > 0 ? (
                            <button type="button" className="underline" onClick={() => { setStepError(''); setStep((current) => current - 1); }}>
                                Kembali
                            </button>
                        ) : null}
                        {step < steps.length - 1 && step !== 2 ? (
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
