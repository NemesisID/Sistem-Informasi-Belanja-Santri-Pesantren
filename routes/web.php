<?php

use App\Http\Controllers\FotoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/foto/{slug}', [FotoController::class, 'show']);
