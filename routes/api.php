<?php

use App\Http\Controllers\PoliticianController;
use App\Models\Politician;
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

Route::middleware('throttle:catalog-api')->group(function () {
    Route::get('/politicians', [PoliticianController::class, 'apiIndex']);

    Route::get('/politicians/{politician}', function (Politician $politician, PoliticianController $politicians) {
        return $politicians->show($politician);
    });
});

Route::middleware('auth:sanctum')->get('/reports', [PoliticianController::class, 'pending']);
