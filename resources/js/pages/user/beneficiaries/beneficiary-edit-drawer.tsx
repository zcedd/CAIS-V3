'use client';

import { AddressCascadeSelect } from '@/components/address-cascade-select';
import {
    BeneficiarySearchCombobox,
    type BeneficiarySearchOption,
} from '@/components/beneficiary-search-combobox';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { edit as beneficiaryEdit } from '@/routes/user/beneficiaries';
import { update as updateIndividual } from '@/routes/user/beneficiaries/individuals';
import { update as updateOrganization } from '@/routes/user/beneficiaries/organizations';
import type {
    DepartmentSummary,
    FormOptions,
    IndividualFormData,
} from '@/types/beneficiary';
import { Form } from '@inertiajs/react';
import { Loader2, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

type EditPayload =
    | {
          type: 'individual';
          beneficiary_id: number;
          individual: {
              first_name: string;
              middle_name: string | null;
              last_name: string;
              suffix: string | null;
              birthday: string | null;
              sex: 'Male' | 'Female';
              other_address: string | null;
              civil_status_id: number | null;
              mobile_number: string | null;
              indigenous: boolean;
              ethnicity: string | null;
              pwd: boolean;
              is_4ps_beneficiary: boolean;
              is_solo_parent: boolean;
              spouse: string | null;
              address_barangay_id: number | null;
              identifications: Array<{
                  identification_id: number;
                  number: string;
              }>;
          };
      }
    | {
          type: 'organization';
          beneficiary_id: number;
          organization: {
              name: string;
              beneficiary_id: number;
              president: {
                  id: number;
                  name: string;
                  cais_number: string;
              } | null;
              addrs_brgy_id: number | null;
              mobile_number: string | null;
              total_member: number | null;
              members: Array<{
                  id: number;
                  name: string;
                  cais_number: string;
              }>;
          };
      };

type BeneficiaryEditDrawerProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    department: DepartmentSummary;
    beneficiaryId: number;
    beneficiaryType: 'individual' | 'organization';
    formOptions?: FormOptions;
};

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

function individualPayloadToForm(
    individual: Extract<EditPayload, { type: 'individual' }>['individual'],
): IndividualFormData {
    return {
        first_name: individual.first_name,
        middle_name: individual.middle_name ?? '',
        last_name: individual.last_name,
        suffix: individual.suffix ?? '',
        birthday: individual.birthday ?? '',
        sex: individual.sex,
        other_address: individual.other_address ?? '',
        civil_status_id: individual.civil_status_id,
        mobile_number: individual.mobile_number ?? '',
        indigenous: individual.indigenous,
        ethnicity: individual.ethnicity ?? '',
        pwd: individual.pwd,
        is_4ps_beneficiary: individual.is_4ps_beneficiary,
        is_solo_parent: individual.is_solo_parent,
        spouse: individual.spouse ?? '',
        address_barangay_id: individual.address_barangay_id,
        identifications: individual.identifications,
    };
}

