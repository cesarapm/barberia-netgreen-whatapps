<?php

use Illuminate\Support\Facades\Route;

// Catch-all: cualquier ruta web que no sea /api sirve el SPA de Quasar
Route::get('/{any}', function () {
    return response()->file(public_path('index.html'));
})->where('any', '.*');
