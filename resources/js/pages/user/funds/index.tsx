import { DataTableFacetedFilter } from '@/components/data-table/data-table-faceted-filter';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Drawer,
    DrawerClose,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import { formatPeso } from '@/lib/format-peso';
import { FundAmountField } from '@/pages/user/funds/fund-amount-field';
import { FundRowActions } from '@/pages/user/funds/fund-row-actions';
import {
    index as departmentFundsIndex,
    store as storeFund,
} from '@/routes/user/funds';
import type { BreadcrumbItem } from '@/types';
import type { FundListFilters, FundRow } from '@/types/fund';
import {
    Form,
    Head,
    InfiniteScroll,
    router,
    setLayoutProps,
} from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';

type DepartmentSummary = {
    id: number;
    name: string;
    slug: string;
};

type PaginatedFunds = {
    data: FundRow[];
};

const fundStatusOptions = [
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' },
] as const;

function buildFundsQuery(
    filters: FundListFilters,
): Record<string, string | string[]> {
    const query: Record<string, string | string[]> = {};
    const search = filters.search.trim();

    if (search !== '') {
        query.search = search;
    }

    if (filters.status.length > 0) {
        query.status = filters.status;
    }

    return query;
}

export default function UserFundsIndex({
    funds,
    department,
    search: initialSearch,
    status: initialStatus,
}: {
    funds: PaginatedFunds;
    department: DepartmentSummary | null;
    search: string;
    status: string[];
}) {
    const [searchQuery, setSearchQuery] = useState(initialSearch);
    const [createOpen, setCreateOpen] = useState(false);

    useEffect(() => {
        setSearchQuery(initialSearch);
    }, [initialSearch]);

    const navigateWithFilters = useCallback(
        (overrides: Partial<FundListFilters> = {}) => {
            if (!department?.slug) {
                return;
            }

            const next: FundListFilters = {
                search: overrides.search ?? searchQuery,
                status: overrides.status ?? initialStatus,
            };

            router.get(
                departmentFundsIndex.url(
                    { department: department.slug },
                    { query: buildFundsQuery(next) },
                ),
                {},
                {
                    preserveState: true,
                    replace: true,
                    only: ['funds', 'search', 'status', 'department'],
                    reset: ['funds'],
                },
            );
        },
        [department?.slug, searchQuery, initialStatus],
    );

    if (department?.slug) {
        const fundsHref = departmentFundsIndex.url(department.slug);
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Funds',
                    href: fundsHref,
                },
            ] satisfies BreadcrumbItem[],
        });
    }

    useEffect(() => {
        const trimmed = searchQuery.trim();

        if (trimmed === initialSearch.trim() || !department?.slug) {
            return;
        }

        const handle = window.setTimeout(() => {
            navigateWithFilters({ search: trimmed });
        }, 400);

        return () => window.clearTimeout(handle);
    }, [searchQuery, initialSearch, department?.slug, navigateWithFilters]);

    const heading = department ? `${department.name} funds` : 'Funds';
    const canManage = Boolean(department?.slug);
    const isFiltered =
        initialSearch.trim() !== '' || initialStatus.length > 0;

    return (
        <>
            <Head title="Department funds" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {heading}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {department
                                ? 'Funds assigned to your department.'
                                : 'You are not linked to a department yet, so no funds are shown.'}
                        </p>
                    </div>
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-1 flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                        <Input
                            type="text"
                            name="search"
                            autoComplete="off"
                            placeholder="Search by fund name"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className={cn(
                                'max-w-md',
                                searchQuery.trim().length > 0 &&
                                    'border-primary bg-primary/5 ring-1 ring-primary/30',
                            )}
                        />
                        <DataTableFacetedFilter
                            filterValue={initialStatus}
                            title="Status"
                            options={[...fundStatusOptions]}
                            onFilterChange={(values) =>
                                navigateWithFilters({ status: values })
                            }
                        />
                        {isFiltered ? (
                            <Button
                                type="button"
                                variant="ghost"
                                className="h-8 px-2 lg:px-3"
                                onClick={() => {
                                    setSearchQuery('');
                                    navigateWithFilters({
                                        search: '',
                                        status: [],
                                    });
                                }}
                            >
                                Reset
                                <X className="ml-2 size-4" />
                            </Button>
                        ) : null}
                    </div>
                    <Button
                        type="button"
                        disabled={!canManage}
                        onClick={() => setCreateOpen(true)}
                    >
                        <Plus className="size-4" />
                        Create fund
                    </Button>
                </div>

                {funds.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No funds match your filters.
                    </p>
                ) : (
                    <InfiniteScroll
                        data="funds"
                        onlyNext
                        next={({ loading }) =>
                            loading ? (
                                <p className="py-4 text-center text-sm text-muted-foreground">
                                    Loading more funds...
                                </p>
                            ) : null
                        }
                    >
                        <div className="rounded-xl border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Amount</TableHead>
                                        <TableHead>Year</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="w-[70px] text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {funds.data.map((fund) => (
                                        <TableRow key={fund.id}>
                                            <TableCell className="font-medium">
                                                {fund.name}
                                            </TableCell>
                                            <TableCell>
                                                {formatPeso(fund.amount)}
                                            </TableCell>
                                            <TableCell>
                                                {fund.year ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        fund.is_active
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {fund.is_active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {department?.slug ? (
                                                    <FundRowActions
                                                        fund={fund}
                                                        departmentSlug={
                                                            department.slug
                                                        }
                                                    />
                                                ) : null}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </InfiniteScroll>
                )}
            </div>

            <Drawer
                open={createOpen}
                onOpenChange={setCreateOpen}
                direction="right"
            >
                <DrawerContent className="data-[vaul-drawer-direction=right]:sm:max-w-lg">
                    <DrawerHeader>
                        <DrawerTitle>Create fund</DrawerTitle>
                        <DrawerDescription>
                            Add a new fund for {department?.name ?? 'your'}{' '}
                            department.
                        </DrawerDescription>
                    </DrawerHeader>
                    {canManage && department && (
                        <Form
                            {...storeFund.form({
                                department: department.slug,
                            })}
                            disableWhileProcessing
                            resetOnSuccess
                            onSuccess={() => {
                                setCreateOpen(false);
                                toast.success('Fund created successfully.');
                            }}
                            className="flex flex-1 flex-col gap-4 overflow-y-auto px-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="space-y-2">
                                        <Label htmlFor="fund-name">Name</Label>
                                        <Input
                                            id="fund-name"
                                            name="name"
                                            placeholder="Fund name"
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="fund-amount">
                                            Amount
                                        </Label>
                                        <FundAmountField id="fund-amount" />
                                        <InputError message={errors.amount} />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="fund-year">Year</Label>
                                        <Input
                                            id="fund-year"
                                            name="year"
                                            placeholder="e.g. 2026"
                                            maxLength={4}
                                        />
                                        <InputError message={errors.year} />
                                    </div>

                                    <div className="flex items-start gap-3">
                                        <Input
                                            id="fund-is-active"
                                            type="checkbox"
                                            name="is_active"
                                            value="1"
                                            defaultChecked
                                            className="mt-1 size-4 shrink-0 rounded border-input"
                                        />
                                        <div className="grid gap-1">
                                            <Label
                                                htmlFor="fund-is-active"
                                                className="font-normal"
                                            >
                                                Active fund
                                            </Label>
                                            <p className="text-sm text-muted-foreground">
                                                New funds are active by default.
                                            </p>
                                        </div>
                                    </div>

                                    <DrawerFooter className="px-0">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Creating...'
                                                : 'Create fund'}
                                        </Button>
                                        <DrawerClose asChild>
                                            <Button
                                                type="button"
                                                variant="outline"
                                            >
                                                Cancel
                                            </Button>
                                        </DrawerClose>
                                    </DrawerFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DrawerContent>
            </Drawer>
        </>
    );
}
