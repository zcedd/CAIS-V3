'use client';

import { Loader2, X } from 'lucide-react';
import { useCallback, useEffect, useId, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverAnchor,
    PopoverContent,
} from '@/components/ui/popover';
import { CodeChip } from '@/components/user/code-chip';
import { cn } from '@/lib/utils';
import { search as searchBeneficiaries } from '@/routes/user/beneficiaries';

export type BeneficiarySearchOption = {
    id: number;
    individual_id: number | null;
    organization_id: number | null;
    cais_number: string;
    name: string;
    label: string;
    sex?: string | null;
    age?: number | null;
    barangay?: string | null;
    city?: string | null;
    sectors?: string[];
};

type BeneficiarySearchComboboxProps = {
    departmentSlug?: string;
    beneficiaryType?: 'individual' | 'organization';
    valueKey?: 'beneficiary_id' | 'organization_id' | 'individual_id';
    value: number | null;
    initialOption?: BeneficiarySearchOption | null;
    onChange: (
        value: number | null,
        option: BeneficiarySearchOption | null,
    ) => void;
    error?: string;
    label?: string;
    name?: string;
    disabled?: boolean;
    includeHiddenInput?: boolean;
};

export function BeneficiarySearchCombobox({
    departmentSlug,
    beneficiaryType,
    valueKey = 'beneficiary_id',
    value,
    initialOption = null,
    onChange,
    error,
    label = 'Beneficiary',
    name = 'beneficiary_id',
    disabled = false,
    includeHiddenInput = true,
}: BeneficiarySearchComboboxProps) {
    const inputId = useId();
    const listId = `${inputId}-suggestions`;
    const containerRef = useRef<HTMLDivElement>(null);
    const [open, setOpen] = useState(false);
    const [inputValue, setInputValue] = useState('');
    const [selectedOption, setSelectedOption] =
        useState<BeneficiarySearchOption | null>(null);
    const [suggestions, setSuggestions] = useState<BeneficiarySearchOption[]>(
        [],
    );
    const [isLoading, setIsLoading] = useState(false);
    const [searchError, setSearchError] = useState<string | null>(null);

    const fetchSuggestions = useCallback(
        async (query: string) => {
            setIsLoading(true);
            setSearchError(null);

            try {
                if (!departmentSlug) {
                    setSuggestions([]);

                    return;
                }

                const response = await fetch(
                    searchBeneficiaries.url(departmentSlug, {
                        query: {
                            q: query,
                            beneficiary_type: beneficiaryType,
                        },
                    }),
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    },
                );

                if (!response.ok) {
                    throw new Error('Search failed');
                }

                const payload = (await response.json()) as {
                    data: BeneficiarySearchOption[];
                };

                setSuggestions(payload.data);
            } catch {
                setSuggestions([]);
                setSearchError('Unable to load beneficiaries. Try again.');
            } finally {
                setIsLoading(false);
            }
        },
        [departmentSlug, beneficiaryType],
    );

    useEffect(() => {
        if (initialOption) {
            setSelectedOption(initialOption);
            setInputValue(initialOption.label);
        }
    }, [initialOption]);

    useEffect(() => {
        if (!open || disabled) {
            return;
        }

        const trimmed = inputValue.trim();
        const handle = window.setTimeout(() => {
            void fetchSuggestions(trimmed);
        }, 300);

        return () => window.clearTimeout(handle);
    }, [inputValue, open, disabled, fetchSuggestions]);

    const handleSelect = (option: BeneficiarySearchOption) => {
        const selectedValue =
            valueKey === 'organization_id'
                ? option.organization_id
                : valueKey === 'individual_id'
                  ? option.individual_id
                  : option.id;

        setSelectedOption(option);
        setInputValue(option.label);
        onChange(selectedValue, option);
        setOpen(false);
    };

    const handleClear = () => {
        setSelectedOption(null);
        setInputValue('');
        onChange(null, null);
        setSuggestions([]);
    };

    return (
        <div className="space-y-2" ref={containerRef}>
            <Label htmlFor={inputId}>{label}</Label>
            {includeHiddenInput ? (
                <input
                    type="hidden"
                    name={name}
                    value={value ?? ''}
                    disabled={disabled}
                />
            ) : null}
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverAnchor asChild>
                    <div className="relative">
                        <Input
                            id={inputId}
                            type="text"
                            role="combobox"
                            aria-expanded={open}
                            aria-controls={listId}
                            aria-autocomplete="list"
                            autoComplete="off"
                            placeholder="Search by CAIS number or name..."
                            value={inputValue}
                            disabled={disabled}
                            onChange={(event) => {
                                const next = event.target.value;
                                setInputValue(next);

                                if (
                                    selectedOption !== null &&
                                    next !== selectedOption.label
                                ) {
                                    setSelectedOption(null);
                                    onChange(null, null);
                                }

                                setOpen(true);
                            }}
                            onFocus={() => setOpen(true)}
                            onKeyDown={(event) => {
                                if (event.key === 'Escape') {
                                    setOpen(false);
                                }
                            }}
                            className={cn(
                                value !== null &&
                                    'border-primary bg-primary/5 ring-1 ring-primary/30',
                            )}
                        />
                        {value !== null ? (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="absolute top-1/2 right-1 size-7 -translate-y-1/2"
                                onClick={handleClear}
                                disabled={disabled}
                                aria-label="Clear beneficiary"
                            >
                                <X className="size-4" />
                            </Button>
                        ) : null}
                    </div>
                </PopoverAnchor>
                <PopoverContent
                    className="w-(--radix-popover-trigger-width) p-0"
                    align="start"
                    onOpenAutoFocus={(event) => event.preventDefault()}
                >
                    <Command shouldFilter={false}>
                        <CommandList id={listId}>
                            {isLoading ? (
                                <div className="flex items-center justify-center gap-2 py-6 text-sm text-muted-foreground">
                                    <Loader2 className="size-4 animate-spin" />
                                    Searching...
                                </div>
                            ) : null}
                            {!isLoading && searchError ? (
                                <CommandEmpty>{searchError}</CommandEmpty>
                            ) : null}
                            {!isLoading &&
                            !searchError &&
                            suggestions.length === 0 ? (
                                <CommandEmpty>
                                    {inputValue.trim().length === 0
                                        ? 'Type to search beneficiaries'
                                        : 'No beneficiaries found'}
                                </CommandEmpty>
                            ) : null}
                            {!isLoading && suggestions.length > 0 ? (
                                <CommandGroup>
                                    {suggestions.map((option) => (
                                        <CommandItem
                                            key={option.id}
                                            value={option.label}
                                            onMouseDown={(event) =>
                                                event.preventDefault()
                                            }
                                            onSelect={() =>
                                                handleSelect(option)
                                            }
                                            className="cursor-pointer items-start gap-2"
                                        >
                                            <BeneficiaryAvatar name={option.name} />
                                            <span className="flex min-w-0 flex-1 flex-col gap-1">
                                                <span className="flex flex-wrap items-center gap-1.5">
                                                    <span className="font-medium">
                                                        {option.name}
                                                    </span>
                                                    <CodeChip>
                                                        {option.cais_number}
                                                    </CodeChip>
                                                </span>
                                                <BeneficiaryMeta option={option} />
                                            </span>
                                        </CommandItem>
                                    ))}
                                </CommandGroup>
                            ) : null}
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
            {value !== null && selectedOption !== null ? (
                <div className="flex items-start gap-3 rounded-lg border bg-muted/40 p-3 ring-1 ring-primary/30">
                    <BeneficiaryAvatar name={selectedOption.name} />
                    <div className="min-w-0 flex-1 space-y-1">
                        <div className="flex flex-wrap items-center gap-1.5">
                            <p className="text-sm font-medium">
                                {selectedOption.name}
                            </p>
                            <CodeChip>{selectedOption.cais_number}</CodeChip>
                        </div>
                        <BeneficiaryMeta option={selectedOption} />
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={handleClear}
                        disabled={disabled}
                    >
                        Change
                    </Button>
                </div>
            ) : null}
            <InputError message={error} />
        </div>
    );
}

