import type { AdminDepartmentOption } from '@/types/admin-user';

export type AdminWorkflowStatusOption = {
    value: string;
    label: string;
};

export type AdminWorkflowRow = {
    id: number;
    name: string;
    code: string | null;
    version: number;
    status: string;
    status_label: string;
    is_default: boolean;
    is_referenced: boolean;
    department: Pick<AdminDepartmentOption, 'id' | 'name' | 'slug'> | null;
};

export type AdminWorkflowTableFilters = {
    search: string;
    department_id: number | null;
    status: string[];
};

export type AdminWorkflowAbilities = {
    create: boolean;
    delete: boolean;
};
