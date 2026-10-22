import { router, usePage } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Lock, MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { ServerPaginationMeta } from '@/components/data-table/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Drawer,
    DrawerClose,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type LookupRow = {
    id: number;
    name: string;
    is_active: boolean;
    is_protected: boolean;
    locks_name: boolean;
    protected_reason: string | null;
    usage_count: number;
    slug?: string;
    address_city_id?: number | null;
    city?: string | null;
    label?: string | null;
};

export type LookupCityOption = {
    id: number;
    name: string;
};

type LookupDraft = {
    name: string;
    is_active: boolean;
    address_city_id: string;
    locks_name: boolean;
    is_protected: boolean;
    protected_reason: string | null;
    slug: string;
};

type ServerLookup = {
    search: string;
    cityId: number | null;
    active: string;
    pagination: ServerPaginationMeta;
    onVisit: (overrides: {
        search?: string;
        cityId?: number | null;
        active?: string;
        page?: number;
        perPage?: number;
    }) => void;
};

function blankDraft(): LookupDraft {
    return {
        name: '',
        is_active: true,
        address_city_id: '',
        locks_name: false,
        is_protected: false,
        protected_reason: null,
        slug: '',
    };
}

function draftFromRow(row: LookupRow): LookupDraft {
    return {
        name: row.name,
        is_active: row.is_active,
        address_city_id:
            row.address_city_id === null || row.address_city_id === undefined
                ? ''
                : String(row.address_city_id),
        locks_name: row.locks_name,
        is_protected: row.is_protected,
        protected_reason: row.protected_reason,
        slug: row.slug ?? '',
    };
}

function errorText(
    errors: Record<string, string | string[] | undefined>,
): string {
    return Object.values(errors)
        .flatMap((message) =>
            Array.isArray(message) ? message : message ? [message] : [],
        )
        .join(' ');
}

