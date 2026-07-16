<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\StaffAuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AvailabilityController;

// ============ AUTH (herkese açık) ============
Route::prefix('customer')->group(function () {
    Route::post('/register', [CustomerAuthController::class, 'register']);
    Route::post('/login', [CustomerAuthController::class, 'login']);
});

Route::prefix('staff')->group(function () {
    Route::post('/login', [StaffAuthController::class, 'login']);
});

// ============ HERKESE AÇIK (login gerekmez) ============
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);
Route::get('/services', [ServiceController::class, 'index']);
Route::get('/services/{service}', [ServiceController::class, 'show']);
Route::get('/availability', [AvailabilityController::class, 'check']);

// ============ MÜŞTERİ GİRİŞİ GEREKTİRİR ============
Route::middleware('auth:customer')->group(function () {
    Route::post('/customer/logout', [CustomerAuthController::class, 'logout']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::patch('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
    Route::get('/my-appointments', [AppointmentController::class, 'myAppointments']); // müşterinin SADECE kendi randevuları
});

// ============ PERSONEL/ADMIN GİRİŞİ GEREKTİRİR ============
Route::middleware('auth:staff')->group(function () {
    Route::post('/staff/logout', [StaffAuthController::class, 'logout']);

    Route::apiResource('categories', CategoryController::class)->except(['index', 'show']);
    Route::apiResource('services', ServiceController::class)->except(['index', 'show']);
    Route::apiResource('staff-members', StaffController::class);

    Route::get('/appointments', [AppointmentController::class, 'index']); // artık rol bazlı filtreli
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show']);
    Route::put('/appointments/{appointment}', [AppointmentController::class, 'update']);
    Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy']);
});
