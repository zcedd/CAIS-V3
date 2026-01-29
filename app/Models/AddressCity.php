<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AddressCity extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['name', 'zipcode', 'excel_name'];
}
