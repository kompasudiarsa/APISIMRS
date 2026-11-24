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
        // Jhon | 2026-11-24 13:55 WITA | TAMBAH: Lookup log BPJS bertanda tangan, tanpa panggilan/tulis ke BPJS.
        Route::post('/bpjs-log/antrean', [\App\Http\Controllers\Api\Service\BpjsLogLookupController::class, 'antrean'])->middleware('throttle:30,1');
        Route::post('/bpjs-log/surat-kontrol', [\App\Http\Controllers\Api\Service\BpjsLogLookupController::class, 'suratKontrol'])->middleware('throttle:30,1');

        // Jhon | 2026-11-13 11:19 WITA | TAMBAH: endpoint surat mewarisi api.signature.
        // Jhon | 2026-11-18 11:49 WITA | TAMBAH: Riwayat registrasi melalui signature server-to-server.
        // Jhon | 2026-11-18 12:05 WITA | TAMBAH: Data pasien berdasarkan No. CM, tetap dalam grup api.signature.
        // Jhon | 2026-11-18 15:18 WITA | TAMBAH: Master, dokter, slot dan simpan reservasi e-reservasi memakai signature server-to-server.
        // Jhon | 2026-11-18 20:53 WITA | TAMBAH: Pemeriksaan Kios-K read-only lengkap termasuk pembatalan dan check-in.
        // Jhon | 2026-11-19 13:40 WITA | TAMBAH: lookup identitas read-only tetap di bawah api.signature dan throttle.
        // Jhon | 2026-11-19 18:51 WITA | TAMBAH: ringkasan booking signed POST tanpa identitas pada URL.
        Route::post('/kiosk/patient-summary', \App\Http\Controllers\Api\Service\KioskPatientSummaryController::class)->middleware('throttle:60,1');
        Route::post('/kiosk/identify', \App\Http\Controllers\Api\Service\KioskIdentifyController::class)->middleware('throttle:60,1');
        Route::get('/kiosk/reservation-check', \App\Http\Controllers\Api\Service\KioskReservationCheckController::class)->middleware('throttle:60,1');
        Route::get('/reservasi-online/master', [\App\Http\Controllers\Api\Service\OnlineReservationController::class,'master']);
        Route::get('/reservasi-online/dokter', [\App\Http\Controllers\Api\Service\OnlineReservationController::class,'doctors']);
        Route::get('/reservasi-online/slot', [\App\Http\Controllers\Api\Service\OnlineReservationController::class,'slots']);
        Route::post('/reservasi-online', [\App\Http\Controllers\Api\Service\OnlineReservationController::class,'save'])->middleware('throttle:20,1');
        Route::get('/pasien-lama', [SIMRSController::class, 'pasienLama']);
        Route::get('/riwayat-registrasi', [SIMRSController::class, 'riwayatRegistrasi']);
        Route::get('/surat-keterangan', [SIMRSController::class, 'suratKeterangan']);

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
            '/getdatariwayatRI',
            [SIMRSController::class, 'getDataRiwayatRI']
        );
        Route::get(
            '/getriwayatreservasi',
            [ReservasiController::class, 'getRiwayatReservasi']
        );
        
        Route::get(
            '/getdatariwayatreservasi',
            [ReservasiController::class, 'getDataRiwayatReservasi']
        );
        //   Route::get(
        //     '/getdatariwayatreservasi',
        //     [ReservasiController::class, 'getDataRiwayatReservasi']
        // );
         Route::get(
            '/listriwayatregistrasipasien',
            [ReservasiController::class, 'listRiwayatRegistrasiPasien']
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