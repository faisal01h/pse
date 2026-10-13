import Public from '@/Layouts/Public';

export default function Guest({ children }) {
    return (
        <Public>
            <div className="max-w-[960px] mx-auto px-4 py-8">
                <div className="max-w-xl">{children}</div>
            </div>
        </Public>
    );
}
