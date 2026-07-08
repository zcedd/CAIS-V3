<?php

namespace App\Actions\User;

use App\Models\Assistance;
use App\Models\Program;

class BulkTransferProgramAssistance
{
    public function __construct(
        private TransferProgramAssistance $transferProgramAssistance,
    ) {}

    /**
     * @param  list<Assistance>  $assistances
     * @param  array{target_program_id: int}  $validated
     */
    public function __invoke(array $assistances, Program $targetProgram, array $validated): int
    {
        $transferredCount = 0;

        foreach ($assistances as $assistance) {
            ($this->transferProgramAssistance)($assistance, $targetProgram, $validated);
            $transferredCount++;
        }

        return $transferredCount;
    }
}
