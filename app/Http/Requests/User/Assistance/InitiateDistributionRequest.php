<?php

namespace App\Http\Requests\User\Assistance;

use App\Http\Requests\User\Concerns\EnsuresAssistanceBelongsToProgram;
use App\Models\Assistance;
use App\Models\Department;
use App\Models\DistributionBatch;
use App\Models\Program;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InitiateDistributionRequest extends FormRequest
{
    use EnsuresAssistanceBelongsToProgram;

    public function authorize(): bool
    {
        $this->ensureAssistanceBelongsToProgram();

        $department = $this->route('department');
        $program = $this->route('program');
        $assistance = $this->route('assistance');

        if (! $department instanceof Department || ! $program instanceof Program || ! $assistance instanceof Assistance) {
            return false;
        }

        if ((int) $program->department_id !== (int) $department->id) {
            abort(404);
        }

        if (! Gate::allows('view', $assistance)) {
            abort(404);
        }

        return $this->user()?->can('create', [DistributionBatch::class, $department]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', 'string', Rule::in(['individual', 'batch'])],
        ];
    }
}
