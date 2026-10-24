<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/login', [AuthController::class, 'authenticate'])->middleware('throttle:15,1');
});
Route::middleware(['auth', 'access:view'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/password', [AuthController::class, 'edit'])->name('password.edit');
    Route::put('/password', [AuthController::class, 'update'])->name('password.update')->middleware('throttle:6,1');
    Route::get('/', [WorkspaceController::class, 'dashboard'])->name('dashboard');
    Route::get('/calendar', [WorkspaceController::class, 'calendar'])->name('calendar');
    Route::get('/availability', [WorkspaceController::class, 'availability'])->name('availability');
    Route::get('/api/v1/availability', [WorkspaceController::class, 'availability'])->middleware('throttle:60,1')->name('api.availability');
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::middleware('access:bookings')->group(function () {
        Route::get('/bookings/create', [BookingController::class, 'create'])->name('bookings.create');
        Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
        Route::get('/bookings/{booking}/edit', [BookingController::class, 'edit'])->name('bookings.edit');
        Route::put('/bookings/{booking}', [BookingController::class, 'update'])->name('bookings.update');
    });
    Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
    Route::get('/bookings/{booking}/print', [BookingController::class, 'print'])->name('bookings.print');
    Route::post('/bookings/{booking}/actions/{action}', [BookingController::class, 'action'])->name('bookings.action');
    Route::post('/bookings/{booking}/payments', [BookingController::class, 'pay'])->middleware('access:finance')->name('bookings.pay');
    Route::get('/inventory', [CatalogController::class, 'inventory'])->name('inventory');
    Route::get('/customers', [CatalogController::class, 'customers'])->name('customers');
    Route::get('/maintenance', [CatalogController::class, 'maintenance'])->name('maintenance');
    Route::middleware('access:inventory')->group(function () {
        Route::get('/inventory/create', [CatalogController::class, 'equipmentForm'])->name('equipment.create');
        Route::get('/inventory/{equipment}/edit', [CatalogController::class, 'equipmentForm'])->name('equipment.edit');
        Route::post('/inventory', [CatalogController::class, 'saveEquipment'])->name('equipment.store');
        Route::put('/inventory/{equipment}', [CatalogController::class, 'saveEquipment'])->name('equipment.update');
        Route::post('/categories', [CatalogController::class, 'category'])->name('categories.store');
    });
    Route::middleware('access:customers')->group(function () {
        Route::get('/customers/create', [CatalogController::class, 'customerForm'])->name('customers.create');
        Route::get('/customers/{customer}/edit', [CatalogController::class, 'customerForm'])->name('customers.edit');
        Route::post('/customers', [CatalogController::class, 'saveCustomer'])->name('customers.store');
        Route::put('/customers/{customer}', [CatalogController::class, 'saveCustomer'])->name('customers.update');
    });
    Route::middleware('access:operations')->group(function () {
        Route::post('/maintenance', [CatalogController::class, 'storeMaintenance'])->name('maintenance.store');
        Route::post('/maintenance/{maintenance}/complete', [CatalogController::class, 'completeMaintenance'])->name('maintenance.complete');
    });
    Route::middleware('access:reports')->group(function () {
        Route::get('/reports', [WorkspaceController::class, 'reports'])->name('reports');
        Route::get('/reports/export', [WorkspaceController::class, 'export'])->name('reports.export');
    });
    Route::middleware('access:admin')->group(function () {
        Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
        Route::put('/settings', [AdminController::class, 'saveSettings'])->name('settings.update');
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::get('/users/create', [AdminController::class, 'userForm'])->name('users.create');
        Route::post('/users', [AdminController::class, 'saveUser'])->name('users.store');
        Route::get('/users/{user}/edit', [AdminController::class, 'userForm'])->name('users.edit');
        Route::put('/users/{user}', [AdminController::class, 'saveUser'])->name('users.update');
        Route::get('/audit', [AdminController::class, 'audit'])->name('audit');
    });
});
