<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\GlobalSearchRequest;
use App\Models\Department;
use App\Models\User;
use App\Services\User\GlobalSearchService;
use Illuminate\Http\JsonResponse;

class GlobalSearchController extends Controller
{
    public function __construct(private GlobalSearchService $globalSearchService) {}

    /**
     * Search programs, beneficiaries, requests, items, and funds
     * the current user is allowed to view in this department.
     */
    public function index(GlobalSearchRequest $request, Department $department): JsonResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return response()->json([
            'data' => $this->globalSearchService->search(
                $user,
                $department,
                $request->queryText(),
                $request->scope(),
            ),
        ]);
    }
}
