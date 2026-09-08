import { AssistanceFieldInputs } from '@/components/assistance-field-inputs';
import { AddressCascadeSelect } from '@/components/address-cascade-select';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import {
    duplicates as applyDuplicates,
    store as applyStore,
} from '@/routes/public/apply';
import type { FormOptions } from '@/types/beneficiary';
import type { ProgramFieldOption } from '@/types/program-field';
import { Head, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

type ProgramItem = {
    id: number;
    name: string;
    unit: string | null;
    kind: string;
};

type DuplicateMatch = {
    id: number;
    cais_number: string | null;
    name: string;
    birth_year: number | null;
    barangay: string | null;
};

type ResumePayload = {
    cais_number: string | null;
    confirmed_beneficiary_id: number;
    identity: {
        first_name: string;
        middle_name: string | null;
        last_name: string;
        suffix: string | null;
        birthday: string | null;
        sex: string | null;
        other_address: string | null;
        mobile_number: string | null;
        indigenous: boolean;
        ethnicity: string | null;
        pwd: boolean;
        is_4ps_beneficiary: boolean;
        is_solo_parent: boolean;
        address_barangay_id: number | null;
    } | null;
    item_details: Array<{
        item_id: number;
        quantity: number;
        specification: string | null;
    }>;
    field_values: Record<number, string>;
    remark: string | null;
};

type IntakeProgram = {
    id: number;
    name: string;
    descriptions: string | null;
    department_name: string | null;
};

function xsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export default function PublicApplyShow({
    program,
    items,
    fields,
    form_options,
    resume,
    kiosk = false,
}: {
    program: IntakeProgram;
    items: ProgramItem[];
    fields: ProgramFieldOption[];
    form_options: FormOptions;
    resume: ResumePayload | null;
    kiosk?: boolean;
}) {
    const initialIdentity = resume?.identity;
    const [step, setStep] = useState<'identity' | 'match' | 'request'>(
        resume ? 'request' : 'identity',
    );
    const [matches, setMatches] = useState<DuplicateMatch[]>([]);
    const [checkingMatches, setCheckingMatches] = useState(false);
    const [matchError, setMatchError] = useState<string | null>(null);

    const initialQuantities = useMemo(() => {
        const quantities: Record<number, number> = {};

        for (const item of items) {
            quantities[item.id] = 0;
        }

        for (const detail of resume?.item_details ?? []) {
            quantities[detail.item_id] = detail.quantity;
        }

        return quantities;
    }, [items, resume]);

    const { data, setData, post, processing, errors, transform } = useForm({
        intent: 'submit',
        consent: false,
        confirmed_beneficiary_id: resume?.confirmed_beneficiary_id ?? null,
        create_new: resume ? false : true,
        first_name: initialIdentity?.first_name ?? '',
        middle_name: initialIdentity?.middle_name ?? '',
        last_name: initialIdentity?.last_name ?? '',
        suffix: initialIdentity?.suffix ?? '',
        birthday: initialIdentity?.birthday ?? '',
        sex: initialIdentity?.sex ?? '',
        other_address: initialIdentity?.other_address ?? '',
        mobile_number: initialIdentity?.mobile_number ?? '',
        indigenous: initialIdentity?.indigenous ?? false,
        ethnicity: initialIdentity?.ethnicity ?? '',
        pwd: initialIdentity?.pwd ?? false,
        is_4ps_beneficiary: initialIdentity?.is_4ps_beneficiary ?? false,
        is_solo_parent: initialIdentity?.is_solo_parent ?? false,
        address_barangay_id: initialIdentity?.address_barangay_id ?? null,
        remark: resume?.remark ?? '',
        item_quantities: initialQuantities,
        field_values: resume?.field_values ?? ({} as Record<number, string>),
    });

    async function continueFromIdentity(): Promise<void> {
        setCheckingMatches(true);
        setMatchError(null);

        try {
            const response = await fetch(applyDuplicates.url(program.id), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({
                    first_name: data.first_name,
                    last_name: data.last_name,
                    birthday: data.birthday,
                    address_barangay_id: data.address_barangay_id,
                }),
            });

            if (!response.ok) {
                setMatchError('Could not check for existing records. Please try again.');

                return;
            }

            const payload = (await response.json()) as {
                matches: DuplicateMatch[];
            };

            if (payload.matches.length > 0) {
                setMatches(payload.matches);
                setData('create_new', false);
                setData('confirmed_beneficiary_id', null);
                setStep('match');

                return;
            }

            setMatches([]);
            setData('create_new', true);
            setData('confirmed_beneficiary_id', null);
            setStep('request');
        } finally {
            setCheckingMatches(false);
        }
    }

    function submitIntent(intent: 'save' | 'submit'): void {
        const itemDetails = items
            .filter((item) => (data.item_quantities[item.id] ?? 0) > 0)
            .map((item) => ({
                item_id: item.id,
                quantity: data.item_quantities[item.id],
                specification: null,
            }));

        transform((form) => ({
            ...form,
            intent,
            consent: intent === 'submit' ? form.consent : true,
            item_details: itemDetails,
            field_values: fields.map((field) => ({
                program_field_id: field.id,
                value: form.field_values[field.id] ?? '',
            })),
        }));

        post(applyStore.url(program.id));
    }

    return (
        <>
            <Head title={program.name} />
            <div className={cn('space-y-6', kiosk && 'text-base')}>
                <div className="space-y-1">
                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        {program.department_name}
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {program.name}
                    </h1>
                    {program.descriptions ? (
                        <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                            {program.descriptions}
                        </p>
                    ) : null}
                </div>

                {step === 'identity' ? (
                    <div className="space-y-4">
                        <h2 className="text-lg font-medium">Your details</h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="first_name">First name</Label>
                                <Input
                                    id="first_name"
                                    value={data.first_name}
                                    onChange={(event) =>
                                        setData('first_name', event.target.value)
                                    }
                                />
                                <InputError message={errors.first_name} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="middle_name">Middle name</Label>
                                <Input
                                    id="middle_name"
                                    value={data.middle_name}
                                    onChange={(event) =>
                                        setData('middle_name', event.target.value)
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="last_name">Last name</Label>
                                <Input
                                    id="last_name"
                                    value={data.last_name}
                                    onChange={(event) =>
                                        setData('last_name', event.target.value)
                                    }
                                />
                                <InputError message={errors.last_name} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="suffix">Suffix</Label>
                                <Input
                                    id="suffix"
                                    value={data.suffix}
                                    onChange={(event) =>
                                        setData('suffix', event.target.value)
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="birthday">Birthday</Label>
                                <Input
                                    id="birthday"
                                    type="date"
                                    value={data.birthday}
                                    onChange={(event) =>
                                        setData('birthday', event.target.value)
                                    }
                                />
                                <InputError message={errors.birthday} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="sex">Sex</Label>
                                <Select
                                    value={data.sex}
                                    onValueChange={(value) =>
                                        setData('sex', value ?? '')
                                    }
                                >
                                    <SelectTrigger id="sex">
                                        <SelectValue placeholder="Select" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="Male">Male</SelectItem>
                                        <SelectItem value="Female">
                                            Female
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.sex} />
                            </div>
                            <div className="space-y-2 sm:col-span-2">
                                <Label htmlFor="mobile_number">Mobile number</Label>
                                <Input
                                    id="mobile_number"
                                    value={data.mobile_number}
                                    onChange={(event) =>
                                        setData(
                                            'mobile_number',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                        </div>

                        <AddressCascadeSelect
                            provinces={form_options.address_provinces}
                            defaultProvinceId={form_options.default_province_id}
                            cities={form_options.address_cities}
                            barangays={form_options.address_barangays}
                            value={data.address_barangay_id}
                            onChange={(barangayId) =>
                                setData('address_barangay_id', barangayId)
                            }
                            name="address_barangay_id"
                            error={errors.address_barangay_id}
                            idPrefix="public-intake"
                        />

                        <div className="grid gap-3">
                            <label className="flex items-center gap-2 text-sm">
                                <Input
                                    type="checkbox"
                                    checked={data.pwd}
                                    onChange={(event) =>
                                        setData('pwd', event.target.checked)
                                    }
                                    className="size-4"
                                />
                                Person with disability
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <Input
                                    type="checkbox"
                                    checked={data.is_4ps_beneficiary}
                                    onChange={(event) =>
                                        setData(
                                            'is_4ps_beneficiary',
                                            event.target.checked,
                                        )
                                    }
                                    className="size-4"
                                />
                                4Ps beneficiary
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <Input
                                    type="checkbox"
                                    checked={data.is_solo_parent}
                                    onChange={(event) =>
                                        setData(
                                            'is_solo_parent',
                                            event.target.checked,
                                        )
                                    }
                                    className="size-4"
                                />
                                Solo parent
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <Input
                                    type="checkbox"
                                    checked={data.indigenous}
                                    onChange={(event) =>
                                        setData('indigenous', event.target.checked)
                                    }
                                    className="size-4"
                                />
                                Indigenous peoples
                            </label>
                        </div>

                        {matchError ? (
                            <InputError message={matchError} />
                        ) : null}

                        <Button
                            type="button"
                            onClick={() => void continueFromIdentity()}
                            disabled={checkingMatches}
                        >
                            {checkingMatches ? 'Checking...' : 'Continue'}
                        </Button>
                    </div>
                ) : null}

                {step === 'match' ? (
                    <div className="space-y-4">
                        <h2 className="text-lg font-medium">Is this you?</h2>
                        <p className="text-sm text-muted-foreground">
                            We found an existing record that matches the details
                            you entered. Confirm it, or create a new profile.
                        </p>
                        <ul className="space-y-3">
                            {matches.map((match) => (
                                <li key={match.id}>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setData(
                                                'confirmed_beneficiary_id',
                                                match.id,
                                            );
                                            setData('create_new', false);
                                            setStep('request');
                                        }}
                                        className="w-full rounded-xl border border-border p-4 text-left hover:border-foreground/25 hover:bg-muted/30"
                                    >
                                        <p className="font-medium">{match.name}</p>
                                        <p className="text-sm text-muted-foreground">
                                            {match.cais_number}
                                            {match.birth_year
                                                ? ` · Born ${match.birth_year}`
                                                : ''}
                                            {match.barangay
                                                ? ` · ${match.barangay}`
                                                : ''}
                                        </p>
                                    </button>
                                </li>
                            ))}
                        </ul>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                setData('confirmed_beneficiary_id', null);
                                setData('create_new', true);
                                setStep('request');
                            }}
                        >
                            None of these, create a new profile
                        </Button>
                        <InputError message={errors.confirmed_beneficiary_id} />
                    </div>
                ) : null}

                {step === 'request' ? (
                    <div className="space-y-4">
                        <h2 className="text-lg font-medium">Requested items</h2>
                        <InputError message={errors.item_details} />
                        <InputError message={errors.eligibility} />
                        <div className="space-y-3">
                            {items.map((item) => (
                                <div
                                    key={item.id}
                                    className="flex items-center justify-between gap-4 rounded-xl border border-border p-3"
                                >
                                    <div>
                                        <p className="font-medium">{item.name}</p>
                                        {item.unit ? (
                                            <p className="text-xs text-muted-foreground">
                                                {item.unit}
                                            </p>
                                        ) : null}
                                    </div>
                                    <Input
                                        type="number"
                                        min={0}
                                        className="w-24"
                                        value={data.item_quantities[item.id] ?? 0}
                                        onChange={(event) =>
                                            setData('item_quantities', {
                                                ...data.item_quantities,
                                                [item.id]: Number(
                                                    event.target.value,
                                                ),
                                            })
                                        }
                                    />
                                </div>
                            ))}
                        </div>

                        <AssistanceFieldInputs
                            fields={fields}
                            values={data.field_values}
                            onChange={(fieldId, value) =>
                                setData('field_values', {
                                    ...data.field_values,
                                    [fieldId]: value,
                                })
                            }
                            errors={errors}
                            idPrefix="public-field"
                        />

                        <div className="space-y-2">
                            <Label htmlFor="remark">Remarks</Label>
                            <Textarea
                                id="remark"
                                value={data.remark}
                                onChange={(event) =>
                                    setData('remark', event.target.value)
                                }
                                rows={3}
                            />
                        </div>

                        <label className="flex items-start gap-2 text-sm">
                            <Input
                                type="checkbox"
                                checked={data.consent}
                                onChange={(event) =>
                                    setData('consent', event.target.checked)
                                }
                                className="mt-1 size-4"
                            />
                            <span>
                                I consent to the collection and processing of my
                                personal information for this assistance request.
                            </span>
                        </label>
                        <InputError message={errors.consent} />

                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                disabled={processing}
                                onClick={() => submitIntent('submit')}
                            >
                                {processing ? 'Submitting...' : 'Submit request'}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={processing}
                                onClick={() => submitIntent('save')}
                            >
                                Save for later
                            </Button>
                        </div>
                    </div>
                ) : null}
            </div>
        </>
    );
}
