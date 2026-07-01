<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fund extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'amount', 'year', 'is_active', 'department_id'];

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }

    /**
     * The programs that belong to the fund.
     */
    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'fund_program', 'fund_id', 'program_id');
    }
}
