<?php

namespace App\Http\Controllers;

use App\Models\AssistanceRequest;
use App\Models\Fund;
use App\Models\Item;
use App\Models\Program;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ProgramController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $page = request()->input('page', 1);
        $perPage = request()->input('per_page', 12);

        $programs = Program::with(
            [
                'pendingAssistance' => function ($query) {
                    $query->select('id', 'program_id', 'date_requested', 'date_verified', 'date_delivered', 'date_denied');
                },
                'verifiedAssistance' => function ($query) {
                    $query->select('id', 'program_id', 'date_requested', 'date_verified', 'date_delivered', 'date_denied');
                },
                'deliveredAssistance' => function ($query) {
                    $query->select('id', 'program_id', 'date_requested', 'date_verified', 'date_delivered', 'date_denied');
                },
                'deniedAssistance' => function ($query) {
                    $query->select('id', 'program_id', 'date_requested', 'date_verified', 'date_delivered', 'date_denied');
                },
            ]
        )
            ->when($request->search, function ($query) use ($request) {
                $query->where('name', 'LIKE', '%'.$request->search.'%');
            })
            ->where('department_id', Auth::user()->department_id)
            ->get()
            ->take($perPage);

        return Inertia::render('program/list/index', [
            'programs' => $programs,
            'search' => $request->search,
            'perPage' => $request->input('per_page'),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $user = Auth::user();
        $funds = Fund::where('department_id', $user->department_id)->orderBy('name')->get();
        $items = Item::where('department_id', $user->department_id)->orderBy('name')->get();

        return Inertia::render('program/list/create', [
            'Funds' => $funds,
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
            'source_of_fund_ids.*' => 'exists:funds,id',
            'item_ids' => 'required|array',
            'item_ids.*' => 'exists:items,id',
        ], [], [
            'name' => 'program name',
            'descriptions' => 'program description',
            'date_started' => 'date started',
            'date_ended' => 'date ended',
            'is_organization' => 'is organization',
            'source_of_fund_ids' => 'source of funds',
            'item_ids' => 'items',
        ]);

        try {
            $program = Program::create([
                'name' => $validated['name'],
                'descriptions' => $validated['descriptions'],
                'date_started' => $validated['date_started'],
                'date_ended' => $validated['date_ended'] ?? null,
                'department_id' => Auth::user()->department_id,
                'is_organization' => $validated['is_organization'] ?? false,
            ]);

            $program->sourceOfFund()->attach($validated['source_of_fund_ids']);
            $program->item()->attach($validated['item_ids']);

            return back()->with('success', 'Program created successfully.');
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
        $program = Program::with([
            'sourceOfFund' => function ($query) {
                $query->select('id', 'name');
            },
        ])
            ->FindOrFail($id);

        $assistance = AssistanceRequest::with([
            'organization' => function ($query) {
                $query->select('id', 'name');
            },
            'beneficiary.individual' => function ($query) {
                $query->select('id', 'beneficiary_id', 'firstName', 'lastName');
            },
            'requestItem.item' => function ($query) {
                $query->select('id', 'name');
            },
            'modeOfRequest' => function ($query) {
                $query->select('id', 'name');
            },
        ])
            ->select(
                'id',
                'program_id',
                'organization_id',
                'beneficiary_id',
                'date_requested',
                'date_verified',
                'date_delivered',
                'date_denied',
                'mode_of_request_id',
                'remark',
            )
            ->where('program_id', $id);

        $personalPendingAssistance = (clone $assistance)
            ->with(['beneficiary.individual'])
            ->personalAssistance()
            ->pending()
            ->get();

        $organizationalPendingAssistance = (clone $assistance)
            ->with(['organization'])
            ->organizationalAssistance()
            ->pending()
            ->get();

        $personalDeliveredAssistance = (clone $assistance)
            ->with(['beneficiary.individual'])
            ->personalAssistance()
            ->delivered()
            ->get();

        $organizationalDeliveredAssistance = (clone $assistance)
            ->with(['organization'])
            ->organizationalAssistance()
            ->delivered()
            ->get();

        $personalDeniedAssistance = (clone $assistance)
            ->with(['beneficiary.individual'])
            ->personalAssistance()
            ->denied()
            ->get();

        $organizationalDeniedAssistance = (clone $assistance)
            ->with(['organization'])
            ->organizationalAssistance()
            ->denied()
            ->get();

        return Inertia::render('program/profile/index', [
            'program' => $program,
            'personalPendingAssistance' => Inertia::defer(fn () => $personalPendingAssistance),
            'organizationalPendingAssistance' => Inertia::defer(fn () => $organizationalPendingAssistance),
            'personalDeliveredAssistance' => Inertia::defer(fn () => $personalDeliveredAssistance),
            'organizationalDeliveredAssistance' => Inertia::defer(fn () => $organizationalDeliveredAssistance),
            'personalDeniedAssistance' => Inertia::defer(fn () => $personalDeniedAssistance),
            'organizationalDeniedAssistance' => Inertia::defer(fn () => $organizationalDeniedAssistance),
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
