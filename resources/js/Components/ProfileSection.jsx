export default function ProfileSection({ id, title, lead, children, className = '' }) {
    return (
        <section id={id} className={`profile-section scroll-mt-8 ${className}`}>
            <div className="border-b-2 border-ink pb-3">
                <h2 className="text-2xl font-bold leading-tight sm:text-3xl">{title}</h2>
                {lead ? <p className="mt-2 max-w-2xl text-muted">{lead}</p> : null}
            </div>
            <div className="mt-6">{children}</div>
        </section>
    );
}
