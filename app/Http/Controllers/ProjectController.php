<?php

namespace App\Http\Controllers;

use App\Models\Assistance;
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

        $projects = Project::with('pendingAssistance', 'verifiedAssistance', 'deliveredAssistance', 'deniedAssistance')
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
            'source_of_fund_ids' => 'required|array',
            'source_of_fund_ids.*' => 'exists:source_of_funds,id',
            'item_ids' => 'required|array',
            'item_ids.*' => 'exists:items,id',
        ], [], [
            'name' => 'project name',
            'descriptions' => 'project description',
            'date_started' => 'date started',
            'date_ended' => 'date ended',
            'is_organization' => 'is organization',
            'source_of_fund_ids' => 'source of funds',
            'item_ids' => 'items',
        ]);

        try {
            $project = Project::create([
                'name' => $validated['name'],
                'descriptions' => $validated['descriptions'],
                'dateStarted' => date('Y-m-d', strtotime($validated['date_started'])),
                'dateEnded' => isset($validated['date_ended']) && $validated['date_ended'] ? date('Y-m-d', strtotime($validated['date_ended'])) : null,
                'department_id' => Auth::user()->department_id,
                'is_organization' => $validated['is_organization'] ?? false,
            ]);

            $project->sourceOfFund()->attach($validated['source_of_fund_ids']);
            $project->item()->attach($validated['item_ids']);

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
        $project = Project::with('sourceOfFund', 'item')->FindOrFail($id);
        $pendingAssistance = Assistance::where('project_id', $id)
            ->pending()
            ->wherePersonalAssistance()
            ->get();

        // dd($pendingAssistance);
        return Inertia::render('project/profile/index', [
            'Project' => $project,
            'PendingAssistance' => $pendingAssistance,
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
