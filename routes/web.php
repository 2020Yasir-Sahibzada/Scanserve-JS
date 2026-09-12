<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DocumentScannerController;

Route::get('/scan', [DocumentScannerController::class, 'index'])
    ->name('scanner.index');

Route::post('/scan/start', [DocumentScannerController::class, 'scan'])
    ->name('scanner.scan');

Route::post('/scan/adf', [DocumentScannerController::class, 'scanAdf'])
    ->name('scanner.scanAdf');

Route::post('/scan/save', [DocumentScannerController::class, 'save'])
    ->name('scanner.save');