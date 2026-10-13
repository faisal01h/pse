import { Head, Link } from '@inertiajs/inertia-react';
import { useState } from 'react';
import Public from '@/Layouts/Public';

export default function LaporanDiterima({ auth, code }) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        if (!code || !navigator.clipboard) {
            return;
        }

        try {
            await navigator.clipboard.writeText(code);
            setCopied(true);
        } catch (error) {
            setCopied(false);
        }
    };

    return (
        <Public auth={auth}>
            <Head title="Catatan diterima" />
            <div className="max-w-[960px] mx-auto px-4 py-8">
                <h1 className="text-4xl font-bold leading-tight">Catatan diterima</h1>
                {code ? (
                    <>
                        <p className="mt-4 max-w-3xl">
                            Simpan kode ini. Kode hanya ditampilkan sekali. Server menyimpan hash-nya, bukan kode itu sendiri, jadi kode tidak bisa dipulihkan jika hilang.
                        </p>
                        <label htmlFor="submission-code" className="mt-6 block font-bold">Kode pelacakan</label>
                        <input
                            id="submission-code"
                            readOnly
                            value={code}
                            className="gov-input mt-1 font-mono tracking-widest"
                            onFocus={(event) => event.target.select()}
                        />
                        <div className="mt-4 flex flex-wrap gap-4 items-center">
                            <button type="button" className="gov-button" onClick={copy}>Salin kode</button>
                            {copied ? <p>Kode tersalin.</p> : null}
                        </div>
                        <p className="mt-6">
                            <Link href="/reports/track">Lacak pengajuan</Link>
                        </p>
                    </>
                ) : (
                    <>
                        <p className="mt-4 max-w-3xl">
                            Kode pelacakan sudah tidak ditampilkan. Jika Anda menyimpannya, buka halaman lacak dan masukkan kode itu.
                        </p>
                        <p className="mt-6">
                            <Link href="/reports/track" className="gov-button">Lacak pengajuan</Link>
                        </p>
                    </>
                )}
            </div>
        </Public>
    );
}
