import { formatDistributionQuantity } from '@/components/distribution/composition';
import { formatPeso } from '@/lib/format-peso';
import type { ItemKind } from '@/types/item';

export type AssistanceLine = {
    id: number;
    item_name: string | null;
    kind: string | null;
    unit?: string | null;
    proposed_quantity: string | null;
    approved_quantity: string | null;
    proposed_amount: string | null;
    approved_amount: string | null;
};

const kindOrder: ItemKind[] = ['goods', 'service', 'cash'];

const kindLabels: Record<ItemKind, string> = {
    goods: 'Goods',
    service: 'Services',
    cash: 'Cash',
};

function lineKind(kind: string | null): ItemKind {
    if (kind === 'cash' || kind === 'service' || kind === 'goods') {
        return kind;
    }

    return 'goods';
}

export function AssistanceLines({ lines }: { lines: AssistanceLine[] }) {
    const groups = kindOrder
        .map((kind) => ({
            kind,
            lines: lines.filter((line) => lineKind(line.kind) === kind),
        }))
        .filter((group) => group.lines.length > 0);

    if (groups.length === 0) {
        return (
            <span className="text-muted-foreground">No assistance lines</span>
        );
    }

    return (
        <div className="flex flex-col gap-2">
            {groups.map((group) => (
                <div key={group.kind} className="flex flex-col gap-1">
                    <div className="text-xs font-medium text-muted-foreground">
                        {kindLabels[group.kind]}
                    </div>
                    {group.lines.map((line) => (
                        <div key={line.id}>{formatAssistanceLine(line)}</div>
                    ))}
                </div>
            ))}
        </div>
    );
}

function formatAssistanceLine(line: AssistanceLine): string {
    const name = line.item_name ?? 'Item';

    if (lineKind(line.kind) === 'cash') {
        const approved = line.approved_amount
            ? ` / approved ${formatPeso(line.approved_amount)}`
            : '';

        return `${name}: proposed ${formatPeso(line.proposed_amount)}${approved}`;
    }

    const approved = line.approved_quantity
        ? ` / approved ${formatDistributionQuantity(line.approved_quantity, line.unit)}`
        : '';

    return `${name}: proposed ${formatDistributionQuantity(line.proposed_quantity, line.unit)}${approved}`;
}