function BeneficiaryAvatar({ name }: { name: string }) {
    return (
        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium">
            {initials(name)}
        </span>
    );
}

function BeneficiaryMeta({ option }: { option: BeneficiarySearchOption }) {
    const line = profileLine(option);
    const sectors = option.sectors ?? [];

    if (line === null && sectors.length === 0) {
        return null;
    }

    return (
        <span className="flex flex-col gap-1">
            {line !== null ? (
                <span className="text-xs text-muted-foreground">{line}</span>
            ) : null}
            {sectors.length > 0 ? (
                <span className="flex flex-wrap gap-1">
                    {sectors.map((sector) => (
                        <Badge key={sector} variant="secondary">
                            {sector}
                        </Badge>
                    ))}
                </span>
            ) : null}
        </span>
    );
}

function initials(name: string): string {
    const letters = name
        .trim()
        .split(/\s+/)
        .filter((part) => part.length > 0)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '');

    return letters.join('') || '?';
}

function profileLine(option: BeneficiarySearchOption): string | null {
    const person = [
        option.sex,
        option.age === null || option.age === undefined ? null : String(option.age),
    ]
        .filter((part): part is string => part !== null && part !== '')
        .join(', ');
    const place = [option.barangay, option.city]
        .filter((part): part is string => part !== null && part !== undefined && part !== '')
        .join(', ');
    const line = [person, place].filter((part) => part !== '').join(' · ');

    return line === '' ? null : line;
}
