<?php

use App\Http\Controllers\User\AssistanceController as UserAssistanceController;
use App\Http\Controllers\User\ProgramController as UserProgramController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::get('{department}/programs', [UserProgramController::class, 'index'])->name('user.programs.index');
    Route::post('{department}/programs', [UserProgramController::class, 'store'])->name('user.programs.store');
    Route::get('{department}/programs/{program}', [UserProgramController::class, 'show'])->name('user.programs.show');
    Route::get('{department}/programs/{program}/assistances/{assistance}', [UserAssistanceController::class, 'show'])->name('user.assistances.show');
});

require __DIR__.'/settings.php';
