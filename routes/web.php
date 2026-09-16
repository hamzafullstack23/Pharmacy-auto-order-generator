<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\SalesImportController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductImportController;

// Home redirects to login
Route::get('/', function () {
    return redirect()->route('login');
});

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// Protected Routes (require authentication)
Route::middleware('auth')->group(function () {

    Route::get('/sales/upload', [SalesImportController::class, 'showUploadForm'])->name('sales.upload');
    Route::post('/sales/upload', [SalesImportController::class, 'upload'])->name('sales.upload.post');
    Route::post('/sales/import/{batchUuid}/resume', [SalesImportController::class, 'resume'])->name('sales.import.resume');

    Route::get('/sales/import/suppliers/search', [SalesImportController::class, 'searchSuppliers'])->name('sales.import.suppliers.search');
    Route::get('/sales/import/companies/search', [SalesImportController::class, 'searchCompanies'])->name('sales.import.companies.search');
    Route::get('/sales/import/medicines/search', [SalesImportController::class, 'searchMedicines'])->name('sales.import.medicines.search');

    Route::post('/sales/import/suppliers', [SalesImportController::class, 'storeSupplier'])->name('sales.import.suppliers.store');
    Route::post('/sales/import/companies', [SalesImportController::class, 'storeCompany'])->name('sales.import.companies.store');

    Route::get('/sales/history', [SalesImportController::class, 'history'])->name('sales.history');

    // routes/web.php
Route::get('/sales/import/options', [SalesImportController::class, 'resolveOptions'])
    ->name('sales.import.options');


    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Supplier Management
    Route::resource('suppliers', SupplierController::class);

    // Company Management
    Route::resource('companies', CompanyController::class);

    // Medicine Management
    // Route::get('/medicines/{medicine}', [MedicineController::class, 'show'])->name('medicines.show');
    Route::resource('medicines', MedicineController::class);
    // Route::get('/medicines', [MedicineController::class, 'index'])->name('medicines.index');
    Route::post('/medicines/{medicine}/update-stock', [MedicineController::class, 'updateStock'])->name('medicines.update-stock');

    // Product Import (from Excel)
    Route::get('/products/upload', [ProductImportController::class, 'showUploadForm'])->name('products.upload');
    Route::post('/products/import', [ProductImportController::class, 'upload'])->name('products.import');
    Route::get('/products/history', [ProductImportController::class, 'history'])->name('products.history');
    Route::get('/products/import/progress', [ProductImportController::class, 'getProgress'])
        ->name('products.import.progress');
    Route::post('/products/import/cancel', [ProductImportController::class, 'cancelImport'])
        ->name('products.import.cancel');

    // Sales routes
    Route::get('/sales/upload', [SalesImportController::class, 'showUploadForm'])->name('sales.upload');
    Route::post('/sales/upload', [SalesImportController::class, 'upload'])->name('sales.upload.post');
    Route::get('/sales/history', [SalesImportController::class, 'history'])->name('sales.history');

    // Or use a resource-like grouping
    Route::prefix('sales')->name('sales.')->group(function () {
        Route::get('/upload', [SalesImportController::class, 'showUploadForm'])->name('upload');
        Route::post('/upload', [SalesImportController::class, 'upload'])->name('upload.post');
        Route::get('/history', [SalesImportController::class, 'history'])->name('history');
    });

    // Order Management
    Route::resource('orders', OrderController::class)->except(['create', 'store']);
    Route::get('/orders/create/{supplier?}', [OrderController::class, 'create'])->name('orders.create');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::post('/orders/{order}/submit', [OrderController::class, 'submit'])->name('orders.submit');
    Route::get('/orders/generate/{supplier}', [OrderController::class, 'generateForSupplier'])->name('orders.generate');

    // routes/web.php - Add these routes in the auth group
    Route::get('/products/upload', [ProductImportController::class, 'showUploadForm'])->name('products.upload');
    Route::post('/products/import', [ProductImportController::class, 'upload'])->name('products.import');
});
