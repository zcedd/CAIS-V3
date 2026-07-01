'use client';

import {
    BeneficiarySearchCombobox,
    type BeneficiarySearchOption,
} from '@/components/beneficiary-search-combobox';
import { AddressCascadeSelect } from '@/components/address-cascade-select';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { index as beneficiariesIndex } from '@/routes/user/beneficiaries';
import { store as storeIndividual } from '@/routes/user/beneficiaries/individuals';
import { store as storeOrganization } from '@/routes/user/beneficiaries/organizations';
import type {
    DepartmentSummary,
    FormOptions,
    IndividualFormData,
} from '@/types/beneficiary';
import type { BreadcrumbItem } from '@/types';
import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

const emptyIndividualForm = (): IndividualFormData => ({
    first_name: '',
    middle_name: '',
    last_name: '',
    suffix: '',
    birthday: '',
    sex: 'Male',
    other_address: '',
    civil_status_id: null,
    mobile_number: '',
    indigenous: false,
    ethnicity: '',
    pwd: false,
    is_4ps_beneficiary: false,
    is_solo_parent: false,
    spouse: '',
    address_barangay_id: null,
    identifications: [],
});

export default function UserBeneficiariesCreate({
    department,
    form_options,
}: {
    department: DepartmentSummary;
    form_options: FormOptions;
}) {
    const [beneficiaryKind, setBeneficiaryKind] = useState<
        'individual' | 'organization'
    >('individual');
    const [individualForm, setIndividualForm] = useState<IndividualFormData>(
        emptyIndividualForm(),
    );
    const [presidentBeneficiaryId, setPresidentBeneficiaryId] = useState<
        number | null
    >(null);
    const [presidentIndividualId, setPresidentIndividualId] = useState<
        number | null
    >(null);
    const [presidentOption, setPresidentOption] =
        useState<BeneficiarySearchOption | null>(null);
    const [members, setMembers] = useState<
        Array<{ individual_id: number; option: BeneficiarySearchOption }>
    >([]);
    const [orgBarangayId, setOrgBarangayId] = useState<number | null>(null);
    const [memberPickerKey, setMemberPickerKey] = useState(0);
    const [pendingMemberId, setPendingMemberId] = useState<number | null>(null);
    const [pendingMemberOption, setPendingMemberOption] =
        useState<BeneficiarySearchOption | null>(null);

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Beneficiaries',
                    href: beneficiariesIndex.url(department.slug),
                },
                {
                    title: 'Add beneficiary',
                    href: '#',
                },
            ] satisfies BreadcrumbItem[],
        });
    }, [department.slug]);

    const addIdentificationRow = () => {
        const firstType = form_options.identifications[0];
        if (!firstType) {
            return;
        }

        setIndividualForm((current) => ({
            ...current,
            identifications: [
                ...current.identifications,
                {
                    identification_id: firstType.id,
                    number: '',
                },
            ],
        }));
    };

    const addMember = () => {
        if (
            pendingMemberId === null ||
            pendingMemberOption === null ||
            pendingMemberOption.individual_id === null
        ) {
            return;
        }

        const individualId = pendingMemberOption.individual_id;

        if (
            members.some((member) => member.individual_id === individualId) ||
            presidentIndividualId === individualId
        ) {
            toast.error('This member is already added.');
            return;
        }

        setMembers((current) => [
            ...current,
            {
                individual_id: individualId,
                option: pendingMemberOption,
            },
        ]);
        setPendingMemberId(null);
        setPendingMemberOption(null);
        setMemberPickerKey((value) => value + 1);
    };

    return (
        <>
            <Head title="Add beneficiary" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Add beneficiary
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Register an individual or create an organization.
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={beneficiariesIndex.url(department.slug)}>
                            Back to beneficiaries
                        </Link>
                    </Button>
                </div>

                <Tabs
                    value={beneficiaryKind}
                    onValueChange={(value) =>
                        setBeneficiaryKind(
                            value as 'individual' | 'organization',
                        )
                    }
                >
                    <TabsList>
                        <TabsTrigger value="individual">Individual</TabsTrigger>
                        <TabsTrigger value="organization">
                            Organization
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="individual" className="mt-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Individual beneficiary</CardTitle>
                                <CardDescription>
                                    Enter the individual beneficiary details.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    {...storeIndividual.form(department.slug)}
                                    className="grid gap-4"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-4 md:grid-cols-2">
                                                <div className="space-y-2">
                                                    <Label htmlFor="first_name">
                                                        First name
                                                    </Label>
                                                    <Input
                                                        id="first_name"
                                                        name="first_name"
                                                        value={
                                                            individualForm.first_name
                                                        }
                                                        onChange={(event) =>
                                                            setIndividualForm(
                                                                (current) => ({
                                                                    ...current,
                                                                    first_name:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                }),
                                                            )
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.first_name
                                                        }
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label htmlFor="middle_name">
                                                        Middle name
                                                    </Label>
                                                    <Input
                                                        id="middle_name"
                                                        name="middle_name"
                                                        value={
                                                            individualForm.middle_name
                                                        }
                                                        onChange={(event) =>
                                                            setIndividualForm(
                                                                (current) => ({
                                                                    ...current,
                                                                    middle_name:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                }),
                                                            )
                                                        }
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label htmlFor="last_name">
                                                        Last name
                                                    </Label>
                                                    <Input
                                                        id="last_name"
                                                        name="last_name"
                                                        value={
                                                            individualForm.last_name
                                                        }
                                                        onChange={(event) =>
                                                            setIndividualForm(
                                                                (current) => ({
                                                                    ...current,
                                                                    last_name:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                }),
                                                            )
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.last_name
                                                        }
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label htmlFor="suffix">
                                                        Suffix
                                                    </Label>
                                                    <Input
                                                        id="suffix"
                                                        name="suffix"
                                                        value={
                                                            individualForm.suffix
                                                        }
                                                        onChange={(event) =>
                                                            setIndividualForm(
                                                                (current) => ({
                                                                    ...current,
                                                                    suffix: event
                                                                        .target
                                                                        .value,
                                                                }),
                                                            )
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-4 md:col-span-2 md:grid-cols-2 lg:grid-cols-4">
                                                    <div className="space-y-2">
                                                        <Label htmlFor="birthday">
                                                            Birthday
                                                        </Label>
                                                        <Input
                                                            id="birthday"
                                                            name="birthday"
                                                            type="date"
                                                            value={
                                                                individualForm.birthday
                                                            }
                                                            onChange={(event) =>
                                                                setIndividualForm(
                                                                    (
                                                                        current,
                                                                    ) => ({
                                                                        ...current,
                                                                        birthday:
                                                                            event
                                                                                .target
                                                                                .value,
                                                                    }),
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                    <div className="space-y-2">
                                                        <Label htmlFor="sex">
                                                            Sex
                                                        </Label>
                                                        <Select
                                                            value={
                                                                individualForm.sex
                                                            }
                                                            onValueChange={(
                                                                value,
                                                            ) =>
                                                                setIndividualForm(
                                                                    (
                                                                        current,
                                                                    ) => ({
                                                                        ...current,
                                                                        sex: value as
                                                                            | 'Male'
                                                                            | 'Female',
                                                                    }),
                                                                )
                                                            }
                                                        >
                                                            <SelectTrigger
                                                                className="w-full"
                                                                id="sex"
                                                            >
                                                                <SelectValue placeholder="Select sex" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem value="Male">
                                                                    Male
                                                                </SelectItem>
                                                                <SelectItem value="Female">
                                                                    Female
                                                                </SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                        <input
                                                            type="hidden"
                                                            name="sex"
                                                            value={
                                                                individualForm.sex
                                                            }
                                                        />
                                                    </div>
                                                    <div className="space-y-2">
                                                        <Label htmlFor="mobile_number">
                                                            Mobile number
                                                        </Label>
                                                        <Input
                                                            id="mobile_number"
                                                            name="mobile_number"
                                                            value={
                                                                individualForm.mobile_number
                                                            }
                                                            onChange={(event) =>
                                                                setIndividualForm(
                                                                    (
                                                                        current,
                                                                    ) => ({
                                                                        ...current,
                                                                        mobile_number:
                                                                            event
                                                                                .target
                                                                                .value,
                                                                    }),
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                    <div className="space-y-2">
                                                        <Label htmlFor="civil_status_id">
                                                            Civil status
                                                        </Label>
                                                        <Select
                                                            value={
                                                                individualForm.civil_status_id
                                                                    ? String(
                                                                          individualForm.civil_status_id,
                                                                      )
                                                                    : undefined
                                                            }
                                                            onValueChange={(
                                                                value,
                                                            ) =>
                                                                setIndividualForm(
                                                                    (
                                                                        current,
                                                                    ) => ({
                                                                        ...current,
                                                                        civil_status_id:
                                                                            Number(
                                                                                value,
                                                                            ),
                                                                    }),
                                                                )
                                                            }
                                                        >
                                                            <SelectTrigger
                                                                className="w-full"
                                                                id="civil_status_id"
                                                            >
                                                                <SelectValue placeholder="Select civil status" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {form_options.civil_statuses.map(
                                                                    (
                                                                        status,
                                                                    ) => (
                                                                        <SelectItem
                                                                            key={
                                                                                status.id
                                                                            }
                                                                            value={String(
                                                                                status.id,
                                                                            )}
                                                                        >
                                                                            {
                                                                                status.name
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectContent>
                                                        </Select>
                                                        <input
                                                            type="hidden"
                                                            name="civil_status_id"
                                                            value={
                                                                individualForm.civil_status_id ??
                                                                ''
                                                            }
                                                        />
                                                    </div>
                                                </div>
                                                <div className="space-y-2 md:col-span-2">
                                                    <AddressCascadeSelect
                                                        provinces={
                                                            form_options.address_provinces
                                                        }
                                                        defaultProvinceId={
                                                            form_options.default_province_id
                                                        }
                                                        cities={
                                                            form_options.address_cities
                                                        }
                                                        barangays={
                                                            form_options.address_barangays
                                                        }
                                                        value={
                                                            individualForm.address_barangay_id
                                                        }
                                                        onChange={(
                                                            barangayId,
                                                        ) =>
                                                            setIndividualForm(
                                                                (current) => ({
                                                                    ...current,
                                                                    address_barangay_id:
                                                                        barangayId,
                                                                }),
                                                            )
                                                        }
                                                        name="address_barangay_id"
                                                        error={
                                                            errors.address_barangay_id
                                                        }
                                                        idPrefix="individual_address"
                                                    />
                                                </div>
                                                <div className="space-y-2 md:col-span-2">
                                                    <Label htmlFor="other_address">
                                                        Other address
                                                    </Label>
                                                    <Textarea
                                                        id="other_address"
                                                        name="other_address"
                                                        value={
                                                            individualForm.other_address
                                                        }
                                                        onChange={(event) =>
                                                            setIndividualForm(
                                                                (current) => ({
                                                                    ...current,
                                                                    other_address:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                }),
                                                            )
                                                        }
                                                    />
                                                </div>
                                                <div className="space-y-2 md:col-span-2">
                                                    <Label htmlFor="spouse">
                                                        Spouse
                                                    </Label>
                                                    <Input
                                                        id="spouse"
                                                        name="spouse"
                                                        value={
                                                            individualForm.spouse
                                                        }
                                                        onChange={(event) =>
                                                            setIndividualForm(
                                                                (current) => ({
                                                                    ...current,
                                                                    spouse: event
                                                                        .target
                                                                        .value,
                                                                }),
                                                            )
                                                        }
                                                    />
                                                    <InputError
                                                        message={errors.spouse}
                                                    />
                                                </div>
                                                <div className="space-y-2 md:col-span-2">
                                                    <Label htmlFor="ethnicity">
                                                        Ethnicity
                                                    </Label>
                                                    <Input
                                                        id="ethnicity"
                                                        name="ethnicity"
                                                        value={
                                                            individualForm.ethnicity
                                                        }
                                                        onChange={(event) =>
                                                            setIndividualForm(
                                                                (current) => ({
                                                                    ...current,
                                                                    ethnicity:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                }),
                                                            )
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.ethnicity
                                                        }
                                                    />
                                                </div>
                                            </div>

                                            <Separator />

                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <div className="flex items-center gap-3">
                                                    <Checkbox
                                                        id="indigenous"
                                                        checked={
                                                            individualForm.indigenous
                                                        }
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            setIndividualForm(
                                                                (current) => ({
                                                                    ...current,
                                                                    indigenous:
                                                                        checked ===
                                                                        true,
                                                                }),
                                                            )
                                                        }
                                                    />
                                                    <Label
                                                        htmlFor="indigenous"
                                                        className="font-normal"
                                                    >
                                                        Indigenous
                                                    </Label>
                                                    <input
                                                        type="hidden"
                                                        name="indigenous"
                                                        value={
                                                            individualForm.indigenous
                                                                ? '1'
                                                                : '0'
                                                        }
                                                    />
                                                </div>
                                                <div className="flex items-center gap-3">
                                                    <Checkbox
                                                        id="pwd"
                                                        checked={
                                                            individualForm.pwd
                                                        }
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            setIndividualForm(
                                                                (current) => ({
                                                                    ...current,
                                                                    pwd:
                                                                        checked ===
                                                                        true,
                                                                }),
                                                            )
                                                        }
                                                    />
                                                    <Label
                                                        htmlFor="pwd"
                                                        className="font-normal"
                                                    >
                                                        Person with disability
                                                    </Label>
                                                    <input
                                                        type="hidden"
                                                        name="pwd"
                                                        value={
                                                            individualForm.pwd
                                                                ? '1'
                                                                : '0'
                                                        }
                                                    />
                                                </div>
                                                <div className="flex items-center gap-3">
                                                    <Checkbox
                                                        id="is_4ps_beneficiary"
                                                        checked={
                                                            individualForm.is_4ps_beneficiary
                                                        }
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            setIndividualForm(
                                                                (current) => ({
                                                                    ...current,
                                                                    is_4ps_beneficiary:
                                                                        checked ===
                                                                        true,
                                                                }),
                                                            )
                                                        }
                                                    />
                                                    <Label
                                                        htmlFor="is_4ps_beneficiary"
                                                        className="font-normal"
                                                    >
                                                        4Ps beneficiary
                                                    </Label>
                                                    <input
                                                        type="hidden"
                                                        name="is_4ps_beneficiary"
                                                        value={
                                                            individualForm.is_4ps_beneficiary
                                                                ? '1'
                                                                : '0'
                                                        }
                                                    />
                                                </div>
                                                <div className="flex items-center gap-3">
                                                    <Checkbox
                                                        id="is_solo_parent"
                                                        checked={
                                                            individualForm.is_solo_parent
                                                        }
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            setIndividualForm(
                                                                (current) => ({
                                                                    ...current,
                                                                    is_solo_parent:
                                                                        checked ===
                                                                        true,
                                                                }),
                                                            )
                                                        }
                                                    />
                                                    <Label
                                                        htmlFor="is_solo_parent"
                                                        className="font-normal"
                                                    >
                                                        Solo parent
                                                    </Label>
                                                    <input
                                                        type="hidden"
                                                        name="is_solo_parent"
                                                        value={
                                                            individualForm.is_solo_parent
                                                                ? '1'
                                                                : '0'
                                                        }
                                                    />
                                                </div>
                                            </div>

                                            <Separator />

                                            <div className="space-y-3">
                                                <div className="flex items-center justify-between">
                                                    <Label>
                                                        Identifications
                                                    </Label>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={
                                                            addIdentificationRow
                                                        }
                                                    >
                                                        Add ID
                                                    </Button>
                                                </div>
                                                {individualForm.identifications.map(
                                                    (entry, index) => (
                                                        <div
                                                            key={`${entry.identification_id}-${index}`}
                                                            className="grid gap-3 md:grid-cols-[1fr_2fr_auto]"
                                                        >
                                                            <Select
                                                                value={String(
                                                                    entry.identification_id,
                                                                )}
                                                                onValueChange={(
                                                                    value,
                                                                ) =>
                                                                    setIndividualForm(
                                                                        (
                                                                            current,
                                                                        ) => ({
                                                                            ...current,
                                                                            identifications:
                                                                                current.identifications.map(
                                                                                    (
                                                                                        row,
                                                                                        rowIndex,
                                                                                    ) =>
                                                                                        rowIndex ===
                                                                                        index
                                                                                            ? {
                                                                                                  ...row,
                                                                                                  identification_id:
                                                                                                      Number(
                                                                                                          value,
                                                                                                      ),
                                                                                              }
                                                                                            : row,
                                                                                ),
                                                                        }),
                                                                    )
                                                                }
                                                            >
                                                                <SelectTrigger className="w-full">
                                                                    <SelectValue placeholder="ID type" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {form_options.identifications.map(
                                                                        (
                                                                            identification,
                                                                        ) => (
                                                                            <SelectItem
                                                                                key={
                                                                                    identification.id
                                                                                }
                                                                                value={String(
                                                                                    identification.id,
                                                                                )}
                                                                            >
                                                                                {
                                                                                    identification.name
                                                                                }
                                                                            </SelectItem>
                                                                        ),
                                                                    )}
                                                                </SelectContent>
                                                            </Select>
                                                            <Input
                                                                value={
                                                                    entry.number
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    setIndividualForm(
                                                                        (
                                                                            current,
                                                                        ) => ({
                                                                            ...current,
                                                                            identifications:
                                                                                current.identifications.map(
                                                                                    (
                                                                                        row,
                                                                                        rowIndex,
                                                                                    ) =>
                                                                                        rowIndex ===
                                                                                        index
                                                                                            ? {
                                                                                                  ...row,
                                                                                                  number: event
                                                                                                      .target
                                                                                                      .value,
                                                                                              }
                                                                                            : row,
                                                                                ),
                                                                        }),
                                                                    )
                                                                }
                                                                placeholder="ID number"
                                                            />
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                onClick={() =>
                                                                    setIndividualForm(
                                                                        (
                                                                            current,
                                                                        ) => ({
                                                                            ...current,
                                                                            identifications:
                                                                                current.identifications.filter(
                                                                                    (
                                                                                        _,
                                                                                        rowIndex,
                                                                                    ) =>
                                                                                        rowIndex !==
                                                                                        index,
                                                                                ),
                                                                        }),
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 className="size-4" />
                                                            </Button>
                                                            <input
                                                                type="hidden"
                                                                name={`identifications[${index}][identification_id]`}
                                                                value={
                                                                    entry.identification_id
                                                                }
                                                            />
                                                            <input
                                                                type="hidden"
                                                                name={`identifications[${index}][number]`}
                                                                value={
                                                                    entry.number
                                                                }
                                                            />
                                                        </div>
                                                    ),
                                                )}
                                            </div>

                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Save individual
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="organization" className="mt-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Organization beneficiary</CardTitle>
                                <CardDescription>
                                    Register an organization and attach a
                                    president plus optional members.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    {...storeOrganization.form(department.slug)}
                                    className="grid gap-4"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-4 md:grid-cols-2">
                                                <div className="space-y-2 md:col-span-2">
                                                    <Label htmlFor="name">
                                                        Organization name
                                                    </Label>
                                                    <Input
                                                        id="name"
                                                        name="name"
                                                    />
                                                    <InputError
                                                        message={errors.name}
                                                    />
                                                </div>
                                                <div className="space-y-2 md:col-span-2">
                                                    <BeneficiarySearchCombobox
                                                        departmentSlug={
                                                            department.slug
                                                        }
                                                        beneficiaryType="individual"
                                                        label="President / representative"
                                                        includeHiddenInput={
                                                            false
                                                        }
                                                        value={
                                                            presidentBeneficiaryId
                                                        }
                                                        initialOption={
                                                            presidentOption
                                                        }
                                                        onChange={(
                                                            value,
                                                            option,
                                                        ) => {
                                                            setPresidentBeneficiaryId(
                                                                value,
                                                            );
                                                            setPresidentIndividualId(
                                                                option?.individual_id ??
                                                                    null,
                                                            );
                                                            setPresidentOption(
                                                                option,
                                                            );
                                                        }}
                                                        error={
                                                            errors.beneficiary_id
                                                        }
                                                    />
                                                    <input
                                                        type="hidden"
                                                        name="beneficiary_id"
                                                        value={
                                                            presidentIndividualId ??
                                                            ''
                                                        }
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label htmlFor="mobile_number">
                                                        Mobile number
                                                    </Label>
                                                    <Input
                                                        id="mobile_number"
                                                        name="mobile_number"
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label htmlFor="total_member">
                                                        Total members
                                                    </Label>
                                                    <Input
                                                        id="total_member"
                                                        name="total_member"
                                                        type="number"
                                                        min={0}
                                                        defaultValue={
                                                            members.length + 1
                                                        }
                                                    />
                                                </div>
                                                <div className="space-y-2 md:col-span-2">
                                                    <AddressCascadeSelect
                                                        provinces={
                                                            form_options.address_provinces
                                                        }
                                                        defaultProvinceId={
                                                            form_options.default_province_id
                                                        }
                                                        cities={
                                                            form_options.address_cities
                                                        }
                                                        barangays={
                                                            form_options.address_barangays
                                                        }
                                                        value={orgBarangayId}
                                                        onChange={
                                                            setOrgBarangayId
                                                        }
                                                        name="addrs_brgy_id"
                                                        error={
                                                            errors.addrs_brgy_id
                                                        }
                                                        idPrefix="organization_address"
                                                    />
                                                </div>
                                            </div>

                                            <div className="space-y-3">
                                                <Label>Members</Label>
                                                <div className="flex flex-col gap-3 md:flex-row md:items-end">
                                                    <div className="flex-1">
                                                        <BeneficiarySearchCombobox
                                                            key={
                                                                memberPickerKey
                                                            }
                                                            departmentSlug={
                                                                department.slug
                                                            }
                                                            beneficiaryType="individual"
                                                            label="Add member"
                                                            includeHiddenInput={
                                                                false
                                                            }
                                                            value={
                                                                pendingMemberId
                                                            }
                                                            initialOption={
                                                                pendingMemberOption
                                                            }
                                                            onChange={(
                                                                value,
                                                                option,
                                                            ) => {
                                                                setPendingMemberId(
                                                                    value,
                                                                );
                                                                setPendingMemberOption(
                                                                    option,
                                                                );
                                                            }}
                                                        />
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        onClick={addMember}
                                                    >
                                                        Add member
                                                    </Button>
                                                </div>
                                                {members.length > 0 ? (
                                                    <ul className="divide-y rounded-md border">
                                                        {members.map(
                                                            (member) => (
                                                                <li
                                                                    key={
                                                                        member.individual_id
                                                                    }
                                                                    className="flex items-center justify-between px-3 py-2 text-sm"
                                                                >
                                                                    <span>
                                                                        {
                                                                            member
                                                                                .option
                                                                                .label
                                                                        }
                                                                    </span>
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        onClick={() =>
                                                                            setMembers(
                                                                                (
                                                                                    current,
                                                                                ) =>
                                                                                    current.filter(
                                                                                        (
                                                                                            row,
                                                                                        ) =>
                                                                                            row.individual_id !==
                                                                                            member.individual_id,
                                                                                    ),
                                                                            )
                                                                        }
                                                                    >
                                                                        <Trash2 className="size-4" />
                                                                    </Button>
                                                                </li>
                                                            ),
                                                        )}
                                                    </ul>
                                                ) : null}
                                                {members.map(
                                                    (member, index) => (
                                                        <input
                                                            key={
                                                                member.individual_id
                                                            }
                                                            type="hidden"
                                                            name={`member_ids[${index}]`}
                                                            value={
                                                                member.individual_id
                                                            }
                                                        />
                                                    ),
                                                )}
                                            </div>

                                            <Button
                                                type="submit"
                                                disabled={
                                                    processing ||
                                                    presidentIndividualId ===
                                                        null
                                                }
                                            >
                                                Save organization
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}