export function LookupManager({
    title,
    description,
    rows,
    storeUrl,
    updateUrl,
    destroyUrl,
    extraColumns = [],
    cities = [],
    includeCity = false,
    server,
}: {
    title: string;
    description: string;
    rows: LookupRow[];
    storeUrl: string;
    updateUrl: (id: number) => string;
    destroyUrl: (id: number) => string;
    extraColumns?: ColumnDef<LookupRow>[];
    cities?: LookupCityOption[];
    includeCity?: boolean;
    server?: ServerLookup;
}) {
    const pageErrors = (usePage().props.errors ?? {}) as Record<
        string,
        string | string[] | undefined
    >;
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [draft, setDraft] = useState<LookupDraft>(blankDraft);
    const [search, setSearch] = useState(server?.search ?? '');
    const [active, setActive] = useState(server?.active || 'all');
    const [deleteTarget, setDeleteTarget] = useState<LookupRow | null>(null);
    const [deleteError, setDeleteError] = useState<string | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);

    const serverSearch = server?.search;
    const serverActive = server?.active;
    const visitServer = server?.onVisit;

    useEffect(() => {
        if (serverSearch === undefined || serverActive === undefined) {
            return;
        }

        setSearch(serverSearch);
        setActive(serverActive === '' ? 'all' : serverActive);
    }, [serverSearch, serverActive]);

    useEffect(() => {
        if (serverSearch === undefined || visitServer === undefined) {
            return;
        }

        const trimmed = search.trim();

        if (trimmed === serverSearch.trim()) {
            return;
        }

        const handle = window.setTimeout(() => {
            visitServer({ search: trimmed, page: 1 });
        }, 250);

        return () => window.clearTimeout(handle);
    }, [search, serverSearch, visitServer]);

    const filteredRows = useMemo(() => {
        if (server) {
            return rows;
        }

        const query = search.trim().toLowerCase();

        return rows.filter((row) => {
            if (active === 'active' && !row.is_active) {
                return false;
            }

            if (active === 'inactive' && row.is_active) {
                return false;
            }

            if (query === '') {
                return true;
            }

            return [row.name, row.slug, row.city, row.label]
                .filter((value) => value !== null && value !== undefined)
                .join(' ')
                .toLowerCase()
                .includes(query);
        });
    }, [rows, search, active, server]);

    const openCreate = () => {
        setEditingId(null);
        setDraft(blankDraft());
        setDrawerOpen(true);
    };

    const openEdit = (row: LookupRow) => {
        setEditingId(row.id);
        setDraft(draftFromRow(row));
        setDrawerOpen(true);
    };

    const columns = useMemo<ColumnDef<LookupRow>[]>(
        () => [
            {
                accessorKey: 'name',
                header: 'Name',
                cell: ({ row }) => (
                    <span className="inline-flex items-center gap-2">
                        {row.original.name}
                        {row.original.is_protected && (
                            <span
                                title={
                                    row.original.protected_reason ??
                                    'Required by the system'
                                }
                            >
                                <Lock className="size-3.5 text-muted-foreground" />
                                <span className="sr-only">
                                    {row.original.protected_reason ??
                                        'Required by the system'}
                                </span>
                            </span>
                        )}
                    </span>
                ),
            },
            ...extraColumns,
            {
                accessorKey: 'is_active',
                header: 'Status',
                cell: ({ row }) => (
                    <Badge
                        variant={
                            row.original.is_active ? 'secondary' : 'outline'
                        }
                    >
                        {row.original.is_active ? 'Active' : 'Inactive'}
                    </Badge>
                ),
            },
            {
                accessorKey: 'usage_count',
                header: 'Used by',
                cell: ({ row }) => row.original.usage_count,
            },
            {
                id: 'actions',
                header: '',
                cell: ({ row }) => {
                    const lookup = row.original;
                    const deleteLabel = lookup.is_protected
                        ? (lookup.protected_reason ??
                          'This lookup cannot be deleted.')
                        : `Delete ${lookup.name}`;

                    return (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="ghost" size="icon-sm">
                                    <MoreHorizontal />
                                    <span className="sr-only">Open menu</span>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-40">
                                <DropdownMenuItem
                                    onSelect={() => openEdit(lookup)}
                                >
                                    <Pencil />
                                    Edit
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    variant="destructive"
                                    disabled={lookup.is_protected}
                                    title={deleteLabel}
                                    onSelect={() => {
                                        setDeleteError(null);
                                        setDeleteTarget(lookup);
                                    }}
                                >
                                    <Trash2 />
                                    Delete
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    );
                },
            },
        ],
        [extraColumns],
    );

    const hasActiveFilters =
        search.trim() !== '' ||
        active !== 'all' ||
        (server?.cityId ?? null) !== null;

    return (
        <div className="flex flex-col gap-4">
            <p className="text-sm text-muted-foreground">{description}</p>
            <Drawer
                open={drawerOpen}
                onOpenChange={setDrawerOpen}
                direction="right"
            >
                <DrawerContent className="h-dvh max-h-dvh overflow-hidden data-[vaul-drawer-direction=right]:sm:max-w-md">
                    <DrawerHeader className="shrink-0">
                        <DrawerTitle>
                            {editingId ? `Update ${title}` : `New ${title}`}
                        </DrawerTitle>
                        <DrawerDescription>
                            {draft.protected_reason ??
                                'Inactive lookups stay on existing records and drop out of new forms.'}
                        </DrawerDescription>
                        {errorText(pageErrors) !== '' && (
                            <p className="text-sm text-destructive" role="alert">
                                {errorText(pageErrors)}
                            </p>
                        )}
                    </DrawerHeader>
                    <form
                        className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto px-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            const payload: Record<string, unknown> = {
                                name: draft.name,
                                is_active: draft.is_active,
                            };

                            if (includeCity) {
                                payload.address_city_id = Number(
                                    draft.address_city_id,
                                );
                            }

                            const close = () => {
                                setDraft(blankDraft());
                                setEditingId(null);
                                setDrawerOpen(false);
                            };

                            if (editingId) {
                                router.put(updateUrl(editingId), payload, {
                                    preserveScroll: true,
                                    onSuccess: close,
                                });

                                return;
                            }

                            router.post(storeUrl, payload, {
                                preserveScroll: true,
                                onSuccess: close,
                            });
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="lookup-name">Name</Label>
                            <Input
                                id="lookup-name"
                                value={draft.name}
                                required
                                disabled={draft.locks_name}
                                onChange={(event) =>
                                    setDraft((current) => ({
                                        ...current,
                                        name: event.target.value,
                                    }))
                                }
                            />
                        </div>
                        {draft.slug !== '' && (
                            <div className="grid gap-2">
                                <Label htmlFor="lookup-slug">Slug</Label>
                                <Input
                                    id="lookup-slug"
                                    value={draft.slug}
                                    readOnly
                                />
                            </div>
                        )}
                        {includeCity && (
                            <div className="grid gap-2">
                                <Label htmlFor="lookup-city">City</Label>
                                <Select
                                    value={draft.address_city_id || 'none'}
                                    onValueChange={(value) =>
                                        setDraft((current) => ({
                                            ...current,
                                            address_city_id:
                                                value === 'none' ? '' : value,
                                        }))
                                    }
                                >
                                    <SelectTrigger
                                        id="lookup-city"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Select a city" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">
                                            Select a city
                                        </SelectItem>
                                        {cities.map((city) => (
                                            <SelectItem
                                                key={city.id}
                                                value={String(city.id)}
                                            >
                                                {city.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={draft.is_active}
                                disabled={draft.is_protected}
                                onCheckedChange={(checked) =>
                                    setDraft((current) => ({
                                        ...current,
                                        is_active: checked === true,
                                    }))
                                }
                            />
                            Active
                        </label>
                        <DrawerFooter className="px-0">
                            <Button
                                type="submit"
                                disabled={
                                    includeCity && draft.address_city_id === ''
                                }
                            >
                                {editingId ? 'Update' : 'Save'}
                            </Button>
                            <DrawerClose asChild>
                                <Button type="button" variant="outline">
                                    Cancel
                                </Button>
                            </DrawerClose>
                        </DrawerFooter>
                    </form>
                </DrawerContent>
            </Drawer>

            <Dialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open && !isDeleting) {
                        setDeleteTarget(null);
                        setDeleteError(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete {deleteTarget?.name}?</DialogTitle>
                        <DialogDescription>
                            This removes the lookup. If records already use it,
                            the delete is blocked and you can deactivate it
                            instead.
                        </DialogDescription>
                    </DialogHeader>
                    {deleteError && (
                        <p className="text-sm text-destructive" role="alert">
                            {deleteError}
                        </p>
                    )}
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline" disabled={isDeleting}>
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            disabled={isDeleting || deleteTarget === null}
                            onClick={() => {
                                if (deleteTarget === null) {
                                    return;
                                }

                                router.delete(destroyUrl(deleteTarget.id), {
                                    preserveScroll: true,
                                    onStart: () => setIsDeleting(true),
                                    onFinish: () => setIsDeleting(false),
                                    onSuccess: () => {
                                        setDeleteTarget(null);
                                        setDeleteError(null);
                                    },
                                    onError: (errors) => {
                                        const message = errors.lookup;
                                        setDeleteError(
                                            typeof message === 'string'
                                                ? message
                                                : 'Unable to delete this lookup.',
                                        );
                                    },
                                });
                            }}
                        >
                            {isDeleting ? 'Deleting...' : 'Delete'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Card>
                <CardContent>
                    <DataTable
                        columns={columns}
                        data={filteredRows}
                        emptyMessage={
                            hasActiveFilters
                                ? `No ${title.toLowerCase()} match your filters.`
                                : `No ${title.toLowerCase()} yet.`
                        }
                        manualPagination={server !== undefined}
                        manualFiltering={server !== undefined}
                        serverPagination={server?.pagination}
                        onPageChange={
                            server
                                ? (page) => server.onVisit({ page })
                                : undefined
                        }
                        onPerPageChange={
                            server
                                ? (perPage) =>
                                      server.onVisit({ perPage, page: 1 })
                                : undefined
                        }
                        toolbar={() => (
                            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                <div className="flex flex-1 flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                                    <Input
                                        placeholder={`Search ${title.toLowerCase()}...`}
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        className="h-9 max-w-sm"
                                    />
                                    {server && (
                                        <Select
                                            value={
                                                server.cityId === null
                                                    ? 'all'
                                                    : String(server.cityId)
                                            }
                                            onValueChange={(value) =>
                                                server.onVisit({
                                                    cityId:
                                                        value === 'all'
                                                            ? null
                                                            : Number(value),
                                                    page: 1,
                                                })
                                            }
                                        >
                                            <SelectTrigger className="h-9 w-48">
                                                <SelectValue placeholder="City" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">
                                                    All cities
                                                </SelectItem>
                                                {cities.map((city) => (
                                                    <SelectItem
                                                        key={city.id}
                                                        value={String(city.id)}
                                                    >
                                                        {city.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    )}
                                    <Select
                                        value={active}
                                        onValueChange={(value) => {
                                            setActive(value);

                                            if (server) {
                                                server.onVisit({
                                                    active:
                                                        value === 'all'
                                                            ? ''
                                                            : value,
                                                    page: 1,
                                                });
                                            }
                                        }}
                                    >
                                        <SelectTrigger className="h-9 w-36">
                                            <SelectValue placeholder="Status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">
                                                All statuses
                                            </SelectItem>
                                            <SelectItem value="active">
                                                Active
                                            </SelectItem>
                                            <SelectItem value="inactive">
                                                Inactive
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button type="button" onClick={openCreate}>
                                    <Plus />
                                    Add
                                </Button>
                            </div>
                        )}
                    />
                </CardContent>
            </Card>
        </div>
    );
}
