<?php

use App\Http\Controllers\Api\KairoMeetingImportController;
use Illuminate\Support\Facades\Route;

Route::post('/integrations/kairo/meetings', KairoMeetingImportController::class)
    ->middleware('throttle:30,1')
    ->name('api.integrations.kairo.meetings.store');
