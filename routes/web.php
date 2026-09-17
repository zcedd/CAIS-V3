<?php

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\WorkflowController as AdminWorkflowController;
use App\Http\Controllers\GlobalDashboardController;
use App\Http\Controllers\User\AssistanceController as UserAssistanceController;
use App\Http\Controllers\User\AssistanceDocumentController as UserAssistanceDocumentController;
use App\Http\Controllers\User\AssistanceQueueController as UserAssistanceQueueController;
use App\Http\Controllers\User\AssistanceReceiptController as UserAssistanceReceiptController;
use App\Http\Controllers\User\BeneficiaryController as UserBeneficiaryController;
use App\Http\Controllers\User\DashboardController as UserDashboardController;
use App\Http\Controllers\User\FundController as UserFundController;
use App\Http\Controllers\User\ItemController as UserItemController;
use App\Http\Controllers\User\ItemStockController as UserItemStockController;
use App\Http\Controllers\User\NotificationController as UserNotificationController;
use App\Http\Controllers\User\ProgramBatchController as UserProgramBatchController;
use App\Http\Controllers\User\ProgramController as UserProgramController;
use App\Http\Controllers\User\UnspscCodeController as UserUnspscCodeController;
use App\Http\Controllers\User\WorkflowController as UserWorkflowController;
use App\Http\Controllers\User\WorkflowTaskController as UserWorkflowTaskController;
use App\Http\Middleware\EnsureUserBelongsToDepartment;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

