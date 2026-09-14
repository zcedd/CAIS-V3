export type ItemKind = 'goods' | 'cash' | 'service';

export const ITEM_KIND_LABELS: Record<ItemKind, string> = {
    goods: 'Goods',
    cash: 'Cash',
    service: 'Service',
};

export const ITEM_KIND_OPTIONS: { value: ItemKind; label: string }[] = [
    { value: 'goods', label: 'Goods' },
    { value: 'cash', label: 'Cash' },
    { value: 'service', label: 'Service' },
];

export function isItemKind(value: string | null | undefined): value is ItemKind {
    return value === 'goods' || value === 'cash' || value === 'service';
}

export function tracksInventory(kind: ItemKind | null | undefined): boolean {
    return kind === 'goods' || kind == null;
}

export function itemQuantityFieldLabel(
    kind: ItemKind | null | undefined,
): string {
    return kind === 'cash' ? 'Amount (₱)' : 'Quantity';
}
