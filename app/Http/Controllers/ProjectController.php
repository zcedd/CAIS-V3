<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $page = request()->input('page', 1);
        $perPage = request()->input('per_page', 12);

        $projects = Project::with('assistance', 'pendingAssistance', 'verifiedAssistance', 'deliveredAssistance', 'deniedAssistance')
            ->when($request->search, function ($query) use ($request) {
                $query->where('name', 'LIKE', '%' . $request->search . "%");
            })
            ->where('department_id', Auth::user()->department_id)
            ->get()
            ->take($perPage);

        return Inertia::render('project/list/index', [
            // 'Projects' => Inertia::defer(fn() => $projects->paginate($perPage, page: $page))->deepMerge(),
            'Projects' => $projects,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('project/list/create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $project = Project::findOrFail($id);
        return Inertia::render('project/profile/index', [
            'Project' => $project
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
