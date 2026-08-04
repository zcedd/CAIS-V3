<?php

namespace App\Models;

use App\Actions\User\SyncAssistanceCurrentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class AssistanceRequestSubStatus extends Pivot
{
    use HasFactory;

    protected $table = 'assistance_request_sub_status';

    protected static function booted(): void
    {
        $syncCurrentStatus = static function (self $pivot): void {
            $assistance = $pivot->assistance()->withTrashed()->first();

            if ($assistance instanceof Assistance) {
                app(SyncAssistanceCurrentStatus::class)($assistance);
            }
        };

        static::created($syncCurrentStatus);
        static::updated($syncCurrentStatus);
        static::deleted($syncCurrentStatus);
    }

    public function assistance(): BelongsTo
    {
        return $this->belongsTo(Assistance::class, 'assistance_id');
    }

    public function requestSubStatus(): BelongsTo
    {
        return $this->belongsTo(RequestSubStatus::class);
    }
}
