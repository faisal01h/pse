import { Link } from '@inertiajs/inertia-react';

/**
 * Shared identity band for politician / party / business profiles.
 * Name is the hero signal; image is the visual anchor.
 */
export default function ProfileHero({
    breadcrumb,
    image,
    imageAlt = '',
    imageContain = false,
    kicker,
    title,
    meta = [],
    action = null,
}) {
    return (
        <header className="profile-hero relative overflow-hidden border-b-2 border-ink bg-canvas">
            <div className="pointer-events-none absolute inset-y-0 left-0 w-2 bg-brand" aria-hidden="true" />
            <div className="pointer-events-none absolute -right-16 -top-16 h-48 w-48 rounded-full bg-brand/10" aria-hidden="true" />
            <div className="pointer-events-none absolute -bottom-20 right-24 h-40 w-40 rounded-full bg-ink/5" aria-hidden="true" />

            <div className="relative mx-auto max-w-[960px] px-4 py-8 sm:py-10">
                {breadcrumb ? (
                    <nav aria-label="Jenjang" className="profile-fade mb-6 text-sm">
                        {breadcrumb}
                    </nav>
                ) : null}

                <div className="profile-rise flex flex-col gap-6 sm:flex-row sm:items-end sm:gap-8">
                    {image ? (
                        <img
                            src={image}
                            alt={imageAlt}
                            className={`h-32 w-32 shrink-0 border-2 border-ink bg-white sm:h-40 sm:w-40 ${
                                imageContain ? 'object-contain p-2' : 'object-cover'
                            }`}
                        />
                    ) : (
                        <div
                            className="flex h-32 w-32 shrink-0 items-end border-2 border-ink bg-white p-3 sm:h-40 sm:w-40"
                            aria-hidden="true"
                        >
                            <span className="text-4xl font-bold leading-none text-brand">
                                {String(title || '?').trim().charAt(0).toUpperCase()}
                            </span>
                        </div>
                    )}

                    <div className="min-w-0 flex-1 pb-1">
                        {kicker ? <p className="text-sm font-bold uppercase tracking-wide text-muted">{kicker}</p> : null}
                        <h1 className="mt-1 text-4xl font-bold leading-tight sm:text-5xl">{title}</h1>
                        {meta.length > 0 ? (
                            <ul className="mt-4 flex flex-col gap-1.5 text-lg">
                                {meta.map((item, index) => (
                                    <li key={index} className={item.muted ? 'text-muted' : 'text-ink'}>
                                        {item.node}
                                    </li>
                                ))}
                            </ul>
                        ) : null}
                        {action ? <div className="mt-5">{action}</div> : null}
                    </div>
                </div>
            </div>
        </header>
    );
}

export function ProfileCrumb({ items }) {
    return (
        <ol className="flex flex-wrap items-center gap-x-2 gap-y-1">
            {items.map((item, index) => (
                <li key={item.href || item.label} className="flex items-center gap-2">
                    {index > 0 ? <span className="text-muted" aria-hidden="true">/</span> : null}
                    {item.href ? (
                        <Link href={item.href} className="text-ink underline">
                            {item.label}
                        </Link>
                    ) : (
                        <span className="text-muted">{item.label}</span>
                    )}
                </li>
            ))}
        </ol>
    );
}
