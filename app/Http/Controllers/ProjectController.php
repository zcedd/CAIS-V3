<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Inertia\Inertia;
use App\Models\Project;
use App\Models\SourceOfFund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;

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
        $user = Auth::user();
        $sourceOfFunds = SourceOfFund::where('department_id', $user->department_id)->orderBy('name')->get();
        $items = Item::where('department_id', $user->department_id)->orderBy('name')->get();
        return Inertia::render('project/list/create', [
            'SourceOfFunds' => $sourceOfFunds,
            'Items' => $items,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'descriptions' => 'required|string|max:255',
            'date_started' => 'required|date',
            'date_ended' => 'nullable|date',
            'is_organization' => 'nullable|boolean',
        ]);

        try {
            Project::create([
                'name' => $validated['name'],
                'descriptions' => $validated['descriptions'],
                'dateStarted' => $validated['date_started'],
                'dateEnded' => $validated['date_ended'],
                'department_id' => Auth::user()->department_id,
                'is_organization' => $validated['is_organization'] ?? false,
            ]);

            return back()->with('success', 'Report created successfully.');
        } catch (QueryException $th) {
            Log::error($th->getMessage());
            return back()->withErrors(['error' => 'Something went wrong.']);
        }
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
