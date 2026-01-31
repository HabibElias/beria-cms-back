<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ReportController;
use App\Models\Book;
use Illuminate\Support\Facades\Route;

// Public Auth Routes
Route::post('login', [SessionController::class, 'store'])->name('login');
Route::post('register', [SessionController::class, 'register']);
Route::post('refresh', [SessionController::class, 'update']);

// Protected Authenticated Routes
Route::middleware(['auth'])->group(function () {

    // Auth
    Route::post('logout', [SessionController::class, 'destroy']);

    // Books
    Route::get('books', [BookController::class, 'index']);
    Route::get('books/{book}', [BookController::class, 'show']);
    Route::post('books', [BookController::class, 'store'])->can('admin', Book::class);
    Route::patch('books/{book}', [BookController::class, 'update'])->can('admin', Book::class);
    Route::delete('books/{book}', [BookController::class, 'destroy'])->can('admin', Book::class);

    // Categories
    Route::apiResource('categories', CategoryController::class);

    // Members  
    Route::get('members', [MemberController::class, 'index']);
    Route::get('members/{member}', [MemberController::class, 'show']);
    Route::post('members', [MemberController::class, 'store']);
    Route::patch('members/{member}', [MemberController::class, 'update']);
    Route::delete('members/{member}', [MemberController::class, 'destroy'])->can('admin', 'user');

    // Checkouts
    Route::get('checkouts', [CheckoutController::class, 'index']);
    Route::get('checkouts/{checkout}', [CheckoutController::class, 'show']);
    Route::post('checkouts', [CheckoutController::class, 'store']);
    Route::delete('checkouts/{checkout}', [CheckoutController::class, 'destroy']);
    Route::patch('checkouts/{checkout}', [CheckoutController::class, 'update']);

    // Dashboard Analytics
    Route::get('analytics', [ReportController::class, 'index']);

    // User profile and password
    Route::post('user/profile', [App\Http\Controllers\UserController::class, 'updateProfile']);
    Route::post('user/password', [App\Http\Controllers\UserController::class, 'updatePassword']);
});
