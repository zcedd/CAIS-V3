<?php

use App\Http\Controllers\User\AssistanceController as UserAssistanceController;
use App\Http\Controllers\User\BeneficiaryController as UserBeneficiaryController;
use App\Http\Controllers\User\ItemController as UserItemController;
use App\Http\Controllers\User\ProgramController as UserProgramController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::get('beneficiaries/search', [UserBeneficiaryController::class, 'search'])->name('user.beneficiaries.search');

    Route::resource('{department}/programs', UserProgramController::class)->only(['index', 'store', 'show', 'update'])->names('user.programs');

    Route::resource('{department}/items', UserItemController::class)->only(['index', 'store', 'update', 'destroy'])->names('user.items');

    Route::post('{department}/programs/{program}/assistances', [UserAssistanceController::class, 'store'])->name('user.programs.assistances.store');
    Route::get('{department}/programs/{program}/assistances/{assistance}/edit', [UserAssistanceController::class, 'edit'])->name('user.programs.assistances.edit');
    Route::put('{department}/programs/{program}/assistances/{assistance}', [UserAssistanceController::class, 'update'])->name('user.programs.assistances.update');
    Route::delete('{department}/programs/{program}/assistances/{assistance}', [UserAssistanceController::class, 'destroy'])->name('user.programs.assistances.destroy');
    Route::patch('{department}/programs/{program}/assistances/{assistance}/status', [UserAssistanceController::class, 'updateStatus'])->name('user.programs.assistances.status.update');
    Route::get('{department}/programs/{program}/assistances/{assistance}', [UserAssistanceController::class, 'show'])->name('user.assistances.show');
});

require __DIR__.'/settings.php';
