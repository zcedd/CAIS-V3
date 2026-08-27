export type ProgramSummary = {
    total_requests: number;
    delivered_requests: number;
    in_progress_requests: number;
    total_delivered_items: number;
};

export type ProgramStatusBreakdownPoint = {
    status: string;
    count: number;
};

export type ProgramFund = {
    id: number;
    name: string;
    year: string | null;
    amount: number | null;
};

export type ProgramCoveredItem = {
    id: number;
    name: string;
    unit: string | null;
};

export type ProgramStockRow = {
    id: number;
    program_id: number;
    program_name: string;
    item_id: number;
    item_name: string;
    unit: string | null;
    remaining: number;
    on_hand: number;
    threshold: number | null;
    is_low: boolean;
};
