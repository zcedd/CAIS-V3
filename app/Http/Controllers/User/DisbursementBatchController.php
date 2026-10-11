<?php

namespace App\Http\Controllers\User;

use App\Actions\Disbursement\CancelDisbursementBatch;
use App\Actions\Disbursement\CompleteDisbursementBatch;
use App\Actions\Disbursement\CreateDisbursementBatch;
use App\Actions\Disbursement\DecideDisbursementBatch;
use App\Actions\Disbursement\DecideDisbursementBatchItem;
use App\Actions\Disbursement\FinalizeDisbursementBatch;
use App\Actions\Disbursement\RecordDisbursementRelease;
use App\Actions\Disbursement\ReopenRejectedDisbursementBatch;
use App\Actions\Disbursement\ScheduleDisbursementBatch;
use App\Actions\Disbursement\StartDisbursementBatch;
use App\Actions\Disbursement\StoreDisbursementBatchDocument;
use App\Actions\Disbursement\SubmitDisbursementBatch;
use App\Actions\Disbursement\UpdateDisbursementBatch;
use App\Actions\Disbursement\ValidateDisbursementBatch;
use App\Enums\DisbursementBatchStatus;
use App\Enums\RequestSubStatusCode;
use App\Exports\User\DisbursementBatchExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Disbursement\BulkDecideDisbursementBatchItemsRequest;
use App\Http\Requests\User\Disbursement\CancelDisbursementBatchRequest;
use App\Http\Requests\User\Disbursement\DecideDisbursementBatchItemRequest;
use App\Http\Requests\User\Disbursement\DecideDisbursementBatchRequest;
use App\Http\Requests\User\Disbursement\RecordDisbursementReleaseRequest;
use App\Http\Requests\User\Disbursement\ScheduleDisbursementBatchRequest;
use App\Http\Requests\User\Disbursement\StoreDisbursementBatchRequest;
use App\Http\Requests\User\Disbursement\UpdateDisbursementBatchRequest;
use App\Models\Assistance;
use App\Models\Department;
use App\Models\DisbursementBatch;
use App\Models\DisbursementBatchDocument;
use App\Models\DisbursementBatchItem;
use App\Models\Program;
use App\Services\Disbursement\DisbursementBatchDetails;
use App\Services\Disbursement\DisbursementReconciliation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DisbursementBatchController extends Controller
{
    public function index(Request $request, Department $department): Response
    {
        Gate::authorize('viewAny', [DisbursementBatch::class, $department]);

        $sort = in_array($request->string('sort')->toString(), ['batch_code', 'scheduled_at', 'status', 'title'], true)
            ? $request->string('sort')->toString()
            : 'scheduled_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $batches = DisbursementBatch::query()
            ->with(['program:id,name', 'creator:id,firstName,lastName'])
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
            ->when($request->filled('approval_mode'), fn ($query) => $query->where('approval_mode', $request->string('approval_mode')->toString()))
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
            ->through(fn (DisbursementBatch $batch): array => [
                'id' => $batch->id,
                'batch_code' => $batch->batch_code,
                'title' => $batch->title,
                'program' => $batch->program?->name,
                'scheduled_at' => $batch->scheduled_at?->toIso8601String(),
                'status' => $batch->status->value,
                'status_label' => $batch->status->label(),
                'approval_mode' => $batch->approval_mode->value,
                'beneficiary_count' => $batch->beneficiary_count,
                'approved_beneficiary_count' => $batch->approved_beneficiary_count,
                'approved_cash_total' => $batch->approved_cash_total,
                'creator' => $batch->creator?->name,
            ]);

        return Inertia::render('user/disbursements/index', [
            'department' => $department->only(['id', 'name', 'slug']),
            'batches' => $batches,
            'filters' => $request->only([
                'search', 'program_id', 'status', 'approval_mode', 'created_by',
                'scheduled_from', 'scheduled_to', 'approver_id', 'sort', 'direction',
                'venue', 'personnel_id', 'beneficiary',
            ]),
            'statuses' => collect(DisbursementBatchStatus::cases())->map(fn (DisbursementBatchStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->all(),
            'programs' => Program::query()->where('department_id', $department->id)->encodable()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function calendar(Request $request, Department $department): Response
    {
        Gate::authorize('viewAny', [DisbursementBatch::class, $department]);
        $month = $request->date('month') ?? now();

        $batches = DisbursementBatch::query()
            ->with('program:id,name')
            ->where('department_id', $department->id)
            ->whereIn('status', [
                DisbursementBatchStatus::Approved,
                DisbursementBatchStatus::Scheduled,
                DisbursementBatchStatus::InProgress,
                DisbursementBatchStatus::Completed,
            ])
            ->whereBetween('scheduled_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (DisbursementBatch $batch): array => [
                'id' => $batch->id,
                'batch_code' => $batch->batch_code,
                'title' => $batch->title,
                'program' => $batch->program?->name,
                'venue' => $batch->venue,
                'scheduled_at' => $batch->scheduled_at?->toIso8601String(),
                'status' => $batch->status->value,
                'status_label' => $batch->status->label(),
            ]);

        return Inertia::render('user/disbursements/calendar', [
            'department' => $department->only(['id', 'name', 'slug']),
            'month' => $month->format('Y-m'),
            'batches' => $batches,
        ]);
    }

    public function create(Request $request, Department $department): Response
    {
        Gate::authorize('create', [DisbursementBatch::class, $department]);

        return Inertia::render('user/disbursements/form', [
            'department' => $department->only(['id', 'name', 'slug']),
            'batch' => null,
            'programs' => $this->programs($department),
            'requests' => $this->eligibleRequests($department, $request->integer('program_id')),
        ]);
    }

    public function store(StoreDisbursementBatchRequest $request, Department $department, CreateDisbursementBatch $create): RedirectResponse
    {
        $batch = $create($department, $request->user(), $request->validated());

        return redirect()
            ->route('user.disbursements.show', ['department' => $department, 'disbursementBatch' => $batch])
            ->with('success', 'Disbursement batch created.');
    }

    public function show(Department $department, DisbursementBatch $disbursementBatch, DisbursementBatchDetails $details): Response
    {
        $this->scoped($department, $disbursementBatch);
        Gate::authorize('view', $disbursementBatch);

        return Inertia::render('user/disbursements/show', [
            'department' => $department->only(['id', 'name', 'slug']),
            'batch' => $details->payload($disbursementBatch),
        ]);
    }

    public function edit(Request $request, Department $department, DisbursementBatch $disbursementBatch): Response
    {
        $this->scoped($department, $disbursementBatch);
        Gate::authorize('update', $disbursementBatch);
        $disbursementBatch->load('items');

        return Inertia::render('user/disbursements/form', [
            'department' => $department->only(['id', 'name', 'slug']),
            'batch' => [
                'id' => $disbursementBatch->id,
                'program_id' => $disbursementBatch->program_id,
                'title' => $disbursementBatch->title,
                'description' => $disbursementBatch->description,
                'scheduled_at' => $disbursementBatch->scheduled_at?->format('Y-m-d\TH:i'),
                'venue' => $disbursementBatch->venue,
                'approval_mode' => $disbursementBatch->approval_mode->value,
                'notes' => $disbursementBatch->notes,
                'lock_version' => $disbursementBatch->lock_version,
                'assistance_ids' => $disbursementBatch->items->pluck('assistance_id')->all(),
            ],
            'programs' => $this->programs($department),
            'requests' => $this->eligibleRequests(
                $department,
                $request->integer('program_id') ?: (int) $disbursementBatch->program_id,
            ),
        ]);
    }

    public function update(
        UpdateDisbursementBatchRequest $request,
        Department $department,
        DisbursementBatch $disbursementBatch,
        UpdateDisbursementBatch $update,
    ): RedirectResponse {
        $this->scoped($department, $disbursementBatch);
        $update($disbursementBatch, $request->user(), $request->validated());

        return redirect()
            ->route('user.disbursements.show', ['department' => $department, 'disbursementBatch' => $disbursementBatch])
            ->with('success', 'Disbursement batch updated.');
    }

    public function validateBatch(Department $department, DisbursementBatch $disbursementBatch, ValidateDisbursementBatch $validate): RedirectResponse
    {
        $this->scoped($department, $disbursementBatch);
        Gate::authorize('validateBatch', $disbursementBatch);
        $validate($disbursementBatch, request()->user());

        return back()->with('success', 'Disbursement batch validated.');
    }

    public function submit(Department $department, DisbursementBatch $disbursementBatch, SubmitDisbursementBatch $submit): RedirectResponse
    {
        $this->scoped($department, $disbursementBatch);
        Gate::authorize('submit', $disbursementBatch);
        $submit($disbursementBatch, request()->user());

        return back()->with('success', 'Disbursement batch submitted for approval.');
    }

    public function decide(
        DecideDisbursementBatchRequest $request,
        Department $department,
        DisbursementBatch $disbursementBatch,
        DecideDisbursementBatch $decide,
    ): RedirectResponse {
        $this->scoped($department, $disbursementBatch);
        $decide($disbursementBatch, $request->user(), $request->validated());

        return back()->with('success', 'Decision recorded.');
    }

    public function decideItem(
        DecideDisbursementBatchItemRequest $request,
        Department $department,
        DisbursementBatch $disbursementBatch,
        DisbursementBatchItem $item,
        DecideDisbursementBatchItem $decide,
    ): RedirectResponse {
        $this->scoped($department, $disbursementBatch);
        abort_unless((int) $item->disbursement_batch_id === (int) $disbursementBatch->id, 404);
        $decide($disbursementBatch, $item, $request->user(), $request->validated());

        return back()->with('success', 'Beneficiary decision recorded.');
    }

    public function bulkDecide(
        BulkDecideDisbursementBatchItemsRequest $request,
        Department $department,
        DisbursementBatch $disbursementBatch,
        DecideDisbursementBatchItem $decide,
    ): RedirectResponse {
        $this->scoped($department, $disbursementBatch);
        $decide->bulk(
            $disbursementBatch,
            $request->user(),
            $request->validated('item_ids'),
            $request->validated('decision'),
            $request->validated('remarks'),
        );

        return back()->with('success', 'Beneficiary decisions recorded.');
    }

    public function finalize(Department $department, DisbursementBatch $disbursementBatch, FinalizeDisbursementBatch $finalize): RedirectResponse
    {
        $this->scoped($department, $disbursementBatch);
        Gate::authorize('finalize', $disbursementBatch);
        $finalize($disbursementBatch, request()->user());

        return back()->with('success', 'Disbursement review finalized.');
    }

    public function schedule(
        ScheduleDisbursementBatchRequest $request,
        Department $department,
        DisbursementBatch $disbursementBatch,
        ScheduleDisbursementBatch $schedule,
    ): RedirectResponse {
        $this->scoped($department, $disbursementBatch);
        $schedule($disbursementBatch, $request->user(), $request->validated());

        return back()->with('success', 'Disbursement schedule updated.');
    }

    public function cancel(
        CancelDisbursementBatchRequest $request,
        Department $department,
        DisbursementBatch $disbursementBatch,
        CancelDisbursementBatch $cancel,
    ): RedirectResponse {
        $this->scoped($department, $disbursementBatch);
        $cancel($disbursementBatch, $request->user(), $request->validated());

        return back()->with('success', 'Disbursement batch cancelled.');
    }

    public function reopen(Request $request, Department $department, DisbursementBatch $disbursementBatch, ReopenRejectedDisbursementBatch $reopen): RedirectResponse
    {
        $this->scoped($department, $disbursementBatch);
        Gate::authorize('reopen', $disbursementBatch);
        $reopen($disbursementBatch, $request->user(), $request->validate(['reason' => ['required', 'string']]));

        return back()->with('success', 'Rejected batch reopened as a draft.');
    }

    public function start(Department $department, DisbursementBatch $disbursementBatch, StartDisbursementBatch $start): RedirectResponse
    {
        $this->scoped($department, $disbursementBatch);
        Gate::authorize('execute', $disbursementBatch);
        $start($disbursementBatch, request()->user());

        return back()->with('success', 'Disbursement started.');
    }

    public function release(
        RecordDisbursementReleaseRequest $request,
        Department $department,
        DisbursementBatch $disbursementBatch,
        RecordDisbursementRelease $record,
    ): RedirectResponse {
        $this->scoped($department, $disbursementBatch);
        $record($disbursementBatch, $request->user(), $request->validated());

        return back()->with('success', 'Release recorded.');
    }

    public function complete(Department $department, DisbursementBatch $disbursementBatch, CompleteDisbursementBatch $complete): RedirectResponse
    {
        $this->scoped($department, $disbursementBatch);
        Gate::authorize('complete', $disbursementBatch);
        $complete($disbursementBatch, request()->user());

        return back()->with('success', 'Disbursement completed.');
    }

    public function storeDocument(
        Request $request,
        Department $department,
        DisbursementBatch $disbursementBatch,
        StoreDisbursementBatchDocument $store,
    ): RedirectResponse {
        $this->scoped($department, $disbursementBatch);
        Gate::authorize('update', $disbursementBatch);
        $store($disbursementBatch, $request->user(), $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'document_type_id' => ['nullable', 'integer', 'exists:document_types,id'],
            'notes' => ['nullable', 'string'],
        ]));

        return back()->with('success', 'Document attached.');
    }

    public function document(Department $department, DisbursementBatch $disbursementBatch, DisbursementBatchDocument $disbursementDocument): StreamedResponse
    {
        $this->scoped($department, $disbursementBatch);
        Gate::authorize('view', $disbursementBatch);
        abort_unless((int) $disbursementDocument->disbursement_batch_id === (int) $disbursementBatch->id, 404);

        return Storage::disk($disbursementDocument->disk)->download($disbursementDocument->path, $disbursementDocument->original_name);
    }

    public function export(
        Department $department,
        DisbursementBatch $disbursementBatch,
        string $report,
        DisbursementBatchDetails $details,
        DisbursementReconciliation $reconciliation,
    ): BinaryFileResponse {
        $this->scoped($department, $disbursementBatch);
        Gate::authorize('view', $disbursementBatch);
        $payload = $details->payload($disbursementBatch);

        $export = match ($report) {
            'reconciliation' => DisbursementBatchExport::reconciliation($reconciliation, $disbursementBatch),
            'history' => new DisbursementBatchExport(collect($payload['decisions']), [
                'id', 'revision', 'item_id', 'decision', 'previous_decision', 'previous_amount', 'new_amount', 'previous_quantity', 'new_quantity', 'remarks', 'actor', 'created_at',
            ]),
            'releases' => new DisbursementBatchExport(
                collect($payload['items'])->flatMap(fn (array $item) => collect($item['releases'])->map(fn (array $release): array => [
                    'beneficiary' => $item['beneficiary']['name'],
                    'cais_number' => $item['beneficiary']['cais_number'],
                    ...$release,
                ])),
                ['beneficiary', 'cais_number', 'id', 'line_id', 'outcome', 'actual_quantity', 'actual_amount', 'released_at', 'receipt_reference', 'identity_verified', 'remarks', 'released_by'],
            ),
            'acknowledgements' => new DisbursementBatchExport(
                collect($payload['items'])->filter(fn (array $item): bool => $item['distributable'])->map(fn (array $item): array => [
                    'cais_number' => $item['beneficiary']['cais_number'],
                    'beneficiary' => $item['beneficiary']['name'],
                    'status' => $item['approval_label'],
                    'receipts' => collect($item['releases'])->pluck('receipt_reference')->filter()->implode(', '),
                ]),
                ['cais_number', 'beneficiary', 'status', 'receipts'],
            ),
            default => new DisbursementBatchExport(
                collect($payload['items'])->filter(fn (array $item): bool => $report === 'roster' ? $item['distributable'] : true)->map(fn (array $item): array => [
                    'cais_number' => $item['beneficiary']['cais_number'],
                    'beneficiary' => $item['beneficiary']['name'],
                    'status' => $item['approval_label'],
                    'approved_cash' => collect($item['lines'])->sum(fn (array $line): float => (float) ($line['approved_amount'] ?? 0)),
                    'approved_quantity' => collect($item['lines'])->sum(fn (array $line): float => (float) ($line['approved_quantity'] ?? 0)),
                ]),
                ['cais_number', 'beneficiary', 'status', 'approved_cash', 'approved_quantity'],
            ),
        };

        return Excel::download($export, $disbursementBatch->batch_code.'-'.$report.'.xlsx');
    }

    private function scoped(Department $department, DisbursementBatch $batch): void
    {
        abort_unless((int) $batch->department_id === (int) $department->id, 404);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function programs(Department $department): array
    {
        return Program::query()
            ->where('department_id', $department->id)
            ->encodable()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Program $program): array => ['id' => $program->id, 'name' => $program->name])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function eligibleRequests(Department $department, int $programId): array
    {
        if ($programId < 1) {
            return [];
        }

        $program = Program::query()->where('department_id', $department->id)->whereKey($programId)->first();

        if (! $program instanceof Program) {
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
