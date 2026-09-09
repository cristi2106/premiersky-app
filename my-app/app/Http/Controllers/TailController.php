<?php

namespace App\Http\Controllers;

use App\Models\Tail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TailController extends Controller
{
    /**
     * Cabin/reference columns pulled from the linked aircraft type. Cruise
     * speed is intentionally excluded — it isn't relevant to this module.
     */
    private const TYPE_REFERENCE_COLUMNS = [
        'id',
        'type_name',
        'cabin_width_m',
        'cabin_height_m',
        'cabin_length_m',
        'cabin_volume_m3',
        'baggage_capacity_m3',
        'seating_capacity',
    ];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $tails = Tail::query()
            ->with(['aircraftSpeedReference:'.implode(',', self::TYPE_REFERENCE_COLUMNS)])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('tail', 'like', "%{$search}%")
                        ->orWhere('operator', 'like', "%{$search}%");
                });
            })
            ->orderBy('tail')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Tails/Index', [
            'tails' => $tails,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Same search (tail number or operator, 20-result cap) as index()'s
     * own listing filter — kept separate rather than reused because this
     * one returns plain JSON for SearchableSelect (see the Quotes module's
     * "Add Offer" form) instead of an Inertia page. Mirrors
     * ClientController::search()/AirportController::search().
     */
    public function search(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $tails = Tail::query()
            ->with('aircraftSpeedReference:id,type_name')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('tail', 'like', "%{$search}%")
                        ->orWhere('operator', 'like', "%{$search}%");
                });
            })
            ->orderBy('tail')
            ->limit(20)
            ->get(['id', 'tail', 'operator', 'year_of_make', 'max_pax', 'aircraft_speed_reference_id']);

        return response()->json($tails);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Tails/Create', [
            'categories' => Tail::CATEGORIES,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data = $this->applyPhotos($request, $data, null);

        Tail::create($data);

        return Redirect::route('tails.index')->with('success', 'Tail created.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Tail $tail): Response
    {
        $tail->load(['aircraftSpeedReference:'.implode(',', self::TYPE_REFERENCE_COLUMNS)]);

        return Inertia::render('Tails/Edit', [
            'tail' => $tail,
            'categories' => Tail::CATEGORIES,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Tail $tail): RedirectResponse
    {
        $data = $this->validated($request);
        $data = $this->applyPhotos($request, $data, $tail);

        $tail->update($data);

        return Redirect::route('tails.index')->with('success', 'Tail updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tail $tail): RedirectResponse
    {
        foreach ([$tail->photo_1, $tail->photo_2] as $photo) {
            if ($photo) {
                Storage::disk('public')->delete($photo);
            }
        }

        $tail->delete();

        return Redirect::route('tails.index')->with('success', 'Tail deleted.');
    }

    /**
     * Validate the incoming request data for storing/updating a tail.
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'tail' => ['required', 'string', 'max:50'],
            'operator' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(Tail::CATEGORIES)],
            'aircraft_speed_reference_id' => ['required', 'exists:aircraft_speed_reference,id'],
            'year_of_make' => ['required', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'year_of_refurbishment' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'max_pax' => ['required', 'integer', 'min:1', 'max:1000'],
            'lavatory' => ['boolean'],
            'wifi' => ['boolean'],
            'bed' => ['boolean'],
            'entertainment_system' => ['boolean'],
            'pets_allowed' => ['boolean'],
            'smoking_allowed' => ['boolean'],
            'photo_1' => ['nullable', 'image', 'max:5120'],
            'photo_2' => ['nullable', 'image', 'max:5120'],
            'remove_photo_1' => ['nullable', 'boolean'],
            'remove_photo_2' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * Resolves the two photo slots against the request: a new upload
     * replaces (and deletes) any existing file, an explicit removal flag
     * clears it, and otherwise the existing value is left untouched.
     */
    private function applyPhotos(Request $request, array $data, ?Tail $tail): array
    {
        foreach ([1, 2] as $slot) {
            $field = "photo_{$slot}";

            if ($request->hasFile($field)) {
                if ($tail?->{$field}) {
                    Storage::disk('public')->delete($tail->{$field});
                }

                $data[$field] = $request->file($field)->store('tails', 'public');
            } elseif ($request->boolean("remove_{$field}")) {
                if ($tail?->{$field}) {
                    Storage::disk('public')->delete($tail->{$field});
                }

                $data[$field] = null;
            } else {
                unset($data[$field]);
            }

            unset($data["remove_{$field}"]);
        }

        return $data;
    }
}
