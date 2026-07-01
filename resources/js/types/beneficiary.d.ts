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
};

export type PaginatedBeneficiaries = {
    data: BeneficiaryListRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
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

export type FormOptions = {
    civil_statuses: SelectOption[];
    identifications: SelectOption[];
    address_provinces: AddressProvinceOption[];
    default_province_id: number | null;
    address_cities: AddressCityOption[];
    address_barangays: AddressBarangayOption[];
};

export type IdentificationEntry = {
    identification_id: number;
    number: string;
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
    name: string;
    cais_number: string;
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