require __DIR__.'/public.php';

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', GlobalDashboardController::class)->name('dashboard');

    Route::middleware('super-admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', fn () => redirect()->route('admin.users.index'))->name('index');

        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('users', [AdminUserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

        Route::get('workflows', [AdminWorkflowController::class, 'index'])->name('workflows.index');
        Route::get('workflows/create', [AdminWorkflowController::class, 'create'])->name('workflows.create');
        Route::post('workflows', [AdminWorkflowController::class, 'store'])->name('workflows.store');
        Route::get('workflows/{workflow}', [AdminWorkflowController::class, 'show'])->name('workflows.show');
        Route::put('workflows/{workflow}', [AdminWorkflowController::class, 'update'])->name('workflows.update');
        Route::delete('workflows/{workflow}', [AdminWorkflowController::class, 'destroy'])->name('workflows.destroy');
        Route::post('workflows/{workflow}/publish', [AdminWorkflowController::class, 'publish'])->name('workflows.publish');
        Route::post('workflows/{workflow}/activate', [AdminWorkflowController::class, 'activate'])->name('workflows.activate');
        Route::post('workflows/{workflow}/deactivate', [AdminWorkflowController::class, 'deactivate'])->name('workflows.deactivate');
        Route::post('workflows/{workflow}/duplicate', [AdminWorkflowController::class, 'duplicate'])->name('workflows.duplicate');
        Route::post('workflows/{workflow}/version', [AdminWorkflowController::class, 'version'])->name('workflows.version');
        Route::put('workflows/{workflow}/programs', [AdminWorkflowController::class, 'assignPrograms'])->name('workflows.programs');
        Route::post('workflows/{workflow}/assistances/{assistance}/reassign', [AdminWorkflowController::class, 'reassignTask'])->name('workflows.tasks.reassign');
        Route::post('workflows/{workflow}/assistances/{assistance}/override', [AdminWorkflowController::class, 'overrideTask'])->name('workflows.tasks.override');
    });

    Route::prefix('{department}')->middleware(EnsureUserBelongsToDepartment::class)->group(function () {
        Route::get('dashboard', [UserDashboardController::class, 'index'])->name('user.dashboard.index');

        Route::get('beneficiaries/search', [UserBeneficiaryController::class, 'search'])->name('user.beneficiaries.search');
        Route::get('beneficiaries/duplicates', [UserBeneficiaryController::class, 'duplicates'])->name('user.beneficiaries.duplicates');
        Route::get('beneficiaries/create', [UserBeneficiaryController::class, 'create'])->name('user.beneficiaries.create');
        Route::get('beneficiaries/{beneficiary}', [UserBeneficiaryController::class, 'show'])->name('user.beneficiaries.show');
        Route::get('beneficiaries/{beneficiary}/edit', [UserBeneficiaryController::class, 'edit'])->name('user.beneficiaries.edit');
        Route::get('beneficiaries', [UserBeneficiaryController::class, 'index'])->name('user.beneficiaries.index');
        Route::post('beneficiaries/individuals/everify', [UserBeneficiaryController::class, 'verifyIndividual'])->name('user.beneficiaries.individuals.everify');
        Route::post('beneficiaries/individuals', [UserBeneficiaryController::class, 'storeIndividual'])->name('user.beneficiaries.individuals.store');
        Route::put('beneficiaries/individuals/{beneficiary}', [UserBeneficiaryController::class, 'updateIndividual'])->name('user.beneficiaries.individuals.update');
        Route::post('beneficiaries/organizations', [UserBeneficiaryController::class, 'storeOrganization'])->name('user.beneficiaries.organizations.store');
        Route::put('beneficiaries/organizations/{beneficiary}', [UserBeneficiaryController::class, 'updateOrganization'])->name('user.beneficiaries.organizations.update');

        Route::get('notifications', [UserNotificationController::class, 'index'])->name('user.notifications.index');
        Route::patch('notifications/read-all', [UserNotificationController::class, 'markAllAsRead'])->name('user.notifications.read-all');
        Route::get('notifications/{notification}', [UserNotificationController::class, 'show'])->name('user.notifications.show');
        Route::patch('notifications/{notification}/read', [UserNotificationController::class, 'markAsRead'])->name('user.notifications.read');

        Route::get('unspsc-codes', [UserUnspscCodeController::class, 'search'])->name('user.unspsc-codes.search');

        Route::get('queue', [UserAssistanceQueueController::class, 'index'])->name('user.queue.index');
        Route::patch('queue/assign', [UserAssistanceQueueController::class, 'bulkAssign'])->name('user.queue.assign');

        Route::post('workflow-tasks/{task}/claim', [UserWorkflowTaskController::class, 'claim'])->name('user.workflow-tasks.claim');
        Route::post('workflow-tasks/{task}/complete', [UserWorkflowTaskController::class, 'complete'])->name('user.workflow-tasks.complete');
        Route::post('workflow-tasks/{task}/return', [UserWorkflowTaskController::class, 'complete'])->name('user.workflow-tasks.return');
        Route::post('workflow-tasks/{task}/reject', [UserWorkflowTaskController::class, 'complete'])->name('user.workflow-tasks.reject');
        Route::post('workflow-tasks/{task}/reassign', [UserWorkflowTaskController::class, 'reassign'])->name('user.workflow-tasks.reassign');

        Route::get('workflows', [UserWorkflowController::class, 'index'])->name('user.workflows.index');
        Route::post('workflows', [UserWorkflowController::class, 'store'])->name('user.workflows.store');
        Route::put('workflows/{workflow}', [UserWorkflowController::class, 'update'])->name('user.workflows.update');
        Route::post('workflows/{workflow}/publish', [UserWorkflowController::class, 'publish'])->name('user.workflows.publish');
        Route::post('workflows/{workflow}/activate', [UserWorkflowController::class, 'activate'])->name('user.workflows.activate');
        Route::post('workflows/{workflow}/deactivate', [UserWorkflowController::class, 'deactivate'])->name('user.workflows.deactivate');
        Route::post('workflows/{workflow}/version', [UserWorkflowController::class, 'version'])->name('user.workflows.version');

        Route::scopeBindings()->group(function () {
            Route::resource('programs', UserProgramController::class)->only(['index', 'store', 'show', 'update'])->names('user.programs');
            Route::post('programs/{program}/batches', [UserProgramBatchController::class, 'store'])->name('user.programs.batches.store');

            Route::resource('items', UserItemController::class)->only(['index', 'store', 'update', 'destroy'])->names('user.items');
            Route::get('items/{item}/stock', [UserItemStockController::class, 'show'])->name('user.items.stock.show');
            Route::post('items/{item}/receipts', [UserItemStockController::class, 'storeReceipt'])->name('user.items.stock.receipts.store');
            Route::post('items/{item}/adjustments', [UserItemStockController::class, 'storeAdjustment'])->name('user.items.stock.adjustments.store');
            Route::post('items/{item}/allocations', [UserItemStockController::class, 'storeAllocation'])->name('user.items.stock.allocations.store');

            Route::resource('funds', UserFundController::class)->only(['index', 'store', 'update', 'destroy'])->names('user.funds');

            Route::post('programs/{program}/assistances', [UserAssistanceController::class, 'store'])->name('user.programs.assistances.store');
            Route::get('programs/{program}/assistances/eligibility', [UserAssistanceController::class, 'eligibility'])->name('user.programs.assistances.eligibility');
            Route::patch('programs/{program}/assistances/bulk-status', [UserAssistanceController::class, 'bulkUpdateStatus'])->name('user.programs.assistances.status.bulk-update');
            Route::patch('programs/{program}/assistances/bulk-transfer', [UserAssistanceController::class, 'bulkTransfer'])->name('user.programs.assistances.bulk-transfer');
            Route::get('programs/{program}/assistances/export', [UserAssistanceController::class, 'export'])->name('user.programs.assistances.export');
            Route::get('programs/{program}/assistances/{assistance}/edit', [UserAssistanceController::class, 'edit'])->name('user.programs.assistances.edit');
            Route::put('programs/{program}/assistances/{assistance}', [UserAssistanceController::class, 'update'])->name('user.programs.assistances.update');
            Route::delete('programs/{program}/assistances/{assistance}', [UserAssistanceController::class, 'destroy'])->name('user.programs.assistances.destroy');
            Route::patch('programs/{program}/assistances/{assistance}/status', [UserAssistanceController::class, 'updateStatus'])->name('user.programs.assistances.status.update');
            Route::patch('programs/{program}/assistances/{assistance}/assign', [UserAssistanceController::class, 'assign'])->name('user.programs.assistances.assign');
            Route::patch('programs/{program}/assistances/{assistance}/claim', [UserAssistanceController::class, 'claim'])->name('user.programs.assistances.claim');
            Route::patch('programs/{program}/assistances/{assistance}/transfer', [UserAssistanceController::class, 'transfer'])->name('user.programs.assistances.transfer');
            Route::get('programs/{program}/assistances/{assistance}/receipt', [UserAssistanceReceiptController::class, 'show'])->name('user.assistances.receipt');
            Route::post('programs/{program}/assistances/{assistance}/documents', [UserAssistanceDocumentController::class, 'store'])->name('user.assistances.documents.store');
            Route::get('programs/{program}/assistances/{assistance}/documents/{document}', [UserAssistanceDocumentController::class, 'show'])->name('user.assistances.documents.show');
            Route::delete('programs/{program}/assistances/{assistance}/documents/{document}', [UserAssistanceDocumentController::class, 'destroy'])->name('user.assistances.documents.destroy');
            Route::get('programs/{program}/assistances/{assistance}', [UserAssistanceController::class, 'show'])->name('user.assistances.show');
        });
    });
});

require __DIR__.'/settings.php';
