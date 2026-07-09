export type NotificationCategory = 'system' | 'personal';

export type DepartmentSummary = {
    id: number;
    name: string;
    slug: string;
};

export type NotificationEntry = {
    id: string;
    type: string;
    category: NotificationCategory;
    title: string;
    message: string;
    url: string | null;
    data: Record<string, unknown>;
    read_at: string | null;
    created_at: string | null;
};

export type NotificationPaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type NotificationPagination = {
    data: NotificationEntry[];
    links: NotificationPaginationLink[];
};
