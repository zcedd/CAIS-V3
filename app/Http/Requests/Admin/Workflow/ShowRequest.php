<?php

namespace App\Http\Requests\Admin\Workflow;

use App\Models\Workflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workflow = $this->route('workflow');

        return $workflow instanceof Workflow && Gate::allows('view', $workflow);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
