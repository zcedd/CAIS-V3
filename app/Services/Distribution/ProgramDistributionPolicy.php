<?php

namespace App\Services\Distribution;

use App\Enums\DistributionType;
use App\Models\Program;
use Illuminate\Validation\ValidationException;

class ProgramDistributionPolicy
{
    public function assertAllows(Program $program, DistributionType $type): void
    {
        $mode = $program->resolvedDistributionMode();

        if ($type === DistributionType::Individual && ! $mode->allowsIndividual()) {
            throw ValidationException::withMessages([
                'program_id' => 'This program does not authorize individual direct release.',
            ]);
        }

        if ($type === DistributionType::Batch && ! $mode->allowsBatch()) {
            throw ValidationException::withMessages([
                'program_id' => 'This program does not authorize batch distribution.',
            ]);
        }
    }
}
