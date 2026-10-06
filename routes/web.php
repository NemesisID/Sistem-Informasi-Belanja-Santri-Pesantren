<?php

use App\Http\Controllers\FotoController;
use App\Http\Controllers\StatusController;
use App\Http\Middleware\EnsureAccessKey;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/foto/{slug}', [FotoController::class, 'show']);

// Halaman monitoring (diproteksi kunci yang sama dengan dokumentasi API).
Route::middleware(EnsureAccessKey::class)->group(function () {
    Route::get('/status', StatusController::class);
});
