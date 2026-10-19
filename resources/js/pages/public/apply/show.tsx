import { Head, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { AddressCascadeSelect } from '@/components/address-cascade-select';
import { AssistanceFieldInputs } from '@/components/assistance-field-inputs';
import InputError from '@/components/input-error';
import { NameSuffixSelect } from '@/components/name-suffix-select';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
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
                setMatchError(
                    'Could not check for existing records. Try again.',
                );

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

    const showMatchStep = step === 'match' || matches.length > 0;
    const steps = [
        { id: 'identity' as const, label: 'Your details' },
        ...(showMatchStep
            ? [{ id: 'match' as const, label: 'Confirm identity' }]
            : []),
        { id: 'request' as const, label: 'Request' },
    ];

    function backFromRequest(): void {
        if (matches.length > 0) {
            setStep('match');

            return;
        }

        setStep('identity');
    }

    const serverErrors = errors as typeof errors & {
        item_details?: string;
        eligibility?: string;
    };

    return (
        <>
            <Head title={program.name} />
            <div className={cn('flex flex-col gap-6', kiosk && 'text-base')}>
                <div className="flex flex-col gap-2">
                    {program.department_name ? (
                        <p className="text-sm text-muted-foreground">
                            {program.department_name}
                        </p>
                    ) : null}
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {program.name}
                    </h1>
                    {program.descriptions ? (
                        <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                            {program.descriptions}
                        </p>
                    ) : null}
                    <ol className="flex flex-wrap gap-x-4 gap-y-2 text-sm">
                        {steps.map((item, index) => {
                            const current = item.id === step;

                            return (
                                <li
                                    key={item.id}
                                    aria-current={current ? 'step' : undefined}
                                    className={cn(
                                        current
                                            ? 'font-medium text-foreground'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {index + 1}. {item.label}
                                </li>
                            );
                        })}
                    </ol>
                </div>

                {step === 'identity' ? (
                    <div className="flex flex-col gap-6">
                        <FieldSet>
                            <FieldLegend>Your details</FieldLegend>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field>
                                    <FieldLabel htmlFor="first_name">
                                        First name
                                    </FieldLabel>
                                    <Input
                                        id="first_name"
                                        value={data.first_name}
                                        onChange={(event) =>
                                            setData(
                                                'first_name',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            errors.first_name ? true : undefined
                                        }
                                    />
                                    <InputError message={errors.first_name} />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="middle_name">
                                        Middle name
                                    </FieldLabel>
                                    <Input
                                        id="middle_name"
                                        value={data.middle_name}
                                        onChange={(event) =>
                                            setData(
                                                'middle_name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="last_name">
                                        Last name
                                    </FieldLabel>
                                    <Input
                                        id="last_name"
                                        value={data.last_name}
                                        onChange={(event) =>
                                            setData(
                                                'last_name',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            errors.last_name ? true : undefined
                                        }
                                    />
                                    <InputError message={errors.last_name} />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="suffix">
                                        Suffix
                                    </FieldLabel>
                                    <NameSuffixSelect
                                        id="suffix"
                                        value={data.suffix ?? ''}
                                        options={form_options.suffixes}
                                        onValueChange={(suffix) =>
                                            setData('suffix', suffix)
                                        }
                                    />
                                    <InputError message={errors.suffix} />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="birthday">
                                        Birthday
                                    </FieldLabel>
                                    <Input
                                        id="birthday"
                                        type="date"
                                        value={data.birthday}
                                        onChange={(event) =>
                                            setData(
                                                'birthday',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            errors.birthday ? true : undefined
                                        }
                                    />
                                    <InputError message={errors.birthday} />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="sex">Sex</FieldLabel>
                                    <Select
                                        value={data.sex}
                                        onValueChange={(value) =>
                                            setData('sex', value ?? '')
                                        }
                                    >
                                        <SelectTrigger
                                            id="sex"
                                            aria-invalid={
                                                errors.sex ? true : undefined
                                            }
                                        >
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectItem value="Male">
                                                    Male
                                                </SelectItem>
                                                <SelectItem value="Female">
                                                    Female
                                                </SelectItem>
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.sex} />
                                </Field>
                                <Field className="sm:col-span-2">
                                    <FieldLabel htmlFor="mobile_number">
                                        Mobile number
                                    </FieldLabel>
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
                                </Field>
                            </div>
                        </FieldSet>

                        <FieldSet>
                            <FieldLegend>Address</FieldLegend>
                            <AddressCascadeSelect
                                provinces={form_options.address_provinces}
                                defaultProvinceId={
                                    form_options.default_province_id
                                }
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
                        </FieldSet>

                        <FieldSet>
                            <FieldLegend>Situation</FieldLegend>
                            <FieldDescription>
                                Select any that apply.
                            </FieldDescription>
                            <FieldGroup className="gap-3">
                                <Field orientation="horizontal">
                                    <Checkbox
                                        id="pwd"
                                        checked={data.pwd}
                                        onCheckedChange={(checked) =>
                                            setData('pwd', checked === true)
                                        }
                                    />
                                    <FieldLabel
                                        htmlFor="pwd"
                                        className="font-normal"
                                    >
                                        Person with disability
                                    </FieldLabel>
                                </Field>
                                <Field orientation="horizontal">
                                    <Checkbox
                                        id="is_4ps_beneficiary"
                                        checked={data.is_4ps_beneficiary}
                                        onCheckedChange={(checked) =>
                                            setData(
                                                'is_4ps_beneficiary',
                                                checked === true,
                                            )
                                        }
                                    />
                                    <FieldLabel
                                        htmlFor="is_4ps_beneficiary"
                                        className="font-normal"
                                    >
                                        4Ps beneficiary
                                    </FieldLabel>
                                </Field>
                                <Field orientation="horizontal">
                                    <Checkbox
                                        id="is_solo_parent"
                                        checked={data.is_solo_parent}
                                        onCheckedChange={(checked) =>
                                            setData(
                                                'is_solo_parent',
                                                checked === true,
                                            )
                                        }
                                    />
                                    <FieldLabel
                                        htmlFor="is_solo_parent"
                                        className="font-normal"
                                    >
                                        Solo parent
                                    </FieldLabel>
                                </Field>
                                <Field orientation="horizontal">
                                    <Checkbox
                                        id="indigenous"
                                        checked={data.indigenous}
                                        onCheckedChange={(checked) =>
                                            setData(
                                                'indigenous',
                                                checked === true,
                                            )
                                        }
                                    />
                                    <FieldLabel
                                        htmlFor="indigenous"
                                        className="font-normal"
                                    >
                                        Indigenous peoples
                                    </FieldLabel>
                                </Field>
                            </FieldGroup>
                        </FieldSet>

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
                    <div className="flex flex-col gap-4">
                        <div className="flex flex-col gap-1">
                            <h2 className="text-lg font-medium">
                                Is this you?
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                A record already matches what you entered.
                                Confirm it, or create a new profile.
                            </p>
                        </div>
                        <ul className="flex flex-col gap-3">
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
                                        className="w-full rounded-xl border border-border bg-card p-4 text-left hover:border-foreground/25 hover:bg-muted/30"
                                    >
                                        <p className="font-medium">
                                            {match.name}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {[
                                                match.cais_number,
                                                match.birth_year
                                                    ? `Born ${match.birth_year}`
                                                    : null,
                                                match.barangay,
                                            ]
                                                .filter(Boolean)
                                                .join(', ')}
                                        </p>
                                    </button>
                                </li>
                            ))}
                        </ul>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setStep('identity')}
                            >
                                Back
                            </Button>
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
                        </div>
                        <InputError message={errors.confirmed_beneficiary_id} />
                    </div>
                ) : null}

                {step === 'request' ? (
                    <div className="flex flex-col gap-4">
                        <div className="flex flex-col gap-1">
                            <h2 className="text-lg font-medium">
                                Requested items
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Enter a quantity for at least one item before
                                you submit.
                            </p>
                        </div>
                        <InputError message={serverErrors.item_details} />
                        <InputError message={serverErrors.eligibility} />
                        <div className="flex flex-col gap-3">
                            {items.map((item) => (
                                <div
                                    key={item.id}
                                    className="flex items-center justify-between gap-4 rounded-xl border border-border bg-card p-3"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {item.name}
                                        </p>
                                        {item.unit ? (
                                            <p className="text-xs text-muted-foreground">
                                                {item.unit}
                                            </p>
                                        ) : null}
                                    </div>
                                    <Input
                                        type="number"
                                        min={0}
                                        aria-label={`Quantity for ${item.name}`}
                                        className="w-24"
                                        value={
                                            data.item_quantities[item.id] ?? 0
                                        }
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

                        <Field>
                            <FieldLabel htmlFor="remark">Remarks</FieldLabel>
                            <Textarea
                                id="remark"
                                value={data.remark}
                                onChange={(event) =>
                                    setData('remark', event.target.value)
                                }
                                rows={3}
                            />
                        </Field>

                        <Field orientation="horizontal">
                            <Checkbox
                                id="consent"
                                checked={data.consent}
                                onCheckedChange={(checked) =>
                                    setData('consent', checked === true)
                                }
                                aria-invalid={errors.consent ? true : undefined}
                                className="mt-0.5"
                            />
                            <FieldLabel
                                htmlFor="consent"
                                className="font-normal"
                            >
                                I consent to the collection and processing of my
                                personal information for this assistance
                                request.
                            </FieldLabel>
                        </Field>
                        <InputError message={errors.consent} />

                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={backFromRequest}
                            >
                                Back
                            </Button>
                            <Button
                                type="button"
                                disabled={processing}
                                onClick={() => submitIntent('submit')}
                            >
                                {processing
                                    ? 'Submitting...'
                                    : 'Submit request'}
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
