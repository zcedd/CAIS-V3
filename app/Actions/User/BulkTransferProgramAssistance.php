<?php

namespace App\Actions\User;

use App\Models\Assistance;
use App\Models\Program;
use Illuminate\Support\Facades\DB;

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
        return DB::transaction(function () use ($assistances, $targetProgram, $validated): int {
            $transferredCount = 0;

            foreach ($assistances as $assistance) {
                ($this->transferProgramAssistance)($assistance, $targetProgram, $validated);
                $transferredCount++;
            }

            return $transferredCount;
        });
    }
}
