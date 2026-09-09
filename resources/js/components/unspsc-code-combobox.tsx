'use client';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { cn } from '@/lib/utils';
import { search as searchUnspscCodes } from '@/routes/user/unspsc-codes';
import { Loader2, X } from 'lucide-react';
import { useCallback, useEffect, useId, useState } from 'react';

export type UnspscCodeOption = {
    id: number;
    code: string;
    title: string;
    path: string;
    is_curated: boolean;
};

type UnspscCodeComboboxProps = {
    departmentSlug: string;
    value: number | null;
    initialOption?: UnspscCodeOption | null;
    onChange?: (value: number | null, option: UnspscCodeOption | null) => void;
    error?: string;
    name?: string;
    disabled?: boolean;
};

export function UnspscCodeCombobox({
    departmentSlug,
    value,
    initialOption = null,
    onChange,
    error,
    name = 'unspsc_code_id',
    disabled = false,
}: UnspscCodeComboboxProps) {
    const inputId = useId();
    const listId = `${inputId}-suggestions`;
    const [open, setOpen] = useState(false);
    const [inputValue, setInputValue] = useState(
        initialOption
            ? `${initialOption.code}: ${initialOption.title}`
            : '',
    );
    const [selectedOption, setSelectedOption] =
        useState<UnspscCodeOption | null>(initialOption);
    const [suggestions, setSuggestions] = useState<UnspscCodeOption[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [searchError, setSearchError] = useState<string | null>(null);
    const [searchAll, setSearchAll] = useState(false);

    const fetchSuggestions = useCallback(
        async (query: string) => {
            setIsLoading(true);
            setSearchError(null);

            try {
                const response = await fetch(
                    searchUnspscCodes.url(departmentSlug, {
                        query: {
                            q: query,
                            curated: searchAll ? 0 : 1,
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
                    data: UnspscCodeOption[];
                };

                setSuggestions(payload.data);
            } catch {
                setSuggestions([]);
                setSearchError('Unable to load UNSPSC codes. Try again.');
            } finally {
                setIsLoading(false);
            }
        },
        [departmentSlug, searchAll],
    );

    useEffect(() => {
        if (initialOption) {
            setSelectedOption(initialOption);
            setInputValue(`${initialOption.code}: ${initialOption.title}`);
        }
    }, [initialOption]);

    useEffect(() => {
        if (!open || disabled) {
            return;
        }

        const handle = window.setTimeout(() => {
            void fetchSuggestions(inputValue.trim());
        }, 300);

        return () => window.clearTimeout(handle);
    }, [inputValue, open, disabled, fetchSuggestions]);

    const handleSelect = (option: UnspscCodeOption) => {
        setSelectedOption(option);
        setInputValue(`${option.code}: ${option.title}`);
        onChange?.(option.id, option);
        setOpen(false);
    };

    const handleClear = () => {
        setSelectedOption(null);
        setInputValue('');
        onChange?.(null, null);
        setSuggestions([]);
    };

    return (
        <div className="space-y-2">
            <Label htmlFor={inputId}>UNSPSC classification</Label>
            <input type="hidden" name={name} value={value ?? ''} />
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
                            placeholder="Search by code or title..."
                            value={inputValue}
                            disabled={disabled}
                            onChange={(event) => {
                                const next = event.target.value;
                                setInputValue(next);

                                if (
                                    selectedOption !== null &&
                                    next !==
                                        `${selectedOption.code}: ${selectedOption.title}`
                                ) {
                                    setSelectedOption(null);
                                    onChange?.(null, null);
                                }

                                setOpen(true);
                            }}
                            onFocus={() => setOpen(true)}
                        />
                        {isLoading ? (
                            <Loader2 className="absolute top-2.5 right-9 size-4 animate-spin text-muted-foreground" />
                        ) : null}
                        {value !== null || inputValue !== '' ? (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="absolute top-1 right-1 size-7"
                                disabled={disabled}
                                onClick={handleClear}
                            >
                                <X className="size-4" />
                                <span className="sr-only">
                                    Clear UNSPSC classification
                                </span>
                            </Button>
                        ) : null}
                    </div>
                </PopoverAnchor>
                <PopoverContent
                    className="w-[var(--radix-popover-trigger-width)] p-0"
                    align="start"
                    onOpenAutoFocus={(event) => event.preventDefault()}
                >
                    <Command shouldFilter={false}>
                        <CommandList id={listId}>
                            {searchError ? (
                                <CommandEmpty>{searchError}</CommandEmpty>
                            ) : suggestions.length === 0 && !isLoading ? (
                                <CommandEmpty>No UNSPSC codes found.</CommandEmpty>
                            ) : (
                                <CommandGroup>
                                    {suggestions.map((option) => (
                                        <CommandItem
                                            key={option.id}
                                            value={String(option.id)}
                                            onSelect={() => handleSelect(option)}
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate font-medium">
                                                    {option.code}: {option.title}
                                                </p>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {option.path}
                                                </p>
                                            </div>
                                        </CommandItem>
                                    ))}
                                </CommandGroup>
                            )}
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
            <label
                className={cn(
                    'flex items-center gap-2 text-xs text-muted-foreground',
                )}
            >
                <Checkbox
                    checked={searchAll}
                    onCheckedChange={(checked) =>
                        setSearchAll(checked === true)
                    }
                    disabled={disabled}
                />
                Search all codes
            </label>
            <InputError message={error} />
        </div>
    );
}
