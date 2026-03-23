<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SuperAdmin\DashboardController as SuperAdminDashboard;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Api\Teacher\DashboardController as TeacherDashboard;
use App\Http\Controllers\Api\Student\DashboardController as StudentDashboard;
use App\Http\Controllers\Api\Parents\DashboardController as ParentDashboard;

// ==================
// PUBLIC ROUTES
// ==================
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

// ==================
// PROTECTED ROUTES (need token)
// ==================
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user',    [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // ==================
    // SUPERADMIN ROUTES
    // ==================
    Route::middleware('role:superadmin')->prefix('superadmin')->group(function () {
        Route::get('/dashboard',              [SuperAdminDashboard::class, 'index']);
        Route::get('/users',                  [SuperAdminDashboard::class, 'users']);
        Route::patch('/users/{user}/role',    [SuperAdminDashboard::class, 'updateUserRole']);
    });

    // ==================
    // ADMIN ROUTES
    // ==================
    Route::middleware('role:admin,superadmin')->prefix('admin')->group(function () {
        Route::get('/dashboard',  [AdminDashboard::class, 'index']);
        Route::get('/users',      [AdminDashboard::class, 'users']);
        Route::post('/users',     [AdminDashboard::class, 'createUser']);
    });

    // ==================
    // TEACHER ROUTES
    // ==================
    Route::middleware('role:teacher,admin,superadmin')->prefix('teacher')->group(function () {
        Route::get('/dashboard', [TeacherDashboard::class, 'index']);
        Route::get('/students',  [TeacherDashboard::class, 'students']);
    });

    // ==================
    // STUDENT ROUTES
    // ==================
    Route::middleware('role:student')->prefix('student')->group(function () {
        Route::get('/dashboard', [StudentDashboard::class, 'index']);
        Route::get('/profile',   [StudentDashboard::class, 'profile']);
    });

    // ==================
    // PARENT ROUTES
    // ==================
    Route::middleware('role:parent')->prefix('parent')->group(function () {
        Route::get('/dashboard', [ParentDashboard::class, 'index']);
        Route::get('/profile',   [ParentDashboard::class, 'profile']);
    });
});
