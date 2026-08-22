<?php

use App\Http\Controllers\CaseAnalysisController;
use App\Http\Controllers\CaseTaskController;
use App\Http\Controllers\EvidenceController;
use App\Http\Controllers\ImprovementCaseController;
use App\Http\Controllers\OfficialDocumentController;
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
    Route::patch('hallazgos/{case}/priorizacion', [CaseAnalysisController::class, 'updatePrioritization'])->name('cases.prioritization.update');
    Route::patch('hallazgos/{case}/analisis', [CaseAnalysisController::class, 'updateAnalysis'])->name('cases.analysis.update');
    Route::post('hallazgos/{case}/documentos', [OfficialDocumentController::class, 'store'])->name('cases.documents.store');
    Route::get('documentos/{document}/descargar', [OfficialDocumentController::class, 'download'])->name('documents.download');
    Route::patch('acciones/{task}', [CaseTaskController::class, 'update'])->name('tasks.update');
    Route::post('acciones/{task}/evidencias', [CaseTaskController::class, 'storeEvidence'])->name('tasks.evidence.store');
    Route::get('evidencias/{evidence}/descargar', [EvidenceController::class, 'download'])->name('evidence.download');
    Route::get('usuarios', [UserManagementController::class, 'index'])->name('users.index');
    Route::post('usuarios', [UserManagementController::class, 'store'])->name('users.store');
    Route::patch('usuarios/{user}', [UserManagementController::class, 'update'])->name('users.update');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
