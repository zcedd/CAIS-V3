<?php

namespace App\Services\Admin;

use App\Models\DocumentType;
use App\Models\ItemUnitMeasurement;
use App\Models\ModeOfRequest;
use App\Models\Sector;
use App\Services\User\BeneficiaryService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LookupService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function modes(): array
    {
        return ModeOfRequest::query()
            ->withCount('assistance')
            ->orderBy('name')
            ->get()
            ->map(fn (ModeOfRequest $mode): array => [
                'id' => $mode->id,
                'name' => $mode->name,
                'is_active' => $mode->is_active,
                'is_protected' => $mode->isProtected(),
                'locks_name' => $mode->isProtected(),
                'protected_reason' => $mode->isProtected()
                    ? 'Required by public intake.'
                    : null,
                'usage_count' => (int) $mode->assistance_count,
            ])
            ->all();
    }

    /**
     * @param  array{name: string, is_active: bool}  $attributes
     */
    public function createMode(array $attributes): ModeOfRequest
    {
        $mode = ModeOfRequest::query()->create($attributes);
        $this->forgetFormOptionCaches();

        return $mode;
    }

    /**
     * @param  array{name: string, is_active: bool}  $attributes
     */
    public function updateMode(ModeOfRequest $mode, array $attributes): ModeOfRequest
    {
        $this->guardLockedName(
            $mode->isProtected(),
            $mode->name,
            $attributes,
            'The Online mode is required by public intake and cannot be changed.',
        );
        $mode->update($attributes);
        $this->forgetFormOptionCaches();

        return $mode;
    }

    public function deleteMode(ModeOfRequest $mode): void
    {
        $this->guardDelete(
            $mode->isProtected(),
            $mode->assistance()->count(),
            'mode of request',
            'The Online mode is required by public intake and cannot be deleted.',
        );
        $mode->delete();
        $this->forgetFormOptionCaches();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function documentTypes(): array
    {
        return DocumentType::query()
            ->withCount(['requirements', 'documents'])
            ->orderBy('name')
            ->get()
            ->map(fn (DocumentType $type): array => [
                'id' => $type->id,
                'name' => $type->name,
                'slug' => $type->slug,
                'is_active' => $type->is_active,
                'is_protected' => $type->isProtected(),
                'locks_name' => false,
                'protected_reason' => $type->isProtected()
                    ? 'Required by the system. The name can change, but the slug stays.'
                    : null,
                'usage_count' => (int) $type->requirements_count + (int) $type->documents_count,
            ])
            ->all();
    }

    /**
     * @param  array{name: string, is_active: bool}  $attributes
     */
    public function createDocumentType(array $attributes): DocumentType
    {
        $type = DocumentType::query()->create([
            ...$attributes,
            'slug' => $this->documentSlug($attributes['name']),
        ]);
        $this->forgetFormOptionCaches();

        return $type;
    }

    /**
     * @param  array{name: string, is_active: bool}  $attributes
     */
    public function updateDocumentType(DocumentType $type, array $attributes): DocumentType
    {
        if ($type->isProtected() && $attributes['is_active'] !== true) {
            throw ValidationException::withMessages([
                'is_active' => 'This document type is required by the system and cannot be deactivated.',
            ]);
        }

        $type->update($attributes);
        $this->forgetFormOptionCaches();

        return $type;
    }

    public function deleteDocumentType(DocumentType $type): void
    {
        $type->loadCount(['requirements', 'documents']);
        $this->guardDelete(
            $type->isProtected(),
            (int) $type->requirements_count + (int) $type->documents_count,
            'document type',
            'This document type is required by the system and cannot be deleted.',
        );
        $type->delete();
        $this->forgetFormOptionCaches();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function units(): array
    {
        return ItemUnitMeasurement::query()
            ->withCount('items')
            ->orderBy('name')
            ->get()
            ->map(fn (ItemUnitMeasurement $unit): array => [
                'id' => $unit->id,
                'name' => $unit->name,
                'is_active' => $unit->is_active,
                'is_protected' => $unit->isProtected(),
                'locks_name' => $unit->isProtected(),
                'protected_reason' => $unit->isProtected()
                    ? 'Required by cash items.'
                    : null,
                'usage_count' => (int) $unit->items_count,
            ])
            ->all();
    }

    /**
     * @param  array{name: string, is_active: bool}  $attributes
     */
    public function createUnit(array $attributes): ItemUnitMeasurement
    {
        $unit = ItemUnitMeasurement::query()->create($attributes);
        $this->forgetFormOptionCaches();

        return $unit;
    }

    /**
     * @param  array{name: string, is_active: bool}  $attributes
     */
    public function updateUnit(ItemUnitMeasurement $unit, array $attributes): ItemUnitMeasurement
    {
        $this->guardLockedName(
            $unit->isProtected(),
            $unit->name,
            $attributes,
            'The php unit is required by cash items and cannot be changed.',
        );
        $unit->update($attributes);
        $this->forgetFormOptionCaches();

        return $unit;
    }

    public function deleteUnit(ItemUnitMeasurement $unit): void
    {
        $this->guardDelete(
            $unit->isProtected(),
            $unit->items()->count(),
            'unit',
            'The php unit is required by cash items and cannot be deleted.',
        );
        $unit->delete();
        $this->forgetFormOptionCaches();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sectors(): array
    {
        return Sector::query()
            ->withCount('department')
            ->orderBy('name')
            ->get()
            ->map(fn (Sector $sector): array => [
                'id' => $sector->id,
                'name' => $sector->name,
                'is_active' => $sector->is_active,
                'is_protected' => false,
                'locks_name' => false,
                'protected_reason' => null,
                'usage_count' => (int) $sector->department_count,
            ])
            ->all();
    }

    /**
     * @param  array{name: string, is_active: bool}  $attributes
     */
    public function createSector(array $attributes): Sector
    {
        $sector = Sector::query()->create($attributes);
        $this->forgetFormOptionCaches();

        return $sector;
    }

    /**
     * @param  array{name: string, is_active: bool}  $attributes
     */
    public function updateSector(Sector $sector, array $attributes): Sector
    {
        $sector->update($attributes);
        $this->forgetFormOptionCaches();

        return $sector;
    }

    public function deleteSector(Sector $sector): void
    {
        $this->guardDelete(
            false,
            $sector->department()->count(),
            'sector',
            '',
        );
        $sector->delete();
        $this->forgetFormOptionCaches();
    }

    /**
     * @param  array{name: string, is_active: bool}  $attributes
     */
    private function guardLockedName(bool $protected, string $currentName, array $attributes, string $message): void
    {
        if (! $protected) {
            return;
        }

        if (strcasecmp($currentName, $attributes['name']) !== 0) {
            throw ValidationException::withMessages([
                'name' => $message,
            ]);
        }

        if ($attributes['is_active'] !== true) {
            throw ValidationException::withMessages([
                'is_active' => $message,
            ]);
        }
    }

    private function guardDelete(bool $protected, int $usage, string $label, string $protectedMessage): void
    {
        if ($protected) {
            throw ValidationException::withMessages([
                'lookup' => $protectedMessage,
            ]);
        }

        if ($usage > 0) {
            $records = $usage === 1 ? 'record' : 'records';

            throw ValidationException::withMessages([
                'lookup' => "This {$label} is used by {$usage} {$records}. Deactivate it instead of deleting it.",
            ]);
        }
    }

    private function documentSlug(string $name): string
    {
        $base = trim(Str::snake(Str::lower($name)), '_');
        $base = $base !== '' ? substr($base, 0, 240) : 'document_type';
        $slug = $base;
        $suffix = 2;

        while (DocumentType::query()->where('slug', $slug)->exists()) {
            $slug = $base.'_'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function forgetFormOptionCaches(): void
    {
        Cache::forget('beneficiary.form_options');
        Cache::forget('beneficiary.form_options.v2');
        Cache::forget('beneficiary.form_options.v3');
        Cache::forget(BeneficiaryService::FORM_OPTIONS_CACHE_KEY);
    }
}
