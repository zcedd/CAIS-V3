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
