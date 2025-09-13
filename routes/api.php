<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserDetailController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\AnalyzerController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\UserAdminController;
use App\Http\Controllers\ProductionBatchController;

// Autenticación y registro
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/register', [AuthController::class, 'register']);
Route::get('/predefined-products', [ProductController::class, 'predefinedProducts']);

// Recuperar contraseña
Route::post('/password/email', [AuthController::class, 'sendResetLinkEmail']);
Route::post('/password/reset', [AuthController::class, 'resetPassword']);

// Rutas protegidas por JWT
Route::middleware('auth:api')->group(function () {

    // Usuario
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/user/details', [UserDetailController::class, 'store']);
    Route::put('/user', [AuthController::class, 'updateProfile']);

    // Rutas accesibles por cualquier usuario logueado
    Route::middleware('roleMiddleware:usuario')->group(function () {
        // Productos (solo lectura)
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{id}', [ProductController::class, 'show']);

        // Materias primas (solo lectura)
        Route::get('/raw-materials', [ProductController::class, 'GetRawMaterials']);

        // Análisis de imagen
        Route::post('/analyze', [AnalyzerController::class, 'analyze']);

        Route::get('/quotes', [AnalyzerController::class, 'listQuotes']);

        // Carrito
        Route::get('/cart', [CartController::class, 'index']);
        Route::post('/cart/items', [CartController::class, 'addItem']);
        Route::delete('/cart/items/{id}', [CartController::class, 'removeItem']);
        Route::delete('/cart', [CartController::class, 'clear']);

        // Checkout y órdenes
        Route::post('/checkout', [OrderController::class, 'checkout']);
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);

        Route::get('/stock', [ProductController::class, 'stock']);
        Route::get('/users/{id}', [UserAdminController::class, 'show']);

        Route::put('/quotes/{id}', [AnalyzerController::class, 'update']);
    });

    // Rutas para empleados (y superadmin)
    Route::middleware('roleMiddleware:empleado,superadmin')->group(function () {
        // ABM productos
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);

        // Materias primas: agregar stock
        Route::put('/raw-materials/{id}/add-stock', [ProductController::class, 'addStockRawMaterial']);

        // Cambiar estado de órdenes
        Route::put('/orders/{id}/status', [OrderController::class, 'updateStatus']);

        // Gestión de lotes de producción
        Route::get('/produccion', [ProductionBatchController::class, 'index']);
        Route::post('/produccion', [ProductionBatchController::class, 'store']);
        Route::put('/produccion/{id}', [ProductionBatchController::class, 'update']);

        // Listado de ventas (productos de línea y personalizados pagados)
        Route::get('/ventas', [OrderController::class, 'ventas']);
    });

    // Rutas exclusivas de superadmin
    Route::middleware('roleMiddleware:superadmin')->group(function () {
        Route::get('/presupuestos', [AnalyzerController::class, 'pendingQuotes']);
        Route::get('/quotes/{id}', [AnalyzerController::class, 'show']);
        Route::get('/users', [UserAdminController::class, 'index']);
        Route::put('/users/{id}/role', [UserAdminController::class, 'updateRole']);
    });
});
