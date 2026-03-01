import { useEffect, useState } from 'react';

export function useAsyncData<T>(
    fetcher: () => Promise<{ data: T }>,
    deps: unknown[] = [],
): { data: T | null; loading: boolean; error: Error | null } {
    const [data, setData] = useState<T | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<Error | null>(null);

    useEffect(() => {
        setLoading(true);
        fetcher()
            .then((res) => setData(res.data))
            .catch((err) => setError(err instanceof Error ? err : new Error(String(err))))
            .finally(() => setLoading(false));
    }, deps);

    return { data, loading, error };
}
