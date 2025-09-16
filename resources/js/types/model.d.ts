export interface Project {
    id: number;
    name: string;
    descriptions: string;
    source_of_fund: number;
    dateStarted: string;
    dateEnded: string;
    department_id: number;
    is_closed: boolean;
    is_request_only: boolean;
    is_organization: boolean;
    created_at: string;
    updated_at: string;
}
