<?php

use App\Http\Controllers\BookController;
use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return response()->json(['name' => 'Beria CMS API', 'health' => '/api/health']);
});

