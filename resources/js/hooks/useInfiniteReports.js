import { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';

export default function useInfiniteReports({
    initialReports = [],
    initialMeta = {},
    initialTotal = 0,
    partyId = null,
}) {
    const [reports, setReports] = useState(initialReports);
    const [meta, setMeta] = useState(initialMeta);
    const [total, setTotal] = useState(initialTotal);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const loadingRef = useRef(false);
    const sentinelRef = useRef(null);
    const partyKey = partyId ?? 'all';

    useEffect(() => {
        setReports(initialReports);
        setMeta(initialMeta);
        setTotal(initialTotal);
        setError('');
        loadingRef.current = false;
    }, [initialReports, initialMeta, initialTotal, partyKey]);

    const loadMore = useCallback(async () => {
        if (loadingRef.current || !meta?.next) {
            return;
        }

        loadingRef.current = true;
        setLoading(true);
        setError('');

        try {
            const params = new URLSearchParams({ page: String(meta.next) });
            if (partyId) {
                params.set('party', String(partyId));
            }

            const { data } = await axios.get(`/api/public-records?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });

            const incoming = data.items ?? [];
            setReports((current) => {
                const seen = new Set(current.map((item) => item.id));
                return current.concat(incoming.filter((item) => !seen.has(item.id)));
            });
            setMeta(data.meta ?? meta);
            setTotal(data.total ?? total);
        } catch {
            setError('Gagal memuat catatan berikutnya.');
        } finally {
            loadingRef.current = false;
            setLoading(false);
        }
    }, [meta, partyId, total]);

    useEffect(() => {
        const node = sentinelRef.current;
        if (!node || !meta?.next) {
            return undefined;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    loadMore();
                }
            },
            { root: null, rootMargin: '320px 0px', threshold: 0 },
        );

        observer.observe(node);
        return () => observer.disconnect();
    }, [loadMore, meta?.next, reports.length]);

    return {
        reports,
        meta,
        total,
        loading,
        error,
        hasMore: Boolean(meta?.next),
        sentinelRef,
        loadMore,
    };
}
