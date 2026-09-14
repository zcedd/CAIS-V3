<?php

namespace App\Exports\User;

use App\Models\Assistance;
use App\Support\EmptyCell;
use App\Support\ItemKind;
use App\Support\SpreadsheetCell;
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
            'Date Delivered',
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
                $name = $assistanceItem->item?->name ?? EmptyCell::VALUE;
                $quantity = $assistanceItem->quantity;
                $unit = $assistanceItem->item?->unitMeasurement?->name;
                $kind = $assistanceItem->item?->kind;
                $specification = trim((string) $assistanceItem->specification);

                $detail = $name;

                if (ItemKind::isCash($kind)) {
                    $detail .= ' '.ItemKind::formatQuantity(
                        $quantity !== null ? (int) $quantity : null,
                        $unit,
                        $kind,
                    );
                } else {
                    if ($quantity !== null) {
                        $detail .= " x{$quantity}";
                    }

                    if ($unit !== null) {
                        $detail .= " {$unit}";
                    }
                }

                if ($specification !== '') {
                    $detail .= " ({$specification})";
                }

                return $detail;
            })
            ->implode('; ');

        return [
            SpreadsheetCell::sanitize($assistance->beneficiary_cais_number ?? EmptyCell::VALUE),
            SpreadsheetCell::sanitize($assistance->beneficiary_name ?? EmptyCell::VALUE),
            SpreadsheetCell::sanitize($items !== '' ? $items : EmptyCell::VALUE),
            SpreadsheetCell::sanitize($assistance->mode_of_request_name ?? EmptyCell::VALUE),
            SpreadsheetCell::sanitize($assistance->request_status_name ?? EmptyCell::VALUE),
            SpreadsheetCell::sanitize($assistance->request_sub_status_name ?? EmptyCell::VALUE),
            SpreadsheetCell::sanitize($this->formatDateTime($assistance->request_sub_status_recorded_at)),
            SpreadsheetCell::sanitize($this->formatDate($assistance->date_requested)),
            SpreadsheetCell::sanitize($this->formatDate($assistance->date_delivered)),
            SpreadsheetCell::sanitize($assistance->remark ?? ''),
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
