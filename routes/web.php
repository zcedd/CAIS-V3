<?php

use App\Http\Controllers\User\AssistanceController as UserAssistanceController;
use App\Http\Controllers\User\BeneficiaryController as UserBeneficiaryController;
use App\Http\Controllers\User\FundController as UserFundController;
use App\Http\Controllers\User\ItemController as UserItemController;
use App\Http\Controllers\User\ProgramController as UserProgramController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::prefix('{department}')->group(function () {
        Route::get('beneficiaries/search', [UserBeneficiaryController::class, 'search'])->name('user.beneficiaries.search');
        Route::get('beneficiaries/create', [UserBeneficiaryController::class, 'create'])->name('user.beneficiaries.create');
        Route::get('beneficiaries/{beneficiary}', [UserBeneficiaryController::class, 'show'])->name('user.beneficiaries.show');
        Route::get('beneficiaries/{beneficiary}/edit', [UserBeneficiaryController::class, 'edit'])->name('user.beneficiaries.edit');
        Route::get('beneficiaries', [UserBeneficiaryController::class, 'index'])->name('user.beneficiaries.index');
        Route::post('beneficiaries/individuals', [UserBeneficiaryController::class, 'storeIndividual'])->name('user.beneficiaries.individuals.store');
        Route::put('beneficiaries/individuals/{beneficiary}', [UserBeneficiaryController::class, 'updateIndividual'])->name('user.beneficiaries.individuals.update');
        Route::post('beneficiaries/organizations', [UserBeneficiaryController::class, 'storeOrganization'])->name('user.beneficiaries.organizations.store');
        Route::put('beneficiaries/organizations/{beneficiary}', [UserBeneficiaryController::class, 'updateOrganization'])->name('user.beneficiaries.organizations.update');

        Route::resource('programs', UserProgramController::class)->only(['index', 'store', 'show', 'update'])->names('user.programs');
      
        Route::resource('items', UserItemController::class)->only(['index', 'store', 'update', 'destroy'])->names('user.items')
          
        Route::resource('funds', UserFundController::class)->only(['index', 'store', 'update', 'destroy'])->names('user.funds');
  
        Route::post('programs/{program}/assistances', [UserAssistanceController::class, 'store'])->name('user.programs.assistances.store');
        Route::get('programs/{program}/assistances/{assistance}/edit', [UserAssistanceController::class, 'edit'])->name('user.programs.assistances.edit');
        Route::put('programs/{program}/assistances/{assistance}', [UserAssistanceController::class, 'update'])->name('user.programs.assistances.update');
        Route::delete('programs/{program}/assistances/{assistance}', [UserAssistanceController::class, 'destroy'])->name('user.programs.assistances.destroy');
        Route::patch('programs/{program}/assistances/{assistance}/status', [UserAssistanceController::class, 'updateStatus'])->name('user.programs.assistances.status.update');
        Route::get('programs/{program}/assistances/{assistance}', [UserAssistanceController::class, 'show'])->name('user.assistances.show');
    });
});

require __DIR__.'/settings.php';
