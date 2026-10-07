export type DepartmentSummary = {
    id: number;
    name: string;
    slug: string;
};

export type BeneficiaryListRow = {
    id: number;
    cais_number: string;
    name: string;
    type: 'individual' | 'organization';
    address: string | null;
    contact: string | null;
    assistances_count: number;
    last_assisted_at: string | null;
    registered_at: string | null;
};

export type BeneficiaryRegistryStats = {
    total: number;
    individuals: number;
    organizations: number;
    assisted: number;
    new_this_month: number;
};

export type PaginatedBeneficiaries = {
    data: BeneficiaryListRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type SelectOption = {
    id: number;
    name: string;
};

export type AddressProvinceOption = {
    id: number;
    name: string;
};

export type AddressCityOption = {
    id: number;
    name: string;
    address_province_id: number | null;
};

export type AddressBarangayOption = {
    id: number;
    name: string;
    address_city_id: number;
    city: string | null;
    label: string;
};

export type NameSuffixOption = {
    value: string;
    label: string;
};

export type FormOptions = {
    civil_statuses: SelectOption[];
    identifications: SelectOption[];
    address_provinces: AddressProvinceOption[];
    default_province_id: number | null;
    address_cities: AddressCityOption[];
    address_barangays: AddressBarangayOption[];
    suffixes: NameSuffixOption[];
};

export type IdentificationEntry = {
    identification_id: number;
    number: string;
};

export type EverifyIntakeMethod = 'manual' | 'everify';

export type EverifyVerificationStatus = 'verified' | 'skipped';

export type EverifyIdentityMethod = 'query' | 'qr' | 'pcn';

export type EverifyBiometricMethod = 'face' | 'fingerprint';

export type EverifyFingerprintConfig = {
    ports: number[];
    env: string;
    domain_uri: string;
    device_id: string | null;
};

export type EverifyIntakeValues = {
    identityMethod: EverifyIdentityMethod;
    biometricMethod: EverifyBiometricMethod;
    qrValue: string;
    faceSessionId: string;
    fingerprint: { biometrics: Array<Record<string, unknown>> } | null;
    fingerprintDeviceId: string;
};

export type IndividualFormData = {
    first_name: string;
    middle_name: string;
    last_name: string;
    suffix: string;
    birthday: string;
    sex: 'Male' | 'Female';
    other_address: string;
    civil_status_id: number | null;
    mobile_number: string;
    indigenous: boolean;
    ethnicity: string;
    pwd: boolean;
    is_4ps_beneficiary: boolean;
    is_solo_parent: boolean;
    spouse: string;
    address_barangay_id: number | null;
    identifications: IdentificationEntry[];
};

export type OrganizationMember = {
    id: number;
    beneficiary_id: number | null;
    name: string;
    cais_number: string;
    is_president: boolean;
};

export type IndividualOrganizationMembership = {
    id: number;
    beneficiary_id: number | null;
    name: string;
    cais_number: string;
    is_president: boolean;
};

export type BeneficiaryProfile = {
    id: number;
    cais_number: string;
    name: string;
    type: 'individual' | 'organization';
    details: Record<string, unknown>;
    programs: Array<{
        id: number;
        name: string;
        department: DepartmentSummary | null;
        is_organization: boolean;
    }>;
    assistances_count: number;
};

export type BeneficiaryAssistanceSummary = {
    total: number;
    delivered: number;
    denied: number;
    in_progress: number;
    programs: number;
    last_requested_at: string | null;
};

export type BeneficiaryAssistanceRow = {
    id: number;
    program_id: number;
    program_name: string;
    department_name: string;
    department_slug: string | null;
    mode_of_request: string;
    date_requested: string | null;
    status: string;
    request_status: string | null;
};

export type PaginatedBeneficiaryAssistances = {
    data: BeneficiaryAssistanceRow[];
};
