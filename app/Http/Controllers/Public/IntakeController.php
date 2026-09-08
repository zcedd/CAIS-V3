<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\Intake\DuplicatesRequest;
use App\Http\Requests\Public\Intake\StoreRequest;
use App\Models\Department;
use App\Models\Program;
use App\Services\Public\PublicIntakeService;
use App\Services\User\BeneficiaryService;
use App\Services\User\ProgramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class IntakeController extends Controller
{
    public function __construct(
        private PublicIntakeService $publicIntakeService,
        private ProgramService $programService,
        private BeneficiaryService $beneficiaryService,
    ) {}

    public function index(): Response
    {
        $programs = Program::query()
            ->acceptingPublicIntake()
            ->with('department:id,name,slug')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'descriptions',
                'start_at',
                'end_at',
                'department_id',
                'kind',
                'batch_name',
            ]);

        $grouped = $programs
            ->groupBy(fn (Program $program): int => (int) $program->department_id)
            ->map(function ($departmentPrograms): array {
                /** @var Program $first */
                $first = $departmentPrograms->first();
                $department = $first->department;

                return [
                    'department_name' => $department instanceof Department
                        ? $department->name
                        : 'Department',
                    'programs' => $departmentPrograms
                        ->map(static fn (Program $program): array => [
                            'id' => $program->id,
                            'name' => $program->name,
                            'descriptions' => $program->descriptions,
                            'start_at' => $program->start_at,
                            'end_at' => $program->end_at,
                            'batch_name' => $program->batch_name,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('public/apply/index', [
            'departments' => $grouped,
        ]);
    }

    public function show(Request $request, Program $program): Response
    {
        abort_unless($program->acceptsPublicIntake(), 404);

        $program->loadMissing('department:id,name');

        $resume = null;
        $caisNumber = $request->string('cais_number')->toString();
        $lastName = $request->string('last_name')->toString();

        if ($caisNumber !== '' && $lastName !== '') {
            try {
                $resume = $this->publicIntakeService->resumePayload($program, $caisNumber, $lastName);
            } catch (ValidationException) {
                $resume = null;
            }
        }

        return Inertia::render('public/apply/show', [
            'program' => [
                'id' => $program->id,
                'name' => $program->name,
                'descriptions' => $program->descriptions,
                'department_name' => $program->department?->name,
                'start_at' => $program->start_at,
                'end_at' => $program->end_at,
            ],
            'items' => $this->programService->programItemsForSelect($program),
            'fields' => $this->programService->programFieldsForForms($program),
            'form_options' => $this->beneficiaryService->formOptions(),
            'resume' => $resume,
            'kiosk' => $request->boolean('kiosk'),
        ]);
    }

    public function duplicates(DuplicatesRequest $request, Program $program): JsonResponse
    {
        abort_unless($program->acceptsPublicIntake(), 404);

        return response()->json([
            'matches' => $this->publicIntakeService->highScoreMatches($request->validated()),
        ]);
    }

    public function store(StoreRequest $request, Program $program): RedirectResponse
    {
        abort_unless($program->acceptsPublicIntake(), 404);
        $result = $this->publicIntakeService->submit($program, $request->validated());

        return redirect()
            ->route('public.apply.confirmation', $program)
            ->with('public_intake_result', [
                'cais_number' => $result['cais_number'],
                'intent' => $result['intent'],
                'program_name' => $program->name,
            ]);
    }

    public function confirmation(Request $request, Program $program): Response
    {
        $result = $request->session()->get('public_intake_result');

        if (! is_array($result)) {
            return redirect()->route('public.apply.show', $program);
        }

        return Inertia::render('public/apply/confirmation', [
            'cais_number' => $result['cais_number'],
            'intent' => $result['intent'],
            'program_name' => $result['program_name'] ?? $program->name,
            'kiosk' => $request->boolean('kiosk'),
        ]);
    }
}
