<?php

namespace App\Models;

use Database\Factories\StockLotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class StockLot extends Model
{
    /** @use HasFactory<StockLotFactory> */
    use HasFactory;

    protected $fillable = [
        'department_id',
        'item_id',
        'batch_number',
        'expires_at',
        'received_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'received_at' => 'datetime',
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

    public function balance(): HasOne
    {
        return $this->hasOne(StockLotBalance::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isExpired(?\DateTimeInterface $at = null): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        $atDate = $at === null
            ? now()->startOfDay()
            : Carbon::parse($at)->startOfDay();

        return $this->expires_at->startOfDay()->lt($atDate);
    }
}
