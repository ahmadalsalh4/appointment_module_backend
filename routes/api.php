<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\StaffAuthController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminProfileController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\CustomerProfileController;
use App\Http\Controllers\StaffProfileController;

// ============ AUTH (herkese açık) ============
Route::prefix('customer')->group(function () {
    Route::post('/register', [CustomerAuthController::class, 'register']);
    Route::post('/login', [CustomerAuthController::class, 'login']);
});

Route::prefix('staff')->group(function () {
    Route::post('/login', [StaffAuthController::class, 'login']);
});

Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login']);
});

// ============ HERKESE AÇIK (login gerekmez) ============
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);
Route::get('/services', [ServiceController::class, 'index']);
Route::get('/services/{service}', [ServiceController::class, 'show']);
Route::get('/availability', [AvailabilityController::class, 'check']);
Route::get('/services/{service}/staff', [ServiceController::class, 'getAvailableStaff']);
Route::get('/categories/{category}/staff', [StaffController::class, 'byCategory']);

// ============ MÜŞTERİ GİRİŞİ GEREKTİRİR ============
Route::middleware('auth:customer')->group(function () {
    Route::post('/customer/logout', [CustomerAuthController::class, 'logout']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::patch('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
    Route::get('/my-appointments', [AppointmentController::class, 'myAppointments']);
    Route::get('/my-appointments/{appointment}', [AppointmentController::class, 'myAppointmentDetail']);

    Route::get('/customer/profile', [CustomerProfileController::class, 'show']);
    Route::put('/customer/profile', [CustomerProfileController::class, 'update']);
});

// ============ PERSONEL GİRİŞİ GEREKTİRİR (sadece sıradan staff) ============
Route::middleware('auth:staff')->group(function () {
    Route::post('/staff/logout', [StaffAuthController::class, 'logout']);
    Route::get('/staff/appointments', [AppointmentController::class, 'myStaffAppointments']);
    Route::get('/staff/appointments/{appointment}', [AppointmentController::class, 'myStaffAppointmentDetail']);
    Route::patch('/staff/appointments/{appointment}/status', [AppointmentController::class, 'updateStatusAsStaff']);

    Route::get('/staff/profile', [StaffProfileController::class, 'show']);
    Route::put('/staff/profile', [StaffProfileController::class, 'update']);
});

// ============ ADMIN GİRİŞİ GEREKTİRİR ============
Route::middleware('auth:admin')->group(function () {
    Route::post('/admin/logout', [AdminAuthController::class, 'logout']);
    Route::get('/admin/profile', [AdminProfileController::class, 'show']);
    Route::put('/admin/profile', [AdminProfileController::class, 'update']);

    Route::apiResource('categories', CategoryController::class)->except(['index', 'show']);
    Route::apiResource('services', ServiceController::class)->except(['index', 'show']);
    Route::apiResource('staff-members', StaffController::class);

    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show']);
    Route::put('/appointments/{appointment}', [AppointmentController::class, 'update']);
    Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy']);
});
