<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/supporters', [\App\Http\Controllers\SupporterController::class, 'getAll']);

Route::get('/laporan', [\App\Http\Controllers\SupporterController::class, 'getPendingReviewLaporan']);

Route::post('/laporan/manage', [\App\Http\Controllers\SupporterController::class, 'getAll']);