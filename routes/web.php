<?php

use App\Http\Controllers\Admin\DisbursementApprovalPolicyController as AdminDisbursementApprovalPolicyController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\WorkflowController as AdminWorkflowController;
use App\Http\Controllers\Executive\DashboardController as ExecutiveDashboardController;
use App\Http\Controllers\Executive\DisbursementApprovalController;
use App\Http\Controllers\Executive\ProgramController as ExecutiveProgramController;
use App\Http\Controllers\GlobalDashboardController;
use App\Http\Controllers\User\AssistanceController as UserAssistanceController;
use App\Http\Controllers\User\AssistanceDocumentController as UserAssistanceDocumentController;
use App\Http\Controllers\User\AssistanceQueueController as UserAssistanceQueueController;
use App\Http\Controllers\User\AssistanceReceiptController as UserAssistanceReceiptController;
use App\Http\Controllers\User\BeneficiaryController as UserBeneficiaryController;
use App\Http\Controllers\User\DashboardController as UserDashboardController;
use App\Http\Controllers\User\DisbursementBatchController as UserDisbursementBatchController;
use App\Http\Controllers\User\DisbursementVenueController as UserDisbursementVenueController;
use App\Http\Controllers\User\FundController as UserFundController;
use App\Http\Controllers\User\ItemController as UserItemController;
use App\Http\Controllers\User\ItemStockController as UserItemStockController;
use App\Http\Controllers\User\NotificationController as UserNotificationController;
use App\Http\Controllers\User\ProgramApprovalController as UserProgramApprovalController;
use App\Http\Controllers\User\ProgramBatchController as UserProgramBatchController;
use App\Http\Controllers\User\ProgramController as UserProgramController;
use App\Http\Controllers\User\UnspscCodeController as UserUnspscCodeController;
use App\Http\Controllers\User\WorkflowTaskController as UserWorkflowTaskController;
use App\Http\Middleware\EnsureUserBelongsToDepartment;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

