export type DuplicateCandidate = {
    id: number;
    cais_number: string | null;
    name: string;
    birthday: string | null;
    barangay: string | null;
    score: 'high' | 'medium' | 'low';
    match_reasons: string[];
};

export type EligibilityFinding = {
    severity: 'hard' | 'soft';
    code: string;
    message: string;
    assistance_id?: number;
    item_id?: number;
};

export type EligibilityHistoryItem = {
    id: number;
    program_id: number;
    program_name: string;
    status: string | null;
    date_requested: string | null;
    date_delivered: string | null;
    items: Array<{
        name: string;
        quantity: number;
        is_received: boolean;
    }>;
};

export type EligibilityPreview = {
    findings: EligibilityFinding[];
    history: EligibilityHistoryItem[];
};

export type ProgramEligibilityFormValue = {
    cooldown_days: string;
    require_pwd: boolean;
    require_4ps: boolean;
    require_solo_parent: boolean;
    require_indigenous: boolean;
    item_caps: Record<string, string>;
};

export const emptyProgramEligibility = (): ProgramEligibilityFormValue => ({
    cooldown_days: '',
    require_pwd: false,
    require_4ps: false,
    require_solo_parent: false,
    require_indigenous: false,
    item_caps: {},
});
