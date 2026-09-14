<?php

namespace App\Models;

use App\Support\AssistanceItemOrigin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AssistanceItem extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'assistance_item';

    protected $fillable = [
        'assistance_id',
        'item_id',
        'origin',
        'is_received',
        'quantity',
        'requested_quantity',
        'substituted_for_assistance_item_id',
        'substituted_at',
        'fulfillment_reason',
        'specification',
    ];

    public $incrementing = true;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_received' => 'boolean',
            'quantity' => 'integer',
            'requested_quantity' => 'integer',
            'substituted_at' => 'datetime',
        ];
    }

    public function assistance(): BelongsTo
    {
        return $this->belongsTo(Assistance::class, 'assistance_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    /**
     * The requested line this released line was handed over in place of.
     */
    public function substitutedFor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'substituted_for_assistance_item_id');
    }

    /**
     * The released lines handed over in place of this requested line.
     */
    public function substitutes(): HasMany
    {
        return $this->hasMany(self::class, 'substituted_for_assistance_item_id');
    }

    /**
     * Lines that came from the original request, excluding additional and substitute releases.
     *
     * @param  Builder<self>  $query
     */
    public function scopeRequestedOrigin(Builder $query): void
    {
        $query->where('origin', AssistanceItemOrigin::Requested);
    }

    /**
     * Lines that were actually handed over, whatever their origin.
     *
     * @param  Builder<self>  $query
     */
    public function scopeReleased(Builder $query): void
    {
        $query->where('is_received', true);
    }

    /**
     * Requested lines still owed to the beneficiary.
     *
     * @param  Builder<self>  $query
     */
    public function scopeAwaitingRelease(Builder $query): void
    {
        $query->requestedOrigin()
            ->where('is_received', false)
            ->whereNull('substituted_at');
    }

    public function isSubstituted(): bool
    {
        return $this->substituted_at !== null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->useLogName('AssistanceItem')
            ->setDescriptionForEvent(fn (string $eventName) => "This AssistanceItem model has been {$eventName}")
            ->dontSubmitEmptyLogs();
    }
}
