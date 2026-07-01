import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
    InputGroupText,
} from '@/components/ui/input-group';

type FundAmountFieldProps = {
    id: string;
    name?: string;
    defaultValue?: string | number | null;
};

export function FundAmountField({
    id,
    name = 'amount',
    defaultValue,
}: FundAmountFieldProps) {
    const value =
        defaultValue === null || defaultValue === undefined
            ? undefined
            : String(defaultValue).replace(/,/g, '');

    return (
        <InputGroup>
            <InputGroupAddon>
                <InputGroupText>₱</InputGroupText>
            </InputGroupAddon>
            <InputGroupInput
                id={id}
                name={name}
                type="text"
                inputMode="decimal"
                placeholder="0.00"
                defaultValue={value}
            />
        </InputGroup>
    );
}
