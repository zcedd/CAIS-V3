export type AdminDepartmentOption = {
    id: number;
    name: string;
    slug: string;
};

export type AdminRoleOption = {
    value: string;
    label: string;
};

export type AdminUserRow = {
    id: number;
    firstName: string;
    middleName: string | null;
    lastName: string;
    name: string;
    email: string;
    department: AdminDepartmentOption | null;
    roles: string[];
};

export type AdminUserTableFilters = {
    search: string;
    department_id: number | null;
    role: string[];
};
