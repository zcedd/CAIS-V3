<?php

namespace App\Models;

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
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'start_at' => 'datetime:M d, Y',
        'end_at' => 'datetime:M d, Y',
        'is_closed' => 'boolean',
        'is_organization' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }

    public function assistance(): HasMany
    {
        return $this->hasMany(Assistance::class);
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
     * Program IDs that share eligibility (this program today; parent + batches later).
     *
     * @return list<int>
     */
    public function familyIds(): array
    {
        return [$this->id];
    }
}
