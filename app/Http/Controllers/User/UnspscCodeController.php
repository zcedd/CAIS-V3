<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UnspscCode\SearchRequest;
use App\Models\Department;
use App\Services\User\UnspscCodeService;
use Illuminate\Http\JsonResponse;

class UnspscCodeController extends Controller
{
    public function __construct(
        private UnspscCodeService $unspscCodeService,
    ) {}

    public function search(SearchRequest $request, Department $department): JsonResponse
    {
        return response()->json([
            'data' => $this->unspscCodeService->search(
                $request->search(),
                $request->curatedOnly(),
            ),
        ]);
    }
}
