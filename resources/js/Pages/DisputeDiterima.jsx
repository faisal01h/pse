import { Head, Link } from '@inertiajs/inertia-react';
import { useState } from 'react';
import Public from '@/Layouts/Public';

export default function DisputeDiterima({ auth, code }) {
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
            <Head title="Sanggahan diterima" />
            <div className="max-w-[960px] mx-auto px-4 py-8">
                <h1 className="text-4xl font-bold leading-tight">Sanggahan diterima</h1>
                <p className="mt-3 max-w-2xl text-xl">Pemeriksa akan meninjau arsip yang Anda sanggah. Arsip tetap tampil sampai keputusan diambil.</p>
                {code ? (
                    <>
                        <p className="mt-4 max-w-3xl">
                            Simpan kode ini. Pemeriksa menyimpan kode yang sama, jadi sanggahan bisa dicocokkan ke pengirimnya.
                        </p>
                        <label htmlFor="dispute-code" className="mt-6 block font-bold">Kode sanggahan</label>
                        <input
                            id="dispute-code"
                            readOnly
                            value={code}
                            className="gov-input mt-1 font-mono tracking-widest"
                            onFocus={(event) => event.target.select()}
                        />
                        <div className="mt-4 flex flex-wrap items-center gap-4">
                            <button type="button" className="gov-button" onClick={copy}>Salin kode</button>
                            {copied ? <p>Kode tersalin.</p> : null}
                        </div>
                    </>
                ) : (
                    <p className="mt-4 max-w-3xl">Kode sanggahan sudah tidak ditampilkan di halaman ini. Pemeriksa tetap menyimpan kode itu.</p>
                )}
                <Link href="/" className="gov-button mt-6">Kembali ke beranda</Link>
            </div>
        </Public>
    );
}
