<?php

namespace App\Http\Controllers;

use App\Models\AircraftSpeedReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AircraftSpeedReferenceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $aircraftSpeedReferences = AircraftSpeedReference::query()
            ->select(['id', 'type_name'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where('type_name', 'like', "%{$search}%");
            })
            ->orderBy('type_name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('AircraftSpeedReferences/Index', [
            'aircraftSpeedReferences' => $aircraftSpeedReferences,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Return aircraft types matching a search query, for the searchable
     * select used by the Flight Calculator (and, later, Contracts).
     */
    public function search(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $aircraftSpeedReferences = AircraftSpeedReference::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where('type_name', 'like', "%{$search}%");
            })
            ->orderBy('type_name')
            ->get(['id', 'type_name']);

        return response()->json($aircraftSpeedReferences);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('AircraftSpeedReferences/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        AircraftSpeedReference::create($data);

        return Redirect::route('aircraft-speed-references.index');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AircraftSpeedReference $aircraftSpeedReference): Response
    {
        return Inertia::render('AircraftSpeedReferences/Edit', [
            'aircraftSpeedReference' => $aircraftSpeedReference,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AircraftSpeedReference $aircraftSpeedReference): RedirectResponse
    {
        $data = $this->validated($request, $aircraftSpeedReference);

        $aircraftSpeedReference->update($data);

        return Redirect::route('aircraft-speed-references.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AircraftSpeedReference $aircraftSpeedReference): RedirectResponse
    {
        $aircraftSpeedReference->delete();

        return Redirect::route('aircraft-speed-references.index');
    }

    /**
     * Validate the incoming request data for storing/updating an aircraft
     * speed reference.
     */
    private function validated(Request $request, ?AircraftSpeedReference $aircraftSpeedReference = null): array
    {
        return $request->validate([
            'type_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('aircraft_speed_reference', 'type_name')->ignore($aircraftSpeedReference?->id),
            ],
            'cruise_speed_knots' => ['required', 'integer', 'min:1', 'max:2000'],
        ]);
    }
}
