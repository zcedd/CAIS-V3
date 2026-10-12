<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\Program\AnalyticsRequest;
use App\Models\Department;
use App\Models\Program;
use App\Services\User\ProgramAnalyticsService;
use Illuminate\Http\JsonResponse;

class ProgramAnalyticsController extends Controller
{
    public function __construct(private ProgramAnalyticsService $analytics) {}

    public function show(
        AnalyticsRequest $request,
        Department $department,
        Program $program,
    ): JsonResponse {
        return response()->json($this->analytics->payload($program, [
            'search' => $request->search(),
            'sex' => $request->sex(),
            'classification' => $request->classification(),
            'page' => $request->page(),
        ]));
    }
}
