<?php

use App\Http\Controllers\ProfileController;
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
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
