<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\OperationController;
use App\Http\Controllers\ImportedOperationController;
use App\Http\Controllers\MoreController;
use App\Http\Controllers\StatsController;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', [AccountController::class, 'index'])->name('index');
    Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::delete('/accounts/{id}', [AccountController::class, 'destroy']);
    Route::get('/accounts/{id}', [AccountController::class, 'show']);
    Route::put('/accounts/{id}', [AccountController::class, 'update']);

    Route::get('/stats', [StatsController::class, 'index'])->name('stats');
    Route::get('/more', [MoreController::class, 'index'])->name('more');
    Route::put('/more/profile', [MoreController::class, 'updateProfile'])->name('more.profile');
    Route::put('/more/avatar', [MoreController::class, 'updateAvatar'])->name('more.avatar');
    Route::put('/more/preferences', [MoreController::class, 'updatePreferences'])->name('more.preferences');
    Route::put('/more/password', [MoreController::class, 'updatePassword'])->name('more.password');

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    Route::put('/categories/{category}', [CategoryController::class, 'update']);

    Route::post('/operations', [OperationController::class, 'store'])->name('operations.store');
    Route::put('/operations/{operation}', [OperationController::class, 'update'])->name('operations.update');
    Route::delete('/operations/{operation}', [OperationController::class, 'destroy'])->name('operations.destroy');
    Route::get('/operations', [OperationController::class, 'index'])->name('operations');
    Route::post('/import', [ImportedOperationController::class, 'import']);
});
