<?php

namespace App\Http\Requests\Governor\Program;

use App\Models\Program;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('reviewInbox', Program::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
