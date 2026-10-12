import { useEffect, useState } from 'react';
import { search as globalSearch } from '@/routes/user';

export type SearchScope =
    | 'all'
    | 'programs'
    | 'beneficiaries'
    | 'requests'
    | 'items'
    | 'funds';

export type ProgramSearchHit = {
    id: number;
    name: string;
    parent_name: string | null;
    is_open: boolean;
    approval_status: string | null;
    url: string;
};

export type BeneficiarySearchHit = {
    id: number;
    name: string;
    cais_number: string | null;
    type: string;
    barangay: string | null;
    city: string | null;
    url: string;
};

export type RequestSearchHit = {
    id: number;
    beneficiary_name: string | null;
    cais_number: string | null;
    program_name: string | null;
    status: string | null;
    date_requested: string | null;
    url: string;
};

export type NamedSearchHit = {
    id: number;
    name: string;
    url: string;
};

export type GlobalSearchGroups = {
    programs?: ProgramSearchHit[];
    beneficiaries?: BeneficiarySearchHit[];
    requests?: RequestSearchHit[];
    items?: NamedSearchHit[];
    funds?: NamedSearchHit[];
};

type UseGlobalSearchResult = {
    groups: GlobalSearchGroups;
    loading: boolean;
    error: string | null;
};

const emptyGroups: GlobalSearchGroups = {};

export function useGlobalSearch(
    departmentSlug: string | undefined,
    query: string,
    scope: SearchScope,
    enabled: boolean,
): UseGlobalSearchResult {
    const trimmed = query.trim();
    const active = enabled && Boolean(departmentSlug) && trimmed.length >= 2;
    const [state, setState] = useState<{
        key: string;
        groups: GlobalSearchGroups;
        loading: boolean;
        error: string | null;
    }>({
        key: '',
        groups: emptyGroups,
        loading: false,
        error: null,
    });

    useEffect(() => {
        if (!active || !departmentSlug) {
            return;
        }

        const controller = new AbortController();
        const key = `${departmentSlug}|${scope}|${trimmed}`;
        const handle = window.setTimeout(() => {
            setState((current) => ({
                ...current,
                key,
                loading: true,
                error: null,
            }));

            void fetch(
                globalSearch.url(departmentSlug, {
                    query: { q: trimmed, scope },
                }),
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: controller.signal,
                },
            )
                .then(async (response) => {
                    if (!response.ok) {
                        throw new Error('Search failed');
                    }

                    const body = (await response.json()) as {
                        data?: GlobalSearchGroups;
                    };

                    setState({
                        key,
                        groups: body.data ?? emptyGroups,
                        loading: false,
                        error: null,
                    });
                })
                .catch((error: unknown) => {
                    if (
                        error instanceof DOMException &&
                        error.name === 'AbortError'
                    ) {
                        return;
                    }

                    setState({
                        key,
                        groups: emptyGroups,
                        loading: false,
                        error: 'Search failed. Try again.',
                    });
                });
        }, 200);

        return () => {
            controller.abort();
            window.clearTimeout(handle);
        };
    }, [active, departmentSlug, scope, trimmed]);

    if (!active || state.key !== `${departmentSlug}|${scope}|${trimmed}`) {
        return {
            groups: emptyGroups,
            loading: active,
            error: null,
        };
    }

    return {
        groups: state.groups,
        loading: state.loading,
        error: state.error,
    };
}
