<?php

namespace App\Http\Requests\User\WorkflowTask;

use App\Http\Requests\User\WorkflowTask\Concerns\AuthorizesWorkflowTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ClaimRequest extends FormRequest
{
    use AuthorizesWorkflowTask;

    public function authorize(): bool
    {
        return Gate::allows('claim', $this->assistance());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
