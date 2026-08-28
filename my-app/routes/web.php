<?php

use App\Http\Controllers\AircraftSpeedReferenceController;
use App\Http\Controllers\AirportController;
use App\Http\Controllers\CharterFleetController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\FlightCalculatorController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\QuoteOfferController;
use App\Http\Controllers\QuoteRequestController;
use App\Http\Controllers\TailController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('clients/search', [ClientController::class, 'search'])->name('clients.search');
    Route::resource('clients', ClientController::class)->except('show');

    Route::get('airports/search', [AirportController::class, 'search'])->name('airports.search');
    Route::resource('airports', AirportController::class)->except('show');

    Route::get('aircraft-speed-references/search', [AircraftSpeedReferenceController::class, 'search'])->name('aircraft-speed-references.search');
    Route::resource('aircraft-speed-references', AircraftSpeedReferenceController::class)->except('show');

    Route::get('flight-calculator', [FlightCalculatorController::class, 'index'])->name('flight-calculator.index');
    Route::post('flight-calculator/calculate', [FlightCalculatorController::class, 'calculate'])->name('flight-calculator.calculate');

    Route::get('contracts/{contract}/pdf', [ContractController::class, 'pdf'])->name('contracts.pdf');
    Route::resource('contracts', ContractController::class)->except('show');

    Route::get('charter-fleet', [CharterFleetController::class, 'index'])->name('charter-fleet.index');
    Route::post('charter-fleet/sync', [CharterFleetController::class, 'sync'])->name('charter-fleet.sync');

    Route::resource('tails', TailController::class)->except('show');

    Route::get('quotes', [QuoteController::class, 'index'])->name('quotes.index');
    Route::patch('quote-offers/{quoteOffer}', [QuoteOfferController::class, 'update'])->name('quote-offers.update');
    Route::post('quote-offers/{quoteOffer}/generate-contract', [QuoteOfferController::class, 'generateContract'])->name('quote-offers.generate-contract');
    Route::delete('quotes/history', [QuoteRequestController::class, 'clearHistory'])->name('quote-requests.clear-history');
    Route::patch('quote-requests/{quoteRequest}', [QuoteRequestController::class, 'update'])->name('quote-requests.update');
    Route::delete('quote-requests/{quoteRequest}', [QuoteRequestController::class, 'destroy'])->name('quote-requests.destroy');
    Route::get('quote-requests/{quoteRequest}/pdf', [QuoteRequestController::class, 'pdf'])->name('quote-requests.pdf');
});

require __DIR__.'/auth.php';
