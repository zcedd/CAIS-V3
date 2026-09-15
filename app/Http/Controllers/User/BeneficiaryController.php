<?php

namespace App\Http\Controllers\User;

use App\Actions\User\FindPossibleDuplicateBeneficiaries;
use App\Enums\EverifyVerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Beneficiary\CreateFormRequest;
use App\Http\Requests\User\Beneficiary\EditRequest;
use App\Http\Requests\User\Beneficiary\FindDuplicatesRequest;
use App\Http\Requests\User\Beneficiary\IndexRequest;
use App\Http\Requests\User\Beneficiary\ShowRequest;
use App\Http\Requests\User\Beneficiary\StoreIndividualRequest;
use App\Http\Requests\User\Beneficiary\StoreOrganizationRequest;
use App\Http\Requests\User\Beneficiary\UpdateIndividualRequest;
use App\Http\Requests\User\Beneficiary\UpdateOrganizationRequest;
use App\Http\Requests\User\Beneficiary\VerifyEverifyRequest;
use App\Http\Requests\User\SearchBeneficiariesRequest;
use App\Models\Beneficiary;
use App\Models\Department;
use App\Models\Individual;
use App\Models\Organization;
use App\Models\User;
use App\Services\Everify\EverifyPersonQuery;
use App\Services\Everify\EverifyVerificationService;
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
        private EverifyVerificationService $everifyVerificationService,
    ) {}

    public function index(IndexRequest $request, Department $department): Response
    {
        $search = $request->search();
        $types = $request->types();
        $perPage = $request->perPage();

        return Inertia::render('user/beneficiaries/index', [
            'beneficiaries' => $this->beneficiaryService->paginate($search, $types, $perPage),
            'department' => $department->only(['id', 'name', 'slug']),
            'search' => $search,
            'type' => $types,
            'per_page' => $perPage,
            'stats' => Inertia::defer(
                fn () => $this->beneficiaryService->registryStats(),
                'stats',
            ),
            'form_options' => Inertia::defer(
                fn () => $this->beneficiaryService->formOptions(),
                'forms',
            ),
        ]);
    }

    public function create(CreateFormRequest $request, Department $department): Response
    {
        $everify = $this->everifyVerificationService->frontendConfig();

        return Inertia::render('user/beneficiaries/create', [
            'department' => $department->only(['id', 'name', 'slug']),
            'form_options' => $this->beneficiaryService->formOptions(),
            'everify_enabled' => $everify['enabled'],
            'everify_public_key' => $everify['public_key'],
            'everify_liveness_sdk_url' => $everify['liveness_sdk_url'],
            'everify_biometrics' => $everify['biometrics'],
            'everify_fingerprint' => $everify['fingerprint'],
        ]);
    }

    public function verifyIndividual(
        VerifyEverifyRequest $request,
        Department $department,
    ): JsonResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $result = $this->everifyVerificationService->verify(
            $user,
            EverifyPersonQuery::fromValidated($request->validated()),
        );

        return response()->json([
            'verified' => true,
            'result_grade' => $result->resultGrade,
            'verification_token' => $this->everifyVerificationService->issueTicket($user, $result),
        ]);
    }

    public function storeIndividual(
        StoreIndividualRequest $request,
        Department $department,
    ): RedirectResponse {
        $individual = $this->individualBeneficiaryService->create(
            $request->validated(),
            $request->user(),
        );
        $individual->load('beneficiaryRecord');

        $message = $individual->everify_status === EverifyVerificationStatus::Verified
            ? 'Individual beneficiary created and verified with PhilSys.'
            : 'Individual beneficiary created successfully.';

        return redirect()
            ->route('user.beneficiaries.show', [
                'department' => $department->slug,
                'beneficiary' => $individual->beneficiaryRecord->id,
            ])
            ->with('success', $message);
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
            'assistance_summary' => Inertia::defer(
                fn () => $this->beneficiaryService->assistanceSummary($beneficiary),
                'kpis',
            ),
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
            ->get(['id', 'cais_number', 'name', 'beneficiable_type', 'beneficiable_id'])
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

    public function duplicates(
        FindDuplicatesRequest $request,
        Department $department,
        FindPossibleDuplicateBeneficiaries $findPossibleDuplicateBeneficiaries,
    ): JsonResponse {
        return response()->json([
            'data' => $findPossibleDuplicateBeneficiaries($request->duplicateSearchInput()),
        ]);
    }
}
