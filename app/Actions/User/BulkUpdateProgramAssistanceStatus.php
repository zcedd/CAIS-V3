<?php

namespace App\Actions\User;

use App\Models\Assistance;
use Illuminate\Support\Facades\DB;

class BulkUpdateProgramAssistanceStatus
{
    public function __construct(
        private UpdateProgramAssistanceStatus $updateProgramAssistanceStatus,
    ) {}

    /**
     * @param  list<Assistance>  $assistances
     * @param  array{
     *     request_sub_status_id: int,
     *     recorded_at: string,
     *     remark?: string|null
     * }  $validated
     */
    public function __invoke(array $assistances, array $validated): int
    {
        return DB::transaction(function () use ($assistances, $validated): int {
            $updatedCount = 0;

            foreach ($assistances as $assistance) {
                ($this->updateProgramAssistanceStatus)($assistance, $validated);
                $updatedCount++;
            }

            return $updatedCount;
        });
    }
}
