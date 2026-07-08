<?php

namespace App\Actions\User;

use App\Models\Assistance;
use App\Models\Program;

class TransferProgramAssistance
{
    /**
     * @param  array{target_program_id: int}  $validated
     */
    public function __invoke(Assistance $assistance, Program $targetProgram, array $validated): Assistance
    {
        $assistance->update([
            'program_id' => $targetProgram->id,
        ]);

        return $assistance->refresh();
    }
}
