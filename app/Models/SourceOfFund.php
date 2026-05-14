<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SourceOfFund extends Model
{
    use HasFactory;

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
