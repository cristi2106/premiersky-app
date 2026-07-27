<?php

use App\Http\Controllers\AircraftSpeedReferenceController;
use App\Http\Controllers\AirportController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\FlightCalculatorController;
use App\Http\Controllers\ProfileController;
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

    Route::resource('clients', ClientController::class)->except('show');

    Route::get('airports/search', [AirportController::class, 'search'])->name('airports.search');
    Route::resource('airports', AirportController::class)->except('show');

    Route::get('aircraft-speed-references/search', [AircraftSpeedReferenceController::class, 'search'])->name('aircraft-speed-references.search');
    Route::resource('aircraft-speed-references', AircraftSpeedReferenceController::class)->except('show');

    Route::get('flight-calculator', [FlightCalculatorController::class, 'index'])->name('flight-calculator.index');
    Route::post('flight-calculator/calculate', [FlightCalculatorController::class, 'calculate'])->name('flight-calculator.calculate');
});

require __DIR__.'/auth.php';
