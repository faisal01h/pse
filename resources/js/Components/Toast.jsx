import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/inertia-react';

export default function Toast() {
    const { toast } = usePage().props;
    const [current, setCurrent] = useState(toast ?? null);

    useEffect(() => {
        setCurrent(toast ?? null);

        if (!toast) {
            return undefined;
        }

        const timer = window.setTimeout(() => setCurrent(null), 6000);

        return () => window.clearTimeout(timer);
    }, [toast]);

    if (!current?.message) {
        return null;
    }

    const failure = current.type === 'failure';

    return (
        <div className="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex justify-center px-4">
            <div
                role={failure ? 'alert' : 'status'}
                className={`pointer-events-auto flex w-full max-w-lg items-start justify-between gap-4 border-2 border-l-8 bg-white px-4 py-3 shadow-[0_2px_0_#0b0c0c] ${failure ? 'border-brand' : 'border-ink'}`}
            >
                <p className="font-bold">{current.message}</p>
                <button type="button" className="font-bold underline" onClick={() => setCurrent(null)}>
                    Tutup
                </button>
            </div>
        </div>
    );
}
