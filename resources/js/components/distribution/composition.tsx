import { Banknote, Handshake, Package } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatPeso } from '@/lib/format-peso';

export type QuantityLine = {
    name: string;
    unit: string | null;
    proposed_quantity: string;
    approved_quantity: string;
    actual_quantity: string;
    remaining_quantity: string;
};

export type CashLine = {
    name: string;
    proposed_amount: string;
    approved_amount: string;
    actual_amount: string;
    remaining_amount: string;
};

export type DistributionCompositionData = {
    goods: { label: string; lines: QuantityLine[] };
    service: { label: string; lines: QuantityLine[] };
    cash: {
        label: string;
        proposed_amount: string;
        approved_amount: string;
        actual_amount: string;
        remaining_amount: string;
        lines: CashLine[];
    };
};

export type DistributionOffer = {
    goods: { name: string; unit: string | null; quantity: string }[];
    service: { name: string; unit: string | null; quantity: string }[];
    cash: string;
};

export function formatDistributionQuantity(
    value: string | null | undefined,
    unit?: string | null,
): string {
    if (value === null || value === undefined || value === '') {
        return '0';
    }

    const numeric = Number.parseFloat(value);
    const formatted = Number.isNaN(numeric)
        ? value
        : new Intl.NumberFormat('en-PH', { maximumFractionDigits: 2 }).format(
              numeric,
          );

    return unit ? `${formatted} ${unit}` : formatted;
}

function isZero(value: string | null | undefined): boolean {
    if (value === null || value === undefined || value === '') {
        return true;
    }

    const numeric = Number.parseFloat(value);

    return Number.isNaN(numeric) || numeric === 0;
}

export function DistributionComposition({
    composition,
}: {
    composition: DistributionCompositionData;
}) {
    return (
        <div className="grid gap-3 lg:grid-cols-3">
            <QuantityCard
                title="Goods"
                icon={Package}
                lines={composition.goods.lines}
                empty="No goods in this batch."
            />
            <QuantityCard
                title="Services"
                icon={Handshake}
                lines={composition.service.lines}
                empty="No services in this batch."
            />
            <CashCard cash={composition.cash} />
        </div>
    );
}

function QuantityCard({
    title,
    icon: Icon,
    lines,
    empty,
}: {
    title: string;
    icon: LucideIcon;
    lines: QuantityLine[];
    empty: string;
}) {
    return (
        <Card size="sm">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Icon className="size-4 text-muted-foreground" />
                    {title}
                </CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                {lines.length === 0 && (
                    <p className="text-muted-foreground">{empty}</p>
                )}
                {lines.map((line) => (
                    <div key={line.name} className="flex flex-col gap-1">
                        <div className="font-medium">{line.name}</div>
                        <Figure
                            label="Proposed"
                            value={formatDistributionQuantity(
                                line.proposed_quantity,
                                line.unit,
                            )}
                        />
                        <Figure
                            label="Approved"
                            value={formatDistributionQuantity(
                                line.approved_quantity,
                                line.unit,
                            )}
                        />
                        <Figure
                            label="Released"
                            value={formatDistributionQuantity(
                                line.actual_quantity,
                                line.unit,
                            )}
                        />
                        <Figure
                            label="Remaining"
                            value={formatDistributionQuantity(
                                line.remaining_quantity,
                                line.unit,
                            )}
                        />
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

function CashCard({ cash }: { cash: DistributionCompositionData['cash'] }) {
    const empty =
        cash.lines.length === 0 &&
        isZero(cash.proposed_amount) &&
        isZero(cash.approved_amount);

    return (
        <Card size="sm">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Banknote className="size-4 text-muted-foreground" />
                    Cash
                </CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                {empty ? (
                    <p className="text-muted-foreground">
                        No cash in this batch.
                    </p>
                ) : (
                    <>
                        {cash.lines.length > 1 && (
                            <p className="text-muted-foreground">
                                {cash.lines.map((line) => line.name).join(', ')}
                            </p>
                        )}
                        {cash.lines.length === 1 && (
                            <div className="font-medium">
                                {cash.lines[0]?.name}
                            </div>
                        )}
                        <Figure
                            label="Proposed"
                            value={formatPeso(cash.proposed_amount)}
                        />
                        <Figure
                            label="Approved"
                            value={formatPeso(cash.approved_amount)}
                        />
                        <Figure
                            label="Released"
                            value={formatPeso(cash.actual_amount)}
                        />
                        <Figure
                            label="Remaining"
                            value={formatPeso(cash.remaining_amount)}
                        />
                    </>
                )}
            </CardContent>
        </Card>
    );
}

function Figure({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-3">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium">{value}</span>
        </div>
    );
}

export function DistributionOfferSummary({
    offer,
}: {
    offer: DistributionOffer;
}) {
    return (
        <div className="flex flex-col gap-1">
            <OfferRow label="Goods" lines={offer.goods} />
            <OfferRow label="Services" lines={offer.service} />
            <div>
                <span className="text-muted-foreground">Cash </span>
                {isZero(offer.cash) ? 'None' : formatPeso(offer.cash)}
            </div>
        </div>
    );
}

function OfferRow({
    label,
    lines,
}: {
    label: string;
    lines: DistributionOffer['goods'];
}) {
    const text =
        lines.length === 0
            ? 'None'
            : lines
                  .map(
                      (line) =>
                          `${line.name} ${formatDistributionQuantity(line.quantity, line.unit)}`,
                  )
                  .join(', ');

    return (
        <div>
            <span className="text-muted-foreground">{label} </span>
            {text}
        </div>
    );
}
