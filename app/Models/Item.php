<?php

namespace App\Models;

use App\Enums\ItemKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Item extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'kind',
        'department_id',
        'item_unit_measurement_id',
        'unspsc_code_id',
        'is_perishable',
        'low_stock_threshold',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'kind' => ItemKind::Goods->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_perishable' => 'boolean',
            'low_stock_threshold' => 'integer',
            'kind' => ItemKind::class,
        ];
    }

    public function tracksInventory(): bool
    {
        return ($this->kind ?? ItemKind::Goods)->tracksInventory();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->useLogName('Item')
            ->setDescriptionForEvent(fn (string $eventName) => "This Item model has been {$eventName}")
            ->dontSubmitEmptyLogs();
    }

    public function assistance()
    {
        return $this->belongsToMany(Assistance::class)->withSoftDeletes()->using(AssistanceItem::class);
    }

    /**
     * The project that belong to the Item
     *
     * @return BelongsToMany
     */
    public function project()
    {
        return $this->belongsToMany(Project::class)->using(ItemProject::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }

    public function unitMeasurement(): BelongsTo
    {
        return $this->belongsTo(ItemUnitMeasurement::class, 'item_unit_measurement_id');
    }

    public function unspscCode(): BelongsTo
    {
        return $this->belongsTo(UnspscCode::class);
    }

    public function stockBalance(): HasOne
    {
        return $this->hasOne(ItemStockBalance::class);
    }

    public function stockLots(): HasMany
    {
        return $this->hasMany(StockLot::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function programStocks(): HasMany
    {
        return $this->hasMany(ProgramItemStock::class);
    }
}
