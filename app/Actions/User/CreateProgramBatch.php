<?php

namespace App\Actions\User;

use App\Models\Program;
use App\Services\User\ProgramDocumentRequirementService;
use App\Services\User\ProgramFieldService;
use App\Support\ProgramKind;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class CreateProgramBatch
{
    public function __construct(
        private ProgramFieldService $programFieldService,
        private ProgramDocumentRequirementService $programDocumentRequirementService,
    ) {}

    /**
     * @param  array{
     *     batch_name: string,
     *     start_at: mixed,
     *     end_at?: mixed,
     *     fund_ids: list<int>,
     *     public_intake?: bool
     * }  $validated
     */
    public function __invoke(Program $scheme, array $validated): Program
    {
        if (! $scheme->isScheme()) {
            throw ValidationException::withMessages([
                'program' => ['Batches can only be added to a parent program.'],
            ]);
        }

        $batchName = trim($validated['batch_name']);
        $nextNumber = ((int) $scheme->batches()->max('batch_number')) + 1;

        $batch = Program::query()->create([
            'name' => Program::composeBatchDisplayName($scheme->name, $batchName),
            'descriptions' => $scheme->descriptions,
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'] ?? null,
            'department_id' => $scheme->department_id,
            'is_closed' => false,
            'is_organization' => $scheme->is_organization,
            'public_intake' => $scheme->is_organization
                ? false
                : (bool) ($validated['public_intake'] ?? false),
            'kind' => ProgramKind::Batch,
            'parent_id' => $scheme->id,
            'batch_number' => $nextNumber,
            'batch_name' => $batchName,
        ]);

        $batch->fund()->attach($validated['fund_ids']);
        $batch->item()->attach($scheme->item()->pluck('items.id')->all());

        $this->programFieldService->copyToProgram($scheme, $batch);
        $this->programDocumentRequirementService->copyToProgram($scheme, $batch);

        if ($scheme->is_closed) {
            $scheme->update(['is_closed' => false]);
        }

        Cache::forget("dashboard.filter_options.{$scheme->department_id}");

        return $batch;
    }
}
