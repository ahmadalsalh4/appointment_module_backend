<?php

use Illuminate\Support\Facades\Route;

// API-only project. The root path is here only because Laravel ships
// with a default web.php stub. Returning a string avoids a 500 from a
// missing `welcome` view; the API is mounted at `/api/*`.
Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'status' => 'ok',
], 200));