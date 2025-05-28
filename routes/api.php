<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserDetailController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\AnalyzerController;

Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/register', [AuthController::class, 'register']);

// Rutas protegidas por JWT
Route::middleware('auth:api')->group(function () {
    // Usuario
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Detalles de usuario
    Route::post('/user/details', [UserDetailController::class, 'store']);

    // Productos (ABM)
    Route::get('/products', [ProductController::class, 'index']);         // Listar todos
    Route::post('/products', [ProductController::class, 'store']);        // Crear
    Route::get('/products/{id}', [ProductController::class, 'show']);     // Ver uno
    Route::put('/products/{id}', [ProductController::class, 'update']);   // Editar
    Route::delete('/products/{id}', [ProductController::class, 'destroy']); // Eliminar

    // Análisis de imagen
    Route::post('/analyze', [AnalyzerController::class, 'analyze']);
});
