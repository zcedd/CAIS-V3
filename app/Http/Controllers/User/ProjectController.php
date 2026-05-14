<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Assistance;
use App\Models\Department;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * Display projects belonging to the authenticated user's department.
     */
    public function index(Request $request, Department $department): Response
    {
        $user = $request->user();

        abort_unless($user->department_id === $department->id, 403);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $search = isset($validated['search']) ? trim($validated['search']) : '';

        $projects = Project::query()
            ->select([
                'id',
                'name',
                'descriptions',
                'start_at',
                'end_at',
                'is_closed',
                'is_organization',
                'department_id',
            ])
            ->with(['department:id,name,slug'])
            ->where('department_id', $department->id)
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderByDesc('id')
            ->get();

        return Inertia::render('user/projects/index', [
            'projects' => $projects,
            'department' => $department->only(['id', 'name', 'slug']),
            'search' => $search,
        ]);
    }

    /**
     * Display a single project for the authenticated user's department.
     */
    public function show(Request $request, Department $department, Project $project): Response
    {
        $user = $request->user();

        abort_unless($user->department_id === $department->id, 403);
        abort_unless($project->department_id === $department->id, 404);

        $project->loadMissing(['department:id,name,slug']);

        $assistances = Assistance::query()
            ->where('project_id', $project->id)
            ->select([
                'id',
                'beneficiary_id',
                'organization_id',
                'mode_of_request_id',
                'date_requested',
                'date_verified',
                'date_delivered',
                'date_denied',
                'remark',
            ])
            ->with([
                'beneficiary:id,cais_number',
                'organization:id,name',
                'modeOfRequest:id,name',
            ])
            ->orderByDesc('id')
            ->get()
            ->map(static function (Assistance $assistance): array {
                $formatDate = static function ($value): ?string {
                    if ($value === null) {
                        return null;
                    }

                    return Carbon::parse($value)->toDateString();
                };

                $status = match (true) {
                    $assistance->date_denied !== null => 'Denied',
                    $assistance->date_delivered !== null => 'Delivered',
                    $assistance->date_verified !== null => 'Verified',
                    $assistance->date_requested !== null => 'Pending',
                    default => 'Unrequested',
                };

                return [
                    'id' => $assistance->id,
                    'party' => $assistance->organization?->name
                        ?? ($assistance->beneficiary?->cais_number !== null
                            ? (string) $assistance->beneficiary->cais_number
                            : null)
                        ?? '—',
                    'party_type' => $assistance->organization_id !== null
                        ? 'Organization'
                        : ($assistance->beneficiary_id !== null ? 'Beneficiary' : '—'),
                    'mode_of_request' => $assistance->modeOfRequest?->name ?? '—',
                    'date_requested' => $formatDate($assistance->date_requested),
                    'date_verified' => $formatDate($assistance->date_verified),
                    'date_delivered' => $formatDate($assistance->date_delivered),
                    'date_denied' => $formatDate($assistance->date_denied),
                    'status' => $status,
                    'remark' => $assistance->remark,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('user/projects/show', [
            'project' => [
                ...$project->only([
                    'id',
                    'name',
                    'descriptions',
                    'start_at',
                    'end_at',
                    'is_closed',
                    'is_organization',
                    'department_id',
                ]),
                'department' => $project->department?->only(['id', 'name', 'slug']),
            ],
            'department' => $department->only(['id', 'name', 'slug']),
            'assistances' => $assistances,
        ]);
    }
}
