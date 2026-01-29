<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class AssistanceRequest extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['program_id', 'beneficiary_id', 'organization_id', 'mode_of_request_id', 'date_verified', 'date_requested', 'date_denied', 'date_delivered', 'user_id', 'remark', 'created_at', 'updated_at'];

    protected function makeAllSearchableUsing($query)
    {
        return $query->with('program', 'beneficiary');
    }

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id', 'id');
    }

    public function individual()
    {
        return $this->belongsTo(Individual::class, 'beneficiary_id', 'id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function modeOfRequest(): BelongsTo
    {
        return $this->belongsTo(ModeOfRequest::class);
    }

    public function item()
    {
        return $this->belongsToMany(Item::class)->withPivot('is_received', 'specification')->withSoftDeletes()->withTimestamps()->using(AssistanceRequestItem::class);
    }

    public function assistanceItem(): HasMany
    {
        return $this->hasMany(AssistanceRequestItem::class);
    }

    public function itemPivot()
    {
        return $this->hasMany(AssistanceRequestItem::class);
    }

    /**
     * Get the requestSubStatus that owns the Assistance
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function requestSubStatus(): BelongsToMany
    {
        return $this->belongsToMany(RequestSubStatus::class)->withPivot('remark', 'created_at', 'updated_at')
            ->orderByDesc('pivot_created_at')
            ->withTimestamps()
            ->using(AssistanceRequestSubStatus::class);
    }

    /*
        filter date between
    */
    public function scopeFilterDate($query, $column, $filterDate)
    {
        if (array_key_exists(1, $filterDate)) {
            $query->whereBetween($column, $filterDate);
        } else if ($filterDate) {
            $query->whereDate($column, $filterDate[0]);
        }

        return $query;
    }

    /**
     * @deprecated Use scopePending instead.
     */
    public function scopeWherePending($query)
    {
        $query->whereNull("dateDelivered")
            ->whereNull("dateDenied")
            ->whereNull("dateVerified")
            ->whereNotNull('dateRequested');
    }

    /**
     * @deprecated Use scopeVerified instead.
     */
    public function scopeWhereVerified($query)
    {
        $query->whereNull("dateDelivered")
            ->whereNotNull("dateVerified");
    }

    /**
     * @deprecated Use scopeDelivered instead.
     */
    public function scopeWhereDelivered(EloquentBuilder $query)
    {
        // This method is deprecated. Use scopeDelivered instead.
        $query->whereNotNull("dateDelivered")
            ->whereHas('assistanceItem', function (EloquentBuilder $query) {
                $query->where('is_received', true);
            });
    }

    /**
     * @deprecated Use scopeDenied instead.
     */
    public function scopeWhereDenied($query)
    {
        $query->whereNotNull("dateDenied");
    }

    public function scopePending($query)
    {
        $query->whereNull("dateDelivered")
            ->whereNull("dateDenied")
            ->whereNull("dateVerified")
            ->whereNotNull('dateRequested');
    }

    public function scopeVerified($query)
    {
        $query->whereNull("dateDelivered")
            ->whereNotNull("dateVerified");
    }

    public function scopeDelivered(EloquentBuilder $query)
    {
        $query->whereNotNull("dateDelivered")
            ->whereHas('assistanceItem', function (EloquentBuilder $query) {
                $query->where('is_received', true);
            });
    }

    public function scopeDenied($query)
    {
        $query->whereNotNull("dateDenied");
    }

    public function scopeWhereWithoutAction($query)
    {
        $query->whereNull('dateDelivered')
            ->whereNull('dateDenied');
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
