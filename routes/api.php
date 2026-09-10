<?php

use App\Http\Controllers\Api\Service\RadiologiController;
use App\Http\Controllers\Api\Service\LaboratoriumController;
use App\Http\Controllers\Api\Service\SIMRSController;
use App\Http\Controllers\Api\Service\ReservasiController;
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
Route::prefix('service')
    ->middleware('api.signature')
    ->group(function () {
        Route::get(
            '/gethasilradiologi',
            [RadiologiController::class, 'GetHasil']
        );
         Route::get(
            '/getpemeriksaanbynrm',
            [RadiologiController::class, 'GetPemeriksaanByNRM']
        );
         Route::get(
            '/gethasillaboratorium',
            [LaboratoriumController::class, 'GetHasil']
        );
       
        Route::get(
            '/getdatapasien',
            [SIMRSController::class, 'GetData']
        );
         Route::get(
            '/getdataeduboard',
            [SIMRSController::class, 'masterEduBoard']
        );
           Route::get(
            '/getantreanpasien',
            [SIMRSController::class, 'cekAntreanPasien']
        );
       
          Route::get(
            '/getborlostoi',
            [SIMRSController::class, 'getBorLosToi']
        );
        Route::get(
            '/get-data-rl3-2',
            [SIMRSController::class, 'getDataRL3_2']
        );
        Route::get(
            '/getriwayatreservasi',
            [ReservasiController::class, 'getRiwayatReservasi']
        );
        
        Route::get(
            '/getdatariwayatreservasi',
            [ReservasiController::class, 'getDataRiwayatReservasi']
        );
          Route::get(
            '/getdatariwayatreservasi',
            [ReservasiController::class, 'getDataRiwayatReservasi']
        );
          Route::get(
            '/getlistantrianfarmasi',
            [SIMRSController::class, 'getListAntrianFarmasi']
        );
        Route::get(
            '/getdetailresep',
            [SIMRSController::class, 'getDetailResep']
        );
    });