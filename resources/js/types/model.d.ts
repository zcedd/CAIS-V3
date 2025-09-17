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
    pending_assistance?: Assistance[];
    verified_assistance?: Assistance[];
    delivered_assistance?: Assistance[];
    denied_assistance?: Assistance[];
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
