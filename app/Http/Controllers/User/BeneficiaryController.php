<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\Beneficiary\CreateFormRequest;
use App\Http\Requests\User\Beneficiary\EditRequest;
use App\Http\Requests\User\Beneficiary\IndexRequest;
use App\Http\Requests\User\Beneficiary\ShowRequest;
use App\Http\Requests\User\Beneficiary\StoreIndividualRequest;
use App\Http\Requests\User\Beneficiary\StoreOrganizationRequest;
use App\Http\Requests\User\Beneficiary\UpdateIndividualRequest;
use App\Http\Requests\User\Beneficiary\UpdateOrganizationRequest;
use App\Http\Requests\User\SearchBeneficiariesRequest;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\Individual;
use App\Models\Organization;
use App\Services\User\BeneficiaryService;
use App\Services\User\IndividualBeneficiaryService;
use App\Services\User\OrganizationBeneficiaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BeneficiaryController extends Controller
{
    private const SEARCH_LIMIT = 15;

    public function __construct(
        private BeneficiaryService $beneficiaryService,
        private IndividualBeneficiaryService $individualBeneficiaryService,
        private OrganizationBeneficiaryService $organizationBeneficiaryService,
    ) {}

    public function index(IndexRequest $request, Department $department): Response
    {
        $search = $request->search();
        $types = $request->types();

        return Inertia::render('user/beneficiaries/index', [
            'beneficiaries' => Inertia::scroll(
                $this->beneficiaryService->paginate($search, $types),
            ),
            'department' => $department->only(['id', 'name', 'slug']),
            'search' => $search,
            'type' => $types,
            'form_options' => Inertia::defer(
                fn () => $this->beneficiaryService->formOptions(),
                'forms',
            ),
        ]);
    }

    public function create(CreateFormRequest $request, Department $department): Response
    {
        return Inertia::render('user/beneficiaries/create', [
            'department' => $department->only(['id', 'name', 'slug']),
            'form_options' => $this->beneficiaryService->formOptions(),
        ]);
    }

    public function storeIndividual(
        StoreIndividualRequest $request,
        Department $department,
    ): RedirectResponse {
        $individual = $this->individualBeneficiaryService->create($request->validated());
        $individual->load('beneficiaryRecord');

        return redirect()
            ->route('user.beneficiaries.show', [
                'department' => $department->slug,
                'beneficiary' => $individual->beneficiaryRecord->id,
            ])
            ->with('success', 'Individual beneficiary created successfully.');
    }

    public function storeOrganization(
        StoreOrganizationRequest $request,
        Department $department,
    ): RedirectResponse {
        $organization = $this->organizationBeneficiaryService->create($request->validated());
        $organization->load('beneficiaryRecord');

        return redirect()
            ->route('user.beneficiaries.show', [
                'department' => $department->slug,
                'beneficiary' => $organization->beneficiaryRecord->id,
            ])
            ->with('success', 'Organization beneficiary created successfully.');
    }

    public function show(
        ShowRequest $request,
        Department $department,
        Beneficiary $beneficiary,
    ): Response {
        $search = $request->search();

        return Inertia::render('user/beneficiaries/show', [
            'beneficiary' => fn () => $this->beneficiaryService->showPayload($beneficiary),
            'department' => fn () => $department->only(['id', 'name', 'slug']),
            'assistances' => Inertia::defer(
                fn () => $this->beneficiaryService->paginatedAssistances($beneficiary, $search),
                'table',
            ),
            'search' => $search,
            'form_options' => Inertia::defer(
                fn () => $this->beneficiaryService->formOptions(),
                'edit',
            ),
        ]);
    }

    public function edit(
        EditRequest $request,
        Department $department,
        Beneficiary $beneficiary,
    ): JsonResponse {
        return response()->json([
            'data' => $this->beneficiaryService->editPayload($beneficiary),
        ]);
    }

    public function updateIndividual(
        UpdateIndividualRequest $request,
        Department $department,
        Beneficiary $beneficiary,
    ): RedirectResponse {
        $this->individualBeneficiaryService->update($beneficiary, $request->validated());

        return redirect()
            ->back()
            ->with('success', 'Individual beneficiary updated successfully.');
    }

    public function updateOrganization(
        UpdateOrganizationRequest $request,
        Department $department,
        Beneficiary $beneficiary,
    ): RedirectResponse {
        $this->organizationBeneficiaryService->update($beneficiary, $request->validated());

        return redirect()
            ->back()
            ->with('success', 'Organization beneficiary updated successfully.');
    }

    /**
     * Search beneficiaries by CAIS number or name for autocomplete.
     */
    public function search(
        SearchBeneficiariesRequest $request,
        Department $department,
    ): JsonResponse {
        $search = $request->search();
        $beneficiaryType = $request->beneficiaryType();

        $query = Beneficiary::query()
            ->orderBy('name')
            ->limit(self::SEARCH_LIMIT);

        if ($beneficiaryType === 'individual') {
            $query->where('beneficiable_type', Individual::class);
        }

        if ($beneficiaryType === 'organization') {
            $query->where('beneficiable_type', Organization::class);
        }

        if ($search !== '') {
            $needle = '%'.$search.'%';

            $query->where(function ($builder) use ($needle): void {
                $builder
                    ->where('name', 'like', $needle)
                    ->orWhere('cais_number', 'like', $needle);
            });
        }

        $beneficiaries = $query
            ->get(['id', 'cais_number', 'name'])
            ->map(static fn (Beneficiary $beneficiary): array => [
                'id' => $beneficiary->id,
                'individual_id' => $beneficiary->beneficiable_type === Individual::class
                    ? $beneficiary->beneficiable_id
                    : null,
                'organization_id' => $beneficiary->beneficiable_type === Organization::class
                    ? $beneficiary->beneficiable_id
                    : null,
                'cais_number' => $beneficiary->cais_number,
                'name' => $beneficiary->name,
                'label' => trim("{$beneficiary->cais_number} — {$beneficiary->name}"),
            ])
            ->values()
            ->all();

        return response()->json([
            'data' => $beneficiaries,
        ]);
    }
}
