export type ServerPaginationMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type ServerSortingState = {
    sort: string;
    direction: 'asc' | 'desc';
};