export function BeneficiaryEditDrawer({
    open,
    onOpenChange,
    department,
    beneficiaryId,
    beneficiaryType,
    formOptions,
}: BeneficiaryEditDrawerProps) {
    const [loading, setLoading] = useState(false);
    const [payload, setPayload] = useState<EditPayload | null>(null);
    const [individualForm, setIndividualForm] =
        useState<IndividualFormData>(emptyIndividualForm());
    const [orgName, setOrgName] = useState('');
    const [orgMobile, setOrgMobile] = useState('');
    const [totalMember, setTotalMember] = useState('');
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
    const [memberPickerKey, setMemberPickerKey] = useState(0);
    const [pendingMemberId, setPendingMemberId] = useState<number | null>(null);
    const [pendingMemberOption, setPendingMemberOption] =
        useState<BeneficiarySearchOption | null>(null);
    const [organizationBarangayId, setOrganizationBarangayId] = useState<
        number | null
    >(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        setLoading(true);
        setPayload(null);

        void fetch(
            beneficiaryEdit.url({
                department: department.slug,
                beneficiary: beneficiaryId,
            }),
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            },
        )
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error('Failed to load beneficiary');
                }

                const json = (await response.json()) as { data: EditPayload };
                setPayload(json.data);

                if (json.data.type === 'individual') {
                    setIndividualForm(
                        individualPayloadToForm(json.data.individual),
                    );
                }

                if (json.data.type === 'organization') {
                    const organization = json.data.organization;
                    setOrgName(organization.name);
                    setOrgMobile(organization.mobile_number ?? '');
                    setTotalMember(
                        String(
                            organization.total_member ??
                                organization.members.length + 1,
                        ),
                    );
                    setOrganizationBarangayId(organization.addrs_brgy_id);
                    const president = organization.president;
                    setPresidentIndividualId(organization.beneficiary_id);
                    setPresidentOption(
                        president
                            ? {
                                  id: 0,
                                  individual_id: president.id,
                                  organization_id: null,
                                  cais_number: president.cais_number,
                                  name: president.name,
                                  label: `${president.cais_number} — ${president.name}`,
                              }
                            : null,
                    );
                    setMembers(
                        organization.members.map((member) => ({
                            individual_id: member.id,
                            option: {
                                id: 0,
                                individual_id: member.id,
                                organization_id: null,
                                cais_number: member.cais_number,
                                name: member.name,
                                label: `${member.cais_number} — ${member.name}`,
                            },
                        })),
                    );
                }
            })
            .catch(() => {
                toast.error('Unable to load beneficiary for editing.');
                onOpenChange(false);
            })
            .finally(() => {
                setLoading(false);
            });
    }, [beneficiaryId, department.slug, onOpenChange, open]);

    const options = formOptions ?? {
        civil_statuses: [],
        identifications: [],
        address_provinces: [],
        default_province_id: null,
        address_cities: [],
        address_barangays: [],
    };

    const addIdentificationRow = () => {
        const firstType = options.identifications[0];
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
        <Drawer open={open} onOpenChange={onOpenChange} direction="right">
            <DrawerContent className="w-full data-[vaul-drawer-direction=right]:w-full sm:max-w-full data-[vaul-drawer-direction=right]:sm:max-w-full lg:max-w-3xl data-[vaul-drawer-direction=right]:lg:max-w-3xl">
                <DrawerHeader>
                    <DrawerTitle>Edit beneficiary</DrawerTitle>
                    <DrawerDescription>
                        Update beneficiary details and save your changes.
                    </DrawerDescription>
                </DrawerHeader>

                <div className="overflow-y-auto px-4 pb-4">
                    {loading || payload === null ? (
                        <div className="flex items-center justify-center py-12">
                            <Loader2 className="size-6 animate-spin text-muted-foreground" />
                        </div>
                    ) : payload.type === 'individual' ? (
                        <Form
                            {...updateIndividual.form({
                                department: department.slug,
                                beneficiary: beneficiaryId,
                            })}
                            className="grid gap-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="edit_first_name">
                                                First name
                                            </Label>
                                            <Input
                                                id="edit_first_name"
                                                name="first_name"
                                                value={
                                                    individualForm.first_name
                                                }
                                                onChange={(event) =>
                                                    setIndividualForm(
                                                        (current) => ({
                                                            ...current,
                                                            first_name:
                                                                event.target
                                                                    .value,
                                                        }),
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={errors.first_name}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="edit_middle_name">
                                                Middle name
                                            </Label>
                                            <Input
                                                id="edit_middle_name"
                                                name="middle_name"
                                                value={
                                                    individualForm.middle_name
                                                }
                                                onChange={(event) =>
                                                    setIndividualForm(
                                                        (current) => ({
                                                            ...current,
                                                            middle_name:
                                                                event.target
                                                                    .value,
                                                        }),
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={errors.middle_name}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="edit_last_name">
                                                Last name
                                            </Label>
                                            <Input
                                                id="edit_last_name"
                                                name="last_name"
                                                value={
                                                    individualForm.last_name
                                                }
                                                onChange={(event) =>
                                                    setIndividualForm(
                                                        (current) => ({
                                                            ...current,
                                                            last_name:
                                                                event.target
                                                                    .value,
                                                        }),
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={errors.last_name}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="edit_suffix">
                                                Suffix
                                            </Label>
                                            <Input
                                                id="edit_suffix"
                                                name="suffix"
                                                value={individualForm.suffix}
                                                onChange={(event) =>
                                                    setIndividualForm(
                                                        (current) => ({
                                                            ...current,
                                                            suffix: event.target
                                                                .value,
                                                        }),
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={errors.suffix}
                                            />
                                        </div>
                                        <div className="grid gap-4 md:col-span-2 md:grid-cols-2 lg:grid-cols-4">
                                            <div className="space-y-2">
                                                <Label htmlFor="edit_birthday">
                                                    Birthday
                                                </Label>
                                                <Input
                                                    id="edit_birthday"
                                                    name="birthday"
                                                    type="date"
                                                    value={
                                                        individualForm.birthday
                                                    }
                                                    onChange={(event) =>
                                                        setIndividualForm(
                                                            (current) => ({
                                                                ...current,
                                                                birthday:
                                                                    event.target
                                                                        .value,
                                                            }),
                                                        )
                                                    }
                                                />
                                                <InputError
                                                    message={errors.birthday}
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="edit_sex">
                                                    Sex
                                                </Label>
                                                <Select
                                                    value={individualForm.sex}
                                                    onValueChange={(value) =>
                                                        setIndividualForm(
                                                            (current) => ({
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
                                                        id="edit_sex"
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
                                                    value={individualForm.sex}
                                                />
                                                <InputError
                                                    message={errors.sex}
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="edit_mobile_number">
                                                    Mobile number
                                                </Label>
                                                <Input
                                                    id="edit_mobile_number"
                                                    name="mobile_number"
                                                    value={
                                                        individualForm.mobile_number
                                                    }
                                                    onChange={(event) =>
                                                        setIndividualForm(
                                                            (current) => ({
                                                                ...current,
                                                                mobile_number:
                                                                    event.target
                                                                        .value,
                                                            }),
                                                        )
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        errors.mobile_number
                                                    }
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="edit_civil_status_id">
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
                                                    onValueChange={(value) =>
                                                        setIndividualForm(
                                                            (current) => ({
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
                                                        id="edit_civil_status_id"
                                                    >
                                                        <SelectValue placeholder="Select civil status" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {options.civil_statuses.map(
                                                            (status) => (
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
                                                <InputError
                                                    message={
                                                        errors.civil_status_id
                                                    }
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <Separator />

                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div className="space-y-2 md:col-span-2">
                                            <AddressCascadeSelect
                                                provinces={
                                                    options.address_provinces
                                                }
                                                defaultProvinceId={
                                                    options.default_province_id
                                                }
                                                cities={options.address_cities}
                                                barangays={
                                                    options.address_barangays
                                                }
                                                value={
                                                    individualForm.address_barangay_id
                                                }
                                                onChange={(barangayId) =>
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
                                                idPrefix="edit_individual_address"
                                            />
                                        </div>
                                        <div className="space-y-2 md:col-span-2">
                                            <Label htmlFor="edit_other_address">
                                                Other address
                                            </Label>
                                            <Textarea
                                                id="edit_other_address"
                                                name="other_address"
                                                value={
                                                    individualForm.other_address
                                                }
                                                onChange={(event) =>
                                                    setIndividualForm(
                                                        (current) => ({
                                                            ...current,
                                                            other_address:
                                                                event.target
                                                                    .value,
                                                        }),
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={errors.other_address}
                                            />
                                        </div>
                                        <div className="space-y-2 md:col-span-2">
                                            <Label htmlFor="edit_spouse">
                                                Spouse
                                            </Label>
                                            <Input
                                                id="edit_spouse"
                                                name="spouse"
                                                value={individualForm.spouse}
                                                onChange={(event) =>
                                                    setIndividualForm(
                                                        (current) => ({
                                                            ...current,
                                                            spouse: event.target
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
                                            <Label htmlFor="edit_ethnicity">
                                                Ethnicity
                                            </Label>
                                            <Input
                                                id="edit_ethnicity"
                                                name="ethnicity"
                                                value={
                                                    individualForm.ethnicity
                                                }
                                                onChange={(event) =>
                                                    setIndividualForm(
                                                        (current) => ({
                                                            ...current,
                                                            ethnicity:
                                                                event.target
                                                                    .value,
                                                        }),
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={errors.ethnicity}
                                            />
                                        </div>
                                    </div>

                                    <Separator />

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="flex items-center gap-3">
                                            <Checkbox
                                                id="edit_indigenous"
                                                checked={
                                                    individualForm.indigenous
                                                }
                                                onCheckedChange={(checked) =>
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
                                                htmlFor="edit_indigenous"
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
                                                id="edit_pwd"
                                                checked={individualForm.pwd}
                                                onCheckedChange={(checked) =>
                                                    setIndividualForm(
                                                        (current) => ({
                                                            ...current,
                                                            pwd: checked === true,
                                                        }),
                                                    )
                                                }
                                            />
                                            <Label
                                                htmlFor="edit_pwd"
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
                                                id="edit_is_4ps_beneficiary"
                                                checked={
                                                    individualForm.is_4ps_beneficiary
                                                }
                                                onCheckedChange={(checked) =>
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
                                                htmlFor="edit_is_4ps_beneficiary"
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
                                                id="edit_is_solo_parent"
                                                checked={
                                                    individualForm.is_solo_parent
                                                }
                                                onCheckedChange={(checked) =>
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
                                                htmlFor="edit_is_solo_parent"
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
                                            <Label>Identifications</Label>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={addIdentificationRow}
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
                                                                (current) => ({
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
                                                            {options.identifications.map(
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
                                                        value={entry.number}
                                                        onChange={(event) =>
                                                            setIndividualForm(
                                                                (current) => ({
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
                                                                (current) => ({
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
                                                        value={entry.number}
                                                    />
                                                </div>
                                            ),
                                        )}
                                        <InputError
                                            message={errors.identifications}
                                        />
                                    </div>

                                    <DrawerFooter className="px-0">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save changes
                                        </Button>
                                        <DrawerClose asChild>
                                            <Button variant="outline">
                                                Cancel
                                            </Button>
                                        </DrawerClose>
                                    </DrawerFooter>
                                </>
                            )}
                        </Form>
                    ) : (
                        <Form
                            {...updateOrganization.form({
                                department: department.slug,
                                beneficiary: beneficiaryId,
                            })}
                            className="grid gap-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div className="space-y-2 md:col-span-2">
                                            <Label htmlFor="edit_org_name">
                                                Organization name
                                            </Label>
                                            <Input
                                                id="edit_org_name"
                                                name="name"
                                                value={orgName}
                                                onChange={(event) =>
                                                    setOrgName(
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <InputError message={errors.name} />
                                        </div>
                                        <div className="space-y-2 md:col-span-2">
                                            <BeneficiarySearchCombobox
                                                departmentSlug={department.slug}
                                                beneficiaryType="individual"
                                                label="President / representative"
                                                includeHiddenInput={false}
                                                value={presidentBeneficiaryId}
                                                initialOption={presidentOption}
                                                onChange={(value, option) => {
                                                    setPresidentBeneficiaryId(
                                                        value,
                                                    );
                                                    setPresidentIndividualId(
                                                        option?.individual_id ??
                                                            null,
                                                    );
                                                    setPresidentOption(option);
                                                }}
                                                error={errors.beneficiary_id}
                                            />
                                            <input
                                                type="hidden"
                                                name="beneficiary_id"
                                                value={
                                                    presidentIndividualId ?? ''
                                                }
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="edit_org_mobile">
                                                Mobile number
                                            </Label>
                                            <Input
                                                id="edit_org_mobile"
                                                name="mobile_number"
                                                value={orgMobile}
                                                onChange={(event) =>
                                                    setOrgMobile(
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={errors.mobile_number}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="edit_total_member">
                                                Total members
                                            </Label>
                                            <Input
                                                id="edit_total_member"
                                                name="total_member"
                                                type="number"
                                                min={0}
                                                value={totalMember}
                                                onChange={(event) =>
                                                    setTotalMember(
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={errors.total_member}
                                            />
                                        </div>
                                        <div className="space-y-2 md:col-span-2">
                                            <AddressCascadeSelect
                                                provinces={
                                                    options.address_provinces
                                                }
                                                defaultProvinceId={
                                                    options.default_province_id
                                                }
                                                cities={options.address_cities}
                                                barangays={
                                                    options.address_barangays
                                                }
                                                value={organizationBarangayId}
                                                onChange={
                                                    setOrganizationBarangayId
                                                }
                                                name="addrs_brgy_id"
                                                error={errors.addrs_brgy_id}
                                                idPrefix="edit_organization_address"
                                            />
                                        </div>
                                    </div>

                                    <Separator />

                                    <div className="space-y-3">
                                        <Label>Members</Label>
                                        <div className="flex flex-col gap-3 md:flex-row md:items-end">
                                            <div className="flex-1">
                                                <BeneficiarySearchCombobox
                                                    key={memberPickerKey}
                                                    departmentSlug={
                                                        department.slug
                                                    }
                                                    beneficiaryType="individual"
                                                    label="Add member"
                                                    includeHiddenInput={false}
                                                    value={pendingMemberId}
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
                                            <ul className="divide-y rounded-md border text-sm">
                                                {members.map((member) => (
                                                    <li
                                                        key={
                                                            member.individual_id
                                                        }
                                                        className="flex items-center justify-between px-3 py-2"
                                                    >
                                                        <span>
                                                            {member.option.label}
                                                        </span>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                setMembers(
                                                                    (current) =>
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
                                                ))}
                                            </ul>
                                        ) : null}
                                        {members.map((member, index) => (
                                            <input
                                                key={member.individual_id}
                                                type="hidden"
                                                name={`member_ids[${index}]`}
                                                value={member.individual_id}
                                            />
                                        ))}
                                        <InputError
                                            message={errors.member_ids}
                                        />
                                    </div>

                                    <DrawerFooter className="px-0">
                                        <Button
                                            type="submit"
                                            disabled={
                                                processing ||
                                                presidentIndividualId === null
                                            }
                                        >
                                            Save changes
                                        </Button>
                                        <DrawerClose asChild>
                                            <Button variant="outline">
                                                Cancel
                                            </Button>
                                        </DrawerClose>
                                    </DrawerFooter>
                                </>
                            )}
                        </Form>
                    )}
                </div>
            </DrawerContent>
        </Drawer>
    );
}
