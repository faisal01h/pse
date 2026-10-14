import { Head, Link } from '@inertiajs/inertia-react';
import SciPol, { SciPolNotice } from '@/Layouts/SciPol';

const CHECKLISTS = [
    {
        title: 'Sebelum mengajukan rekaman',
        href: null,
        hrefLabel: null,
        items: [
            'Sumber primer atau pemberitaan yang dapat dibuka publik.',
            'Bedakan pernyataan, dugaan, dan vonis — jangan campur kategori.',
            'Ringkasan / kutipan sesuai apa yang tertulis di sumber, bukan opini Anda.',
            'Cantumkan tanggal kejadian bila ada di sumber.',
        ],
    },
    {
        title: 'Ajukan ke arsip',
        href: null,
        hrefLabel: 'Buat pengajuan',
        items: [
            'Gunakan formulir pengajuan PolitiSEE untuk rekaman atau afiliasi bisnis.',
            'Simpan kode lacak setelah berhasil mengirim.',
        ],
    },
    {
        title: 'Sanggah data terbit',
        href: null,
        hrefLabel: 'Dispute',
        items: [
            'Dispute hanya untuk data yang sudah terbit.',
            'Sertakan sumber tandingan yang menjelaskan mengapa klaim keliru atau kedaluwarsa.',
            'Lacak status sanggahan dengan kode yang diberikan.',
        ],
    },
];

export default function Latihan({ report_url, track_url, dispute_url }) {
    const blocks = [
        { ...CHECKLISTS[0] },
        { ...CHECKLISTS[1], href: report_url, secondaryHref: track_url, secondaryLabel: 'Lacak pengajuan' },
        { ...CHECKLISTS[2], href: dispute_url },
    ];

    return (
        <SciPol title="latihan">
            <Head title="SciPol · Latihan sumber" />

            <h1 className="text-2xl font-bold">Latihan sumber</h1>
            <p className="mt-1 mb-4 max-w-2xl text-sm text-muted">
                Latihan kewargaan dan literasi media: temukan sumber, dokumentasikan klaim, lalu ajukan atau sanggah
                lewat alur PolitiSEE yang sudah ada. Akademisi dapat melewati bagian ini.
            </p>

            <SciPolNotice>
                SciPol tidak mengganti antrean editorial. Pengajuan dan dispute tetap memakai formulir publik PolitiSEE.
            </SciPolNotice>

            <div className="space-y-4">
                {blocks.map((block) => (
                    <section key={block.title} className="border border-ink/20 bg-white p-4">
                        <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <h2 className="font-bold">{block.title}</h2>
                            <div className="flex flex-wrap gap-2 text-sm">
                                {block.href ? (
                                    <a href={block.href} className="bg-ink px-2 py-1 font-bold text-white no-underline">
                                        {block.hrefLabel || 'Buka'}
                                    </a>
                                ) : null}
                                {block.secondaryHref ? (
                                    <a href={block.secondaryHref} className="border border-ink px-2 py-1 font-bold text-ink no-underline">
                                        {block.secondaryLabel}
                                    </a>
                                ) : null}
                            </div>
                        </div>
                        <ol className="list-decimal space-y-1 pl-5 text-sm">
                            {block.items.map((item) => (
                                <li key={item}>{item}</li>
                            ))}
                        </ol>
                    </section>
                ))}
            </div>

            <p className="mt-6 text-sm text-muted">
                Setelah latihan, kembali ke{' '}
                <Link href="/scipol/bandingkan" className="underline">Bandingkan</Link>
                {' '}atau{' '}
                <Link href="/scipol/topik" className="underline">Arsip topik</Link>
                {' '}untuk analisis.
            </p>
        </SciPol>
    );
}
