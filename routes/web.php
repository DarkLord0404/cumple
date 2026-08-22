<?php

use App\Http\Controllers\CaseTaskController;
use App\Http\Controllers\ImprovementCaseController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserManagementController;
use App\Models\Task;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard', ['metrics' => [
        'pending' => Task::query()->where('status', 'pending')->count(),
        'in_progress' => Task::query()->where('status', 'in_progress')->count(),
        'in_review' => Task::query()->where('status', 'in_review')->count(),
        'overdue' => Task::query()->whereNotIn('status', ['completed', 'cancelled'])->where('due_at', '<', now())->count(),
    ]]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::resource('hallazgos', ImprovementCaseController::class)->only(['index', 'create', 'store', 'show'])->parameters(['hallazgos' => 'case'])->names('cases');
    Route::post('hallazgos/{case}/acciones', [CaseTaskController::class, 'store'])->name('cases.tasks.store');
    Route::get('usuarios', [UserManagementController::class, 'index'])->name('users.index');
    Route::post('usuarios', [UserManagementController::class, 'store'])->name('users.store');
    Route::patch('usuarios/{user}', [UserManagementController::class, 'update'])->name('users.update');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
