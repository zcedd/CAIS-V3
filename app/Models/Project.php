<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
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
        'source_of_fund_id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'start_at' => 'datetime:M d, Y',
        'end_at' => 'datetime:M d, Y',
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

    public function sourceOfFund(): BelongsToMany
    {
        return $this->belongsToMany(SourceOfFund::class);
    }

    public function item(): BelongsToMany
    {
        return $this->belongsToMany(Item::class);
    }
}
