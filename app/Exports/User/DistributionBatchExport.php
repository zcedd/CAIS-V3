<?php

namespace App\Exports\User;

use App\Models\DistributionBatch;
use App\Services\Distribution\DistributionReconciliation;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DistributionBatchExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  list<string>  $headings
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly array $headings,
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->headings;
    }

    public static function reconciliation(DistributionReconciliation $reconciliation, DistributionBatch $batch): self
    {
        $summary = $reconciliation->summarize($batch);

        return new self(collect([
            [
                'approved_beneficiaries' => $summary['approved_beneficiaries'],
                'served_beneficiaries' => $summary['served_beneficiaries'],
                'partial_beneficiaries' => $summary['partial_beneficiaries'],
                'absent_beneficiaries' => $summary['absent_beneficiaries'],
                'excluded_beneficiaries' => $summary['excluded_beneficiaries'],
                'unserved_beneficiaries' => $summary['unserved_beneficiaries'],
                'approved_cash' => $summary['approved_cash'],
                'actual_cash' => $summary['actual_cash'],
            ],
        ]), [
            'Approved beneficiaries',
            'Served',
            'Partial',
            'Absent',
            'Excluded',
            'Unserved',
            'Approved cash',
            'Actual cash',
        ]);
    }
}
