<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Workflow\IndexRequest;
use App\Models\Department;
use App\Services\User\WorkflowService;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    public function __construct(
        private WorkflowService $workflowService,
    ) {}

    public function index(IndexRequest $request): Response
    {
        $departments = Department::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $slug = $request->departmentSlug();
        $department = $slug === ''
            ? null
            : $departments->firstWhere('slug', $slug);

        $payload = [
            'departments' => $departments,
            'department' => $department?->only(['id', 'name', 'slug']),
            'workflows' => [],
            'statuses' => [],
            'reasons' => [],
            'staff_options' => [],
            'can_create' => false,
        ];

        if ($department !== null) {
            $payload['workflows'] = $this->workflowService->listForDepartment($department);
            $payload['statuses'] = $this->workflowService->activeStatuses();
            $payload['reasons'] = $this->workflowService->activeReasons();
            $payload['staff_options'] = $this->workflowService->departmentStaffForSelect($department);
            $payload['can_create'] = true;
        }

        return Inertia::render('admin/workflows/index', $payload);
    }
}
