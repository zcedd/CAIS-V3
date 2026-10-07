<?php

namespace App\Http\Requests\Admin\Workflow;

use App\Models\Workflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ActivateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workflow = $this->route('workflow');

        return $workflow instanceof Workflow && Gate::allows('manage', $workflow);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
