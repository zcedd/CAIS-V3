<?php

namespace App\Http\Requests\User\Concerns;

use App\Models\Assistance;
use App\Models\Program;

trait EnsuresAssistanceBelongsToProgram
{
    protected function ensureAssistanceBelongsToProgram(): void
    {
        $assistance = $this->route('assistance');
        $program = $this->route('program');

        if (! $assistance instanceof Assistance || ! $program instanceof Program) {
            abort(404);
        }

        if ($assistance->program_id !== $program->id) {
            abort(404);
        }
    }
}
