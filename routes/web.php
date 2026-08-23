<?php

use App\Http\Controllers\AdministrationCatalogController;
use App\Http\Controllers\CaseAnalysisController;
use App\Http\Controllers\CaseEffectivenessController;
use App\Http\Controllers\CaseTaskController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EvidenceController;
use App\Http\Controllers\ImprovementCaseController;
use App\Http\Controllers\MeetingMinuteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OfficialDocumentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::view('/terminos-y-condiciones', 'legal.terms')->name('legal.terms');
Route::view('/politica-de-privacidad', 'legal.privacy')->name('legal.privacy');
Route::view('/tratamiento-de-datos-personales', 'legal.data-processing')->name('legal.data-processing');

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::post('hallazgos/importar-excel', [ImprovementCaseController::class, 'importSpreadsheet'])->name('cases.import');
    Route::resource('hallazgos', ImprovementCaseController::class)->only(['index', 'create', 'store', 'show'])->parameters(['hallazgos' => 'case'])->names('cases');
    Route::resource('actas', MeetingMinuteController::class)->only(['index', 'create', 'store', 'show'])->parameters(['actas' => 'minute'])->names('minutes');
    Route::post('actas/{minute}/compromisos', [MeetingMinuteController::class, 'addCommitment'])->name('minutes.commitments.store');
    Route::post('actas/{minute}/generar', [MeetingMinuteController::class, 'generate'])->name('minutes.generate');
    Route::get('actas/{minute}/descargar', [MeetingMinuteController::class, 'download'])->name('minutes.download');
    Route::post('hallazgos/{case}/acciones', [CaseTaskController::class, 'store'])->name('cases.tasks.store');
    Route::patch('hallazgos/{case}/priorizacion', [CaseAnalysisController::class, 'updatePrioritization'])->name('cases.prioritization.update');
    Route::patch('hallazgos/{case}/analisis', [CaseAnalysisController::class, 'updateAnalysis'])->name('cases.analysis.update');
    Route::patch('hallazgos/{case}/eficacia', [CaseEffectivenessController::class, 'update'])->name('cases.effectiveness.update');
    Route::post('hallazgos/{case}/documentos', [OfficialDocumentController::class, 'store'])->name('cases.documents.store');
    Route::get('documentos/{document}/descargar', [OfficialDocumentController::class, 'download'])->name('documents.download');
    Route::get('acciones/{task}', [CaseTaskController::class, 'show'])->name('tasks.show');
    Route::patch('acciones/{task}', [CaseTaskController::class, 'update'])->name('tasks.update');
    Route::patch('acciones/{task}/responsables', [CaseTaskController::class, 'updateAssignees'])->name('tasks.assignees.update');
    Route::patch('acciones/{task}/revision', [CaseTaskController::class, 'review'])->name('tasks.review');
    Route::post('acciones/{task}/evidencias', [CaseTaskController::class, 'storeEvidence'])->name('tasks.evidence.store');
    Route::post('acciones/{task}/comentarios', [CaseTaskController::class, 'storeComment'])->name('tasks.comments.store');
    Route::get('evidencias/{evidence}/descargar', [EvidenceController::class, 'download'])->name('evidence.download');
    Route::get('notificaciones', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('notificaciones/leer-todas', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::patch('notificaciones/{notification}/leer', [NotificationController::class, 'read'])->name('notifications.read');
    Route::get('usuarios', [UserManagementController::class, 'index'])->name('users.index');
    Route::post('usuarios', [UserManagementController::class, 'store'])->name('users.store');
    Route::patch('usuarios/{user}', [UserManagementController::class, 'update'])->name('users.update');
    Route::patch('usuarios/{user}/contrasena', [UserManagementController::class, 'resetPassword'])->name('users.password.update');
    Route::get('administracion/catalogos', [AdministrationCatalogController::class, 'index'])->name('administration.catalogs');
    Route::get('administracion/organizacion', [AdministrationCatalogController::class, 'organization'])->name('administration.organization');
    Route::get('administracion/areas', [AdministrationCatalogController::class, 'areas'])->name('administration.areas');
    Route::get('administracion/fuentes', [AdministrationCatalogController::class, 'sources'])->name('administration.sources');
    Route::patch('administracion/organizacion', [AdministrationCatalogController::class, 'updateOrganization'])->name('organization.update');
    Route::post('administracion/areas', [AdministrationCatalogController::class, 'storeArea'])->name('areas.store');
    Route::patch('administracion/areas/{area}', [AdministrationCatalogController::class, 'updateArea'])->name('areas.update');
    Route::delete('administracion/areas/{area}', [AdministrationCatalogController::class, 'destroyArea'])->name('areas.destroy');
    Route::post('administracion/fuentes', [AdministrationCatalogController::class, 'storeSource'])->name('sources.store');
    Route::patch('administracion/fuentes/{source}', [AdministrationCatalogController::class, 'updateSource'])->name('sources.update');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
