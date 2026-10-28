<?php

use App\Http\Controllers\TriageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TriageController::class, 'index'])->name('home');
Route::post('/triage', [TriageController::class, 'store'])->name('triage.store');
