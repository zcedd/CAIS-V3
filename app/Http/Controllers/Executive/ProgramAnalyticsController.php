<?php

namespace App\Http\Controllers\Executive;

use App\Http\Controllers\Controller;
use App\Http\Requests\Executive\Program\AnalyticsRequest;
use App\Models\Program;
use App\Services\User\ProgramAnalyticsService;
use Illuminate\Http\JsonResponse;

class ProgramAnalyticsController extends Controller
{
    public function __construct(private ProgramAnalyticsService $analytics) {}

    public function show(AnalyticsRequest $request, Program $program): JsonResponse
    {
        return response()->json($this->analytics->payload($program, [
            'search' => $request->search(),
            'sex' => $request->sex(),
            'classification' => $request->classification(),
            'page' => $request->page(),
        ]));
    }
}
