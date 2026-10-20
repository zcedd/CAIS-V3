<?php

use App\Http\Controllers\Api\Mobile\AuthController;
use App\Http\Controllers\Api\Mobile\DistributionController;
use App\Http\Controllers\Api\Mobile\DistributionReceiptController;
use App\Http\Controllers\Api\Mobile\DistributionSyncController;
use App\Http\Middleware\EnsureMobileDistributionDepartment;
use App\Http\Middleware\ForceJsonRequest;
use App\Http\Middleware\MarkMobileDistributionAudit;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/mobile')
    ->middleware([ForceJsonRequest::class, MarkMobileDistributionAudit::class])
    ->name('api.mobile.')
    ->group(function (): void {
        Route::middleware('throttle:mobile-login')->group(function (): void {
            Route::post('login', [AuthController::class, 'login'])->name('login');
            Route::post('two-factor', [AuthController::class, 'twoFactor'])->name('two-factor');
        });

        Route::middleware(['auth:sanctum', 'abilities:mobile:distribution'])->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::get('distributions', [DistributionController::class, 'index'])->name('distributions.index');

            Route::middleware(EnsureMobileDistributionDepartment::class)->group(function (): void {
                Route::get('distributions/{distributionBatch}/package', [DistributionController::class, 'package'])
                    ->name('distributions.package');
                Route::post('distributions/{distributionBatch}/items/{item}/releases', [DistributionSyncController::class, 'release'])
                    ->name('distributions.releases.store');
                Route::post('distributions/{distributionBatch}/items/{item}/absent', [DistributionSyncController::class, 'absent'])
                    ->name('distributions.absent.store');
                Route::get('distributions/{distributionBatch}/transactions/{transaction}/receipt.pdf', [DistributionReceiptController::class, 'show'])
                    ->name('distributions.transactions.receipt');
            });
        });
    });