require __DIR__.'/public.php';

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', GlobalDashboardController::class)->name('dashboard');

    Route::middleware('executive')->prefix('executive')->name('executive.')->group(function () {
        Route::get('dashboard', [ExecutiveDashboardController::class, 'index'])->name('dashboard');
        Route::get('programs', [ExecutiveProgramController::class, 'index'])->name('programs.index');
        Route::get('programs/{program}', [ExecutiveProgramController::class, 'show'])->name('programs.show');
        Route::post('programs/{program}/approve', [ExecutiveProgramController::class, 'approve'])->name('programs.approve');
        Route::post('programs/{program}/return', [ExecutiveProgramController::class, 'returnToDepartment'])->name('programs.return');
        Route::get('disbursements', [DisbursementApprovalController::class, 'index'])->name('disbursements.index');
        Route::get('disbursements/{disbursementBatch}', [DisbursementApprovalController::class, 'show'])->name('disbursements.show');
        Route::post('disbursements/{disbursementBatch}/decide', [DisbursementApprovalController::class, 'decide'])->name('disbursements.decide');
        Route::post('disbursements/{disbursementBatch}/items/bulk-decide', [DisbursementApprovalController::class, 'bulkDecide'])->name('disbursements.items.bulk-decide');
        Route::post('disbursements/{disbursementBatch}/items/{item}/decide', [DisbursementApprovalController::class, 'decideItem'])->name('disbursements.items.decide');
        Route::post('disbursements/{disbursementBatch}/finalize', [DisbursementApprovalController::class, 'finalize'])->name('disbursements.finalize');
    });

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

        Route::get('disbursement-policies', [AdminDisbursementApprovalPolicyController::class, 'index'])->name('disbursement-policies.index');
        Route::post('disbursement-policies', [AdminDisbursementApprovalPolicyController::class, 'store'])->name('disbursement-policies.store');
        Route::put('disbursement-policies/{policy}', [AdminDisbursementApprovalPolicyController::class, 'update'])->name('disbursement-policies.update');
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

        Route::get('disbursements', [UserDisbursementBatchController::class, 'index'])->name('user.disbursements.index');
        Route::get('disbursements/calendar', [UserDisbursementBatchController::class, 'calendar'])->name('user.disbursements.calendar');
        Route::get('disbursements/create', [UserDisbursementBatchController::class, 'create'])->name('user.disbursements.create');
        Route::post('disbursements', [UserDisbursementBatchController::class, 'store'])->name('user.disbursements.store');
        Route::get('disbursements/{disbursementBatch}', [UserDisbursementBatchController::class, 'show'])->name('user.disbursements.show');
        Route::get('disbursements/{disbursementBatch}/edit', [UserDisbursementBatchController::class, 'edit'])->name('user.disbursements.edit');
        Route::put('disbursements/{disbursementBatch}', [UserDisbursementBatchController::class, 'update'])->name('user.disbursements.update');
        Route::post('disbursements/{disbursementBatch}/validate', [UserDisbursementBatchController::class, 'validateBatch'])->name('user.disbursements.validate');
        Route::post('disbursements/{disbursementBatch}/submit', [UserDisbursementBatchController::class, 'submit'])->name('user.disbursements.submit');
        Route::post('disbursements/{disbursementBatch}/decide', [UserDisbursementBatchController::class, 'decide'])->name('user.disbursements.decide');
        Route::post('disbursements/{disbursementBatch}/items/bulk-decide', [UserDisbursementBatchController::class, 'bulkDecide'])->name('user.disbursements.items.bulk-decide');
        Route::post('disbursements/{disbursementBatch}/items/{item}/decide', [UserDisbursementBatchController::class, 'decideItem'])->name('user.disbursements.items.decide');
        Route::post('disbursements/{disbursementBatch}/finalize', [UserDisbursementBatchController::class, 'finalize'])->name('user.disbursements.finalize');
        Route::post('disbursements/{disbursementBatch}/schedule', [UserDisbursementBatchController::class, 'schedule'])->name('user.disbursements.schedule');
        Route::post('disbursements/{disbursementBatch}/cancel', [UserDisbursementBatchController::class, 'cancel'])->name('user.disbursements.cancel');
        Route::post('disbursements/{disbursementBatch}/reopen', [UserDisbursementBatchController::class, 'reopen'])->name('user.disbursements.reopen');
        Route::post('disbursements/{disbursementBatch}/start', [UserDisbursementBatchController::class, 'start'])->name('user.disbursements.start');
        Route::post('disbursements/{disbursementBatch}/releases', [UserDisbursementBatchController::class, 'release'])->name('user.disbursements.releases.store');
        Route::post('disbursements/{disbursementBatch}/complete', [UserDisbursementBatchController::class, 'complete'])->name('user.disbursements.complete');
        Route::post('disbursements/{disbursementBatch}/documents', [UserDisbursementBatchController::class, 'storeDocument'])->name('user.disbursements.documents.store');
        Route::get('disbursements/{disbursementBatch}/documents/{disbursementDocument}', [UserDisbursementBatchController::class, 'document'])->name('user.disbursements.documents.show');
        Route::get('disbursements/{disbursementBatch}/exports/{report}', [UserDisbursementBatchController::class, 'export'])->name('user.disbursements.report');
        Route::get('disbursements/{disbursementBatch}/venue', [UserDisbursementVenueController::class, 'show'])->name('user.disbursements.venue');
        Route::post('disbursements/{disbursementBatch}/personnel', [UserDisbursementVenueController::class, 'assign'])->name('user.disbursements.personnel.store');
        Route::post('disbursements/{disbursementBatch}/items/{item}/transactions', [UserDisbursementVenueController::class, 'start'])->name('user.disbursements.transactions.store');
        Route::post('disbursements/{disbursementBatch}/transactions/{transaction}/evidence', [UserDisbursementVenueController::class, 'evidence'])->name('user.disbursements.transactions.evidence.store');
        Route::get('disbursements/{disbursementBatch}/transactions/{transaction}/evidence/{evidence}', [UserDisbursementVenueController::class, 'showEvidence'])->name('user.disbursements.transactions.evidence.show');
        Route::post('disbursements/{disbursementBatch}/transactions/{transaction}/finalize', [UserDisbursementVenueController::class, 'finalize'])->name('user.disbursements.transactions.finalize');
        Route::post('disbursements/{disbursementBatch}/transactions/{transaction}/void', [UserDisbursementVenueController::class, 'void'])->name('user.disbursements.transactions.void');
        Route::get('disbursements/{disbursementBatch}/transactions/{transaction}/receipt', [UserDisbursementVenueController::class, 'receipt'])->name('user.disbursements.transactions.receipt');
        Route::get('disbursements/{disbursementBatch}/transactions/{transaction}/receipt/pdf', [UserDisbursementVenueController::class, 'receiptPdf'])->name('user.disbursements.transactions.receipt.pdf');
        Route::post('disbursements/{disbursementBatch}/receipts/{receipt}/reprint', [UserDisbursementVenueController::class, 'reprint'])->name('user.disbursements.receipts.reprint');
        Route::post('disbursements/{disbursementBatch}/receipts/{receipt}/supersede', [UserDisbursementVenueController::class, 'supersede'])->name('user.disbursements.receipts.supersede');

        Route::get('unspsc-codes', [UserUnspscCodeController::class, 'search'])->name('user.unspsc-codes.search');

        Route::get('queue', [UserAssistanceQueueController::class, 'index'])->name('user.queue.index');
        Route::patch('queue/assign', [UserAssistanceQueueController::class, 'bulkAssign'])->name('user.queue.assign');

        Route::post('workflow-tasks/{task}/claim', [UserWorkflowTaskController::class, 'claim'])->name('user.workflow-tasks.claim');
        Route::post('workflow-tasks/{task}/complete', [UserWorkflowTaskController::class, 'complete'])->name('user.workflow-tasks.complete');
        Route::post('workflow-tasks/{task}/return', [UserWorkflowTaskController::class, 'complete'])->name('user.workflow-tasks.return');
        Route::post('workflow-tasks/{task}/reject', [UserWorkflowTaskController::class, 'complete'])->name('user.workflow-tasks.reject');
        Route::post('workflow-tasks/{task}/reassign', [UserWorkflowTaskController::class, 'reassign'])->name('user.workflow-tasks.reassign');

        Route::scopeBindings()->group(function () {
            Route::resource('programs', UserProgramController::class)->only(['index', 'store', 'show', 'update'])->names('user.programs');
            Route::post('programs/{program}/approval', [UserProgramApprovalController::class, 'store'])->name('user.programs.approval.store');
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
