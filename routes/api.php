<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserDetailController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\AnalyzerController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;

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
    Route::get('/products', [ProductController::class, 'index']);         
    Route::post('/products', [ProductController::class, 'store']);    
    Route::get('/products/{id}', [ProductController::class, 'show']);   
    Route::put('/products/{id}', [ProductController::class, 'update']);  
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);

    // Análisis de imagen
    Route::post('/analyze', [AnalyzerController::class, 'analyze']);

    // Carrito
    Route::get('/cart', [CartController::class, 'index']);                    
    Route::post('/cart/items', [CartController::class, 'addItem']);          
    Route::delete('/cart/items/{id}', [CartController::class, 'removeItem']);
    Route::delete('/cart', [CartController::class, 'clear']);

    // Checkout y órdenes
    Route::post('/checkout', [OrderController::class, 'checkout']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    
});
