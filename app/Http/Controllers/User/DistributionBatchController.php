<?php

namespace App\Http\Controllers\User;

use App\Actions\Distribution\CancelDistributionBatch;
use App\Actions\Distribution\CompleteDistributionBatch;
use App\Actions\Distribution\CreateDistributionBatch;
use App\Actions\Distribution\DecideDistributionBatch;
use App\Actions\Distribution\DecideDistributionBatchItem;
use App\Actions\Distribution\FinalizeDistributionBatch;
use App\Actions\Distribution\RecordDistributionRelease;
use App\Actions\Distribution\ReopenRejectedDistributionBatch;
use App\Actions\Distribution\ScheduleDistributionBatch;
use App\Actions\Distribution\StartDistributionBatch;
use App\Actions\Distribution\StoreDistributionBatchDocument;
use App\Actions\Distribution\SubmitDistributionBatch;
use App\Actions\Distribution\UpdateDistributionBatch;
use App\Actions\Distribution\ValidateDistributionBatch;
use App\Enums\DistributionBatchStatus;
use App\Enums\ProgramApprovalStatus;
use App\Enums\RequestSubStatusCode;
use App\Exports\User\DistributionBatchExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Distribution\BulkDecideDistributionBatchItemsRequest;
use App\Http\Requests\User\Distribution\CancelDistributionBatchRequest;
use App\Http\Requests\User\Distribution\DecideDistributionBatchItemRequest;
use App\Http\Requests\User\Distribution\DecideDistributionBatchRequest;
use App\Http\Requests\User\Distribution\RecordDistributionReleaseRequest;
use App\Http\Requests\User\Distribution\ScheduleDistributionBatchRequest;
use App\Http\Requests\User\Distribution\StoreDistributionBatchRequest;
use App\Http\Requests\User\Distribution\UpdateDistributionBatchRequest;
use App\Models\Assistance;
use App\Models\Department;
use App\Models\DistributionBatch;
use App\Models\DistributionBatchDocument;
use App\Models\DistributionBatchItem;
use App\Models\Program;
use App\Services\Distribution\DistributionBatchDetails;
use App\Services\Distribution\DistributionReconciliation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DistributionBatchController extends Controller
{
    public function index(Request $request, Department $department, DistributionReconciliation $reconciliation): Response
    {
        Gate::authorize('viewAny', [DistributionBatch::class, $department]);

        $sort = in_array($request->string('sort')->toString(), ['batch_code', 'scheduled_at', 'status', 'title'], true)
            ? $request->string('sort')->toString()
            : 'scheduled_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $batches = DistributionBatch::query()
            ->with([
                'program:id,name',
                'creator:id,firstName,lastName',
                'items.lines.item.unitMeasurement',
            ])
            ->where('department_id', $department->id)
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->trim()->toString().'%';
                $query->where(function ($inner) use ($search): void {
                    $inner->where('batch_code', 'like', $search)
                        ->orWhere('title', 'like', $search);
                });
            })
            ->when($request->filled('program_id'), fn ($query) => $query->where('program_id', $request->integer('program_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('created_by'), fn ($query) => $query->where('created_by', $request->integer('created_by')))
            ->when($request->filled('scheduled_from'), fn ($query) => $query->whereDate('scheduled_at', '>=', $request->string('scheduled_from')->toString()))
            ->when($request->filled('scheduled_to'), fn ($query) => $query->whereDate('scheduled_at', '<=', $request->string('scheduled_to')->toString()))
            ->when($request->filled('venue'), fn ($query) => $query->where('venue', 'like', '%'.$request->string('venue')->trim()->toString().'%'))
            ->when($request->filled('personnel_id'), fn ($query) => $query->whereHas(
                'assignments',
                fn ($assignments) => $assignments->where('user_id', $request->integer('personnel_id')),
            ))
            ->when($request->filled('beneficiary'), function ($query) use ($request): void {
                $search = '%'.$request->string('beneficiary')->trim()->toString().'%';
                $query->whereHas('items.beneficiary', function ($beneficiary) use ($search): void {
                    $beneficiary->where('name', 'like', $search)
                        ->orWhere('cais_number', 'like', $search);
                });
            })
            ->when($request->filled('approver_id'), function ($query) use ($request): void {
                $query->whereHas('decisions', fn ($decisions) => $decisions->where('actor_id', $request->integer('approver_id')));
            })
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString()
            ->through(fn (DistributionBatch $batch): array => [
                'id' => $batch->id,
                'batch_code' => $batch->batch_code,
                'title' => $batch->title,
                'program' => $batch->program?->name,
                'scheduled_at' => $batch->scheduled_at?->toIso8601String(),
                'status' => $batch->status->value,
                'status_label' => $batch->status->label(),
                'beneficiary_count' => $batch->beneficiary_count,
                'approved_beneficiary_count' => $batch->approved_beneficiary_count,
                'approved_cash_total' => $batch->approved_cash_total,
                'offer' => $reconciliation->offer($batch),
                'creator' => $batch->creator?->name,
            ]);

        return Inertia::render('user/distributions/index', [
            'department' => $department->only(['id', 'name', 'slug']),
            'batches' => $batches,
            'filters' => $request->only([
                'search', 'program_id', 'status', 'created_by',
                'scheduled_from', 'scheduled_to', 'approver_id', 'sort', 'direction',
                'venue', 'personnel_id', 'beneficiary',
            ]),
            'statuses' => collect(DistributionBatchStatus::cases())->map(fn (DistributionBatchStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->all(),
            'programs' => Program::query()->where('department_id', $department->id)->encodable()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function calendar(Request $request, Department $department): Response
    {
        Gate::authorize('viewAny', [DistributionBatch::class, $department]);
        $month = $request->date('month') ?? now();

        $batches = DistributionBatch::query()
            ->with('program:id,name')
            ->where('department_id', $department->id)
            ->whereIn('status', [
                DistributionBatchStatus::Approved,
                DistributionBatchStatus::Scheduled,
                DistributionBatchStatus::InProgress,
                DistributionBatchStatus::Completed,
            ])
            ->whereBetween('scheduled_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (DistributionBatch $batch): array => [
                'id' => $batch->id,
                'batch_code' => $batch->batch_code,
                'title' => $batch->title,
                'program' => $batch->program?->name,
                'venue' => $batch->venue,
                'scheduled_at' => $batch->scheduled_at?->toIso8601String(),
                'status' => $batch->status->value,
                'status_label' => $batch->status->label(),
            ]);

        return Inertia::render('user/distributions/calendar', [
            'department' => $department->only(['id', 'name', 'slug']),
            'month' => $month->format('Y-m'),
            'batches' => $batches,
        ]);
    }

    public function create(Request $request, Department $department): Response
    {
        Gate::authorize('create', [DistributionBatch::class, $department]);

        return Inertia::render('user/distributions/form', [
            'department' => $department->only(['id', 'name', 'slug']),
            'batch' => null,
            'programs' => $this->programs($department),
            'requests' => $this->eligibleRequests($department, $request->integer('program_id')),
        ]);
    }

    public function store(StoreDistributionBatchRequest $request, Department $department, CreateDistributionBatch $create): RedirectResponse
    {
        $batch = $create($department, $request->user(), $request->validated());

        return redirect()
            ->route('user.distributions.show', ['department' => $department, 'distributionBatch' => $batch])
            ->with('success', 'Distribution batch created.');
    }

    public function show(Department $department, DistributionBatch $distributionBatch, DistributionBatchDetails $details): Response
    {
        $this->scoped($department, $distributionBatch);
        Gate::authorize('view', $distributionBatch);

        return Inertia::render('user/distributions/show', [
            'department' => $department->only(['id', 'name', 'slug']),
            'batch' => $details->payload($distributionBatch),
        ]);
    }

    public function edit(Request $request, Department $department, DistributionBatch $distributionBatch): Response
    {
        $this->scoped($department, $distributionBatch);
        Gate::authorize('update', $distributionBatch);
        $distributionBatch->load('items');

        return Inertia::render('user/distributions/form', [
            'department' => $department->only(['id', 'name', 'slug']),
            'batch' => [
                'id' => $distributionBatch->id,
                'program_id' => $distributionBatch->program_id,
                'title' => $distributionBatch->title,
                'description' => $distributionBatch->description,
                'scheduled_at' => $distributionBatch->scheduled_at?->format('Y-m-d\TH:i'),
                'venue' => $distributionBatch->venue,
                'notes' => $distributionBatch->notes,
                'lock_version' => $distributionBatch->lock_version,
                'assistance_ids' => $distributionBatch->items->pluck('assistance_id')->all(),
            ],
            'programs' => $this->programs($department, (int) $distributionBatch->program_id),
            'requests' => $this->eligibleRequests(
                $department,
                $request->integer('program_id') ?: (int) $distributionBatch->program_id,
                (int) $distributionBatch->program_id,
            ),
        ]);
    }

    public function update(
        UpdateDistributionBatchRequest $request,
        Department $department,
        DistributionBatch $distributionBatch,
        UpdateDistributionBatch $update,
    ): RedirectResponse {
        $this->scoped($department, $distributionBatch);
        $update($distributionBatch, $request->user(), $request->validated());

        return redirect()
            ->route('user.distributions.show', ['department' => $department, 'distributionBatch' => $distributionBatch])
            ->with('success', 'Distribution batch updated.');
    }

    public function validateBatch(Department $department, DistributionBatch $distributionBatch, ValidateDistributionBatch $validate): RedirectResponse
    {
        $this->scoped($department, $distributionBatch);
        Gate::authorize('validateBatch', $distributionBatch);
        $validate($distributionBatch, request()->user());

        return back()->with('success', 'Distribution batch validated.');
    }

    public function submit(Department $department, DistributionBatch $distributionBatch, SubmitDistributionBatch $submit): RedirectResponse
    {
        $this->scoped($department, $distributionBatch);
        Gate::authorize('submit', $distributionBatch);
        $submit($distributionBatch, request()->user());

        return back()->with('success', 'Distribution batch submitted for approval.');
    }

    public function decide(
        DecideDistributionBatchRequest $request,
        Department $department,
        DistributionBatch $distributionBatch,
        DecideDistributionBatch $decide,
    ): RedirectResponse {
        $this->scoped($department, $distributionBatch);
        $decide($distributionBatch, $request->user(), $request->validated());

        return back()->with('success', 'Decision recorded.');
    }

    public function decideItem(
        DecideDistributionBatchItemRequest $request,
        Department $department,
        DistributionBatch $distributionBatch,
        DistributionBatchItem $item,
        DecideDistributionBatchItem $decide,
    ): RedirectResponse {
        $this->scoped($department, $distributionBatch);
        abort_unless((int) $item->distribution_batch_id === (int) $distributionBatch->id, 404);
        $decide($distributionBatch, $item, $request->user(), $request->validated());

        return back()->with('success', 'Beneficiary decision recorded.');
    }

    public function bulkDecide(
        BulkDecideDistributionBatchItemsRequest $request,
        Department $department,
        DistributionBatch $distributionBatch,
        DecideDistributionBatchItem $decide,
    ): RedirectResponse {
        $this->scoped($department, $distributionBatch);
        $decide->bulk(
            $distributionBatch,
            $request->user(),
            $request->validated('item_ids'),
            $request->validated('decision'),
            $request->validated('remarks'),
        );

        return back()->with('success', 'Beneficiary decisions recorded.');
    }

    public function finalize(Department $department, DistributionBatch $distributionBatch, FinalizeDistributionBatch $finalize): RedirectResponse
    {
        $this->scoped($department, $distributionBatch);
        Gate::authorize('finalize', $distributionBatch);
        $finalize($distributionBatch, request()->user());

        return back()->with('success', 'Distribution review finalized.');
    }

    public function schedule(
        ScheduleDistributionBatchRequest $request,
        Department $department,
        DistributionBatch $distributionBatch,
        ScheduleDistributionBatch $schedule,
    ): RedirectResponse {
        $this->scoped($department, $distributionBatch);
        $schedule($distributionBatch, $request->user(), $request->validated());

        return back()->with('success', 'Distribution schedule updated.');
    }

    public function cancel(
        CancelDistributionBatchRequest $request,
        Department $department,
        DistributionBatch $distributionBatch,
        CancelDistributionBatch $cancel,
    ): RedirectResponse {
        $this->scoped($department, $distributionBatch);
        $cancel($distributionBatch, $request->user(), $request->validated());

        return back()->with('success', 'Distribution batch cancelled.');
    }

    public function reopen(Request $request, Department $department, DistributionBatch $distributionBatch, ReopenRejectedDistributionBatch $reopen): RedirectResponse
    {
        $this->scoped($department, $distributionBatch);
        Gate::authorize('reopen', $distributionBatch);
        $reopen($distributionBatch, $request->user(), $request->validate(['reason' => ['required', 'string']]));

        return back()->with('success', 'Rejected batch reopened as a draft.');
    }

    public function start(Department $department, DistributionBatch $distributionBatch, StartDistributionBatch $start): RedirectResponse
    {
        $this->scoped($department, $distributionBatch);
        Gate::authorize('execute', $distributionBatch);
        $start($distributionBatch, request()->user());

        return back()->with('success', 'Distribution started.');
    }

    public function release(
        RecordDistributionReleaseRequest $request,
        Department $department,
        DistributionBatch $distributionBatch,
        RecordDistributionRelease $record,
    ): RedirectResponse {
        $this->scoped($department, $distributionBatch);
        $record($distributionBatch, $request->user(), $request->validated());

        return back()->with('success', 'Release recorded.');
    }

    public function complete(Department $department, DistributionBatch $distributionBatch, CompleteDistributionBatch $complete): RedirectResponse
    {
        $this->scoped($department, $distributionBatch);
        Gate::authorize('complete', $distributionBatch);
        $complete($distributionBatch, request()->user());

        return back()->with('success', 'Distribution completed.');
    }

    public function storeDocument(
        Request $request,
        Department $department,
        DistributionBatch $distributionBatch,
        StoreDistributionBatchDocument $store,
    ): RedirectResponse {
        $this->scoped($department, $distributionBatch);
        Gate::authorize('update', $distributionBatch);
        $store($distributionBatch, $request->user(), $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'document_type_id' => ['nullable', 'integer', 'exists:document_types,id'],
            'notes' => ['nullable', 'string'],
        ]));

        return back()->with('success', 'Document attached.');
    }

    public function document(Department $department, DistributionBatch $distributionBatch, DistributionBatchDocument $distributionDocument): StreamedResponse
    {
        $this->scoped($department, $distributionBatch);
        Gate::authorize('view', $distributionBatch);
        abort_unless((int) $distributionDocument->distribution_batch_id === (int) $distributionBatch->id, 404);

        return Storage::disk($distributionDocument->disk)->download($distributionDocument->path, $distributionDocument->original_name);
    }

    public function export(
        Department $department,
        DistributionBatch $distributionBatch,
        string $report,
        DistributionBatchDetails $details,
        DistributionReconciliation $reconciliation,
    ): BinaryFileResponse {
        $this->scoped($department, $distributionBatch);
        Gate::authorize('view', $distributionBatch);
        $payload = $details->payload($distributionBatch);

        $export = match ($report) {
            'reconciliation' => DistributionBatchExport::reconciliation($reconciliation, $distributionBatch),
            'history' => new DistributionBatchExport(collect($payload['decisions']), [
                'id', 'revision', 'item_id', 'decision', 'previous_decision', 'previous_amount', 'new_amount', 'previous_quantity', 'new_quantity', 'remarks', 'actor', 'created_at',
            ]),
            'releases' => new DistributionBatchExport(
                collect($payload['items'])->flatMap(fn (array $item) => collect($item['releases'])->map(fn (array $release): array => [
                    'beneficiary' => $item['beneficiary']['name'],
                    'cais_number' => $item['beneficiary']['cais_number'],
                    ...$release,
                ])),
                ['beneficiary', 'cais_number', 'id', 'line_id', 'outcome', 'actual_quantity', 'actual_amount', 'released_at', 'receipt_reference', 'identity_verified', 'remarks', 'released_by'],
            ),
            'acknowledgements' => new DistributionBatchExport(
                collect($payload['items'])->filter(fn (array $item): bool => $item['distributable'])->map(fn (array $item): array => [
                    'cais_number' => $item['beneficiary']['cais_number'],
                    'beneficiary' => $item['beneficiary']['name'],
                    'status' => $item['approval_label'],
                    'receipts' => collect($item['releases'])->pluck('receipt_reference')->filter()->implode(', '),
                ]),
                ['cais_number', 'beneficiary', 'status', 'receipts'],
            ),
            default => new DistributionBatchExport(
                collect($payload['items'])->filter(fn (array $item): bool => $report === 'roster' ? $item['distributable'] : true)->map(fn (array $item): array => [
                    'cais_number' => $item['beneficiary']['cais_number'],
                    'beneficiary' => $item['beneficiary']['name'],
                    'status' => $item['approval_label'],
                    'goods' => $this->linesForKind($item['lines'], 'goods'),
                    'services' => $this->linesForKind($item['lines'], 'service'),
                    'cash' => $this->linesForKind($item['lines'], 'cash'),
                ]),
                ['cais_number', 'beneficiary', 'status', 'goods', 'services', 'cash'],
            ),
        };

        return Excel::download($export, $distributionBatch->batch_code.'-'.$report.'.xlsx');
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function linesForKind(array $lines, string $kind): string
    {
        $parts = [];

        foreach ($lines as $line) {
            if (($line['kind'] ?? 'goods') !== $kind) {
                continue;
            }

            $name = (string) ($line['item_name'] ?? 'Item');

            if ($kind === 'cash') {
                $parts[] = $name.' '.($line['approved_amount'] ?? $line['proposed_amount'] ?? '0');

                continue;
            }

            $quantity = $line['approved_quantity'] ?? $line['proposed_quantity'] ?? '0';
            $unit = $line['unit'] ?? null;
            $parts[] = trim($name.' '.$quantity.(filled($unit) ? ' '.$unit : ''));
        }

        return implode('; ', $parts);
    }

    private function scoped(Department $department, DistributionBatch $batch): void
    {
        abort_unless((int) $batch->department_id === (int) $department->id, 404);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function programs(Department $department, ?int $includeProgramId = null): array
    {
        return Program::query()
            ->where('department_id', $department->id)
            ->encodable()
            ->where(function (Builder $query) use ($includeProgramId): void {
                $query->approved();

                if ($includeProgramId !== null) {
                    $query->orWhere('id', $includeProgramId);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Program $program): array => ['id' => $program->id, 'name' => $program->name])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function eligibleRequests(Department $department, int $programId, ?int $retainedProgramId = null): array
    {
        if ($programId < 1) {
            return [];
        }

        $program = Program::query()->where('department_id', $department->id)->whereKey($programId)->first();

        if (! $program instanceof Program) {
            return [];
        }

        $keepsCurrentProgram = $retainedProgramId !== null && (int) $program->id === $retainedProgramId;

        if ($program->approval_status !== ProgramApprovalStatus::Approved && ! $keepsCurrentProgram) {
            return [];
        }

        return Assistance::query()
            ->with(['beneficiary:id,name,cais_number', 'assistanceItem.item:id,name,kind', 'currentRequestSubStatus'])
            ->where('program_id', $program->id)
            ->where('was_delivered', false)
            ->get()
            ->filter(function (Assistance $assistance): bool {
                foreach ([RequestSubStatusCode::Verified, RequestSubStatusCode::Approved, RequestSubStatusCode::ReadyForRelease] as $code) {
                    if ($code->matches($assistance->currentRequestSubStatus)) {
                        return true;
                    }
                }

                return false;
            })
            ->map(fn (Assistance $assistance): array => [
                'id' => $assistance->id,
                'beneficiary' => $assistance->beneficiary?->name,
                'cais_number' => $assistance->beneficiary?->cais_number,
                'status' => $assistance->currentRequestSubStatus?->name,
                'lines' => $assistance->assistanceItem->map(fn ($line): array => [
                    'name' => $line->item?->name,
                    'quantity' => $line->requested_quantity ?? $line->quantity,
                    'kind' => $line->item?->kind?->value,
                ])->all(),
            ])
            ->values()
            ->all();
    }
}
