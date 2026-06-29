<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @deprecated This class is deprecated and should not be used in new code.
 * Use Fund instead.
 */
class SourceOfFund extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'funds';

    protected $fillable = ['name', 'amount', 'year', 'is_active', 'department_id'];

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }
    /**
     * The project that belong to the SourceOfFund
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function project(): BelongsToMany
    {
        return $this->belongsToMany(Project::class);
    }
}
