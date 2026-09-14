<?php

use App\Http\Controllers\Public\IntakeController;
use App\Http\Controllers\Public\TrackingController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:public-intake')->group(function (): void {
    Route::get('apply', [IntakeController::class, 'index'])->name('public.apply.index');
    Route::get('apply/{program}', [IntakeController::class, 'show'])->name('public.apply.show');
    Route::get('apply/{program}/confirmation', [IntakeController::class, 'confirmation'])->name('public.apply.confirmation');
    Route::post('apply/{program}/duplicates', [IntakeController::class, 'duplicates'])
        ->middleware('throttle:public-intake-write')
        ->name('public.apply.duplicates');
    Route::post('apply/{program}', [IntakeController::class, 'store'])
        ->middleware('throttle:public-intake-write')
        ->name('public.apply.store');

    Route::get('track', [TrackingController::class, 'index'])->name('public.track.index');
    Route::post('track', [TrackingController::class, 'lookup'])
        ->middleware('throttle:public-intake-write')
        ->name('public.track.lookup');
});
