<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Ruta necesaria para el enlace de recuperación de contraseña
Route::get('/password/reset/{token}', function ($token) {
    return "Pantalla de recuperación de contraseña. Token: $token";
})->name('password.reset');
