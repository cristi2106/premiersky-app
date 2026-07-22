<?php

namespace App\Http\Controllers;

use App\Models\Airport;
use App\Services\CountryLookup;
use App\Services\TimezoneResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AirportController extends Controller
{
    public function __construct(
        private readonly TimezoneResolver $timezoneResolver,
        private readonly CountryLookup $countryLookup,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $airports = Airport::query()
            ->select(['id', 'name', 'icao_code', 'iata_code', 'country'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('icao_code', 'like', "%{$search}%")
                        ->orWhere('iata_code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Airports/Index', [
            'airports' => $airports,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Airports/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['timezone'] = $this->resolveTimezone($data);

        Airport::create($data);

        return Redirect::route('airports.index');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Airport $airport): Response
    {
        return Inertia::render('Airports/Edit', [
            'airport' => $airport,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Airport $airport): RedirectResponse
    {
        $data = $this->validated($request, $airport);
        $data['timezone'] = $this->resolveTimezone($data);

        $airport->update($data);

        return Redirect::route('airports.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Airport $airport): RedirectResponse
    {
        $airport->delete();

        return Redirect::route('airports.index');
    }

    /**
     * Validate the incoming request data for storing/updating an airport.
     */
    private function validated(Request $request, ?Airport $airport = null): array
    {
        $request->merge([
            'icao_code' => strtoupper(trim((string) $request->input('icao_code'))),
            'iata_code' => $request->filled('iata_code')
                ? strtoupper(trim((string) $request->input('iata_code')))
                : null,
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'icao_code' => [
                'required',
                'regex:/^[A-Z]{4}$/',
                Rule::unique('airports', 'icao_code')->ignore($airport?->id),
            ],
            'iata_code' => ['nullable', 'regex:/^[A-Z]{3}$/'],
            'country' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
    }

    /**
     * Derive the IANA timezone for a lat/long, using the country name (if
     * recognized) to disambiguate — the same underlying resolver the
     * airports:import command uses.
     */
    private function resolveTimezone(array $data): string
    {
        $countryCode = $this->countryLookup->codeForName($data['country']);

        return $this->timezoneResolver->resolve(
            (float) $data['latitude'],
            (float) $data['longitude'],
            $countryCode
        ) ?? 'UTC';
    }
}
