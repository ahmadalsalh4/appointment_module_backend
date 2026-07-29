<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminProfileController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\ArtisanCommandController;
use App\Http\Controllers\AuthRefreshController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\CustomerProfileController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StaffAuthController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffProfileController;
use App\Http\Controllers\UnifiedAuthController;
use Illuminate\Support\Facades\Route;

// ============ INTERNAL ARTISAN BRIDGE (operator only) ============
// Disabled unless INTERNAL_ARTISAN_TOKEN is set. Token is checked
// against the X-Internal-Token request header. The route is named for
// observability but does not appear in any UI. Render web services
// don't expose a shell, so this is how operators run whitelisted
// artisan commands after deploy.
Route::post('/internal/artisan', [ArtisanCommandController::class, 'run']);

// ============ BİRLEŞİK GİRİŞ (herkese açık) ============
Route::post('/login', [UnifiedAuthController::class, 'login'])->middleware('throttle:10,1');

// ============ ROL İŞLEMLERİ (herhangi bir guard ile giriş yapmış kullanıcı) ============
Route::middleware(['auth:customer,staff,admin', 'throttle:10,1'])->group(function () {
    Route::get('/me/roles', [UnifiedAuthController::class, 'myRoles']);
    Route::post('/switch-role', [UnifiedAuthController::class, 'switchRole']);
    Route::post('/auth/refresh', [AuthRefreshController::class, 'refresh']);
});

// ============ MÜŞTERİ KAYIT (herkese açık) ============
Route::prefix('customer')->group(function () {
    Route::post('/register', [CustomerAuthController::class, 'register'])->middleware('throttle:30,1');
});

// ============ HERKESE AÇIK (login gerekmez) ============
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);
    Route::get('/services', [ServiceController::class, 'index']);
    Route::get('/services/{service}', [ServiceController::class, 'show']);
    Route::get('/availability', [AvailabilityController::class, 'check']);
    Route::get('/services/{service}/staff', [ServiceController::class, 'getAvailableStaff']);
    Route::get('/categories/{category}/staff', [StaffController::class, 'byCategory']);
});

// ============ MÜŞTERİ GİRİŞİ GEREKTİRİR ============
Route::middleware(['auth:customer', 'ensureUserModel:App\Models\Customer'])->group(function () {
    Route::post('/customer/logout', [CustomerAuthController::class, 'logout']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::patch('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
    Route::get('/my-appointments', [AppointmentController::class, 'myAppointments']);
    Route::get('/my-appointments/{appointment}', [AppointmentController::class, 'myAppointmentDetail']);
    Route::put('/my-appointments/{appointment}', [AppointmentController::class, 'updateMyAppointment']);

    Route::get('/customer/profile', [CustomerProfileController::class, 'show']);
    Route::put('/customer/profile', [CustomerProfileController::class, 'update']);
});

// ============ PERSONEL GİRİŞİ GEREKTİRİR (sadece sıradan staff) ============
Route::middleware(['auth:staff', 'ensureUserModel:App\Models\Staff'])->group(function () {
    Route::post('/staff/logout', [StaffAuthController::class, 'logout']);
    Route::get('/staff/appointments', [AppointmentController::class, 'myStaffAppointments']);
    Route::get('/staff/appointments/{appointment}', [AppointmentController::class, 'myStaffAppointmentDetail']);
    Route::patch('/staff/appointments/{appointment}/status', [AppointmentController::class, 'updateStatusAsStaff']);

    Route::get('/staff/profile', [StaffProfileController::class, 'show']);
    Route::put('/staff/profile', [StaffProfileController::class, 'update']);
});

// ============ ADMIN GİRİŞİ GEREKTİRİR ============
Route::middleware(['auth:admin', 'ensureUserModel:App\Models\Admin'])->group(function () {
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
