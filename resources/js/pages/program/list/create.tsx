import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MultiSelect } from '@/components/ui/multi-select';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { create, index, store } from '@/routes/program';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/react';
import { CalendarDays, ChevronDownIcon, RotateCcw } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Program List',
        href: index().url,
    },
    {
        title: 'Create',
        href: create().url,
    },
];
export interface SourceOfFund {
    id: number;
    name: string;
    amount: string;
    year: string;
    is_active: string;
    department_id: string;
}

export interface Item {
    id: number;
    name: string;
    department_id: string;
}

export default function Create({ Funds, Items }: { Funds: SourceOfFund[]; Items: Item[] }) {
    const sourceOfFundOptions = [...Funds.map((fund) => ({ value: String(fund.id), label: fund.name }))];
    const [selectedSourceOfFunds, setSelectedSourceOfFunds] = React.useState<string[]>([]);

    const itemOptions = [...Items.map((item) => ({ value: String(item.id), label: item.name }))];
    const [selectedItems, setSelectedItems] = React.useState<string[]>([]);

    const [dateStartedOpen, setDateStartedOpen] = React.useState(false);
    const [dateStarted, setDateStarted] = React.useState<Date | undefined>(undefined);

    const [dateEndedOpen, setDateEndedOpen] = React.useState(false);
    const [dateEnded, setDateEnded] = React.useState<Date | undefined>(undefined);

    const dateStartedReset = () => {
        setDateStarted(undefined);
    };

    const dateStartedToday = () => {
        setDateStarted(new Date());
    };

    const dateEndedReset = () => {
        setDateEnded(undefined);
    };

    const dateEndedToday = () => {
        setDateEnded(new Date());
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Project" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Form
                    action={store().url}
                    method="post"
                    disableWhileProcessing
                    resetOnSuccess
                    onSuccess={() => {
                        toast('Event has been created.');

                        setSelectedItems([]);
                        setSelectedSourceOfFunds([]);
                        setDateStarted(undefined);
                        setDateEnded(undefined);
                    }}
                    transform={(data) => ({
                        ...data,
                        source_of_fund_ids: selectedSourceOfFunds,
                        item_ids: selectedItems,
                        date_started: dateStarted,
                        date_ended: dateEnded,
                    })}
                    className="mx-auto w-full max-w-3xl space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div className="md:col-span-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input type="text" id="name" name="name" placeholder="Project name" />
                                    {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                                </div>

                                <div className="md:col-span-2">
                                    <Label htmlFor="descriptions">Descriptions</Label>
                                    <Textarea id="descriptions" name="descriptions" placeholder="Describe the program" />
                                    {errors.descriptions && <p className="mt-1 text-sm text-red-600">{errors.descriptions}</p>}
                                </div>

                                <div className="md:col-span-2">
                                    <Label htmlFor="source-of-fund">Source of Fund</Label>
                                    <MultiSelect
                                        options={sourceOfFundOptions}
                                        selected={selectedSourceOfFunds}
                                        onChange={setSelectedSourceOfFunds}
                                        placeholder="Choose source of fund..."
                                        className="w-full"
                                    />
                                    {errors.source_of_fund_ids && <p className="mt-1 text-sm text-red-600">{errors.source_of_fund_ids}</p>}
                                </div>

                                <div className="md:col-span-2">
                                    <Label htmlFor="item">Items</Label>
                                    <MultiSelect
                                        options={itemOptions}
                                        selected={selectedItems}
                                        onChange={setSelectedItems}
                                        placeholder="Choose items..."
                                        className="w-full"
                                    />
                                    {errors.item_ids && <p className="mt-1 text-sm text-red-600">{errors.item_ids}</p>}
                                </div>

                                <div className="flex flex-col gap-1">
                                    <Label htmlFor="date-started">Date Started</Label>
                                    <Popover open={dateStartedOpen} onOpenChange={setDateStartedOpen}>
                                        <PopoverTrigger asChild>
                                            <Button variant="outline" id="date" className="w-full justify-between font-normal">
                                                {dateStarted ? dateStarted.toLocaleDateString() : 'Select date'}
                                                <ChevronDownIcon />
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent className="w-auto overflow-hidden p-0" align="start">
                                            <div className="flex gap-2 px-2 pt-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={dateStartedToday}
                                                    className="flex items-center gap-2 bg-transparent"
                                                >
                                                    <CalendarDays className="h-4 w-4" />
                                                    Today
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={dateStartedReset}
                                                    className="flex items-center gap-2 bg-transparent"
                                                >
                                                    <RotateCcw className="h-4 w-4" />
                                                    Reset
                                                </Button>
                                            </div>
                                            <Calendar
                                                mode="single"
                                                selected={dateStarted}
                                                captionLayout="dropdown"
                                                onSelect={(dateStarted) => {
                                                    setDateStarted(dateStarted);
                                                    setDateStartedOpen(false);
                                                }}
                                            />
                                        </PopoverContent>
                                    </Popover>
                                    {errors.date_started && <p className="mt-1 text-sm text-red-600">{errors.date_started}</p>}
                                </div>

                                <div className="flex flex-col gap-1">
                                    <Label htmlFor="date-ended">Date Ended</Label>
                                    <Popover open={dateEndedOpen} onOpenChange={setDateEndedOpen}>
                                        <PopoverTrigger asChild>
                                            <Button variant="outline" id="date" className="w-full justify-between font-normal">
                                                {dateEnded ? dateEnded.toLocaleDateString() : 'Select date'}
                                                <ChevronDownIcon />
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent className="w-auto overflow-hidden p-0" align="start">
                                            <div className="flex gap-2 px-2 pt-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={dateEndedToday}
                                                    className="flex items-center gap-2 bg-transparent"
                                                >
                                                    <CalendarDays className="h-4 w-4" />
                                                    Today
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={dateEndedReset}
                                                    className="flex items-center gap-2 bg-transparent"
                                                >
                                                    <RotateCcw className="h-4 w-4" />
                                                    Reset
                                                </Button>
                                            </div>
                                            <Calendar
                                                mode="single"
                                                selected={dateEnded}
                                                captionLayout="dropdown"
                                                onSelect={(dateEnded) => {
                                                    setDateEnded(dateEnded);
                                                    setDateEndedOpen(false);
                                                }}
                                            />
                                        </PopoverContent>
                                    </Popover>
                                    {errors.date_ended && <p className="mt-1 text-sm text-red-600">{errors.date_ended}</p>}
                                </div>

                                <div className="flex items-center gap-3 md:col-span-2">
                                    <Input
                                        id="is-organization"
                                        type="checkbox"
                                        name="is_organization"
                                        className="h-4 w-4 rounded border-neutral-300 text-blue-600 focus:ring-blue-500 dark:border-neutral-700"
                                    />
                                    <Label
                                        htmlFor="is-organization"
                                        className="flex flex-col text-sm font-medium text-neutral-700 dark:text-neutral-200"
                                    >
                                        Mark as organization
                                        <small>This will determine if the program is for personal or organizational assistance.</small>
                                    </Label>
                                </div>
                            </div>

                            <div className="flex items-center justify-end gap-3">
                                <Button variant={'outline'} asChild>
                                    <Link href={index().url}>Cancel</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving...' : 'Save Project'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </AppLayout>
    );
}
