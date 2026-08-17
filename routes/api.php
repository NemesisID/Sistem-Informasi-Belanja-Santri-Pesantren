<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BniUploadController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PenarikanController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SantriController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\WaliController;
use App\Http\Controllers\Api\WaliUserController;
use Illuminate\Support\Facades\Route;

// ============================= AUTH =============================
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
});

// ===================== GANTI PASSWORD (semua role) =====================
Route::middleware('auth:sanctum')->group(function () {
    Route::put('/auth/change-password', [AuthController::class, 'changePassword']);
});

// ===================== MANAJEMEN ADMIN (admin only) =====================
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/admins', [AdminController::class, 'index']);
    Route::post('/admins', [AdminController::class, 'store']);
    Route::put('/admins/{user}', [AdminController::class, 'update']);
    Route::delete('/admins/{user}', [AdminController::class, 'destroy']);
});

// ===================== MANAJEMEN WALI USER (admin only) =====================
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/wali-users', [WaliUserController::class, 'index']);
    Route::post('/wali-users', [WaliUserController::class, 'store']);
    Route::put('/wali-users/{user}', [WaliUserController::class, 'update']);
    Route::delete('/wali-users/{user}', [WaliUserController::class, 'destroy']);
    Route::post('/wali-users/{user}/santri/{santri}', [WaliUserController::class, 'linkSantri']);
    Route::delete('/wali-users/{user}/santri/{santri}', [WaliUserController::class, 'unlinkSantri']);
});

// ===================== DASHBOARD (admin, staff) =====================
Route::middleware(['auth:sanctum', 'role:admin,staff'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});

// ===================== SANTRI (admin, staff) =====================
Route::middleware(['auth:sanctum', 'role:admin,staff'])->group(function () {
    Route::get('/santris', [SantriController::class, 'index']);
    Route::get('/santris/by-nis', [SantriController::class, 'byNis']);
    Route::get('/santris/{santri}', [SantriController::class, 'show']);
    Route::get('/santris/{santri}/mutasi', [SantriController::class, 'mutasi']);
});

// Santri — khusus admin
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::post('/santris', [SantriController::class, 'store']);
    Route::put('/santris/{santri}', [SantriController::class, 'update']);
    Route::delete('/santris/{santri}', [SantriController::class, 'destroy']);
    Route::post('/santris/import', [SantriController::class, 'import']);
    Route::post('/santris/{santri}/penyesuaian', [SantriController::class, 'penyesuaian']);
});

// ===================== STAFF (admin) =====================
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/staff', [StaffController::class, 'index']);
    Route::post('/staff', [StaffController::class, 'store']);
    Route::put('/staff/{user}', [StaffController::class, 'update']);
    Route::delete('/staff/{user}', [StaffController::class, 'destroy']);
});

// ===================== PENARIKAN (staff) =====================
Route::middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
    Route::post('/penarikan', [PenarikanController::class, 'store']);
});

// ===================== UPLOAD BNI (staff) =====================
Route::middleware(['auth:sanctum', 'role:staff,admin'])->group(function () {
    Route::get('/bni-uploads', [BniUploadController::class, 'index']);
    Route::post('/bni-uploads', [BniUploadController::class, 'store']);
    Route::get('/bni-uploads/{upload}', [BniUploadController::class, 'show']);
    Route::post('/bni-uploads/{upload}/apply', [BniUploadController::class, 'apply']);
});

// ===================== TRANSAKSI (admin, staff) =====================
Route::middleware(['auth:sanctum', 'role:admin,staff'])->group(function () {
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/export', [TransactionController::class, 'export']);
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show']);
});

// ===================== LAPORAN (admin) =====================
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/reports/financial', [ReportController::class, 'financial']);
    Route::get('/reports/financial/summary', [ReportController::class, 'summary']);
    Route::get('/reports/financial/export', [ReportController::class, 'export']);
});

// ===================== PORTAL WALI =====================
Route::prefix('wali')->middleware(['auth:sanctum', 'role:wali'])->group(function () {
    Route::get('/dashboard', [WaliController::class, 'dashboard']);
    Route::get('/transactions', [WaliController::class, 'transactions']);
    Route::get('/transactions/export', [WaliController::class, 'export']);
});
