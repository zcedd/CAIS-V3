<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Fund extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'amount', 'year', 'is_active', 'department_id'];

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }
    /**
     * The program that belong to the Fund
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function program(): BelongsToMany
    {
        return $this->belongsToMany(Program::class);
    }
}
