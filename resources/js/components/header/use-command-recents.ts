import { useSyncExternalStore } from 'react';

export type RecentRecord = {
    label: string;
    url: string;
    group: string;
};

type Recents = {
    queries: string[];
    opened: RecentRecord[];
};

const emptyRecents: Recents = { queries: [], opened: [] };
const cache = new Map<string, Recents>();
const listeners = new Set<() => void>();

function storageKey(userId: number): string {
    return `cais-command-recents:${userId}`;
}

function publish(): void {
    listeners.forEach((listener) => listener());
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

function read(userId: number | undefined): Recents {
    if (!userId || typeof window === 'undefined') {
        return emptyRecents;
    }

    const key = storageKey(userId);
    const cached = cache.get(key);

    if (cached) {
        return cached;
    }

    try {
        const parsed = JSON.parse(
            window.localStorage.getItem(key) ?? '',
        ) as Partial<Recents>;
        const recents: Recents = {
            queries: Array.isArray(parsed.queries)
                ? parsed.queries
                      .filter((entry) => typeof entry === 'string')
                      .slice(0, 5)
                : [],
            opened: Array.isArray(parsed.opened)
                ? parsed.opened
                      .filter(
                          (entry): entry is RecentRecord =>
                              typeof entry?.label === 'string' &&
                              typeof entry?.url === 'string' &&
                              typeof entry?.group === 'string',
                      )
                      .slice(0, 6)
                : [],
        };
        cache.set(key, recents);

        return recents;
    } catch {
        cache.set(key, emptyRecents);

        return emptyRecents;
    }
}

function write(userId: number, recents: Recents): void {
    const key = storageKey(userId);
    cache.set(key, recents);
    window.localStorage.setItem(key, JSON.stringify(recents));
    publish();
}

export function useCommandRecents(userId: number | undefined): {
    queries: string[];
    opened: RecentRecord[];
    rememberQuery: (query: string) => void;
    forgetQuery: (query: string) => void;
    rememberOpened: (record: RecentRecord) => void;
    clear: () => void;
} {
    const recents = useSyncExternalStore(
        subscribe,
        () => read(userId),
        () => emptyRecents,
    );

    return {
        queries: recents.queries,
        opened: recents.opened,
        rememberQuery(query: string) {
            if (!userId) {
                return;
            }

            const trimmed = query.trim();

            if (trimmed === '') {
                return;
            }

            const current = read(userId);
            write(userId, {
                ...current,
                queries: [
                    trimmed,
                    ...current.queries.filter((entry) => entry !== trimmed),
                ].slice(0, 5),
            });
        },
        forgetQuery(query: string) {
            if (!userId) {
                return;
            }

            const current = read(userId);
            write(userId, {
                ...current,
                queries: current.queries.filter((entry) => entry !== query),
            });
        },
        rememberOpened(record: RecentRecord) {
            if (!userId) {
                return;
            }

            const current = read(userId);
            write(userId, {
                ...current,
                opened: [
                    record,
                    ...current.opened.filter(
                        (entry) => entry.url !== record.url,
                    ),
                ].slice(0, 6),
            });
        },
        clear() {
            if (!userId) {
                return;
            }

            write(userId, emptyRecents);
        },
    };
}
