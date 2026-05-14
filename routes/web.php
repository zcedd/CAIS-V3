<?php

use App\Http\Controllers\User\ProjectController as UserProjectController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::get('{department}/projects', [UserProjectController::class, 'index'])->name('user.projects.index');
    Route::get('{department}/projects/{project}', [UserProjectController::class, 'show'])->name('user.projects.show');
});

require __DIR__.'/settings.php';
