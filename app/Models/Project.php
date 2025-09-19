<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Project extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'descriptions',
        'dateStarted',
        'dateEnded',
        'department_id',
        'is_closed',
        'created_at',
        'updated_at',
        'is_organization'
    ];

    protected $casts = [
        'dateStarted' => 'datetime:M d, Y',
        'dateEnded' => 'datetime:M d, Y',
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
        return $this->hasMany(Assistance::class); //todo:->delivered();
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
