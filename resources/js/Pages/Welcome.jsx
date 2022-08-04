import React, { useEffect, useState } from 'react';
import { Link, Head } from '@inertiajs/inertia-react';
import Figure from '@/Components/Figure';
import axios from 'axios';

export default function Welcome(props) {
    const [ supporters, setSupporters ] = useState([]);
    useEffect(() => {
        axios.get('/api/supporters')
        .then((e) => {
            setSupporters(e.data)
            
        })
        .catch(console.error)
    }, [])

    return (
        <>
            <Head title={props.i18n? props.i18n : "Beranda"} />
            <div className="bg-gradient-to-b from-slate-200 to-rose-200 dark:bg-slate-800 min-h-screen w-screen flex flex-col gap-5">
                <div className="mx-5 md:mx-12 md:pt-48 pt-5">
                    <h1 className="text-5xl md:text-7xl font-bold text-rose-500">PSE</h1>
                    <p>Penyelenggara Sistem Elektronik</p>
                </div>

                <div className="mx-5 md:mx-12 flex flex-col gap-3">
                    <p className="md:w-[55vw]">
                        Peraturan Menteri Komunikasi dan Informatika No. 5 Tahun 2020 mengatur tentang kewajiban PSE Lingkup Privat dalam melakukan pemutusan akses (take down) terhadap informasi elektronik dan/atau dokumen elektronik yang dilarang. PSE Lingkup Privat wajib memberikan akses terhadap sistem elektronik dan/atau data elektronik untuk kepentingan pengawasan dan penegakan hukum pidana.
                    </p>
                    <a className="p-3 bg-rose-500 w-fit text-white rounded-lg hover:shadow-xl hover:shadow-rose-300 hover:bg-rose-600 transition-all duration-300" href="https://jdih.kominfo.go.id/produk_hukum/view/id/759/t/peraturan+menteri+komunikasi+dan+informatika+nomor+5+tahun+2020">
                        Lihat Berkas Permenkominfo
                    </a>
                </div>

                <div className="mx-5 md:mx-12 mt-10 flex flex-col gap-3">
                    <p className="md:w-[55vw]">
                        Untuk melihat daftar situs web populer yang telah atau belum melakukan registrasi PSE, dan situs web yang telah diblokir, anda dapat mengunjungi
                        <a href="https://kominfod.angelo.fyi/" className="text-rose-600 hover:text-white hover:bg-rose-500 p-1 transition-all duration-300 rounded-lg underline hover:no-underline">kominfod.angelo.fyi</a>
                    </p>
                </div>

                <div className="mx-5 md:mx-12 mt-10 flex flex-col gap-3">
                    <h2 className="text-xl md:text-2xl font-bold">Figur publik yang mendukung Permenkominfo No. 5 Tahun 2020</h2>
                    <div>

                    </div>
                    <div className="flex flex-row md:flex-col gap-3 flex-wrap md:max-h-[95vh] md:items-start md:justify-start md:overflow-x-auto">
                        {
                            supporters.length > 0 ?
                            supporters.map((e, i) => {
                                return (
                                    
                                    <Figure
                                        key={i}
                                        name={e.name}
                                        picture={e.pictureUrl}
                                        position={e.position}
                                        department={e.department}
                                        quote={e.quoted_statement}
                                        party={e.party}
                                        evidence={[e.evidences]}
                                    />
                                )
                            }) : "Tidak ada data."
                        }
                    </div>
                    <p>
                            Jika anda mengetahui figur lain yang belum terdaftar, silakan melakukan
                             <Link href="/laporan" className="text-rose-600 hover:text-white hover:bg-rose-500 p-1 transition-all duration-300 rounded-lg underline hover:no-underline">pelaporan.</Link>
                             Laporan anda akan diproses untuk verifikasi bukti.
                        </p>
                </div>
                
                <div>

                </div>
            </div>
        </>
    );
}
