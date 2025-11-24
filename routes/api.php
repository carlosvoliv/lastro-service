<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LastroIngestionController;

// Rotas públicas (para o MVP)
Route::post('/lastro/upload', [LastroIngestionController::class, 'store']);
Route::get('/lastro/status/{id}', [LastroIngestionController::class, 'show']);
