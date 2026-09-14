<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemStockBalance extends Model
{
    protected $fillable = [
        'department_id',
        'item_id',
        'on_hand',
        'allocated',
        'available',
        'low_stock_notified',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'on_hand' => 'integer',
            'allocated' => 'integer',
            'available' => 'integer',
            'low_stock_notified' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
