<?php

namespace App\Http\Requests\User\Assistance;

use App\Models\Assistance;
use App\Models\Program;
use App\Models\RequestSubStatus;
use App\Support\RequestStatusCode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BulkUpdateStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Program $program */
        $program = $this->route('program');

        return $this->user()?->department_id === $program->department_id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Program $program */
        $program = $this->route('program');

        return [
            'assistance_ids' => ['required', 'array', 'min:1'],
            'assistance_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('assistances', 'id')
                    ->where('program_id', $program->id),
            ],
            'request_sub_status_id' => [
                'required',
                'integer',
                Rule::exists('request_sub_statuses', 'id'),
            ],
            'recorded_at' => ['required', 'date'],
            'remark' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->isDeliveredSubStatus()) {
                    $validator->errors()->add(
                        'request_sub_status_id',
                        'Delivered status cannot be applied in bulk. Update delivered items individually.',
                    );
                }

                foreach ($this->assistances() as $assistance) {
                    if (! Gate::allows('update', $assistance)) {
                        $validator->errors()->add(
                            'assistance_ids',
                            'You are not authorized to update one or more selected assistance records.',
                        );

                        break;
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'assistance_ids' => 'selected assistance records',
            'assistance_ids.*' => 'assistance record',
            'request_sub_status_id' => 'status',
            'recorded_at' => 'recorded at',
            'remark' => 'remark',
        ];
    }

    /**
     * @return list<Assistance>
     */
    public function assistances(): array
    {
        /** @var Program $program */
        $program = $this->route('program');

        return Assistance::query()
            ->where('program_id', $program->id)
            ->whereIn('id', $this->input('assistance_ids', []))
            ->get()
            ->all();
    }

    private function isDeliveredSubStatus(): bool
    {
        $subStatusId = $this->integer('request_sub_status_id');

        if ($subStatusId === 0) {
            return false;
        }

        return RequestSubStatus::query()
            ->whereKey($subStatusId)
            ->whereHas('requestStatus', fn ($query) => $query
                ->where('code', RequestStatusCode::Delivered->value)
                ->orWhere('name', 'Delivered'))
            ->exists();
    }
}
