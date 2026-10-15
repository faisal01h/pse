import { Head, Link } from '@inertiajs/inertia-react';
import Public from '@/Layouts/Public';
import FeedRecord from '@/Components/FeedRecord';
import PageLinks from '@/Components/PageLinks';
import ProfileHero, { ProfileCrumb } from '@/Components/ProfileHero';

export default function Topic({ auth, topic, reports = [], meta = {}, total = 0 }) {
    return (
        <Public auth={auth}>
            <Head title={`Topik: ${topic.name}`} />

            <ProfileHero
                breadcrumb={(
                    <ProfileCrumb
                        items={[
                            { href: '/', label: 'Beranda' },
                            { label: `Topik: ${topic.name}` },
                        ]}
                    />
                )}
                kicker="Topik"
                title={topic.name}
                meta={[
                    {
                        node: total > 0
                            ? `${total} catatan terbit dengan topik ini.`
                            : 'Belum ada catatan terbit dengan topik ini.',
                        muted: true,
                    },
                ]}
            />

            <section className="feed-section border-t-2 border-ink">
                <div className="mx-auto max-w-[960px] px-4 py-8 sm:py-10">
                    {reports.length > 0 ? (
                        <div className="flex flex-col gap-3">
                            {reports.map((report, index) => (
                                <FeedRecord key={report.id} report={report} index={index} />
                            ))}
                        </div>
                    ) : (
                        <div className="border-2 border-dashed border-ink/30 bg-white px-4 py-10 text-center">
                            <p className="text-lg font-bold">Belum ada catatan untuk topik ini.</p>
                            <Link href="/" className="mt-4 inline-block font-bold">
                                Kembali ke beranda
                            </Link>
                        </div>
                    )}

                    <PageLinks path={`/topics/${topic.id}`} meta={meta} />
                </div>
            </section>
        </Public>
    );
}
