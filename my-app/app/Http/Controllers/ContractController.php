<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RetriesOnReferenceCollision;
use App\Models\Airport;
use App\Models\AircraftSpeedReference;
use App\Models\Contract;
use App\Models\ContractLeg;
use App\Services\FlightCalculator;
use App\Services\SequentialReferenceGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ContractController extends Controller
{
    use RetriesOnReferenceCollision;

    public function __construct(private readonly FlightCalculator $calculator) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $contracts = Contract::query()
            ->with(['client', 'legs.departureAirport', 'legs.arrivalAirport'])
            ->latest('id')
            ->paginate(25);

        return Inertia::render('Contracts/Index', [
            'contracts' => $contracts->through(function (Contract $contract) {
                $firstLeg = $contract->legs->first();
                $lastLeg = $contract->legs->last();

                return [
                    'id' => $contract->id,
                    'reference_number' => $contract->reference_number,
                    'client_name' => $contract->client->company_name ?? 'Untitled client',
                    'status' => $contract->status,
                    'flight_date' => $firstLeg?->flight_date?->format('Y-m-d'),
                    'departure_icao' => $firstLeg?->departureAirport?->icao_code,
                    'arrival_icao' => $lastLeg?->arrivalAirport?->icao_code,
                    'extra_legs' => max($contract->legs->count() - 1, 0),
                ];
            }),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Contracts/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $this->retryOnReferenceCollision(function () use ($data) {
            DB::transaction(function () use ($data) {
                $contract = Contract::create([
                    'client_id' => $data['client_id'],
                    'aircraft_speed_reference_id' => $data['aircraft_speed_reference_id'],
                    'reference_number' => $this->nextReferenceNumber(),
                    'price' => $data['price'],
                    'currency' => $data['currency'],
                    'vat_percentage' => $data['vat_percentage'],
                    'special_information' => $data['special_information'] ?? null,
                    'cancellation_policy' => $data['cancellation_policy'] ?? null,
                ]);

                $this->saveLegs($contract, $data['legs']);
            });
        });

        return Redirect::route('contracts.index')->with('success', 'Contract created.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Contract $contract): Response
    {
        $contract->load(['legs.departureAirport', 'legs.arrivalAirport']);

        return Inertia::render('Contracts/Edit', [
            // Set when QuoteOfferController::generateContract() just
            // redirected here from the Quotes module — see its own doc
            // comment for why this landing page, specifically, is the
            // review step for a best-effort-matched draft.
            'status' => session('status'),
            'contract' => [
                'id' => $contract->id,
                'reference_number' => $contract->reference_number,
                'client_id' => $contract->client_id,
                'aircraft_speed_reference_id' => $contract->aircraft_speed_reference_id,
                'price' => $contract->price,
                'currency' => $contract->currency,
                'vat_percentage' => $contract->vat_percentage,
                'special_information' => $contract->special_information,
                'cancellation_policy' => $contract->cancellation_policy,
                'status' => $contract->status,
                'client' => $contract->client()->select(['id', 'company_name'])->first(),
                'aircraft' => $contract->aircraft()->select(['id', 'type_name'])->first(),
                'legs' => $contract->legs->map(fn (ContractLeg $leg) => [
                    'departure_airport_id' => $leg->departure_airport_id,
                    'arrival_airport_id' => $leg->arrival_airport_id,
                    'flight_date' => $leg->flight_date->format('Y-m-d'),
                    'departure_time' => substr($leg->departure_time, 0, 5),
                    'pax' => $leg->pax,
                    'departure_airport' => $leg->departureAirport,
                    'arrival_airport' => $leg->arrivalAirport,
                ])->all(),
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Contract $contract): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($contract, $data) {
            $contract->update([
                'client_id' => $data['client_id'],
                'aircraft_speed_reference_id' => $data['aircraft_speed_reference_id'],
                'price' => $data['price'],
                'currency' => $data['currency'],
                'vat_percentage' => $data['vat_percentage'],
                'special_information' => $data['special_information'] ?? null,
                'cancellation_policy' => $data['cancellation_policy'] ?? null,
                'status' => $data['status'],
            ]);

            $contract->legs()->delete();
            $this->saveLegs($contract, $data['legs']);
        });

        return Redirect::route('contracts.index')->with('success', 'Contract updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Contract $contract): RedirectResponse
    {
        $contract->delete();

        return Redirect::route('contracts.index')->with('success', 'Contract deleted.');
    }

    /**
     * Stream the charter agreement PDF for the given contract.
     */
    public function pdf(Contract $contract): SymfonyResponse
    {
        $contract->load(['client', 'aircraft', 'legs.departureAirport', 'legs.arrivalAirport']);

        $pdf = Pdf::loadView('contracts.pdf', ['contract' => $contract]);

        // The reference number contains a "/" (e.g. "07-2026/01"), which
        // Content-Disposition filenames can't — swap it for a dash here only.
        $filenameSafeReference = str_replace('/', '-', $contract->reference_number);

        return $pdf->stream("charter-agreement-{$filenameSafeReference}.pdf");
    }

    /**
     * Calculate and persist each leg for a contract, in order, reusing the
     * same FlightCalculator the standalone Flight Calculator page and its
     * live per-leg preview both call.
     */
    private function saveLegs(Contract $contract, array $legs): void
    {
        $aircraft = AircraftSpeedReference::findOrFail($contract->aircraft_speed_reference_id);

        foreach ($legs as $index => $legData) {
            $departureAirport = Airport::findOrFail($legData['departure_airport_id']);
            $arrivalAirport = Airport::findOrFail($legData['arrival_airport_id']);

            $departureLocal = CarbonImmutable::createFromFormat(
                'Y-m-d H:i',
                "{$legData['flight_date']} {$legData['departure_time']}",
                $departureAirport->timezone,
            );

            $result = $this->calculator->calculate(
                $departureAirport,
                $arrivalAirport,
                $aircraft->cruise_speed_knots,
                $departureLocal,
            );

            ContractLeg::create([
                'contract_id' => $contract->id,
                'leg_number' => $index + 1,
                'departure_airport_id' => $departureAirport->id,
                'arrival_airport_id' => $arrivalAirport->id,
                'flight_date' => $legData['flight_date'],
                'departure_time' => $legData['departure_time'],
                'pax' => $legData['pax'],
                'flight_duration_minutes' => $result->durationMinutes,
                'distance_nm' => round($result->distanceNauticalMiles, 1),
                'arrival_datetime' => $result->arrivalLocal->format('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Auto-generate the next contract reference number as "MM-YYYY/NN",
     * where NN restarts from 01 each calendar month.
     *
     * See SequentialReferenceGenerator for why this is a max-suffix
     * lookup rather than a row count, and retryOnReferenceCollision() at
     * this method's call site for how a same-instant collision with
     * another request is handled.
     */
    private function nextReferenceNumber(): string
    {
        return SequentialReferenceGenerator::next(Contract::class, 'reference_number');
    }

    /**
     * Validate the incoming request data for storing/updating a contract
     * and its legs.
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'aircraft_speed_reference_id' => ['required', 'integer', 'exists:aircraft_speed_reference,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', Rule::in(['EUR', 'RON', 'USD'])],
            'vat_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'special_information' => ['nullable', 'string'],
            'cancellation_policy' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', Rule::in(['draft', 'confirmed', 'completed'])],
            'legs' => ['required', 'array', 'min:1'],
            'legs.*.departure_airport_id' => ['required', 'integer', 'exists:airports,id'],
            'legs.*.arrival_airport_id' => ['required', 'integer', 'different:legs.*.departure_airport_id', 'exists:airports,id'],
            'legs.*.flight_date' => ['required', 'date_format:Y-m-d'],
            'legs.*.departure_time' => ['required', 'date_format:H:i'],
            'legs.*.pax' => ['required', 'integer', 'min:1'],
        ]);
    }
}
