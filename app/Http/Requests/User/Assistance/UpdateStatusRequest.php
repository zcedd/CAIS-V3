<?php

namespace App\Http\Requests\User\Assistance;

use App\Models\Assistance;
use App\Models\RequestSubStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->assistance);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Assistance $assistance */
        $assistance = $this->route('assistance');

        return [
            'request_sub_status_id' => [
                'required',
                'integer',
                Rule::exists('request_sub_statuses', 'id'),
            ],
            'recorded_at' => ['required', 'date'],
            'remark' => ['nullable', 'string'],
            'delivered_assistance_item_id' => [
                Rule::requiredIf(fn (): bool => $this->isDeliveredSubStatus()),
                'nullable',
                'integer',
                Rule::exists('assistance_item', 'id')
                    ->where('assistance_id', $assistance->id)
                    ->where('is_received', false),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'request_sub_status_id' => 'status',
            'recorded_at' => 'recorded at',
            'remark' => 'remark',
            'delivered_assistance_item_id' => 'delivered item',
        ];
    }

    private function isDeliveredSubStatus(): bool
    {
        $subStatusId = $this->integer('request_sub_status_id');

        if ($subStatusId === 0) {
            return false;
        }

        return RequestSubStatus::query()
            ->whereKey($subStatusId)
            ->whereHas('requestStatus', fn ($query) => $query->where('name', 'Delivered'))
            ->exists();
    }
}
