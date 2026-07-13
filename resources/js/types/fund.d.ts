export type FundRow = {
    id: number;
    name: string;
    amount: string | null;
    year: string | null;
    is_active: boolean | null;
    department_id: number;
};

export type FundListFilters = {
    search: string;
    status: string[];
};

export type PaginatedFunds = {
    data: FundRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};
