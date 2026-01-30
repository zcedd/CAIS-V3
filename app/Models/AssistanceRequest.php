<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssistanceRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['program_id', 'beneficiary_id', 'organization_id', 'mode_of_request_id', 'date_verified', 'date_requested', 'date_denied', 'date_delivered', 'user_id', 'remark', 'created_at', 'updated_at'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function modeOfRequest(): BelongsTo
    {
        return $this->belongsTo(ModeOfRequest::class);
    }

    public function requestItem(): HasMany
    {
        return $this->hasMany(AssistanceRequestItem::class);
    }

    public function itemRelease(): HasMany
    {
        return $this->hasMany(AssistanceItemRelease::class);
    }

    public function requestSubStatus(): HasMany
    {
        return $this->hasMany(AssistanceRequestSubStatus::class);
    }

    public function scopePending($query)
    {
        $query->whereNull('date_delivered')
            ->whereNull('date_denied')
            ->whereNull('date_verified')
            ->whereNotNull('date_requested');
    }

    public function scopeVerified($query)
    {
        $query->whereNull('date_delivered')
            ->whereNotNull('date_verified');
    }

    public function scopeDelivered(EloquentBuilder $query)
    {
        $query->whereNotNull('date_delivered')
            ->whereHas('requestItem', function (EloquentBuilder $q) {
                $q->where('is_received', true);
            });
    }

    public function scopeDenied($query)
    {
        $query->whereNotNull('date_denied');
    }

    public function scopeWhereWithoutAction($query)
    {
        $query->whereNull('date_delivered')
            ->whereNull('date_denied');
    }

    /**
     * @deprecated Use scopePersonalAssistance instead.
     */
    public function scopeWherePersonalAssistance($query)
    {
        $query->whereNotNull('beneficiary_id');
    }

    public function scopePersonalAssistance($query)
    {
        $query->whereNotNull('beneficiary_id');
    }

    /**
     * @deprecated Use scopeOrganizationalAssistance instead.
     */
    public function scopeWhereOrganizationalAssistance($query)
    {
        $query->whereNotNull('organization_id');
    }

    public function scopeOrganizationalAssistance($query)
    {
        $query->whereNotNull('organization_id');
    }
}
