<?php

use App\Http\Controllers\User\AssistanceController as UserAssistanceController;
use App\Http\Controllers\User\BeneficiaryController as UserBeneficiaryController;
use App\Http\Controllers\User\ProgramController as UserProgramController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::get('beneficiaries/search', [UserBeneficiaryController::class, 'search'])->name('user.beneficiaries.search');
    Route::get('{department}/programs', [UserProgramController::class, 'index'])->name('user.programs.index');
    Route::post('{department}/programs', [UserProgramController::class, 'store'])->name('user.programs.store');
    Route::get('{department}/programs/{program}', [UserProgramController::class, 'show'])->name('user.programs.show');
    Route::post('{department}/programs/{program}/assistances', [UserProgramController::class, 'storeAssistance'])->name('user.programs.assistances.store');
    Route::get('{department}/programs/{program}/assistances/{assistance}/edit', [UserProgramController::class, 'editAssistance'])->name('user.programs.assistances.edit');
    Route::put('{department}/programs/{program}/assistances/{assistance}', [UserProgramController::class, 'updateAssistance'])->name('user.programs.assistances.update');
    Route::patch('{department}/programs/{program}/assistances/{assistance}/status', [UserProgramController::class, 'updateAssistanceStatus'])->name('user.programs.assistances.status.update');
    Route::put('{department}/programs/{program}', [UserProgramController::class, 'update'])->name('user.programs.update');
    Route::get('{department}/programs/{program}/assistances/{assistance}', [UserAssistanceController::class, 'show'])->name('user.assistances.show');
});

require __DIR__.'/settings.php';
