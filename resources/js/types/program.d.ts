export interface SourceOfFund {
    id: number;
    name: string;
}

export interface Program {
    id: number;
    name: string;
    descriptions: string;
    date_started: string;
    date_ended: string | null;
    department_id: number;
    is_closed: boolean;
    is_organization: boolean;
    created_at: string;
    updated_at: string;
    sourceOfFund?: SourceOfFund[];
    item?: Item[];
    pendingAssistance?: Assistance[];
    verifiedAssistance?: Assistance[];
    deliveredAssistance?: Assistance[];
    deniedAssistance?: Assistance[];
}

export interface Assistance {
    id: number;
    program_id: number;
    beneficiary_id: number | null;
    organization_id: number | null;
    mode_of_request_id: number;
    date_verified: string | null;
    date_requested: string | null;
    date_denied: string | null;
    date_delivered: string | null;
    user_id: number | null;
    remark: string | null;
    created_at: string;
    updated_at: string;
    modeOfRequest?: ModeOfRequest;
    beneficiary?: Beneficiary;
    organization?: Organization;
    requestItem?: AssistanceRequestItem[];
}

export interface AssistanceRequestItem {
    id: number;
    item_id: number;
    item?: Item;
}

export interface ModeOfRequest {
    id: number;
    name: string;
    created_at: string;
    updated_at: string;
}

export interface Beneficiary {
    id: number;
    individual?: {
        id: number;
        firstName: string;
        lastName: string;
    };
}

export interface Organization {
    id: number;
    name: string;
}

export interface Item {
    id: number;
    name: string;
    unit?: string;
    created_at: string;
    updated_at: string;
}
