<?php

namespace App\Models;

use App\Support\ProgramKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Program extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'descriptions',
        'start_at',
        'end_at',
        'department_id',
        'is_closed',
        'is_organization',
        'public_intake',
        'kind',
        'parent_id',
        'batch_number',
        'batch_name',
        'created_at',
        'updated_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'kind' => ProgramKind::Standalone,
        'is_closed' => false,
        'is_organization' => false,
        'public_intake' => false,
    ];

    protected $casts = [
        'start_at' => 'datetime:M d, Y',
        'end_at' => 'datetime:M d, Y',
        'is_closed' => 'boolean',
        'is_organization' => 'boolean',
        'public_intake' => 'boolean',
        'batch_number' => 'integer',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('batch_number')->orderBy('id');
    }

    public function assistance(): HasMany
    {
        return $this->hasMany(Assistance::class);
    }

    public function assistances(): HasMany
    {
        return $this->assistance();
    }

    public function pendingAssistance(): HasMany
    {
        return $this->hasMany(Assistance::class)->pending();
    }

    public function verifiedAssistance(): HasMany
    {
        return $this->hasMany(Assistance::class)->verified();
    }

    public function deliveredAssistance(): HasMany
    {
        return $this->hasMany(Assistance::class)->delivered();
    }

    public function deniedAssistance(): HasMany
    {
        return $this->hasMany(Assistance::class)->denied();
    }

    public function fund(): BelongsToMany
    {
        return $this->belongsToMany(Fund::class, 'fund_program', 'program_id', 'fund_id');
    }

    public function item(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'item_program', 'program_id', 'item_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(ProgramField::class)->orderBy('sort_order')->orderBy('id');
    }

    public function documentRequirements(): HasMany
    {
        return $this->hasMany(ProgramDocumentRequirement::class)->orderBy('sort_order')->orderBy('id');
    }

    public function eligibilityRule(): HasOne
    {
        return $this->hasOne(ProgramEligibilityRule::class);
    }

    public function itemCaps(): HasMany
    {
        return $this->hasMany(ProgramItemCap::class);
    }

    public function itemStocks(): HasMany
    {
        return $this->hasMany(ProgramItemStock::class);
    }

    /**
     * @param  Builder<Program>  $query
     * @return Builder<Program>
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * @param  Builder<Program>  $query
     * @return Builder<Program>
     */
    public function scopeEncodable(Builder $query): Builder
    {
        return $query->whereIn('kind', ProgramKind::encodableValues());
    }

    /**
     * @param  Builder<Program>  $query
     * @return Builder<Program>
     */
    public function scopeTransferTargetsFor(Builder $query, Program $program): Builder
    {
        $query
            ->where('department_id', $program->department_id)
            ->whereKeyNot($program->id)
            ->where('is_closed', false)
            ->where('is_organization', $program->is_organization);

        if ($program->isBatch()) {
            return $query
                ->where('kind', ProgramKind::Batch)
                ->where('parent_id', $program->parent_id);
        }

        return $query->where('kind', ProgramKind::Standalone);
    }

    public function isScheme(): bool
    {
        return ProgramKind::isScheme($this->kind);
    }

    public function isBatch(): bool
    {
        return ProgramKind::isBatch($this->kind);
    }

    public function isStandalone(): bool
    {
        return ProgramKind::isStandalone($this->kind);
    }

    public function isEncodable(): bool
    {
        return ProgramKind::isEncodable($this->kind) && ! $this->is_closed;
    }

    public function acceptsPublicIntake(): bool
    {
        return (bool) $this->public_intake
            && $this->isEncodable()
            && ! $this->is_organization;
    }

    /**
     * @param  Builder<Program>  $query
     * @return Builder<Program>
     */
    public function scopeAcceptingPublicIntake(Builder $query): Builder
    {
        return $query
            ->where('public_intake', true)
            ->where('is_organization', false)
            ->where('is_closed', false)
            ->whereIn('kind', ProgramKind::encodableValues());
    }

    public function isEffectivelyClosed(): bool
    {
        if (! $this->isScheme()) {
            return (bool) $this->is_closed;
        }

        if (! $this->relationLoaded('batches')) {
            return $this->batches()->exists()
                && ! $this->batches()->where('is_closed', false)->exists();
        }

        if ($this->batches->isEmpty()) {
            return (bool) $this->is_closed;
        }

        return $this->batches->every(static fn (Program $batch): bool => (bool) $batch->is_closed);
    }

    /**
     * Program IDs that share eligibility (sibling batches, or this standalone).
     *
     * @return list<int>
     */
    public function familyIds(): array
    {
        if ($this->isStandalone() || $this->kind === null) {
            return [$this->id];
        }

        $parentId = $this->isScheme() ? $this->id : $this->parent_id;

        if ($parentId === null) {
            return [$this->id];
        }

        return self::query()
            ->where('parent_id', $parentId)
            ->where('kind', ProgramKind::Batch)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    public function eligibilityProgram(): Program
    {
        if ($this->isBatch()) {
            $parent = $this->parent;

            if ($parent instanceof self) {
                return $parent;
            }
        }

        return $this;
    }

    public static function composeBatchDisplayName(string $schemeName, string $batchName): string
    {
        return $schemeName.' — '.$batchName;
    }
}
