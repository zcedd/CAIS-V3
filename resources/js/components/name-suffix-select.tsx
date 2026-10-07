import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { NameSuffixOption } from '@/types/beneficiary';

const EMPTY_SUFFIX = 'none';

export function NameSuffixSelect({
    id,
    name,
    value,
    options,
    onValueChange,
}: {
    id: string;
    name?: string;
    value: string;
    options: NameSuffixOption[];
    onValueChange: (value: string) => void;
}) {
    const items =
        value !== '' && !options.some((option) => option.value === value)
            ? [{ value, label: value }, ...options]
            : options;

    return (
        <>
            <Select
                value={value === '' ? EMPTY_SUFFIX : value}
                onValueChange={(next) =>
                    onValueChange(next === EMPTY_SUFFIX ? '' : next)
                }
            >
                <SelectTrigger id={id} className="w-full">
                    <SelectValue placeholder="Select suffix" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={EMPTY_SUFFIX}>None</SelectItem>
                    {items.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {name ? <input type="hidden" name={name} value={value} /> : null}
        </>
    );
}
