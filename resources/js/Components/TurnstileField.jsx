import { useEffect, useRef } from 'react';

/**
 * Explicit Turnstile widget. Callers bump `resetKey` after a failed submit
 * so the single-use token is replaced before retry.
 */
export default function TurnstileField({ sitekey, action, resetKey = 0, onToken }) {
    const host = useRef(null);
    const widgetId = useRef(null);

    useEffect(() => {
        let cancelled = false;
        let timer;

        const clear = () => {
            if (widgetId.current != null && window.turnstile) {
                window.turnstile.remove(widgetId.current);
            }
            widgetId.current = null;
        };

        const render = () => {
            if (cancelled || !host.current || !window.turnstile || !sitekey) {
                return;
            }

            clear();
            widgetId.current = window.turnstile.render(host.current, {
                sitekey,
                action,
                callback: (token) => onToken?.(token),
                'expired-callback': () => onToken?.(''),
                'error-callback': () => onToken?.(''),
            });
        };

        onToken?.('');

        if (window.turnstile) {
            render();
        } else {
            timer = window.setInterval(() => {
                if (window.turnstile) {
                    window.clearInterval(timer);
                    render();
                }
            }, 50);
        }

        return () => {
            cancelled = true;
            if (timer) {
                window.clearInterval(timer);
            }
            clear();
        };
    }, [sitekey, action, resetKey]);

    if (!sitekey) {
        return <p className="text-muted" role="alert">Verifikasi keamanan belum dikonfigurasi.</p>;
    }

    return <div ref={host} className="cf-turnstile" />;
}
