<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DDZobov\PivotSoftDeletes\Relations\Pivot;

class DepartmentUser extends Pivot
{
    use HasFactory;
}
