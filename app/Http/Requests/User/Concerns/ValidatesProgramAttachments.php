<?php

namespace App\Http\Requests\User\Concerns;

use App\Models\Program;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;

trait ValidatesProgramAttachments
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function programAttachmentRules(bool $allowRemoval = false, bool $prohibited = false): array
    {
        if ($prohibited) {
            return [
                'attachments' => ['prohibited'],
                'remove_attachment_ids' => ['prohibited'],
            ];
        }

        $rules = [
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => [
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,webp,pdf',
                'mimetypes:image/jpeg,image/png,image/webp,application/pdf',
            ],
        ];

        if ($allowRemoval) {
            $rules['remove_attachment_ids'] = ['nullable', 'array', 'max:10'];
            $rules['remove_attachment_ids.*'] = ['integer'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function programAttachmentAttributes(): array
    {
        return [
            'attachments' => 'attachments',
            'attachments.*' => 'attachment',
            'remove_attachment_ids' => 'attachments to remove',
            'remove_attachment_ids.*' => 'attachment',
        ];
    }

    protected function afterProgramAttachments(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $program = $this->route('program');

            if (! $program instanceof Program) {
                return;
            }

            $rawIds = $this->input('remove_attachment_ids', []);
            $removeIds = collect(is_array($rawIds) ? $rawIds : [])
                ->map(static fn (mixed $id): int => (int) $id)
                ->unique()
                ->values();
            $ownedIds = $program->attachments()->pluck('id')->map(static fn (mixed $id): int => (int) $id);
            $invalidRemoval = false;

            foreach ($removeIds as $index => $id) {
                if (! $ownedIds->contains($id)) {
                    $invalidRemoval = true;
                    $validator->errors()->add(
                        "remove_attachment_ids.{$index}",
                        'The selected attachment is invalid for this program.',
                    );
                }
            }

            if ($invalidRemoval) {
                return;
            }

            $incoming = $this->file('attachments', []);
            $incomingCount = is_array($incoming) ? count($incoming) : 0;
            $remaining = $ownedIds->reject(static fn (int $id): bool => $removeIds->contains($id))->count();

            if ($remaining + $incomingCount > 10) {
                $validator->errors()->add(
                    'attachments',
                    'A program can have at most 10 attachments.',
                );
            }
        });
    }
}
