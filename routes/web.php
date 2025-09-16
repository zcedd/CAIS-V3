<?php

use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::resource('project', ProjectController::class)->only([
        'index',
        'show',
        'create',
        'store',
        // 'edit',
        // 'update'
    ])->names([
        'index' => 'project.index',
        'show' => 'project.show',
        'create' => 'project.create',
        'store' => 'project.store',
        // 'edit' => 'project.edit',
        // 'update' => 'project.update',
    ]);
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
