<?php

namespace App\Http\Requests\User\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesProgramEligibilityRules
{
    /**
     * @return array<string, mixed>
     */
    protected function programEligibilityRules(array $itemIds): array
    {
        $allowedItemIds = array_values(array_filter(array_map('intval', $itemIds)));

        return [
            'cooldown_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'require_pwd' => ['nullable', 'boolean'],
            'require_4ps' => ['nullable', 'boolean'],
            'require_solo_parent' => ['nullable', 'boolean'],
            'require_indigenous' => ['nullable', 'boolean'],
            'item_caps' => ['nullable', 'array'],
            'item_caps.*.item_id' => ['required', 'integer', Rule::in($allowedItemIds)],
            'item_caps.*.max_released_per_year' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function programEligibilityAttributes(): array
    {
        return [
            'cooldown_days' => 'cooldown days',
            'require_pwd' => 'PWD filter',
            'require_4ps' => '4Ps filter',
            'require_solo_parent' => 'solo parent filter',
            'require_indigenous' => 'indigenous peoples filter',
            'item_caps' => 'item caps',
            'item_caps.*.item_id' => 'item cap',
            'item_caps.*.max_released_per_year' => 'yearly item cap',
        ];
    }

    protected function afterProgramEligibilityRules(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->boolean('is_organization')) {
                return;
            }

            $caps = $this->input('item_caps', []);

            if (! is_array($caps)) {
                return;
            }

            $itemIds = [];

            foreach ($caps as $index => $cap) {
                if (! is_array($cap) || ! isset($cap['item_id'])) {
                    continue;
                }

                $itemId = (int) $cap['item_id'];

                if (in_array($itemId, $itemIds, true)) {
                    $validator->errors()->add(
                        "item_caps.{$index}.item_id",
                        'Each item can only have one yearly cap.',
                    );
                }

                $itemIds[] = $itemId;
            }
        });
    }
}
