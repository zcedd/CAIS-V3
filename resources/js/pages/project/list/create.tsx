import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { create, index, store } from '@/routes/project';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/react';
import { toast } from 'sonner';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Project List',
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

export default function Create({ SourceOfFunds, Items }: { SourceOfFunds: SourceOfFund[]; Items: Item[] }) {
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
                        console.log('asda');
                        toast('Event has been created.');
                    }}
                    className="mx-auto w-full max-w-3xl space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div className="md:col-span-2">
                                    <Label className="mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-200">Name</Label>
                                    <Input
                                        type="text"
                                        name="name"
                                        className="block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 shadow-sm transition outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100"
                                        placeholder="Project name"
                                    />
                                    {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                                </div>

                                <div className="md:col-span-2">
                                    <Label className="mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-200">Descriptions</Label>
                                    <Textarea
                                        name="descriptions"
                                        className="block min-h-28 w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 shadow-sm transition outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100"
                                        placeholder="Describe the project"
                                    />
                                    {errors.descriptions && <p className="mt-1 text-sm text-red-600">{errors.descriptions}</p>}
                                </div>

                                <Select>
                                    <SelectTrigger className="">
                                        <SelectValue placeholder="Theme" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {SourceOfFunds.map((fund) => (
                                            <SelectItem key={fund.id} value={String(fund.id)}>
                                                {fund.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                <div>
                                    <Label className="mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-200">Date Started</Label>
                                    <Input
                                        type="date"
                                        name="date_started"
                                        className="block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 shadow-sm transition outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100"
                                    />
                                    {errors.date_started && <p className="mt-1 text-sm text-red-600">{errors.date_started}</p>}
                                </div>

                                <div>
                                    <Label className="mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-200">Date Ended</Label>
                                    <Input
                                        type="date"
                                        name="date_ended"
                                        className="block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 shadow-sm transition outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100"
                                    />
                                    {errors.date_ended && <p className="mt-1 text-sm text-red-600">{errors.date_ended}</p>}
                                </div>

                                <div className="flex items-center gap-3 md:col-span-2">
                                    <Input
                                        id="is_organization"
                                        type="checkbox"
                                        name="is_organization"
                                        className="h-4 w-4 rounded border-neutral-300 text-blue-600 focus:ring-blue-500 dark:border-neutral-700"
                                    />
                                    <Label htmlFor="is_organization" className="text-sm font-medium text-neutral-700 dark:text-neutral-200">
                                        Mark as organization
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
