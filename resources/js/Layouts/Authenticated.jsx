import Public from '@/Layouts/Public';

export default function Authenticated({ auth, header, children, wide = false }) {
    return (
        <Public auth={auth}>
            <div className={`${wide ? 'max-w-[1180px]' : 'max-w-[960px]'} mx-auto px-4 py-8`}>
                {header ? <div className="mb-6">{header}</div> : null}
                {children}
            </div>
        </Public>
    );
}
