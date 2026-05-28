<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Assistance;
use App\Models\Department;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AssistanceController extends Controller
{
    /**
     * Display the specified assistance profile.
     */
    public function show(
        Request $request,
        Department $department,
        Program $program,
        Assistance $assistance,
    ): Response {
        $user = $request->user();

        abort_unless($user->department_id === $department->id, 403);
        abort_unless($program->department_id === $department->id, 404);
        abort_unless($assistance->program_id === $program->id, 404);

        $assistance->load([
            'beneficiary:id,name,cais_number,beneficiable_type,beneficiable_id',
            'modeOfRequest:id,name',
            'program:id,name,department_id',
            'program.department:id,name,slug',
            'assistanceItem:id,assistance_id,item_id,quantity,specification,is_received',
            'assistanceItem.item:id,name,item_unit_measurement_id',
            'assistanceItem.item.unitMeasurement:id,name',
            'requestSubStatus' => function ($query): void {
                $query->with('requestStatus:id,name');
            },
        ]);

        $formatDate = static function ($value): ?string {
            if ($value === null) {
                return null;
            }

            return Carbon::parse($value)->toDateString();
        };

        $beneficiaryName =  $assistance->beneficiary?->name ?? '—';
        $caisNumber = $assistance->beneficiary?->cais_number ?? '—';

        return Inertia::render('user/assistances/show', [
            'department' => $department->only(['id', 'name', 'slug']),
            'program' => $program->only(['id', 'name']),
            'assistance' => [
                'id' => $assistance->id,
                'cais_number' => $caisNumber,
                'beneficiary_name' => $beneficiaryName,
                'beneficiary_type' => class_basename($assistance->beneficiary->beneficiable),
                'mode_of_request' => $assistance->modeOfRequest?->name ?? '—',
                'date_requested' => $formatDate($assistance->date_requested),
                'date_verified' => $formatDate($assistance->date_verified),
                'date_delivered' => $formatDate($assistance->date_delivered),
                'date_denied' => $formatDate($assistance->date_denied),
                'remark' => $assistance->remark,
                'items' => $assistance->assistanceItem
                    ->map(static fn($assistanceItem): array => [
                        'name' => $assistanceItem->item?->name ?? '—',
                        'quantity' => $assistanceItem->quantity,
                        'unit' => $assistanceItem->item?->unitMeasurement?->name,
                        'specification' => $assistanceItem->specification,
                        'is_received' => (bool) $assistanceItem->is_received,
                    ])
                    ->values()
                    ->all(),
                'status_history' => $assistance->requestSubStatus
                    ->sortBy(static fn($subStatus) => $subStatus->pivot->recorded_at)
                    ->map(static fn($subStatus): array => [
                        'id' => (int) $subStatus->pivot->id,
                        'name' => $subStatus->name,
                        'parent_status' => $subStatus->requestStatus?->name,
                        'remark' => $subStatus->pivot->remark,
                        'recorded_at' => Carbon::parse($subStatus->pivot->recorded_at)->toDateTimeString(),
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }
}
