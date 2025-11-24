<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LastroIngestionController;

// Rotas públicas (para o MVP)
Route::post('/lastro/upload', [LastroIngestionController::class, 'store']);
Route::get('/lastro/status/{id}', [LastroIngestionController::class, 'show']);

// Listagem geral
Route::get('/lastro/batches', [LastroIngestionController::class, 'index']);

// Download de um item específico
Route::get('/lastro/item/{id}/download', [LastroIngestionController::class, 'downloadItem']);

