const pesoFormatter = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

export function formatPeso(
    amount: string | number | null | undefined,
): string {
    if (amount === null || amount === undefined || amount === '') {
        return '—';
    }

    const numericAmount =
        typeof amount === 'number'
            ? amount
            : Number.parseFloat(amount.replace(/,/g, ''));

    if (Number.isNaN(numericAmount)) {
        return amount.toString();
    }

    return pesoFormatter.format(numericAmount);
}
