import { Head, Link } from '@inertiajs/inertia-react';
import SciPol, { SciPolNotice } from '@/Layouts/SciPol';

const CHECKLISTS = [
    {
        title: 'Before submitting a record',
        href: null,
        hrefLabel: null,
        items: [
            'Use a primary source or publicly accessible reporting.',
            'Keep the summary or quote aligned with what the source says—not your opinion.',
            'Include the event date when the source provides one.',
            'Choose the record category that matches the source content.',
        ],
    },
    {
        title: 'Submit to the archive',
        href: null,
        hrefLabel: 'Create submission',
        items: [
            'Use the PolitiSEE submission form for records or business affiliations.',
            'Save the tracking code after a successful submit.',
        ],
    },
    {
        title: 'Dispute published data',
        href: null,
        hrefLabel: 'Dispute',
        items: [
            'Disputes apply only to already published data.',
            'Include a counter-source explaining why the claim is wrong or outdated.',
            'Track dispute status with the code you receive.',
        ],
    },
];

export default function Latihan({ report_url, track_url, dispute_url }) {
    const blocks = [
        { ...CHECKLISTS[0] },
        { ...CHECKLISTS[1], href: report_url, secondaryHref: track_url, secondaryLabel: 'Track submission' },
        { ...CHECKLISTS[2], href: dispute_url },
    ];

    return (
        <SciPol title="practice">
            <Head title="SciPol · Source practice" />

            <h1 className="text-2xl font-bold">Source practice</h1>
            <p className="mt-1 mb-4 max-w-2xl text-sm text-muted">
                Civic and media-literacy practice: find a source, document the claim, then submit or dispute through
                existing PolitiSEE flows. Academics can skip this section.
            </p>

            <SciPolNotice>
                SciPol does not replace the editorial queue. Submissions and disputes still use PolitiSEE public forms.
            </SciPolNotice>

            <div className="space-y-4">
                {blocks.map((block) => (
                    <section key={block.title} className="border border-ink/20 bg-white p-4">
                        <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <h2 className="font-bold">{block.title}</h2>
                            <div className="flex flex-wrap gap-2 text-sm">
                                {block.href ? (
                                    <a href={block.href} className="bg-ink px-2 py-1 font-bold text-white no-underline">
                                        {block.hrefLabel || 'Open'}
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
                After practice, return to{' '}
                <Link href="/scipol/bandingkan" className="underline">Compare</Link>
                {', '}
                <Link href="/scipol/topik" className="underline">Topic archive</Link>
                {', or '}
                <Link href="/scipol/realpolitik" className="underline">REALPOLITIK</Link>
                {' '}for analysis (REALPOLITIK appears in the sidebar when the plugin is active).
            </p>
        </SciPol>
    );
}
