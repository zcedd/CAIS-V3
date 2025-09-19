import { SourceOfFund } from '@/pages/project/list/create';

export interface Project {
    id: number;
    name: string;
    descriptions: string;
    source_of_fund_id: number;
    dateStarted: string;
    dateEnded: string;
    department_id: number;
    is_closed: boolean;
    is_request_only: boolean;
    is_organization: boolean;
    created_at: string;
    updated_at: string;
    source_of_fund?: SourceOfFund[];
    item?: Item[];
    pending_assistance?: Assistance[];
    verified_assistance?: Assistance[];
    delivered_assistance?: Assistance[];
    denied_assistance?: Assistance[];
}

export interface PaginatedProject {
    current_page: number;
    data: Project[];
    links: PaginateLink[];
}

export interface PaginateLink {
    url: string;
    label: string;
    active: boolean;
}

export interface Assistance {
    id: number;
    project_id: number;
    beneficiary_id: number;
    organization_id: number;
    mode_of_request_id: number;
    dateVerified: string;
    dateRequested: string;
    dateDenied: string;
    dateDelivered: string;
    user_id: number;
    remark: string;
    created_at: string;
    updated_at: string;
}

export interface SourceOfFund {
    id: number;
    name: string;
    created_at: string;
    updated_at: string;
}

export interface Item {
    id: number;
    name: string;
    unit: string;
    created_at: string;
    updated_at: string;
}
