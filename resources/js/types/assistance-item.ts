export type AssistanceItemOrigin = 'requested' | 'additional' | 'substitute';

/**
 * A requested line rolled up per item, so partial releases do not show as duplicates.
 */
export type AssistanceRequestedItem = {
    item_id: number;
    name: string;
    unit: string | null;
    specification: string | null;
    requested_quantity: number;
    released_quantity: number;
    substituted_quantity: number;
    pending_quantity: number;
};

/**
 * A line that was actually handed over, whether it came from the request or not.
 */
export type AssistanceReleasedItem = {
    id: number;
    item_id: number;
    name: string;
    unit: string | null;
    quantity: number;
    specification: string | null;
    origin: AssistanceItemOrigin;
    fulfillment_reason: string | null;
    substituted_for_name: string | null;
};

export type AssistanceItemVariance = {
    requested_quantity: number;
    released_quantity: number;
    fulfilled_quantity: number;
    additional_quantity: number;
    substitute_quantity: number;
    substituted_quantity: number;
    shortfall_quantity: number;
    has_variance: boolean;
};

export const ASSISTANCE_ITEM_ORIGIN_LABELS: Record<
    AssistanceItemOrigin,
    string
> = {
    requested: 'Requested',
    additional: 'Additional',
    substitute: 'Substitute',
};

export function formatItemQuantity(
    quantity: number | null,
    unit: string | null,
): string {
    if (quantity === null) {
        return unit ?? '—';
    }

    return unit ? `${quantity} ${unit}` : String(quantity);
}
