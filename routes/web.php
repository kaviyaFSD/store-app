<?php

use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::post('/orders', [OrderController::class, 'store']);
Route::get('/customers/{email}/orders', [OrderController::class, 'history']);
Route::get('/products/low-stock', [OrderController::class, 'lowStock']);