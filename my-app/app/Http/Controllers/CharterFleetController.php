<?php

namespace App\Http\Controllers;

use App\Jobs\SyncCharterFleetJob;
use App\Models\CharterFleetAircraft;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class CharterFleetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $aircraft = CharterFleetAircraft::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('registration_number', 'like', "%{$search}%")
                        ->orWhere('operator_name', 'like', "%{$search}%");
                });
            })
            ->orderBy('registration_number')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('CharterFleet/Index', [
            'aircraft' => $aircraft,
            'filters' => ['search' => $search],
            'syncStatus' => [
                'running' => Cache::get(SyncCharterFleetJob::CACHE_RUNNING_KEY, false),
                'summary' => Cache::get(SyncCharterFleetJob::CACHE_SUMMARY_KEY),
            ],
        ]);
    }

    /**
     * Kick off an async sync from Aviapages, unless one is already running.
     */
    public function sync(): RedirectResponse
    {
        if (! Cache::get(SyncCharterFleetJob::CACHE_RUNNING_KEY, false)) {
            Cache::put(SyncCharterFleetJob::CACHE_RUNNING_KEY, true, now()->addHour());
            SyncCharterFleetJob::dispatch();
        }

        return Redirect::route('charter-fleet.index');
    }
}
