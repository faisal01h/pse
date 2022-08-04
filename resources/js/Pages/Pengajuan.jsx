import { Head, Link } from "@inertiajs/inertia-react";
import Label from "@/Components/Label";
import Input from "@/Components/Input";
import { GoChevronLeft as ChevronLeft } from 'react-icons/all'

export default function Pengajuan() {

    const onHandleChange = () => {

    }

    return (
        <div>
            <Head title="Laporan" />
            <div className="bg-slate-200 dark:bg-slate-800 p-5 md:p-12 min-h-screen flex flex-col gap-5">
                <Link href="/" className="flex flex-row items-center hover:text-rose-500 w-fit transition-all duration-300"><ChevronLeft /> Kembali</Link>
                <h1 className="font-bold text-2xl md:text-4xl text-rose-500">Buat Laporan</h1>
                <div>
                    <form onSubmit="" method="post" className="flex flex-col gap-3 max-w-xl">
                        <h3 className="text-xl">Profil</h3>
                        <div>
                            <Label forInput="name" value="Nama figur" />

                            <Input
                                type="text"
                                name="name"
                                className="mt-1 block w-full"
                                isFocused={true}
                                placeholder="cth: Sandiaga Uno"
                                handleChange={onHandleChange}
                            />
                        </div>

                        <div>
                            <Label forInput="position" value="Jabatan" />

                            <Input
                                type="text"
                                name="position"
                                className="mt-1 block w-full"
                                placeholder="cth: Menteri"
                                handleChange={onHandleChange}
                            />
                        </div>

                        <div>
                            <Label forInput="department" value="Instansi" />

                            <Input
                                type="text"
                                name="department"
                                className="mt-1 block w-full"
                                placeholder="cth: Kementerian Pariwisata dan Ekonomi Kreatif"
                                handleChange={onHandleChange}
                            />
                        </div>

                        <div>
                            <Label forInput="party" value="Afiliasi partai" />

                            <Input
                                type="text"
                                name="party"
                                className="mt-1 block w-full"
                                placeholder="cth: Independen / Nasdem"
                                handleChange={onHandleChange}
                            />
                        </div>

                        <h3 className="text-xl">Bukti</h3>

                        <div>
                            <Label forInput="evtype" value="Tipe bukti" />

                            <Input
                                type="text"
                                name="evtype"
                                className="mt-1 block w-full"
                                placeholder="cth: tweet / berita / video"
                                handleChange={onHandleChange}
                            />
                        </div>

                        <div>
                            <Label forInput="link" value="Link" />

                            <Input
                                type="text"
                                name="link"
                                className="mt-1 block w-full"
                                placeholder="cth: https://twitter.com/sandiuno/status/1553304444263022592"
                                handleChange={onHandleChange}
                            />
                        </div>
                        
                        <button className="bg-rose-500 rounded-lg p-3 my-5 text-white font-semibold">Submit</button>
                    </form>
                </div>
            </div>
        </div>
    )
}