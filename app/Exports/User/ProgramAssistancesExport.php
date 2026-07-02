<?php

namespace App\Exports\User;

use App\Models\Assistance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProgramAssistancesExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @param  Collection<int, Assistance>  $assistances
     */
    public function __construct(private readonly Collection $assistances) {}

    public function collection()
    {
        return $this->assistances;
    }

    public function headings(): array
    {
        return [
            'CAIS Number',
            'Beneficiary Name',
            'Items Requested',
            'Mode of Request',
            'Request Status',
            'Request Sub-status',
            'Sub-status Recorded At',
            'Date Requested',
            'Date Verified',
            'Date Delivered',
            'Date Denied',
            'Remark',
        ];
    }

    /**
     * @return list<string>
     */
    public function map($assistance): array
    {
        $items = $assistance->assistanceItem
            ->map(static function ($assistanceItem): string {
                $name = $assistanceItem->item?->name ?? '—';
                $quantity = $assistanceItem->quantity;
                $unit = $assistanceItem->item?->unitMeasurement?->name;
                $specification = trim((string) $assistanceItem->specification);

                $detail = $name;

                if ($quantity !== null) {
                    $detail .= " x{$quantity}";
                }

                if ($unit !== null) {
                    $detail .= " {$unit}";
                }

                if ($specification !== '') {
                    $detail .= " ({$specification})";
                }

                return $detail;
            })
            ->implode('; ');

        return [
            $assistance->beneficiary_cais_number ?? '—',
            $assistance->beneficiary_name ?? '—',
            $items !== '' ? $items : '—',
            $assistance->mode_of_request_name ?? '—',
            $assistance->request_status_name ?? '—',
            $assistance->request_sub_status_name ?? '—',
            $this->formatDateTime($assistance->request_sub_status_recorded_at),
            $this->formatDate($assistance->date_requested),
            $this->formatDate($assistance->date_verified),
            $this->formatDate($assistance->date_delivered),
            $this->formatDate($assistance->date_denied),
            $assistance->remark ?? '',
        ];
    }

    private function formatDate(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return Carbon::parse($value)->toDateString();
    }

    private function formatDateTime(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return Carbon::parse($value)->toDateTimeString();
    }
}
