<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLotBalance extends Model
{
    protected $fillable = [
        'stock_lot_id',
        'on_hand',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'on_hand' => 'integer',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class, 'stock_lot_id');
    }
}
