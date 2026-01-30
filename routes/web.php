<?php

use App\Http\Controllers\ProgramController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::resource('program', ProgramController::class)->only([
        'index',
        'show',
        'create',
        'store',
        // 'edit',
        // 'update'
    ])->names([
        'index' => 'program.index',
        'show' => 'program.show',
        'create' => 'program.create',
        'store' => 'program.store',
        // 'edit' => 'program.edit',
        // 'update' => 'program.update',
    ]);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
