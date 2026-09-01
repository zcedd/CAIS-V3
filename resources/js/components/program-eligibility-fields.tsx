'use client';

import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ProgramEligibilityFormValue } from '@/types/eligibility';

type ProgramItemOption = {
    id: number;
    name: string;
    unit?: string | null;
};

type ProgramEligibilityFieldsProps = {
    value: ProgramEligibilityFormValue;
    onChange: (value: ProgramEligibilityFormValue) => void;
    items: ProgramItemOption[];
    selectedItemIds: string[];
    isOrganization: boolean;
    errors: Record<string, string>;
    idPrefix?: string;
};

export function ProgramEligibilityFields({
    value,
    onChange,
    items,
    selectedItemIds,
    isOrganization,
    errors,
    idPrefix = 'program-eligibility',
}: ProgramEligibilityFieldsProps) {
    const selectedItems = items.filter((item) =>
        selectedItemIds.includes(String(item.id)),
    );

    return (
        <div className="space-y-4 rounded-lg border p-4">
            <div>
                <p className="text-sm font-medium">Eligibility</p>
                <p className="text-sm text-muted-foreground">
                    Optional rules applied when staff encode assistance.
                </p>
            </div>

            <div className="space-y-2">
                <Label htmlFor={`${idPrefix}-cooldown`}>
                    Cooldown after delivery (days)
                </Label>
                <Input
                    id={`${idPrefix}-cooldown`}
                    name="cooldown_days"
                    type="number"
                    min={1}
                    value={value.cooldown_days}
                    onChange={(event) =>
                        onChange({
                            ...value,
                            cooldown_days: event.target.value,
                        })
                    }
                    placeholder="Leave blank for no cooldown"
                />
                <InputError message={errors.cooldown_days} />
            </div>

            {!isOrganization ? (
                <div className="space-y-3">
                    <p className="text-sm font-medium">Demographic filters</p>
                    <label className="flex items-start gap-3 text-sm">
                        <Checkbox
                            checked={value.require_pwd}
                            onCheckedChange={(checked) =>
                                onChange({
                                    ...value,
                                    require_pwd: checked === true,
                                })
                            }
                        />
                        <span>PWD only</span>
                    </label>
                    <input
                        type="hidden"
                        name="require_pwd"
                        value={value.require_pwd ? '1' : '0'}
                    />
                    <label className="flex items-start gap-3 text-sm">
                        <Checkbox
                            checked={value.require_4ps}
                            onCheckedChange={(checked) =>
                                onChange({
                                    ...value,
                                    require_4ps: checked === true,
                                })
                            }
                        />
                        <span>4Ps only</span>
                    </label>
                    <input
                        type="hidden"
                        name="require_4ps"
                        value={value.require_4ps ? '1' : '0'}
                    />
                    <label className="flex items-start gap-3 text-sm">
                        <Checkbox
                            checked={value.require_solo_parent}
                            onCheckedChange={(checked) =>
                                onChange({
                                    ...value,
                                    require_solo_parent: checked === true,
                                })
                            }
                        />
                        <span>Solo parent only</span>
                    </label>
                    <input
                        type="hidden"
                        name="require_solo_parent"
                        value={value.require_solo_parent ? '1' : '0'}
                    />
                    <label className="flex items-start gap-3 text-sm">
                        <Checkbox
                            checked={value.require_indigenous}
                            onCheckedChange={(checked) =>
                                onChange({
                                    ...value,
                                    require_indigenous: checked === true,
                                })
                            }
                        />
                        <span>Indigenous peoples only</span>
                    </label>
                    <input
                        type="hidden"
                        name="require_indigenous"
                        value={value.require_indigenous ? '1' : '0'}
                    />
                </div>
            ) : null}

            {selectedItems.length > 0 ? (
                <div className="space-y-3">
                    <p className="text-sm font-medium">
                        Yearly released item caps
                    </p>
                    {selectedItems.map((item) => (
                        <div key={item.id} className="space-y-2">
                            <Label htmlFor={`${idPrefix}-cap-${item.id}`}>
                                {item.unit
                                    ? `${item.name} (${item.unit})`
                                    : item.name}
                            </Label>
                            <Input
                                id={`${idPrefix}-cap-${item.id}`}
                                type="number"
                                min={1}
                                value={value.item_caps[String(item.id)] ?? ''}
                                onChange={(event) =>
                                    onChange({
                                        ...value,
                                        item_caps: {
                                            ...value.item_caps,
                                            [String(item.id)]:
                                                event.target.value,
                                        },
                                    })
                                }
                                placeholder="No cap"
                            />
                        </div>
                    ))}
                </div>
            ) : null}
        </div>
    );
}

export function eligibilityPayloadFromForm(
    value: ProgramEligibilityFormValue,
    selectedItemIds: string[],
): {
    cooldown_days: number | null;
    require_pwd: boolean;
    require_4ps: boolean;
    require_solo_parent: boolean;
    require_indigenous: boolean;
    item_caps: Array<{ item_id: number; max_released_per_year: number }>;
} {
    const cooldown = value.cooldown_days.trim();

    return {
        cooldown_days: cooldown === '' ? null : Number(cooldown),
        require_pwd: value.require_pwd,
        require_4ps: value.require_4ps,
        require_solo_parent: value.require_solo_parent,
        require_indigenous: value.require_indigenous,
        item_caps: selectedItemIds
            .map((itemId) => ({
                item_id: Number(itemId),
                max_released_per_year: Number(value.item_caps[itemId] ?? ''),
            }))
            .filter((cap) => Number.isFinite(cap.max_released_per_year) && cap.max_released_per_year > 0),
    };
}
