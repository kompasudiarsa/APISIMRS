<?php

namespace App\Http\Controllers\Api\Service;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class ReservasiController extends Controller
{
    //
    public function getRiwayatReservasi(Request $request, $kdProfile = 1)
    {
        $kdProfile = (int) ($kdProfile ?: 1);

        $noreservasi = trim((string) $request->input('noReservasi', ''));
        $nocmnama = trim((string) $request->input('rm', ''));
        $tgllahir = trim((string) $request->input('tgllahir', ''));


        $jadwalDokter = DB::connection('pgsql')
            ->table('jadwaldokter_m')
            ->selectRaw("
            objectruanganfk,
            objectpegawaifk,
            CAST(tanggal AS DATE) AS tanggal,
            MIN(jammulai) AS jammulai,
            MAX(jamakhir) AS jamakhir
        ")
            ->where('statusenabled', true)
            ->where('kdprofile', $kdProfile)
            ->groupBy(
                'objectruanganfk',
                'objectpegawaifk'
            )
            ->groupByRaw('CAST(tanggal AS DATE)');

        $data = DB::connection('pgsql')
            ->table('antrianpasienregistrasi_t as apr')
            ->leftJoin(
                'pasien_m as pm',
                'pm.id',
                '=',
                'apr.nocmfk'
            )
            ->leftJoin(
                'alamat_m as alm',
                'alm.nocmfk',
                '=',
                'pm.id'
            )

            ->leftJoin(
                'jeniskelamin_m as jk',
                'jk.id',
                '=',
                'pm.objectjeniskelaminfk'
            )

            ->leftJoin(
                'jeniskelamin_m as jks',
                'jks.id',
                '=',
                'apr.objectjeniskelaminfk'
            )

            ->leftJoin(
                'pekerjaan_m as pk',
                'pk.id',
                '=',
                'pm.objectpekerjaanfk'
            )

            ->leftJoin(
                'pendidikan_m as pdd',
                'pdd.id',
                '=',
                'pm.objectpendidikanfk'
            )

            ->leftJoin(
                'ruangan_m as ru',
                'ru.id',
                '=',
                'apr.objectruanganfk'
            )

            ->leftJoin(
                'pegawai_m as pg',
                'pg.id',
                '=',
                'apr.objectpegawaifk'
            )


            ->leftJoinSub(
                $jadwalDokter,
                'jd',
                function ($join) {
                    $join->on(
                        'jd.objectruanganfk',
                        '=',
                        'apr.objectruanganfk'
                    );

                    $join->on(
                        'jd.objectpegawaifk',
                        '=',
                        'apr.objectpegawaifk'
                    );

                    $join->whereRaw("
                    CAST(apr.tanggalreservasi AS DATE)
                    = jd.tanggal
                ");
                }
            )

            ->join(
                'kelompokpasien_m as kps',
                'kps.id',
                '=',
                'apr.objectkelompokpasienfk'
            )


            ->select(
                'apr.norec',

                'pm.nocm',

                'apr.noreservasi',

                'apr.tanggalreservasi',

                DB::raw("
                CASE
                    WHEN jd.jammulai IS NOT NULL
                    THEN
                        jd.jammulai || ' - ' || jd.jamakhir
                    ELSE NULL
                END AS jamreservasi
            "),

                'apr.objectruanganfk',

                'apr.objectpegawaifk',

                'ru.namaruangan',

                'apr.isconfirm',

                'apr.noantrian',

                'apr.noantrianpoli',

                'pg.namalengkap as dokter',

                'pm.id as nocmfk',

                'pk.pekerjaan',

                'pm.noasuransilain',

                'pdd.pendidikan',

                'apr.type',

                'kps.kelompokpasien',

                'apr.objectkelompokpasienfk',

                'ru.objectdepartemenfk',

                'ru.prefixnoantrian',

                'apr.norujukan',

                'apr.nosuratkontrol',

                'ru.id as idruangan',

                'pg.id as iddokter',

                'pg.kddokterbpjs',

                'apr.jenis',


                DB::raw("
                CASE
                    WHEN apr.isconfirm = true
                    THEN 'Confirm'
                    ELSE 'Reservasi'
                END AS status
            "),


                DB::raw("
                CASE
                    WHEN pm.tempatlahir IS NULL
                    THEN apr.tempatlahir
                    ELSE pm.tempatlahir
                END AS tempatlahir
            "),


                DB::raw("
                CASE
                    WHEN pm.noidentitas IS NULL
                    THEN apr.noidentitas
                    ELSE pm.noidentitas
                END AS noidentitas
            "),


                DB::raw("
                CASE
                    WHEN pm.nobpjs IS NULL
                    THEN apr.nobpjs
                    ELSE pm.nobpjs
                END AS nobpjs
            "),


                DB::raw("
                CASE
                    WHEN pm.namapasien IS NULL
                    THEN apr.namapasien
                    ELSE pm.namapasien
                END AS namapasien
            "),


                DB::raw("
                CASE
                    WHEN pm.objectjeniskelaminfk IS NULL
                    THEN apr.objectjeniskelaminfk
                    ELSE pm.objectjeniskelaminfk
                END AS objectjeniskelaminfk
            "),


                DB::raw("
                CASE
                    WHEN pm.nohp IS NULL
                    THEN apr.notelepon
                    ELSE pm.nohp
                END AS notelepon
            "),


                DB::raw("
                CASE
                    WHEN pm.email IS NULL
                    THEN apr.email
                    ELSE pm.email
                END AS email
            "),

                /*
            |--------------------------------------------------------------------------
            | Alamat
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN alm.alamatlengkap IS NULL
                    THEN apr.alamatlengkap
                    ELSE alm.alamatlengkap
                END AS alamatlengkap
            "),

                /*
            |--------------------------------------------------------------------------
            | Kebangsaan
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN pm.objectkebangsaanfk IS NULL
                    THEN apr.objectkebangsaanfk
                    ELSE pm.objectkebangsaanfk
                END AS objectkebangsaanfk
            "),

                /*
            |--------------------------------------------------------------------------
            | Agama
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN pm.objectagamafk IS NULL
                    THEN apr.objectagamafk
                    ELSE pm.objectagamafk
                END AS objectagamafk
            "),

                /*
            |--------------------------------------------------------------------------
            | Jenis Kelamin
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN jk.jeniskelamin IS NULL
                    THEN jks.jeniskelamin
                    ELSE jk.jeniskelamin
                END AS jeniskelamin
            "),

                /*
            |--------------------------------------------------------------------------
            | Tanggal Lahir
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN pm.tgllahir IS NULL
                    THEN to_char(
                        apr.tgllahir,
                        'YYYY-MM-DD'
                    )
                    ELSE to_char(
                        pm.tgllahir,
                        'YYYY-MM-DD'
                    )
                END AS tgllahir
            "),

                'apr.loketkiosk'
            )

            /*
            |--------------------------------------------------------------------------
            | Filter Default
            |--------------------------------------------------------------------------
            */
            ->whereNotNull('apr.noreservasi')

            ->whereNull('apr.noantrian')

            ->where(
                'apr.kdprofile',
                $kdProfile
            )

            ->where(
                'apr.statusenabled',
                true
            );

        /*
        |--------------------------------------------------------------------------
        | Filter Tanggal Lahir
        |--------------------------------------------------------------------------
        */
        if (
            $tgllahir !== '' &&
            $tgllahir !== 'undefined' &&
            $tgllahir !== 'null' &&
            $tgllahir !== 'Invalid date'
        ) {
            $data->where(
                function ($query) use ($tgllahir) {
                    $query
                        ->whereDate(
                            'pm.tgllahir',
                            $tgllahir
                        )
                        ->orWhereDate(
                            'apr.tgllahir',
                            $tgllahir
                        );
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Filter NOCM / Nama
        |--------------------------------------------------------------------------
        |
        | Bisa mencari berdasarkan:
        | - nomor reservasi
        | - nomor rekam medis
        | - NIK
        | - nomor BPJS
        | - nama pasien
        |
        */
        if (
            $nocmnama !== '' &&
            $nocmnama !== 'undefined' &&
            $nocmnama !== 'null'
        ) {
            $data->where(
                function ($query) use ($nocmnama) {
                    $query
                        ->where(
                            'apr.noreservasi',
                            $nocmnama
                        )

                        ->orWhere(
                            'pm.nocm',
                            $nocmnama
                        )

                        ->orWhere(
                            'pm.noidentitas',
                            $nocmnama
                        )

                        ->orWhere(
                            'pm.nobpjs',
                            $nocmnama
                        )

                        ->orWhere(
                            'apr.nobpjs',
                            $nocmnama
                        )

                        ->orWhere(
                            'pm.namapasien',
                            $nocmnama
                        )

                        ->orWhere(
                            'apr.namapasien',
                            $nocmnama
                        );
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Filter Nomor Reservasi
        |--------------------------------------------------------------------------
        */
        if (
            $noreservasi !== '' &&
            $noreservasi !== 'undefined' &&
            $noreservasi !== 'null'
        ) {
            $data->where(
                function ($query) use ($noreservasi) {
                    $query
                        ->where(
                            'apr.noreservasi',
                            $noreservasi
                        )

                        ->orWhere(
                            'pm.nocm',
                            $noreservasi
                        )

                        ->orWhere(
                            'pm.noidentitas',
                            $noreservasi
                        )

                        ->orWhere(
                            'pm.nobpjs',
                            $noreservasi
                        )

                        ->orWhere(
                            'apr.nobpjs',
                            $noreservasi
                        )

                        ->orWhere(
                            'pm.namapasien',
                            $noreservasi
                        )

                        ->orWhere(
                            'apr.namapasien',
                            $noreservasi
                        );
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Order dan Eksekusi
        |--------------------------------------------------------------------------
        */
        $data = $data
            ->orderBy(
                'apr.tanggalreservasi',
                'desc'
            )
            ->orderBy(
                'apr.noantrianpoli',
                'asc'
            )
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */
        $result = [
            'total' => $data->count(),
            'data' => $data,
            'as' => '@epic',
        ];


        return response()->json($result, 200);
    }
    public function listRiwayatRegistrasiPasien(
        Request $request,
        $kdProfile = 1
    ) {
        /*
    |--------------------------------------------------------------------------
    | Parameter
    |--------------------------------------------------------------------------
    */
        $kdProfile = (int) ($kdProfile ?: 1);

        $tanggalHariIni = now(
            'Asia/Makassar'
        )->toDateString();

        $noreservasi = trim(
            (string) $request->input(
                'noReservasi',
                $request->input(
                    'noreservasi',
                    ''
                )
            )
        );

        $nocmnama = trim(
            (string) $request->input(
                'rm',
                ''
            )
        );

        /*
    |--------------------------------------------------------------------------
    | Tanggal Lahir
    |--------------------------------------------------------------------------
    |
    | Mendukung:
    |
    | 23-05-1985
    | 23/05/1985
    | 1985-05-23
    |
    | Semua akan dinormalisasi menjadi:
    |
    | 1985-05-23
    |
    */
        $tgllahirInput = trim(
            (string) $request->input(
                'tanggal_lahir',
                $request->input(
                    'tgllahir',
                    ''
                )
            )
        );

        $tgllahir = null;

        if (
            $tgllahirInput !== ''
            && $tgllahirInput !== 'undefined'
            && $tgllahirInput !== 'null'
            && $tgllahirInput !== 'Invalid date'
        ) {
            try {

                /*
             * Format DD-MM-YYYY
             * Contoh: 23-05-1985
             */
                if (
                    preg_match(
                        '/^\d{2}-\d{2}-\d{4}$/',
                        $tgllahirInput
                    )
                ) {
                    $tgllahir = \Carbon\Carbon::createFromFormat(
                        'd-m-Y',
                        $tgllahirInput
                    )->format('Y-m-d');

                    /*
             * Format DD/MM/YYYY
             * Contoh: 23/05/1985
             */
                } elseif (
                    preg_match(
                        '/^\d{2}\/\d{2}\/\d{4}$/',
                        $tgllahirInput
                    )
                ) {
                    $tgllahir = \Carbon\Carbon::createFromFormat(
                        'd/m/Y',
                        $tgllahirInput
                    )->format('Y-m-d');

                    /*
             * Format YYYY-MM-DD
             * Contoh: 1985-05-23
             */
                } elseif (
                    preg_match(
                        '/^\d{4}-\d{2}-\d{2}$/',
                        $tgllahirInput
                    )
                ) {
                    $tgllahir = \Carbon\Carbon::createFromFormat(
                        'Y-m-d',
                        $tgllahirInput
                    )->format('Y-m-d');
                } else {

                    $tgllahir = \Carbon\Carbon::parse(
                        $tgllahirInput
                    )->format('Y-m-d');
                }
            } catch (\Throwable $e) {

                return response()->json(
                    [
                        'success' => false,
                        'message' =>
                        'Format tanggal lahir tidak valid.',
                        'tanggal_lahir' =>
                        $tgllahirInput,
                    ],
                    422
                );
            }
        }


        /*
    |--------------------------------------------------------------------------
    | Jadwal Dokter
    |--------------------------------------------------------------------------
    */
        $jadwalDokter = DB::connection('pgsql')
            ->table('jadwaldokter_m')
            ->selectRaw("
            objectruanganfk,
            objectpegawaifk,
            CAST(tanggal AS DATE) AS tanggal,
            MIN(jammulai) AS jammulai,
            MAX(jamakhir) AS jamakhir
        ")
            ->where(
                'statusenabled',
                true
            )
            ->where(
                'kdprofile',
                $kdProfile
            )
            ->groupBy(
                'objectruanganfk',
                'objectpegawaifk'
            )
            ->groupByRaw(
                'CAST(tanggal AS DATE)'
            );


        /*
    |--------------------------------------------------------------------------
    | Query Utama
    |--------------------------------------------------------------------------
    */
        $data = DB::connection('pgsql')
            ->table(
                'antrianpasienregistrasi_t as apr'
            )

            /*
        |--------------------------------------------------------------------------
        | PASIENDAFTAR
        |--------------------------------------------------------------------------
        |
        | Relasi:
        |
        | apr.norec
        |     =
        | pdt.antrianpasienregistrasifk
        |
        | Contoh SQL:
        |
        | SELECT *
        | FROM pasiendaftar_t
        | WHERE antrianpasienregistrasifk =
        | '8666e327-85fa-435b-9834-d5b3e4c577d0'
        |
        */
            ->join(
                'pasiendaftar_t as pdt',
                'pdt.antrianpasienregistrasifk',
                '=',
                'apr.norec'
            )

            /*
        |--------------------------------------------------------------------------
        | Pasien
        |--------------------------------------------------------------------------
        */
            ->leftJoin(
                'pasien_m as pm',
                'pm.id',
                '=',
                'apr.nocmfk'
            )

            /*
        |--------------------------------------------------------------------------
        | Alamat
        |--------------------------------------------------------------------------
        */
            ->leftJoin(
                'alamat_m as alm',
                'alm.nocmfk',
                '=',
                'pm.id'
            )

            /*
        |--------------------------------------------------------------------------
        | Jenis Kelamin
        |--------------------------------------------------------------------------
        */
            ->leftJoin(
                'jeniskelamin_m as jk',
                'jk.id',
                '=',
                'pm.objectjeniskelaminfk'
            )

            ->leftJoin(
                'jeniskelamin_m as jks',
                'jks.id',
                '=',
                'apr.objectjeniskelaminfk'
            )

            /*
        |--------------------------------------------------------------------------
        | Pekerjaan
        |--------------------------------------------------------------------------
        */
            ->leftJoin(
                'pekerjaan_m as pk',
                'pk.id',
                '=',
                'pm.objectpekerjaanfk'
            )

            /*
        |--------------------------------------------------------------------------
        | Pendidikan
        |--------------------------------------------------------------------------
        */
            ->leftJoin(
                'pendidikan_m as pdd',
                'pdd.id',
                '=',
                'pm.objectpendidikanfk'
            )

            /*
        |--------------------------------------------------------------------------
        | Ruangan
        |--------------------------------------------------------------------------
        */
            ->leftJoin(
                'ruangan_m as ru',
                'ru.id',
                '=',
                'apr.objectruanganfk'
            )

            /*
        |--------------------------------------------------------------------------
        | Dokter
        |--------------------------------------------------------------------------
        */
            ->leftJoin(
                'pegawai_m as pg',
                'pg.id',
                '=',
                'apr.objectpegawaifk'
            )

            /*
        |--------------------------------------------------------------------------
        | Jadwal Dokter
        |--------------------------------------------------------------------------
        */
            ->leftJoinSub(
                $jadwalDokter,
                'jd',
                function ($join) {

                    $join->on(
                        'jd.objectruanganfk',
                        '=',
                        'apr.objectruanganfk'
                    );

                    $join->on(
                        'jd.objectpegawaifk',
                        '=',
                        'apr.objectpegawaifk'
                    );

                    $join->whereRaw("
                    CAST(
                        apr.tanggalreservasi
                        AS DATE
                    ) = jd.tanggal
                ");
                }
            )

            /*
        |--------------------------------------------------------------------------
        | Kelompok Pasien
        |--------------------------------------------------------------------------
        */
            ->join(
                'kelompokpasien_m as kps',
                'kps.id',
                '=',
                'apr.objectkelompokpasienfk'
            )

            /*
        |--------------------------------------------------------------------------
        | SELECT
        |--------------------------------------------------------------------------
        */
            ->select(

                /*
            |--------------------------------------------------------------------------
            | Reservasi
            |--------------------------------------------------------------------------
            */
                'apr.norec',

                'apr.noreservasi',

                'apr.tanggalreservasi',


                /*
            |--------------------------------------------------------------------------
            | PASIENDAFTAR
            |--------------------------------------------------------------------------
            |
            | Field penting untuk modul obat:
            |
            | norec_pd
            | noregistrasi
            |
            */
                'pdt.norec as norec_pd',

                'pdt.noregistrasi',

                'pdt.tglregistrasi',


                /*
            |--------------------------------------------------------------------------
            | Pasien
            |--------------------------------------------------------------------------
            */
                'pm.nocm',

                'pm.id as nocmfk',


                /*
            |--------------------------------------------------------------------------
            | Jadwal
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN jd.jammulai IS NOT NULL
                    THEN
                        jd.jammulai
                        || ' - '
                        || jd.jamakhir

                    ELSE NULL
                END AS jamreservasi
            "),


                /*
            |--------------------------------------------------------------------------
            | Ruangan
            |--------------------------------------------------------------------------
            */
                'apr.objectruanganfk',

                'ru.namaruangan',

                'ru.objectdepartemenfk',

                'ru.prefixnoantrian',

                'ru.id as idruangan',


                /*
            |--------------------------------------------------------------------------
            | Dokter
            |--------------------------------------------------------------------------
            */
                'apr.objectpegawaifk',

                'pg.id as iddokter',

                'pg.namalengkap as dokter',

                'pg.kddokterbpjs',


                /*
            |--------------------------------------------------------------------------
            | Data Reservasi
            |--------------------------------------------------------------------------
            */
                'apr.isconfirm',

                'apr.noantrian',

                'apr.noantrianpoli',

                'apr.type',

                'apr.jenis',

                'apr.norujukan',

                'apr.nosuratkontrol',

                'apr.loketkiosk',


                /*
            |--------------------------------------------------------------------------
            | Kelompok Pasien
            |--------------------------------------------------------------------------
            */
                'kps.kelompokpasien',

                'apr.objectkelompokpasienfk',


                /*
            |--------------------------------------------------------------------------
            | Pekerjaan / Pendidikan
            |--------------------------------------------------------------------------
            */
                'pk.pekerjaan',

                'pm.noasuransilain',

                'pdd.pendidikan',


                /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN CAST(
                        apr.tanggalreservasi
                        AS DATE
                    ) = DATE '" . $tanggalHariIni . "'

                    THEN 'Hari Ini'

                    ELSE 'Sudah Dilayani'
                END AS status
            "),


                /*
            |--------------------------------------------------------------------------
            | Tempat Lahir
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN pm.tempatlahir IS NULL
                    THEN apr.tempatlahir

                    ELSE pm.tempatlahir
                END AS tempatlahir
            "),


                /*
            |--------------------------------------------------------------------------
            | NIK
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN pm.noidentitas IS NULL
                    THEN apr.noidentitas

                    ELSE pm.noidentitas
                END AS noidentitas
            "),


                /*
            |--------------------------------------------------------------------------
            | BPJS
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN pm.nobpjs IS NULL
                    THEN apr.nobpjs

                    ELSE pm.nobpjs
                END AS nobpjs
            "),


                /*
            |--------------------------------------------------------------------------
            | Nama Pasien
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN pm.namapasien IS NULL
                    THEN apr.namapasien

                    ELSE pm.namapasien
                END AS namapasien
            "),


                /*
            |--------------------------------------------------------------------------
            | Object Jenis Kelamin
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN pm.objectjeniskelaminfk
                         IS NULL
                    THEN apr.objectjeniskelaminfk

                    ELSE pm.objectjeniskelaminfk
                END AS objectjeniskelaminfk
            "),


                /*
            |--------------------------------------------------------------------------
            | Telepon
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN pm.nohp IS NULL
                    THEN apr.notelepon

                    ELSE pm.nohp
                END AS notelepon
            "),


                /*
            |--------------------------------------------------------------------------
            | Email
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN pm.email IS NULL
                    THEN apr.email

                    ELSE pm.email
                END AS email
            "),


                /*
            |--------------------------------------------------------------------------
            | Alamat
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN alm.alamatlengkap IS NULL
                    THEN apr.alamatlengkap

                    ELSE alm.alamatlengkap
                END AS alamatlengkap
            "),


                /*
            |--------------------------------------------------------------------------
            | Kebangsaan
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN pm.objectkebangsaanfk
                         IS NULL
                    THEN apr.objectkebangsaanfk

                    ELSE pm.objectkebangsaanfk
                END AS objectkebangsaanfk
            "),


                /*
            |--------------------------------------------------------------------------
            | Agama
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN pm.objectagamafk IS NULL
                    THEN apr.objectagamafk

                    ELSE pm.objectagamafk
                END AS objectagamafk
            "),


                /*
            |--------------------------------------------------------------------------
            | Jenis Kelamin
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN jk.jeniskelamin IS NULL
                    THEN jks.jeniskelamin

                    ELSE jk.jeniskelamin
                END AS jeniskelamin
            "),


                /*
            |--------------------------------------------------------------------------
            | Tanggal Lahir
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN pm.tgllahir IS NULL
                    THEN to_char(
                        apr.tgllahir,
                        'YYYY-MM-DD'
                    )

                    ELSE to_char(
                        pm.tgllahir,
                        'YYYY-MM-DD'
                    )
                END AS tgllahir
            ")
            )


            /*
        |--------------------------------------------------------------------------
        | FILTER DEFAULT
        |--------------------------------------------------------------------------
        |
        | Tidak lagi memakai:
        |
        | whereNotNull('apr.noantrian')
        |
        | Karena INNER JOIN pasiendaftar_t sudah memastikan bahwa pasien
        | memang sudah menjadi registrasi.
        |
        */

            /*
         * Hari ini dan riwayat sebelumnya.
         * Tanggal yang akan datang tidak ditampilkan.
         */
            ->whereDate(
                'apr.tanggalreservasi',
                '<=',
                $tanggalHariIni
            )

            ->where(
                'apr.kdprofile',
                $kdProfile
            )

            ->where(
                'apr.statusenabled',
                true
            );


        /*
    |--------------------------------------------------------------------------
    | Filter Tanggal Lahir
    |--------------------------------------------------------------------------
    |
    | $tgllahir pada titik ini selalu YYYY-MM-DD.
    |
    | Contoh input:
    |
    | 23-05-1985
    |
    | sudah berubah menjadi:
    |
    | 1985-05-23
    |
    */
        if ($tgllahir !== null) {

            $data->where(
                function ($query) use ($tgllahir) {

                    $query
                        ->whereDate(
                            'pm.tgllahir',
                            '=',
                            $tgllahir
                        )

                        ->orWhereDate(
                            'apr.tgllahir',
                            '=',
                            $tgllahir
                        );
                }
            );
        }


        /*
    |--------------------------------------------------------------------------
    | Filter RM / Identitas Pasien
    |--------------------------------------------------------------------------
    */
        if (
            $nocmnama !== ''
            && $nocmnama !== 'undefined'
            && $nocmnama !== 'null'
        ) {

            $data->where(
                function ($query) use ($nocmnama) {

                    $query
                        /*
                     * Nomor Reservasi
                     */
                        ->where(
                            'apr.noreservasi',
                            $nocmnama
                        )

                        /*
                     * Nomor RM
                     */
                        ->orWhere(
                            'pm.nocm',
                            $nocmnama
                        )

                        /*
                     * NIK
                     */
                        ->orWhere(
                            'pm.noidentitas',
                            $nocmnama
                        )

                        /*
                     * BPJS dari pasien
                     */
                        ->orWhere(
                            'pm.nobpjs',
                            $nocmnama
                        )

                        /*
                     * BPJS dari reservasi
                     */
                        ->orWhere(
                            'apr.nobpjs',
                            $nocmnama
                        )

                        /*
                     * Nama Pasien
                     */
                        ->orWhere(
                            'pm.namapasien',
                            $nocmnama
                        )

                        ->orWhere(
                            'apr.namapasien',
                            $nocmnama
                        )

                        /*
                     * Nomor Registrasi
                     */
                        ->orWhere(
                            'pdt.noregistrasi',
                            $nocmnama
                        );
                }
            );
        }


        /*
    |--------------------------------------------------------------------------
    | Filter Nomor Reservasi
    |--------------------------------------------------------------------------
    */
        if (
            $noreservasi !== ''
            && $noreservasi !== 'undefined'
            && $noreservasi !== 'null'
        ) {

            $data->where(
                'apr.noreservasi',
                $noreservasi
            );
        }


        /*
    |--------------------------------------------------------------------------
    | Order
    |--------------------------------------------------------------------------
    |
    | Yang terbaru ditampilkan terlebih dahulu.
    |
    */
        $data = $data
            ->orderBy(
                'apr.tanggalreservasi',
                'desc'
            )

            ->orderBy(
                'pdt.tglregistrasi',
                'desc'
            )

            ->get();


        /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */
        $result = [
            'total' => $data->count(),

            'data' => $data,

            'as' => '@epic',
        ];


        return response()->json(
            $result,
            200
        );
    }

    // public function getDataRiwayatReservasi(Request $request, $kdProfile = 1)
    // {
    //     $kdProfile = (int) ($kdProfile ?: 1);

    //     $noreservasi = trim((string) $request->input('noReservasi', ''));
    //     $nocmnama = trim((string) $request->input('rm', ''));
    //     $tgllahir = trim((string) $request->input('tgllahir', ''));

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Tanggal hari ini
    //     |--------------------------------------------------------------------------
    //     */
    //     $tanggalHariIni = now()->format('Y-m-d');

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Jadwal Dokter
    //     |--------------------------------------------------------------------------
    //     */
    //     $jadwalDokter = DB::connection('pgsql')
    //         ->table('jadwaldokter_m')
    //         ->selectRaw("
    //         objectruanganfk,
    //         objectpegawaifk,
    //         CAST(tanggal AS DATE) AS tanggal,
    //         MIN(jammulai) AS jammulai,
    //         MAX(jamakhir) AS jamakhir
    //     ")
    //         ->where('statusenabled', true)
    //         ->where('kdprofile', $kdProfile)
    //         ->groupBy(
    //             'objectruanganfk',
    //             'objectpegawaifk'
    //         )
    //         ->groupByRaw('CAST(tanggal AS DATE)');

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Query Reservasi
    //     |--------------------------------------------------------------------------
    //     */
    //     $data = DB::connection('pgsql')
    //         ->table('antrianpasienregistrasi_t as apr')

    //         ->leftJoin(
    //             'pasien_m as pm',
    //             'pm.id',
    //             '=',
    //             'apr.nocmfk'
    //         )

    //         ->leftJoin(
    //             'alamat_m as alm',
    //             'alm.nocmfk',
    //             '=',
    //             'pm.id'
    //         )

    //         ->leftJoin(
    //             'jeniskelamin_m as jk',
    //             'jk.id',
    //             '=',
    //             'pm.objectjeniskelaminfk'
    //         )

    //         ->leftJoin(
    //             'jeniskelamin_m as jks',
    //             'jks.id',
    //             '=',
    //             'apr.objectjeniskelaminfk'
    //         )

    //         ->leftJoin(
    //             'pekerjaan_m as pk',
    //             'pk.id',
    //             '=',
    //             'pm.objectpekerjaanfk'
    //         )

    //         ->leftJoin(
    //             'pendidikan_m as pdd',
    //             'pdd.id',
    //             '=',
    //             'pm.objectpendidikanfk'
    //         )

    //         ->leftJoin(
    //             'ruangan_m as ru',
    //             'ru.id',
    //             '=',
    //             'apr.objectruanganfk'
    //         )

    //         ->leftJoin(
    //             'pegawai_m as pg',
    //             'pg.id',
    //             '=',
    //             'apr.objectpegawaifk'
    //         )

    //         ->leftJoinSub(
    //             $jadwalDokter,
    //             'jd',
    //             function ($join) {
    //                 $join->on(
    //                     'jd.objectruanganfk',
    //                     '=',
    //                     'apr.objectruanganfk'
    //                 );

    //                 $join->on(
    //                     'jd.objectpegawaifk',
    //                     '=',
    //                     'apr.objectpegawaifk'
    //                 );

    //                 $join->whereRaw("
    //                 CAST(apr.tanggalreservasi AS DATE)
    //                 = jd.tanggal
    //             ");
    //             }
    //         )

    //         ->join(
    //             'kelompokpasien_m as kps',
    //             'kps.id',
    //             '=',
    //             'apr.objectkelompokpasienfk'
    //         )

    //         ->select(
    //             'apr.norec',
    //             'apr.nocmfk',

    //             'pm.nocm',

    //             'apr.noreservasi',

    //             /*
    //         |--------------------------------------------------------------------------
    //         | Tanggal tanpa jam
    //         |--------------------------------------------------------------------------
    //         */
    //             DB::raw("
    //             CAST(apr.tanggalreservasi AS DATE)
    //             AS tanggalreservasi
    //         "),

    //             /*
    //         |--------------------------------------------------------------------------
    //         | Jam layanan dokter
    //         |--------------------------------------------------------------------------
    //         */
    //             DB::raw("
    //             CASE
    //                 WHEN jd.jammulai IS NOT NULL
    //                 THEN jd.jammulai || ' - ' || jd.jamakhir
    //                 ELSE NULL
    //             END AS jamreservasi
    //         "),

    //             'apr.objectruanganfk',
    //             'apr.objectpegawaifk',

    //             'ru.namaruangan',

    //             /*
    //             |--------------------------------------------------------------------------
    //             | Kode Poli Subspesialis BPJS
    //             |--------------------------------------------------------------------------
    //             */
    //             'ru.kdsubspesialisbpjs as kodepolisubspesialis',

    //             'apr.isconfirm',

    //             'apr.noantrian',

    //             'apr.noantrianpoli',

    //             'pg.namalengkap as dokter',

    //             'pm.id as patient_id',

    //             'pk.pekerjaan',

    //             'pm.noasuransilain',

    //             'pdd.pendidikan',

    //             'apr.type',

    //             'kps.kelompokpasien',

    //             'apr.objectkelompokpasienfk',

    //             'ru.objectdepartemenfk',

    //             'ru.prefixnoantrian',

    //             'apr.norujukan',

    //             'apr.nosuratkontrol',

    //             'ru.id as idruangan',

    //             'pg.id as iddokter',

    //             'pg.kddokterbpjs',

    //             'apr.jenis',

    //             /*
    //         |--------------------------------------------------------------------------
    //         | Status reservasi
    //         |--------------------------------------------------------------------------
    //         */
    //             DB::raw("
    //             CASE
    //                 WHEN apr.isconfirm = true
    //                 THEN 'Confirm'
    //                 ELSE 'Reservasi'
    //             END AS status
    //         "),

    //             /*
    //         |--------------------------------------------------------------------------
    //         | Tempat lahir
    //         |--------------------------------------------------------------------------
    //         */
    //             DB::raw("
    //             CASE
    //                 WHEN pm.tempatlahir IS NULL
    //                 THEN apr.tempatlahir
    //                 ELSE pm.tempatlahir
    //             END AS tempatlahir
    //         "),

    //             /*
    //         |--------------------------------------------------------------------------
    //         | NIK
    //         |--------------------------------------------------------------------------
    //         */
    //             DB::raw("
    //             CASE
    //                 WHEN pm.noidentitas IS NULL
    //                 THEN apr.noidentitas
    //                 ELSE pm.noidentitas
    //             END AS noidentitas
    //         "),

    //             /*
    //         |--------------------------------------------------------------------------
    //         | BPJS
    //         |--------------------------------------------------------------------------
    //         */
    //             DB::raw("
    //             CASE
    //                 WHEN pm.nobpjs IS NULL
    //                 THEN apr.nobpjs
    //                 ELSE pm.nobpjs
    //             END AS nobpjs
    //         "),

    //             /*
    //         |--------------------------------------------------------------------------
    //         | Nama Pasien
    //         |--------------------------------------------------------------------------
    //         */
    //             DB::raw("
    //             CASE
    //                 WHEN pm.namapasien IS NULL
    //                 THEN apr.namapasien
    //                 ELSE pm.namapasien
    //             END AS namapasien
    //         "),

    //             /*
    //         |--------------------------------------------------------------------------
    //         | Jenis Kelamin FK
    //         |--------------------------------------------------------------------------
    //         */
    //             DB::raw("
    //             CASE
    //                 WHEN pm.objectjeniskelaminfk IS NULL
    //                 THEN apr.objectjeniskelaminfk
    //                 ELSE pm.objectjeniskelaminfk
    //             END AS objectjeniskelaminfk
    //         "),

    //             /*
    //         |--------------------------------------------------------------------------
    //         | Telepon
    //         |--------------------------------------------------------------------------
    //         */
    //             DB::raw("
    //             CASE
    //                 WHEN pm.nohp IS NULL
    //                 THEN apr.notelepon
    //                 ELSE pm.nohp
    //             END AS notelepon
    //         "),

    //             /*
    //         |--------------------------------------------------------------------------
    //         | Email
    //         |--------------------------------------------------------------------------
    //         */
    //             DB::raw("
    //             CASE
    //                 WHEN pm.email IS NULL
    //                 THEN apr.email
    //                 ELSE pm.email
    //             END AS email
    //         "),

    //             /*
    //         |--------------------------------------------------------------------------
    //         | Alamat
    //         |--------------------------------------------------------------------------
    //         */
    //             DB::raw("
    //             CASE
    //                 WHEN alm.alamatlengkap IS NULL
    //                 THEN apr.alamatlengkap
    //                 ELSE alm.alamatlengkap
    //             END AS alamatlengkap
    //         "),

    //             /*
    //         |--------------------------------------------------------------------------
    //         | Kebangsaan
    //         |--------------------------------------------------------------------------
    //         */
    //             DB::raw("
    //             CASE
    //                 WHEN pm.objectkebangsaanfk IS NULL
    //                 THEN apr.objectkebangsaanfk
    //                 ELSE pm.objectkebangsaanfk
    //             END AS objectkebangsaanfk
    //         "),

    //             /*
    //         |--------------------------------------------------------------------------
    //         | Agama
    //         |--------------------------------------------------------------------------
    //         */
    //             DB::raw("
    //             CASE
    //                 WHEN pm.objectagamafk IS NULL
    //                 THEN apr.objectagamafk
    //                 ELSE pm.objectagamafk
    //             END AS objectagamafk
    //         "),

    //             /*
    //         |--------------------------------------------------------------------------
    //         | Jenis Kelamin
    //         |--------------------------------------------------------------------------
    //         */
    //             DB::raw("
    //             CASE
    //                 WHEN jk.jeniskelamin IS NULL
    //                 THEN jks.jeniskelamin
    //                 ELSE jk.jeniskelamin
    //             END AS jeniskelamin
    //         "),

    //             /*
    //         |--------------------------------------------------------------------------
    //         | Tanggal Lahir
    //         |--------------------------------------------------------------------------
    //         */
    //             DB::raw("
    //             CASE
    //                 WHEN pm.tgllahir IS NULL
    //                 THEN to_char(
    //                     apr.tgllahir,
    //                     'YYYY-MM-DD'
    //                 )
    //                 ELSE to_char(
    //                     pm.tgllahir,
    //                     'YYYY-MM-DD'
    //                 )
    //             END AS tgllahir
    //         "),

    //             'apr.loketkiosk'
    //         )

    //         /*
    //     |--------------------------------------------------------------------------
    //     | Filter Dasar
    //     |--------------------------------------------------------------------------
    //     */
    //         ->whereNotNull('apr.noreservasi')

    //         ->where(
    //             'apr.noreservasi',
    //             '!=',
    //             '-'
    //         )

    //         ->where(
    //             'apr.kdprofile',
    //             $kdProfile
    //         )

    //         ->where(
    //             'apr.statusenabled',
    //             true
    //         )

    //         /*
    //     |--------------------------------------------------------------------------
    //     | Jangan tampilkan reservasi yang pelayanannya sudah selesai
    //     |--------------------------------------------------------------------------
    //     |
    //     | status_pasien dan label_statusperiksa bukan kolom database.
    //     | Keduanya dibentuk dari:
    //     | - pd.tglclosing       => Sudah Closing / Selesai Dilayani
    //     | - pd.isasmed          => Selesai Dilayani
    //     | - apd.iscppt_dokter   => Selesai Dilayani
    //     |
    //     | Karena itu filter dilakukan terhadap sumber statusnya.
    //     |
    //     */
    //         ->whereNotExists(function ($query) {
    //             $query
    //                 ->select(DB::raw(1))
    //                 ->from('pasiendaftar_t as pd_done')
    //                 ->whereColumn(
    //                     'pd_done.antrianpasienregistrasifk',
    //                     'apr.norec'
    //                 )
    //                 ->where(
    //                     'pd_done.statusenabled',
    //                     true
    //                 )
    //                 ->where(function ($status) {
    //                     $status
    //                         ->whereNotNull(
    //                             'pd_done.tglclosing'
    //                         )
    //                         ->orWhere(
    //                             'pd_done.isasmed',
    //                             true
    //                         );
    //                 });
    //         })

    //         ->whereNotExists(function ($query) {
    //             $query
    //                 ->select(DB::raw(1))
    //                 ->from('pasiendaftar_t as pd_cppt')
    //                 ->join(
    //                     'antrianpasiendiperiksa_t as apd_done',
    //                     'apd_done.noregistrasifk',
    //                     '=',
    //                     'pd_cppt.norec'
    //                 )
    //                 ->whereColumn(
    //                     'pd_cppt.antrianpasienregistrasifk',
    //                     'apr.norec'
    //                 )
    //                 ->where(
    //                     'pd_cppt.statusenabled',
    //                     true
    //                 )
    //                 ->where(
    //                     'apd_done.statusenabled',
    //                     true
    //                 )
    //                 ->where(function ($status) {
    //                     $status
    //                         ->where(
    //                             'apd_done.iscppt_dokter',
    //                             true
    //                         )
    //                         ->orWhereRaw(
    //                             "LOWER(COALESCE(apd_done.status, '')) = 'selesai'"
    //                         );
    //                 });
    //         })

    //         /*
    //     |--------------------------------------------------------------------------
    //     | Hari ini dan berikutnya
    //     |--------------------------------------------------------------------------
    //     */
    //         ->whereRaw(
    //             'CAST(apr.tanggalreservasi AS DATE) >= ?',
    //             [$tanggalHariIni]
    //         );

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Filter tanggal lahir
    //     |--------------------------------------------------------------------------
    //     */
    //     if (
    //         $tgllahir !== '' &&
    //         $tgllahir !== 'undefined' &&
    //         $tgllahir !== 'null' &&
    //         $tgllahir !== 'Invalid date'
    //     ) {
    //         $data->where(function ($query) use ($tgllahir) {
    //             $query
    //                 ->whereDate(
    //                     'pm.tgllahir',
    //                     $tgllahir
    //                 )
    //                 ->orWhereDate(
    //                     'apr.tgllahir',
    //                     $tgllahir
    //                 );
    //         });
    //     }

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Filter RM / NIK / BPJS / Nama
    //     |--------------------------------------------------------------------------
    //     */
    //     if (
    //         $nocmnama !== '' &&
    //         $nocmnama !== 'undefined' &&
    //         $nocmnama !== 'null'
    //     ) {
    //         $data->where(function ($query) use ($nocmnama) {
    //             $query
    //                 ->where(
    //                     'apr.noreservasi',
    //                     $nocmnama
    //                 )

    //                 ->orWhere(
    //                     'pm.nocm',
    //                     $nocmnama
    //                 )

    //                 ->orWhere(
    //                     'pm.noidentitas',
    //                     $nocmnama
    //                 )

    //                 ->orWhere(
    //                     'pm.nobpjs',
    //                     $nocmnama
    //                 )

    //                 ->orWhere(
    //                     'apr.nobpjs',
    //                     $nocmnama
    //                 )

    //                 ->orWhere(
    //                     'pm.namapasien',
    //                     $nocmnama
    //                 )

    //                 ->orWhere(
    //                     'apr.namapasien',
    //                     $nocmnama
    //                 );
    //         });
    //     }

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Filter nomor reservasi
    //     |--------------------------------------------------------------------------
    //     */
    //     if (
    //         $noreservasi !== '' &&
    //         $noreservasi !== 'undefined' &&
    //         $noreservasi !== 'null'
    //     ) {
    //         $data->where(function ($query) use ($noreservasi) {
    //             $query
    //                 ->where(
    //                     'apr.noreservasi',
    //                     $noreservasi
    //                 )

    //                 ->orWhere(
    //                     'pm.nocm',
    //                     $noreservasi
    //                 )

    //                 ->orWhere(
    //                     'pm.noidentitas',
    //                     $noreservasi
    //                 )

    //                 ->orWhere(
    //                     'pm.nobpjs',
    //                     $noreservasi
    //                 )

    //                 ->orWhere(
    //                     'apr.nobpjs',
    //                     $noreservasi
    //                 );
    //         });
    //     }

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Ambil hanya 1 reservasi terdekat
    //     |--------------------------------------------------------------------------
    //     */
    //     $reservasi = $data
    //         ->orderByRaw(
    //             'CAST(apr.tanggalreservasi AS DATE) ASC'
    //         )
    //         ->orderBy(
    //             'apr.tanggalreservasi',
    //             'asc'
    //         )
    //         ->first();

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Tidak ada reservasi
    //     |--------------------------------------------------------------------------
    //     */
    //     if (!$reservasi) {
    //         return response()->json([
    //             'total' => 0,
    //             'data' => [],
    //             'as' => '@epic',
    //         ], 200);
    //     }

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Cari Registrasi / PD
    //     |--------------------------------------------------------------------------
    //     |
    //     | Prioritas:
    //     | 1. PD yang langsung terhubung dengan APR
    //     | 2. Fallback PD pasien pada tanggal yang sama
    //     |
    //     */

    //     $tanggalReservasi = (string) $reservasi->tanggalreservasi;

    //     $registrasi = DB::connection('pgsql')
    //         ->table('pasiendaftar_t as pd')
    //         ->select(
    //             'pd.norec as norec_pd',
    //             'pd.noregistrasi',
    //             'pd.nocmfk',
    //             'pd.tglregistrasi',
    //             'pd.tglclosing',
    //             'pd.exam_started_at',
    //             'pd.isasmed',
    //             'pd.iscppt',
    //             'pd.isaskeprj',
    //             'pd.istindakan',
    //             'pd.objectpegawaifk',
    //             'pd.objectruanganlastfk',
    //             'pd.antrianpasienregistrasifk'
    //         )
    //         ->where(
    //             'pd.statusenabled',
    //             true
    //         )
    //         ->where(
    //             'pd.antrianpasienregistrasifk',
    //             $reservasi->norec
    //         )
    //         ->orderByDesc(
    //             'pd.tglregistrasi'
    //         )
    //         ->first();

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Fallback registrasi langsung
    //     |--------------------------------------------------------------------------
    //     |
    //     | Ini mengikuti konsep cekAntreanPasien() yang juga dapat mengenali
    //     | registrasi pasien walaupun tidak mempunyai FK APR.
    //     |
    //     */
    //     if (!$registrasi) {
    //         $registrasi = DB::connection('pgsql')
    //             ->table('pasiendaftar_t as pd')
    //             ->select(
    //                 'pd.norec as norec_pd',
    //                 'pd.noregistrasi',
    //                 'pd.nocmfk',
    //                 'pd.tglregistrasi',
    //                 'pd.tglclosing',
    //                 'pd.exam_started_at',
    //                 'pd.isasmed',
    //                 'pd.iscppt',
    //                 'pd.isaskeprj',
    //                 'pd.istindakan',
    //                 'pd.objectpegawaifk',
    //                 'pd.objectruanganlastfk',
    //                 'pd.antrianpasienregistrasifk'
    //             )
    //             ->where(
    //                 'pd.statusenabled',
    //                 true
    //             )
    //             ->where(
    //                 'pd.nocmfk',
    //                 $reservasi->nocmfk
    //             )
    //             ->whereDate(
    //                 'pd.tglregistrasi',
    //                 $tanggalReservasi
    //             )
    //             ->orderByDesc(
    //                 'pd.tglregistrasi'
    //             )
    //             ->first();
    //     }

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Cari APD
    //     |--------------------------------------------------------------------------
    //     */
    //     $apd = null;

    //     if ($registrasi) {
    //         $ruanganReservasi = (int) (
    //             $reservasi->objectruanganfk ?? 0
    //         );

    //         $apd = DB::connection('pgsql')
    //             ->table('antrianpasiendiperiksa_t as apd')

    //             ->select(
    //                 'apd.norec as norec_apd',
    //                 'apd.noregistrasifk',
    //                 'apd.objectruanganfk',
    //                 'apd.objectpegawaifk',

    //                 'apd.noantrian',

    //                 'apd.status',

    //                 'apd.iskonsul as konsul',

    //                 'apd.tglregistrasi',

    //                 'apd.tglkeluar',

    //                 'apd.iscppt_perawat',

    //                 'apd.iscppt_dokter'
    //             )

    //             ->where(
    //                 'apd.noregistrasifk',
    //                 $registrasi->norec_pd
    //             )

    //             ->where(
    //                 'apd.statusenabled',
    //                 true
    //             )

    //             /*
    //         |--------------------------------------------------------------------------
    //         | Prioritaskan APD aktif
    //         |--------------------------------------------------------------------------
    //         */
    //             ->orderByRaw("
    //             CASE
    //                 WHEN apd.tglkeluar IS NULL
    //                      AND LOWER(
    //                          COALESCE(apd.status, '')
    //                      ) NOT LIKE '%selesai%'
    //                 THEN 0
    //                 ELSE 1
    //             END ASC
    //         ")

    //             /*
    //         |--------------------------------------------------------------------------
    //         | Prioritaskan ruangan reservasi
    //         |--------------------------------------------------------------------------
    //         */
    //             ->orderByRaw(
    //                 '
    //             CASE
    //                 WHEN apd.objectruanganfk = ?
    //                 THEN 0
    //                 ELSE 1
    //             END ASC
    //             ',
    //                 [
    //                     $ruanganReservasi > 0
    //                         ? $ruanganReservasi
    //                         : -1,
    //                 ]
    //             )

    //             ->orderByDesc(
    //                 'apd.tglregistrasi'
    //             )

    //             ->first();
    //     }

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Helper Boolean
    //     |--------------------------------------------------------------------------
    //     |
    //     | Mengikuti fungsi cekAntreanPasien karena beberapa field dapat berisi:
    //     |
    //     | true / false
    //     | 1 / 0
    //     | t / f
    //     | string
    //     |
    //     */
    //     $nilaiAktif = static function ($value): bool {
    //         if ($value === null) {
    //             return false;
    //         }

    //         if (is_bool($value)) {
    //             return $value;
    //         }

    //         if (
    //             is_int($value) ||
    //             is_float($value)
    //         ) {
    //             return (int) $value !== 0;
    //         }

    //         $value = strtolower(
    //             trim((string) $value)
    //         );

    //         return !in_array(
    //             $value,
    //             [
    //                 '',
    //                 '0',
    //                 'false',
    //                 'f',
    //                 'no',
    //                 'n',
    //                 'null',
    //             ],
    //             true
    //         );
    //     };

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Tentukan Status Pasien
    //     |--------------------------------------------------------------------------
    //     |
    //     | Urutan mengikuti cekAntreanPasien:
    //     |
    //     | 1. Sudah Closing
    //     | 2. Selesai
    //     | 3. Sedang Diperiksa
    //     | 4. Sudah Teregistrasi
    //     | 5. Menunggu Pelayanan
    //     |
    //     */

    //     if (!$registrasi) {

    //         /*
    //     |--------------------------------------------------------------------------
    //     | Belum masuk pasiendaftar_t
    //     |--------------------------------------------------------------------------
    //     */
    //         $statusPasien = 'Belum Teregistrasi';
    //         $labelStatus = 'Belum Teregistrasi';
    //         $classStatus = 'is-info';
    //     } else {

    //         $tglClosing = data_get(
    //             $registrasi,
    //             'tglclosing'
    //         );

    //         $isTindakan = $nilaiAktif(
    //             data_get(
    //                 $registrasi,
    //                 'istindakan'
    //             )
    //         );

    //         $isAsmed = $nilaiAktif(
    //             data_get(
    //                 $registrasi,
    //                 'isasmed'
    //             )
    //         );

    //         $isCpptDokter = $nilaiAktif(
    //             data_get(
    //                 $apd,
    //                 'iscppt_dokter'
    //             )
    //         );

    //         $isKonsul = $nilaiAktif(
    //             data_get(
    //                 $apd,
    //                 'konsul'
    //             )
    //         );

    //         /*
    //     |--------------------------------------------------------------------------
    //     | 1. Sudah Closing
    //     |--------------------------------------------------------------------------
    //     */
    //         if (!empty($tglClosing)) {

    //             $statusPasien = 'Selesai Dilayani';
    //             $labelStatus = 'Sudah Closing';
    //             $classStatus = 'is-success';

    //             /*
    //     |--------------------------------------------------------------------------
    //     | 2. Selesai
    //     |--------------------------------------------------------------------------
    //     */
    //         } elseif (
    //             $isAsmed ||
    //             $isCpptDokter
    //         ) {

    //             $statusPasien = 'Selesai Dilayani';
    //             $labelStatus = 'Selesai';
    //             $classStatus = 'is-warning';

    //             /*
    //     |--------------------------------------------------------------------------
    //     | 3. Sedang diperiksa
    //     |--------------------------------------------------------------------------
    //     */
    //         } elseif (
    //             $isTindakan ||
    //             $isKonsul
    //         ) {

    //             $statusPasien = 'Sedang Diperiksa';
    //             $labelStatus = 'Sedang Diperiksa';
    //             $classStatus = 'is-primary';

    //             /*
    //     |--------------------------------------------------------------------------
    //     | 4. Sudah registrasi tetapi APD belum dibuat
    //     |--------------------------------------------------------------------------
    //     */
    //         } elseif (!$apd) {

    //             $statusPasien = 'Sudah Teregistrasi';
    //             $labelStatus = 'Sudah Teregistrasi';
    //             $classStatus = 'is-info';

    //             /*
    //     |--------------------------------------------------------------------------
    //     | 5. APD sudah ada, menunggu pelayanan
    //     |--------------------------------------------------------------------------
    //     */
    //         } else {

    //             $statusPasien = 'Menunggu Pelayanan';
    //             $labelStatus = 'Menunggu Pelayanan';
    //             $classStatus = 'is-danger';
    //         }
    //     }

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Tambahkan informasi registrasi
    //     |--------------------------------------------------------------------------
    //     */
    //     $reservasi->sudah_teregistrasi =
    //         $registrasi ? true : false;

    //     $reservasi->status_registrasi =
    //         $registrasi
    //         ? 'Sudah Teregistrasi'
    //         : 'Belum Teregistrasi';

    //     $reservasi->asal_registrasi =
    //         $registrasi
    //         ? (
    //             !empty(data_get(
    //                     $registrasi,
    //                     'antrianpasienregistrasifk'
    //                 ))
    //             ? 'Dari Reservasi'
    //             : 'Registrasi Langsung'
    //         )
    //         : 'Belum Teregistrasi';

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Identitas Registrasi
    //     |--------------------------------------------------------------------------
    //     */
    //     $reservasi->noregistrasi = data_get(
    //         $registrasi,
    //         'noregistrasi'
    //     );

    //     $reservasi->norec_pd = data_get(
    //         $registrasi,
    //         'norec_pd'
    //     );

    //     $reservasi->norec_apd = data_get(
    //         $apd,
    //         'norec_apd'
    //     );

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Status Pasien
    //     |--------------------------------------------------------------------------
    //     */
    //     $reservasi->status_pasien =
    //         $statusPasien;

    //     $reservasi->label_statusperiksa =
    //         $labelStatus;

    //     $reservasi->class_statusperiksa =
    //         $classStatus;

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Status asli APD
    //     |--------------------------------------------------------------------------
    //     */
    //     $reservasi->status_asli = data_get(
    //         $apd,
    //         'status'
    //     );

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Informasi proses pemeriksaan
    //     |--------------------------------------------------------------------------
    //     */
    //     $reservasi->tglregistrasi = data_get(
    //         $registrasi,
    //         'tglregistrasi'
    //     );

    //     $reservasi->tglclosing = data_get(
    //         $registrasi,
    //         'tglclosing'
    //     );

    //     $reservasi->istindakan = data_get(
    //         $registrasi,
    //         'istindakan'
    //     );

    //     $reservasi->isasmed = data_get(
    //         $registrasi,
    //         'isasmed'
    //     );

    //     $reservasi->iscppt_dokter = data_get(
    //         $apd,
    //         'iscppt_dokter'
    //     );

    //     $reservasi->konsul = data_get(
    //         $apd,
    //         'konsul'
    //     );

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Response
    //     |--------------------------------------------------------------------------
    //     |
    //     | Tetap array agar kompatibel dengan response sebelumnya:
    //     |
    //     | data: [ {...} ]
    //     |
    //     */
    //     return response()->json([
    //         'total' => 1,
    //         'data' => [
    //             $reservasi,
    //         ],
    //         'as' => '@epic',
    //     ], 200);
    // }
    public function getDataRiwayatReservasi(Request $request, $kdProfile = 1)
    {
        $kdProfile = (int) ($kdProfile ?: 1);

        $noreservasi = trim((string) $request->input('noReservasi', ''));
        $nocmnama = trim((string) $request->input('rm', ''));
        $tgllahir = trim((string) $request->input('tgllahir', ''));

        $tanggalHariIni = now()->format('Y-m-d');

        /*
    |--------------------------------------------------------------------------
    | Jadwal Dokter
    |--------------------------------------------------------------------------
    */
        $jadwalDokter = DB::connection('pgsql')
            ->table('jadwaldokter_m')
            ->selectRaw("
            objectruanganfk,
            objectpegawaifk,
            CAST(tanggal AS DATE) AS tanggal,
            MIN(jammulai) AS jammulai,
            MAX(jamakhir) AS jamakhir
        ")
            ->where('statusenabled', true)
            ->where('kdprofile', $kdProfile)
            ->groupBy(
                'objectruanganfk',
                'objectpegawaifk'
            )
            ->groupByRaw(
                'CAST(tanggal AS DATE)'
            );

        /*
    |--------------------------------------------------------------------------
    | Query Reservasi
    |--------------------------------------------------------------------------
    */
        $data = DB::connection('pgsql')
            ->table('antrianpasienregistrasi_t as apr')

            ->leftJoin(
                'pasien_m as pm',
                'pm.id',
                '=',
                'apr.nocmfk'
            )

            ->leftJoin(
                'alamat_m as alm',
                'alm.nocmfk',
                '=',
                'pm.id'
            )

            ->leftJoin(
                'jeniskelamin_m as jk',
                'jk.id',
                '=',
                'pm.objectjeniskelaminfk'
            )

            ->leftJoin(
                'jeniskelamin_m as jks',
                'jks.id',
                '=',
                'apr.objectjeniskelaminfk'
            )

            ->leftJoin(
                'pekerjaan_m as pk',
                'pk.id',
                '=',
                'pm.objectpekerjaanfk'
            )

            ->leftJoin(
                'pendidikan_m as pdd',
                'pdd.id',
                '=',
                'pm.objectpendidikanfk'
            )

            ->leftJoin(
                'ruangan_m as ru',
                'ru.id',
                '=',
                'apr.objectruanganfk'
            )

            ->leftJoin(
                'pegawai_m as pg',
                'pg.id',
                '=',
                'apr.objectpegawaifk'
            )

            ->leftJoinSub(
                $jadwalDokter,
                'jd',
                function ($join) {
                    $join->on(
                        'jd.objectruanganfk',
                        '=',
                        'apr.objectruanganfk'
                    );

                    $join->on(
                        'jd.objectpegawaifk',
                        '=',
                        'apr.objectpegawaifk'
                    );

                    $join->whereRaw("
                    CAST(apr.tanggalreservasi AS DATE)
                    = jd.tanggal
                ");
                }
            )

            ->join(
                'kelompokpasien_m as kps',
                'kps.id',
                '=',
                'apr.objectkelompokpasienfk'
            )

            ->select(
                'apr.norec',
                'apr.nocmfk',

                'pm.nocm',

                'apr.noreservasi',

                DB::raw("
                CAST(apr.tanggalreservasi AS DATE)
                AS tanggalreservasi
            "),

                DB::raw("
                CASE
                    WHEN jd.jammulai IS NOT NULL
                    THEN jd.jammulai || ' - ' || jd.jamakhir
                    ELSE NULL
                END AS jamreservasi
            "),

                'apr.objectruanganfk',
                'apr.objectpegawaifk',

                'ru.namaruangan',

                'ru.kdsubspesialisbpjs as kodepolisubspesialis',

                'apr.isconfirm',

                'apr.noantrian',
                'apr.noantrianpoli',

                /*
                 * Antrean loket:
                 * 1 digit  -> jenis + 00 + nomor
                 * 2 digit  -> jenis + 0  + nomor
                 * 3 digit  -> jenis + nomor
                 * Contoh   -> LU009
                 */
                DB::raw("
                    CASE
                        WHEN apr.noantrian IS NULL THEN NULL
                        WHEN LENGTH(apr.noantrian::varchar) = 1
                            THEN COALESCE(apr.jenis, '')
                                || '00'
                                || apr.noantrian::varchar
                        WHEN LENGTH(apr.noantrian::varchar) = 2
                            THEN COALESCE(apr.jenis, '')
                                || '0'
                                || apr.noantrian::varchar
                        WHEN LENGTH(apr.noantrian::varchar) = 3
                            THEN COALESCE(apr.jenis, '')
                                || apr.noantrian::varchar
                        ELSE COALESCE(apr.jenis, '')
                            || apr.noantrian::varchar
                    END AS antrianloket
                "),

                'pg.namalengkap as dokter',

                'pm.id as patient_id',

                'pk.pekerjaan',
                'pm.noasuransilain',
                'pdd.pendidikan',

                'apr.type',

                'kps.kelompokpasien',
                'apr.objectkelompokpasienfk',

                'ru.objectdepartemenfk',
                'ru.prefixnoantrian',

                'apr.norujukan',
                'apr.nosuratkontrol',

                'ru.id as idruangan',

                'pg.id as iddokter',
                'pg.kddokterbpjs',

                /*
            |--------------------------------------------------------------------------
            | Jenis APR
            |--------------------------------------------------------------------------
            */
                'apr.jenis',
                'apr.jenis as jenisapr',

                /*
            |--------------------------------------------------------------------------
            | Status Reservasi
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN apr.isconfirm = true
                    THEN 'Confirm'
                    ELSE 'Reservasi'
                END AS status
            "),

                /*
            |--------------------------------------------------------------------------
            | Tempat Lahir
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN pm.tempatlahir IS NULL
                    THEN apr.tempatlahir
                    ELSE pm.tempatlahir
                END AS tempatlahir
            "),

                /*
            |--------------------------------------------------------------------------
            | NIK
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN pm.noidentitas IS NULL
                    THEN apr.noidentitas
                    ELSE pm.noidentitas
                END AS noidentitas
            "),

                /*
            |--------------------------------------------------------------------------
            | BPJS
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN pm.nobpjs IS NULL
                    THEN apr.nobpjs
                    ELSE pm.nobpjs
                END AS nobpjs
            "),

                /*
            |--------------------------------------------------------------------------
            | Nama Pasien
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN pm.namapasien IS NULL
                    THEN apr.namapasien
                    ELSE pm.namapasien
                END AS namapasien
            "),

                /*
            |--------------------------------------------------------------------------
            | Jenis Kelamin FK
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN pm.objectjeniskelaminfk IS NULL
                    THEN apr.objectjeniskelaminfk
                    ELSE pm.objectjeniskelaminfk
                END AS objectjeniskelaminfk
            "),

                /*
            |--------------------------------------------------------------------------
            | Telepon
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN pm.nohp IS NULL
                    THEN apr.notelepon
                    ELSE pm.nohp
                END AS notelepon
            "),

                /*
            |--------------------------------------------------------------------------
            | Email
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN pm.email IS NULL
                    THEN apr.email
                    ELSE pm.email
                END AS email
            "),

                /*
            |--------------------------------------------------------------------------
            | Alamat
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN alm.alamatlengkap IS NULL
                    THEN apr.alamatlengkap
                    ELSE alm.alamatlengkap
                END AS alamatlengkap
            "),

                /*
            |--------------------------------------------------------------------------
            | Kebangsaan
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN pm.objectkebangsaanfk IS NULL
                    THEN apr.objectkebangsaanfk
                    ELSE pm.objectkebangsaanfk
                END AS objectkebangsaanfk
            "),

                /*
            |--------------------------------------------------------------------------
            | Agama
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN pm.objectagamafk IS NULL
                    THEN apr.objectagamafk
                    ELSE pm.objectagamafk
                END AS objectagamafk
            "),

                /*
            |--------------------------------------------------------------------------
            | Jenis Kelamin
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN jk.jeniskelamin IS NULL
                    THEN jks.jeniskelamin
                    ELSE jk.jeniskelamin
                END AS jeniskelamin
            "),

                /*
            |--------------------------------------------------------------------------
            | Tanggal Lahir
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CASE
                    WHEN pm.tgllahir IS NULL
                    THEN to_char(
                        apr.tgllahir,
                        'YYYY-MM-DD'
                    )
                    ELSE to_char(
                        pm.tgllahir,
                        'YYYY-MM-DD'
                    )
                END AS tgllahir
            "),

                'apr.loketkiosk'
            )

            /*
        |--------------------------------------------------------------------------
        | Filter Dasar
        |--------------------------------------------------------------------------
        */
            ->whereNotNull(
                'apr.noreservasi'
            )

            ->where(
                'apr.noreservasi',
                '!=',
                '-'
            )

            ->where(
                'apr.kdprofile',
                $kdProfile
            )

            ->where(
                'apr.statusenabled',
                true
            )

            /*
        |--------------------------------------------------------------------------
        | JANGAN TAMPILKAN PASIEN YANG SUDAH CLOSING
        |--------------------------------------------------------------------------
        |
        | Jika pd.tglclosing sudah terisi, reservasi tidak ditampilkan.
        |
        */
            ->whereNotExists(function ($query) {
                $query
                    ->select(DB::raw(1))

                    ->from(
                        'pasiendaftar_t as pd_closing'
                    )

                    ->whereColumn(
                        'pd_closing.antrianpasienregistrasifk',
                        'apr.norec'
                    )

                    ->where(
                        'pd_closing.statusenabled',
                        true
                    )

                    ->whereNotNull(
                        'pd_closing.tglclosing'
                    );
            })

            /*
        |--------------------------------------------------------------------------
        | Jangan Tampilkan Jika Asesmen / CPPT Sudah Selesai
        |--------------------------------------------------------------------------
        */
            ->whereNotExists(function ($query) {
                $query
                    ->select(DB::raw(1))

                    ->from(
                        'pasiendaftar_t as pd_done'
                    )

                    ->whereColumn(
                        'pd_done.antrianpasienregistrasifk',
                        'apr.norec'
                    )

                    ->where(
                        'pd_done.statusenabled',
                        true
                    )

                    ->whereRaw("
                        LOWER(
                            TRIM(
                                COALESCE(
                                    pd_done.isasmed::text,
                                    ''
                                )
                            )
                        ) IN (
                            '1',
                            'true',
                            't',
                            'yes',
                            'y'
                        )
                    ");
            })

            ->whereNotExists(function ($query) {
                $query
                    ->select(DB::raw(1))

                    ->from(
                        'pasiendaftar_t as pd_cppt'
                    )

                    ->join(
                        'antrianpasiendiperiksa_t as apd_done',
                        'apd_done.noregistrasifk',
                        '=',
                        'pd_cppt.norec'
                    )

                    ->whereColumn(
                        'pd_cppt.antrianpasienregistrasifk',
                        'apr.norec'
                    )

                    ->where(
                        'pd_cppt.statusenabled',
                        true
                    )

                    ->where(
                        'apd_done.statusenabled',
                        true
                    )

                    ->where(function ($status) {
                        $status
                            ->whereRaw("
                                LOWER(
                                    TRIM(
                                        COALESCE(
                                            apd_done.iscppt_dokter::text,
                                            ''
                                        )
                                    )
                                ) IN (
                                    '1',
                                    'true',
                                    't',
                                    'yes',
                                    'y'
                                )
                            ")

                            ->orWhereRaw(
                                "LOWER(COALESCE(apd_done.status, '')) = 'selesai'"
                            );
                    });
            })

            /*
        |--------------------------------------------------------------------------
        | JANGAN TAMPILKAN JIKA STATUS RESEP / FARMASI = 6
        |--------------------------------------------------------------------------
        |
        | Relasi:
        |
        | apr.norec
        |    ↓
        | pd.antrianpasienregistrasifk
        |
        | pd.noregistrasi
        |    ↓
        | aa.noregistrasi
        |
        | Jika aa.status = 6 maka kontrol/reservasi tidak ditampilkan.
        |
        */
            ->whereNotExists(function ($query) {
                $query
                    ->select(DB::raw(1))

                    ->from(
                        'pasiendaftar_t as pd_resep'
                    )

                    ->join(
                        'antrianapotik_t as aa_resep',
                        'aa_resep.noregistrasi',
                        '=',
                        'pd_resep.noregistrasi'
                    )

                    ->whereColumn(
                        'pd_resep.antrianpasienregistrasifk',
                        'apr.norec'
                    )

                    ->where(
                        'pd_resep.statusenabled',
                        true
                    )

                    ->where(
                        'aa_resep.status',
                        6
                    );
            })

            /*
        |--------------------------------------------------------------------------
        | Hari Ini dan Berikutnya
        |--------------------------------------------------------------------------
        */
            ->whereRaw(
                'CAST(apr.tanggalreservasi AS DATE) >= ?',
                [
                    $tanggalHariIni
                ]
            );

        /*
    |--------------------------------------------------------------------------
    | Filter Tanggal Lahir
    |--------------------------------------------------------------------------
    */
        if (
            $tgllahir !== '' &&
            $tgllahir !== 'undefined' &&
            $tgllahir !== 'null' &&
            $tgllahir !== 'Invalid date'
        ) {
            $data->where(
                function ($query) use ($tgllahir) {
                    $query
                        ->whereDate(
                            'pm.tgllahir',
                            $tgllahir
                        )

                        ->orWhereDate(
                            'apr.tgllahir',
                            $tgllahir
                        );
                }
            );
        }

        /*
    |--------------------------------------------------------------------------
    | Filter RM / NIK / BPJS / Nama
    |--------------------------------------------------------------------------
    */
        if (
            $nocmnama !== '' &&
            $nocmnama !== 'undefined' &&
            $nocmnama !== 'null'
        ) {
            $data->where(
                function ($query) use ($nocmnama) {
                    $query
                        ->where(
                            'apr.noreservasi',
                            $nocmnama
                        )

                        ->orWhere(
                            'pm.nocm',
                            $nocmnama
                        )

                        ->orWhere(
                            'pm.noidentitas',
                            $nocmnama
                        )

                        ->orWhere(
                            'pm.nobpjs',
                            $nocmnama
                        )

                        ->orWhere(
                            'apr.nobpjs',
                            $nocmnama
                        )

                        ->orWhere(
                            'pm.namapasien',
                            $nocmnama
                        )

                        ->orWhere(
                            'apr.namapasien',
                            $nocmnama
                        );
                }
            );
        }

        /*
    |--------------------------------------------------------------------------
    | Filter No Reservasi
    |--------------------------------------------------------------------------
    */
        if (
            $noreservasi !== '' &&
            $noreservasi !== 'undefined' &&
            $noreservasi !== 'null'
        ) {
            $data->where(
                function ($query) use ($noreservasi) {
                    $query
                        ->where(
                            'apr.noreservasi',
                            $noreservasi
                        )

                        ->orWhere(
                            'pm.nocm',
                            $noreservasi
                        )

                        ->orWhere(
                            'pm.noidentitas',
                            $noreservasi
                        )

                        ->orWhere(
                            'pm.nobpjs',
                            $noreservasi
                        )

                        ->orWhere(
                            'apr.nobpjs',
                            $noreservasi
                        );
                }
            );
        }

        /*
    |--------------------------------------------------------------------------
    | Ambil Reservasi Terdekat
    |--------------------------------------------------------------------------
    */
        $reservasi = $data
            ->orderByRaw(
                'CAST(apr.tanggalreservasi AS DATE) ASC'
            )

            ->orderBy(
                'apr.tanggalreservasi',
                'asc'
            )

            ->first();

        /*
    |--------------------------------------------------------------------------
    | FALLBACK SUMBER ANTREAN
    |--------------------------------------------------------------------------
    |
    | PRIORITAS WAJIB:
    | 1. antrianpasienregistrasi_t (APR)  -> sudah dicari di atas.
    | 2. antrianpasiendiperiksa_t (APD)  -> jika APR tidak ditemukan.
    | 3. antrianpasien_t                  -> jika APR dan APD tidak ditemukan.
    |
    | Semua fallback tetap dipadankan ke pasiendaftar_t agar status pelayanan,
    | closing, asesmen, resep, pasien, poli, dan dokter tetap konsisten.
    |
    */
        $registrasi = null;
        $sumberData = 'antrianpasienregistrasi_t';

        /*
         * Helper flag SQL untuk kolom SIMRS yang kadang boolean,
         * integer, atau varchar ('1', 'true', 't', dst.).
         */
        $sqlFlagTidakAktif = static function (string $column): string {
            return "LOWER(TRIM(COALESCE({$column}::text, ''))) "
                . "NOT IN ('1','true','t','yes','y')";
        };

        /*
         * Membentuk object $reservasi kompatibel dengan struktur lama,
         * agar bagian bawah fungsi dan Blade tidak perlu diubah.
         */
        $buatReservasiFallback = static function (
            $row,
            string $tanggal,
            string $status,
            $norecApr = null,
            $noAntrian = null,
            $noAntrianPoli = null,
            $ruanganFk = null,
            $dokterFk = null,
            $namaRuangan = null,
            $kodePoliBpjs = null,
            $namaDokter = null,
            $antrianLoket = null,
            $jenisApr = null
        ) {
            return (object) [
                'norec' => $norecApr,
                'nocmfk' => data_get($row, 'nocmfk'),
                'nocm' => data_get($row, 'nocm'),
                'noreservasi' => null,
                'tanggalreservasi' => $tanggal,
                'jamreservasi' => null,
                'objectruanganfk' => $ruanganFk,
                'objectpegawaifk' => $dokterFk,
                'namaruangan' => $namaRuangan,
                'kodepolisubspesialis' => $kodePoliBpjs,
                'isconfirm' => true,
                'noantrian' => $noAntrian,
                'noantrianpoli' => $noAntrianPoli,
                // Pada fallback APD, nomor loket dapat diambil dari APR
                // yang masih terhubung melalui pd.antrianpasienregistrasifk.
                'antrianloket' => $antrianLoket,
                'dokter' => $namaDokter,
                'patient_id' => data_get($row, 'nocmfk'),
                'pekerjaan' => null,
                'noasuransilain' => null,
                'pendidikan' => null,
                'type' => null,
                'kelompokpasien' => null,
                'objectkelompokpasienfk' => null,
                'objectdepartemenfk' => null,
                'prefixnoantrian' => null,
                'norujukan' => null,
                'nosuratkontrol' => null,
                'idruangan' => $ruanganFk,
                'iddokter' => $dokterFk,
                'kddokterbpjs' => null,
                'jenis' => $jenisApr,
                'jenisapr' => $jenisApr,
                'status' => $status,
                'tempatlahir' => data_get($row, 'tempatlahir'),
                'noidentitas' => data_get($row, 'noidentitas'),
                'nobpjs' => data_get($row, 'nobpjs'),
                'namapasien' => data_get($row, 'namapasien'),
                'objectjeniskelaminfk' => data_get(
                    $row,
                    'objectjeniskelaminfk'
                ),
                'notelepon' => data_get($row, 'nohp'),
                'email' => data_get($row, 'email'),
                'alamatlengkap' => null,
                'objectkebangsaanfk' => null,
                'objectagamafk' => null,
                'jeniskelamin' => data_get($row, 'jeniskelamin'),
                'tgllahir' => data_get($row, 'tgllahir'),
                'loketkiosk' => null,
            ];
        };

        /*
    |--------------------------------------------------------------------------
    | FALLBACK 1: ANTRIANPASIENDIPERIKSA_T (APD)
    |--------------------------------------------------------------------------
    |
    | Penting: query benar-benar DIMULAI dari APD, bukan dari PD LEFT JOIN APD.
    | Dengan demikian kalau APR tidak ada tetapi pasien sudah masuk antrean poli,
    | record tetap ditemukan.
    |
    */
        if (!$reservasi && $nocmnama !== '') {
            $fallbackApd = DB::connection('pgsql')
                ->table('antrianpasiendiperiksa_t as apd_fb')
                ->join(
                    'pasiendaftar_t as pd_fb',
                    'pd_fb.norec',
                    '=',
                    'apd_fb.noregistrasifk'
                )
                ->join(
                    'pasien_m as pm_fb',
                    'pm_fb.id',
                    '=',
                    'pd_fb.nocmfk'
                )
                ->leftJoin(
                    'antrianpasienregistrasi_t as apr_loket_fb',
                    'apr_loket_fb.norec',
                    '=',
                    'pd_fb.antrianpasienregistrasifk'
                )
                ->leftJoin(
                    'ruangan_m as ru_fb',
                    'ru_fb.id',
                    '=',
                    'apd_fb.objectruanganfk'
                )
                ->leftJoin(
                    'pegawai_m as pg_apd_fb',
                    'pg_apd_fb.id',
                    '=',
                    'apd_fb.objectpegawaifk'
                )
                ->leftJoin(
                    'pegawai_m as pg_pd_fb',
                    'pg_pd_fb.id',
                    '=',
                    'pd_fb.objectpegawaifk'
                )
                ->leftJoin(
                    'jeniskelamin_m as jk_fb',
                    'jk_fb.id',
                    '=',
                    'pm_fb.objectjeniskelaminfk'
                )
                ->select(
                    'pd_fb.norec as norec_pd',
                    'pd_fb.noregistrasi',
                    'pd_fb.nocmfk',
                    'pd_fb.tglregistrasi',
                    'pd_fb.tglclosing',
                    'pd_fb.exam_started_at',
                    'pd_fb.isasmed',
                    'pd_fb.iscppt',
                    'pd_fb.isaskeprj',
                    'pd_fb.istindakan',
                    'pd_fb.objectpegawaifk',
                    'pd_fb.objectruanganlastfk',
                    'pd_fb.antrianpasienregistrasifk',

                    // Antrean loket berasal dari APR yang terhubung, bukan
                    // dari apd_fb.noantrian yang merupakan antrean poli.
                    'apr_loket_fb.noantrian as noantrian_loket_fallback',
                    'apr_loket_fb.jenis as jenis_loket_fallback',
                    DB::raw("
                        CASE
                            WHEN apr_loket_fb.noantrian IS NULL THEN NULL
                            WHEN LENGTH(apr_loket_fb.noantrian::varchar) = 1
                                THEN COALESCE(apr_loket_fb.jenis, '')
                                    || '00'
                                    || apr_loket_fb.noantrian::varchar
                            WHEN LENGTH(apr_loket_fb.noantrian::varchar) = 2
                                THEN COALESCE(apr_loket_fb.jenis, '')
                                    || '0'
                                    || apr_loket_fb.noantrian::varchar
                            WHEN LENGTH(apr_loket_fb.noantrian::varchar) = 3
                                THEN COALESCE(apr_loket_fb.jenis, '')
                                    || apr_loket_fb.noantrian::varchar
                            ELSE COALESCE(apr_loket_fb.jenis, '')
                                || apr_loket_fb.noantrian::varchar
                        END AS antrianloket_fallback
                    "),

                    'apd_fb.norec as norec_apd_fallback',
                    'apd_fb.objectruanganfk as ruanganfk_fallback',
                    'apd_fb.objectpegawaifk as dokterfk_apd',
                    'apd_fb.noantrian as noantrian_fallback',
                    'apd_fb.status as apd_status',
                    'apd_fb.tglregistrasi as apd_tglregistrasi',
                    'apd_fb.tglkeluar as apd_tglkeluar',
                    'apd_fb.iskonsul as apd_konsul',
                    'apd_fb.iscppt_dokter as apd_iscppt_dokter',

                    'pm_fb.nocm',
                    'pm_fb.namapasien',
                    'pm_fb.nobpjs',
                    'pm_fb.noidentitas',
                    'pm_fb.tgllahir',
                    'pm_fb.tempatlahir',
                    'pm_fb.nohp',
                    'pm_fb.email',
                    'pm_fb.objectjeniskelaminfk',
                    'jk_fb.jeniskelamin',

                    'ru_fb.namaruangan as namaruangan_fallback',
                    'ru_fb.kdsubspesialisbpjs as kodepolisubspesialis_fallback',

                    DB::raw("
                        COALESCE(
                            apd_fb.objectpegawaifk,
                            pd_fb.objectpegawaifk
                        ) AS dokterfk_fallback
                    "),
                    DB::raw("
                        COALESCE(
                            pg_apd_fb.namalengkap,
                            pg_pd_fb.namalengkap
                        ) AS dokter_fallback
                    ")
                )
                ->where('apd_fb.statusenabled', true)
                ->where('pd_fb.statusenabled', true)
                ->where('pm_fb.statusenabled', true)
                /*
                 * Jangan paksa pd_fb.kdprofile pada fallback APD.
                 * Pada data operasional lama/bridging, kdprofile dapat tidak sama
                 * dengan parameter fungsi walaupun PD + APD pasien valid.
                 */
                ->where(function ($tanggal) use ($tanggalHariIni) {
                    $tanggal
                        ->whereDate(
                            'apd_fb.tglregistrasi',
                            $tanggalHariIni
                        )
                        ->orWhereDate(
                            'pd_fb.tglregistrasi',
                            $tanggalHariIni
                        );
                })
                ->where(function ($query) use ($nocmnama) {
                    $query
                        ->where('pm_fb.nocm', $nocmnama)
                        ->orWhere('pm_fb.noidentitas', $nocmnama)
                        ->orWhere('pm_fb.nobpjs', $nocmnama)
                        ->orWhere('pm_fb.namapasien', $nocmnama)
                        ->orWhere('pd_fb.noregistrasi', $nocmnama);
                })
                ->when(
                    $tgllahir !== ''
                        && $tgllahir !== 'undefined'
                        && $tgllahir !== 'null'
                        && $tgllahir !== 'Invalid date',
                    function ($query) use ($tgllahir) {
                        $query->whereDate('pm_fb.tgllahir', $tgllahir);
                    }
                )
                ->whereNull('pd_fb.tglclosing')
                ->whereRaw($sqlFlagTidakAktif('pd_fb.isasmed'))
                /*
                 * Jangan jadikan apd_fb.tglkeluar sebagai syarat mutlak.
                 * Status selesai mengikuti logika pelayanan: closing/asmed/CPPT
                 * dokter/status selesai. Ini disamakan dengan cekAntreanPasien().
                 */
                ->whereRaw($sqlFlagTidakAktif('apd_fb.iscppt_dokter'))
                ->whereRaw("
                    LOWER(COALESCE(apd_fb.status::text, ''))
                    NOT LIKE '%selesai%'
                ")
                ->whereNotExists(function ($query) {
                    $query
                        ->select(DB::raw(1))
                        ->from('antrianapotik_t as aa_done')
                        ->whereColumn(
                            'aa_done.noregistrasi',
                            'pd_fb.noregistrasi'
                        )
                        ->where('aa_done.status', 6);
                })
                ->orderByRaw("
                    CASE
                        WHEN apd_fb.tglkeluar IS NULL
                             AND LOWER(
                                COALESCE(apd_fb.status::text, '')
                             ) NOT LIKE '%selesai%'
                        THEN 0
                        ELSE 1
                    END ASC
                ")
                ->orderByDesc('apd_fb.tglregistrasi')
                ->orderByDesc('pd_fb.tglregistrasi')
                ->first();

            if ($fallbackApd) {
                $sumberData = 'antrianpasiendiperiksa_t';

                $registrasi = (object) [
                    'norec_pd' => data_get($fallbackApd, 'norec_pd'),
                    'noregistrasi' => data_get($fallbackApd, 'noregistrasi'),
                    'nocmfk' => data_get($fallbackApd, 'nocmfk'),
                    'tglregistrasi' => data_get($fallbackApd, 'tglregistrasi'),
                    'tglclosing' => data_get($fallbackApd, 'tglclosing'),
                    'exam_started_at' => data_get(
                        $fallbackApd,
                        'exam_started_at'
                    ),
                    'isasmed' => data_get($fallbackApd, 'isasmed'),
                    'iscppt' => data_get($fallbackApd, 'iscppt'),
                    'isaskeprj' => data_get($fallbackApd, 'isaskeprj'),
                    'istindakan' => data_get($fallbackApd, 'istindakan'),
                    'objectpegawaifk' => data_get(
                        $fallbackApd,
                        'objectpegawaifk'
                    ),
                    'objectruanganlastfk' => data_get(
                        $fallbackApd,
                        'objectruanganlastfk'
                    ),
                    'antrianpasienregistrasifk' => data_get(
                        $fallbackApd,
                        'antrianpasienregistrasifk'
                    ),
                ];

                $tanggalFallback = substr(
                    (string) (
                        data_get($fallbackApd, 'apd_tglregistrasi')
                        ?: data_get($fallbackApd, 'tglregistrasi')
                    ),
                    0,
                    10
                );

                $reservasi = $buatReservasiFallback(
                    $fallbackApd,
                    $tanggalFallback ?: $tanggalHariIni,
                    'Antrean Pemeriksaan',
                    data_get($fallbackApd, 'antrianpasienregistrasifk'),
                    data_get($fallbackApd, 'noantrian_fallback'),
                    null,
                    (int) data_get($fallbackApd, 'ruanganfk_fallback'),
                    (int) data_get($fallbackApd, 'dokterfk_fallback'),
                    data_get($fallbackApd, 'namaruangan_fallback'),
                    data_get(
                        $fallbackApd,
                        'kodepolisubspesialis_fallback'
                    ),
                    data_get($fallbackApd, 'dokter_fallback'),
                    data_get($fallbackApd, 'antrianloket_fallback'),
                    data_get($fallbackApd, 'jenis_loket_fallback')
                );
            }
        }

        /*
    |--------------------------------------------------------------------------
    | FALLBACK 2: ANTRIANPASIEN_T
    |--------------------------------------------------------------------------
    |
    | Struktur antrianpasien_t dapat berbeda antar versi SIMRS. Karena itu
    | kolom relasi dan kolom antrean dideteksi dari schema database terlebih
    | dahulu. Query hanya dijalankan bila ditemukan relasi yang aman ke PD.
    |
    */
        if (!$reservasi && $nocmnama !== '') {
            $schema = DB::connection('pgsql')->getSchemaBuilder();

            if ($schema->hasTable('antrianpasien_t')) {
                $kolomApt = $schema->getColumnListing('antrianpasien_t');

                $pilihKolom = static function (
                    array $columns,
                    array $candidates
                ) {
                    foreach ($candidates as $candidate) {
                        if (in_array($candidate, $columns, true)) {
                            return $candidate;
                        }
                    }

                    return null;
                };

                /* Relasi antrianpasien_t -> pasiendaftar_t */
                $aptPdFk = $pilihKolom($kolomApt, [
                    'noregistrasifk',
                    'norecpdfk',
                    'pasiendaftarfk',
                    'norec_pd',
                ]);

                /*
                 * Beberapa versi menyimpan nomor registrasi langsung, bukan
                 * norec PD. Dukungan ini mencegah fallback gagal hanya karena
                 * bentuk relasinya berbeda.
                 */
                $aptNoRegistrasi = $pilihKolom($kolomApt, [
                    'noregistrasi',
                    'no_registrasi',
                ]);

                $aptNorec = $pilihKolom($kolomApt, ['norec', 'id']);
                $aptNoAntrian = $pilihKolom($kolomApt, [
                    'noantrian',
                    'noantrianpoli',
                    'nomorantrean',
                    'nomor_urut',
                ]);
                $aptRuanganFk = $pilihKolom($kolomApt, [
                    'objectruanganfk',
                    'ruanganfk',
                ]);
                $aptDokterFk = $pilihKolom($kolomApt, [
                    'objectpegawaifk',
                    'pegawaifk',
                    'dokterfk',
                ]);
                $aptTanggal = $pilihKolom($kolomApt, [
                    'tglregistrasi',
                    'tanggal',
                    'tanggalantrian',
                    'tglantrian',
                ]);
                $aptStatus = $pilihKolom($kolomApt, [
                    'status',
                    'statusantrian',
                    'statuspelayanan',
                ]);
                $aptStatusEnabled = $pilihKolom($kolomApt, [
                    'statusenabled',
                ]);
                $aptKdProfile = $pilihKolom($kolomApt, [
                    'kdprofile',
                ]);

                if ($aptPdFk || $aptNoRegistrasi) {
                    $queryApt = DB::connection('pgsql')
                        ->table('antrianpasien_t as apt_fb');

                    if ($aptPdFk) {
                        $queryApt->join(
                            'pasiendaftar_t as pd_apt',
                            'pd_apt.norec',
                            '=',
                            'apt_fb.' . $aptPdFk
                        );
                    } else {
                        $queryApt->join(
                            'pasiendaftar_t as pd_apt',
                            'pd_apt.noregistrasi',
                            '=',
                            'apt_fb.' . $aptNoRegistrasi
                        );
                    }

                    $queryApt
                        ->join(
                            'pasien_m as pm_apt',
                            'pm_apt.id',
                            '=',
                            'pd_apt.nocmfk'
                        )
                        ->leftJoin(
                            'jeniskelamin_m as jk_apt',
                            'jk_apt.id',
                            '=',
                            'pm_apt.objectjeniskelaminfk'
                        );

                    if ($aptRuanganFk) {
                        $queryApt->leftJoin(
                            'ruangan_m as ru_apt',
                            'ru_apt.id',
                            '=',
                            'apt_fb.' . $aptRuanganFk
                        );
                    }

                    if ($aptDokterFk) {
                        $queryApt->leftJoin(
                            'pegawai_m as pg_apt',
                            'pg_apt.id',
                            '=',
                            'apt_fb.' . $aptDokterFk
                        );
                    }

                    $selectApt = [
                        'pd_apt.norec as norec_pd',
                        'pd_apt.noregistrasi',
                        'pd_apt.nocmfk',
                        'pd_apt.tglregistrasi',
                        'pd_apt.tglclosing',
                        'pd_apt.exam_started_at',
                        'pd_apt.isasmed',
                        'pd_apt.iscppt',
                        'pd_apt.isaskeprj',
                        'pd_apt.istindakan',
                        'pd_apt.objectpegawaifk',
                        'pd_apt.objectruanganlastfk',
                        'pd_apt.antrianpasienregistrasifk',
                        'pm_apt.nocm',
                        'pm_apt.namapasien',
                        'pm_apt.nobpjs',
                        'pm_apt.noidentitas',
                        'pm_apt.tgllahir',
                        'pm_apt.tempatlahir',
                        'pm_apt.nohp',
                        'pm_apt.email',
                        'pm_apt.objectjeniskelaminfk',
                        'jk_apt.jeniskelamin',
                    ];

                    if ($aptNorec) {
                        $selectApt[] = DB::raw(
                            'apt_fb.' . $aptNorec
                                . ' as norec_antrianpasien'
                        );
                    }

                    if ($aptNoAntrian) {
                        $selectApt[] = DB::raw(
                            'apt_fb.' . $aptNoAntrian
                                . ' as noantrian_fallback'
                        );
                    }

                    if ($aptRuanganFk) {
                        $selectApt[] = DB::raw(
                            'apt_fb.' . $aptRuanganFk
                                . ' as ruanganfk_fallback'
                        );
                        $selectApt[] = 'ru_apt.namaruangan as namaruangan_fallback';
                        $selectApt[] = 'ru_apt.kdsubspesialisbpjs as kodepolisubspesialis_fallback';
                    } else {
                        $selectApt[] = 'pd_apt.objectruanganlastfk as ruanganfk_fallback';
                    }

                    if ($aptDokterFk) {
                        $selectApt[] = DB::raw(
                            'apt_fb.' . $aptDokterFk
                                . ' as dokterfk_fallback'
                        );
                        $selectApt[] = 'pg_apt.namalengkap as dokter_fallback';
                    } else {
                        $selectApt[] = 'pd_apt.objectpegawaifk as dokterfk_fallback';
                    }

                    if ($aptTanggal) {
                        $selectApt[] = DB::raw(
                            'apt_fb.' . $aptTanggal
                                . ' as tanggal_antrian_fallback'
                        );
                    }

                    if ($aptStatus) {
                        $selectApt[] = DB::raw(
                            'apt_fb.' . $aptStatus
                                . ' as status_antrian_fallback'
                        );
                    }

                    $queryApt
                        ->select($selectApt)
                        ->where('pd_apt.statusenabled', true)
                        ->where('pd_apt.kdprofile', $kdProfile)
                        ->where('pm_apt.statusenabled', true)
                        ->whereNull('pd_apt.tglclosing')
                        ->whereRaw($sqlFlagTidakAktif('pd_apt.isasmed'))
                        ->where(function ($query) use ($nocmnama) {
                            $query
                                ->where('pm_apt.nocm', $nocmnama)
                                ->orWhere('pm_apt.noidentitas', $nocmnama)
                                ->orWhere('pm_apt.nobpjs', $nocmnama)
                                ->orWhere('pm_apt.namapasien', $nocmnama)
                                ->orWhere('pd_apt.noregistrasi', $nocmnama);
                        })
                        ->when(
                            $tgllahir !== ''
                                && $tgllahir !== 'undefined'
                                && $tgllahir !== 'null'
                                && $tgllahir !== 'Invalid date',
                            function ($query) use ($tgllahir) {
                                $query->whereDate(
                                    'pm_apt.tgllahir',
                                    $tgllahir
                                );
                            }
                        )
                        ->whereNotExists(function ($query) {
                            $query
                                ->select(DB::raw(1))
                                ->from('antrianapotik_t as aa_done')
                                ->whereColumn(
                                    'aa_done.noregistrasi',
                                    'pd_apt.noregistrasi'
                                )
                                ->where('aa_done.status', 6);
                        });

                    if ($aptStatusEnabled) {
                        $queryApt->whereRaw(
                            "LOWER(TRIM(COALESCE(apt_fb.{$aptStatusEnabled}::text, ''))) "
                                . "IN ('1','true','t','yes','y')"
                        );
                    }

                    if ($aptKdProfile) {
                        $queryApt->where(
                            'apt_fb.' . $aptKdProfile,
                            $kdProfile
                        );
                    }

                    if ($aptTanggal) {
                        $queryApt->whereDate(
                            'apt_fb.' . $aptTanggal,
                            $tanggalHariIni
                        );
                    } else {
                        $queryApt->whereDate(
                            'pd_apt.tglregistrasi',
                            $tanggalHariIni
                        );
                    }

                    if ($aptStatus) {
                        $queryApt->whereRaw(
                            "LOWER(COALESCE(apt_fb.{$aptStatus}::text, '')) "
                                . "NOT LIKE '%selesai%'"
                        );
                    }

                    if ($aptTanggal) {
                        $queryApt->orderByDesc('apt_fb.' . $aptTanggal);
                    }

                    $queryApt->orderByDesc('pd_apt.tglregistrasi');

                    $fallbackApt = $queryApt->first();

                    if ($fallbackApt) {
                        $sumberData = 'antrianpasien_t';

                        $registrasi = (object) [
                            'norec_pd' => data_get(
                                $fallbackApt,
                                'norec_pd'
                            ),
                            'noregistrasi' => data_get(
                                $fallbackApt,
                                'noregistrasi'
                            ),
                            'nocmfk' => data_get(
                                $fallbackApt,
                                'nocmfk'
                            ),
                            'tglregistrasi' => data_get(
                                $fallbackApt,
                                'tglregistrasi'
                            ),
                            'tglclosing' => data_get(
                                $fallbackApt,
                                'tglclosing'
                            ),
                            'exam_started_at' => data_get(
                                $fallbackApt,
                                'exam_started_at'
                            ),
                            'isasmed' => data_get(
                                $fallbackApt,
                                'isasmed'
                            ),
                            'iscppt' => data_get(
                                $fallbackApt,
                                'iscppt'
                            ),
                            'isaskeprj' => data_get(
                                $fallbackApt,
                                'isaskeprj'
                            ),
                            'istindakan' => data_get(
                                $fallbackApt,
                                'istindakan'
                            ),
                            'objectpegawaifk' => data_get(
                                $fallbackApt,
                                'objectpegawaifk'
                            ),
                            'objectruanganlastfk' => data_get(
                                $fallbackApt,
                                'objectruanganlastfk'
                            ),
                            'antrianpasienregistrasifk' => data_get(
                                $fallbackApt,
                                'antrianpasienregistrasifk'
                            ),
                        ];

                        $tanggalFallback = substr(
                            (string) (
                                data_get(
                                    $fallbackApt,
                                    'tanggal_antrian_fallback'
                                )
                                ?: data_get(
                                    $fallbackApt,
                                    'tglregistrasi'
                                )
                            ),
                            0,
                            10
                        );

                        $reservasi = $buatReservasiFallback(
                            $fallbackApt,
                            $tanggalFallback ?: $tanggalHariIni,
                            'Antrean Pasien',
                            data_get(
                                $fallbackApt,
                                'antrianpasienregistrasifk'
                            ),
                            data_get(
                                $fallbackApt,
                                'noantrian_fallback'
                            ),
                            null,
                            (int) data_get(
                                $fallbackApt,
                                'ruanganfk_fallback'
                            ),
                            (int) data_get(
                                $fallbackApt,
                                'dokterfk_fallback'
                            ),
                            data_get(
                                $fallbackApt,
                                'namaruangan_fallback'
                            ),
                            data_get(
                                $fallbackApt,
                                'kodepolisubspesialis_fallback'
                            ),
                            data_get(
                                $fallbackApt,
                                'dokter_fallback'
                            )
                        );
                    }
                }
            }
        }

        /*
    |--------------------------------------------------------------------------
    | Tidak Ada APR dan Tidak Ada PD/APD Aktif
    |--------------------------------------------------------------------------
    */
        if (!$reservasi) {
            $debug = null;

            if ($request->boolean('debug')) {
                $pasienDebug = DB::connection('pgsql')
                    ->table('pasien_m as pm_dbg')
                    ->select(
                        'pm_dbg.id',
                        'pm_dbg.nocm',
                        'pm_dbg.namapasien',
                        'pm_dbg.nobpjs',
                        'pm_dbg.noidentitas',
                        'pm_dbg.tgllahir'
                    )
                    ->where('pm_dbg.statusenabled', true)
                    ->where(function ($query) use ($nocmnama) {
                        $query
                            ->where('pm_dbg.nocm', $nocmnama)
                            ->orWhere('pm_dbg.noidentitas', $nocmnama)
                            ->orWhere('pm_dbg.nobpjs', $nocmnama)
                            ->orWhere('pm_dbg.namapasien', $nocmnama);
                    })
                    ->when(
                        $tgllahir !== ''
                            && $tgllahir !== 'undefined'
                            && $tgllahir !== 'null'
                            && $tgllahir !== 'Invalid date',
                        function ($query) use ($tgllahir) {
                            $query->whereDate('pm_dbg.tgllahir', $tgllahir);
                        }
                    )
                    ->first();

                $pdDebug = collect();
                $apdDebug = collect();

                if ($pasienDebug) {
                    $pdDebug = DB::connection('pgsql')
                        ->table('pasiendaftar_t as pd_dbg')
                        ->select(
                            'pd_dbg.norec',
                            'pd_dbg.noregistrasi',
                            'pd_dbg.tglregistrasi',
                            'pd_dbg.tglclosing',
                            'pd_dbg.isasmed',
                            'pd_dbg.istindakan',
                            'pd_dbg.objectruanganlastfk',
                            'pd_dbg.objectpegawaifk',
                            'pd_dbg.antrianpasienregistrasifk',
                            'pd_dbg.kdprofile',
                            'pd_dbg.statusenabled'
                        )
                        ->where('pd_dbg.nocmfk', $pasienDebug->id)
                        ->where(function ($tanggal) use ($tanggalHariIni) {
                            $tanggal
                                ->whereDate(
                                    'pd_dbg.tglregistrasi',
                                    $tanggalHariIni
                                );
                        })
                        ->orderByDesc('pd_dbg.tglregistrasi')
                        ->get();

                    $apdDebug = DB::connection('pgsql')
                        ->table('antrianpasiendiperiksa_t as apd_dbg')
                        ->join(
                            'pasiendaftar_t as pd_apd_dbg',
                            'pd_apd_dbg.norec',
                            '=',
                            'apd_dbg.noregistrasifk'
                        )
                        ->select(
                            'apd_dbg.norec',
                            'apd_dbg.noregistrasifk',
                            'apd_dbg.tglregistrasi',
                            'apd_dbg.tglkeluar',
                            'apd_dbg.status',
                            'apd_dbg.statusenabled',
                            'apd_dbg.noantrian',
                            'apd_dbg.objectruanganfk',
                            'apd_dbg.objectpegawaifk',
                            'apd_dbg.iscppt_dokter',
                            'apd_dbg.iskonsul',
                            'pd_apd_dbg.noregistrasi',
                            'pd_apd_dbg.tglclosing',
                            'pd_apd_dbg.isasmed',
                            'pd_apd_dbg.kdprofile'
                        )
                        ->where('pd_apd_dbg.nocmfk', $pasienDebug->id)
                        ->where(function ($tanggal) use ($tanggalHariIni) {
                            $tanggal
                                ->whereDate(
                                    'apd_dbg.tglregistrasi',
                                    $tanggalHariIni
                                )
                                ->orWhereDate(
                                    'pd_apd_dbg.tglregistrasi',
                                    $tanggalHariIni
                                );
                        })
                        ->orderByDesc('apd_dbg.tglregistrasi')
                        ->get();
                }

                $debug = [
                    'tanggal_hari_ini' => $tanggalHariIni,
                    'keyword' => $nocmnama,
                    'tgllahir' => $tgllahir,
                    'pasien_ditemukan' => (bool) $pasienDebug,
                    'pasien' => $pasienDebug,
                    'jumlah_pd_hari_ini' => $pdDebug->count(),
                    'pd_hari_ini' => $pdDebug,
                    'jumlah_apd_hari_ini' => $apdDebug->count(),
                    'apd_hari_ini' => $apdDebug,
                ];
            }

            return response()->json([
                'total' => 0,
                'data' => [],
                'debug' => $debug,
                'as' => '@epic',
            ], 200);
        }

        /*
    |--------------------------------------------------------------------------
    | Cari Registrasi / PD
    |--------------------------------------------------------------------------
    */
        $tanggalReservasi =
            (string) $reservasi->tanggalreservasi;

        /*
         * Jika fallback PD sudah memperoleh registrasi, jangan cari ulang
         * berdasarkan APR. Pencarian berdasarkan APR hanya dijalankan bila
         * norec APR memang tersedia.
         */
        if (
            !$registrasi
            && !empty(data_get($reservasi, 'norec'))
        ) {
            $registrasi = DB::connection('pgsql')
                ->table(
                    'pasiendaftar_t as pd'
                )

                ->select(
                    'pd.norec as norec_pd',
                    'pd.noregistrasi',
                    'pd.nocmfk',
                    'pd.tglregistrasi',
                    'pd.tglclosing',
                    'pd.exam_started_at',
                    'pd.isasmed',
                    'pd.iscppt',
                    'pd.isaskeprj',
                    'pd.istindakan',
                    'pd.objectpegawaifk',
                    'pd.objectruanganlastfk',
                    'pd.antrianpasienregistrasifk'
                )

                ->where(
                    'pd.statusenabled',
                    true
                )

                ->where(
                    'pd.antrianpasienregistrasifk',
                    data_get($reservasi, 'norec')
                )

                ->orderByDesc(
                    'pd.tglregistrasi'
                )

                ->first();
        }

        /*
    |--------------------------------------------------------------------------
    | Fallback Registrasi Langsung
    |--------------------------------------------------------------------------
    |
    | Jangan hanya mencocokkan pasien + tanggal.
    | Pasien dapat mempunyai lebih dari satu kunjungan pada hari yang sama.
    | Fallback harus tetap cocok dengan ruangan reservasi, baik dari
    | objectruanganlastfk maupun APD yang terbentuk untuk PD tersebut.
    |
    */
        if (!$registrasi) {
            $ruanganReservasi = (int) (
                $reservasi->objectruanganfk ?? 0
            );

            $registrasi = DB::connection('pgsql')
                ->table('pasiendaftar_t as pd')
                ->select(
                    'pd.norec as norec_pd',
                    'pd.noregistrasi',
                    'pd.nocmfk',
                    'pd.tglregistrasi',
                    'pd.tglclosing',
                    'pd.exam_started_at',
                    'pd.isasmed',
                    'pd.iscppt',
                    'pd.isaskeprj',
                    'pd.istindakan',
                    'pd.objectpegawaifk',
                    'pd.objectruanganlastfk',
                    'pd.antrianpasienregistrasifk'
                )
                ->where('pd.statusenabled', true)
                ->where('pd.nocmfk', $reservasi->nocmfk)
                ->whereDate('pd.tglregistrasi', $tanggalReservasi)
                ->when(
                    $ruanganReservasi > 0,
                    function ($query) use ($ruanganReservasi) {
                        $query->where(function ($ruang) use ($ruanganReservasi) {
                            $ruang
                                ->where(
                                    'pd.objectruanganlastfk',
                                    $ruanganReservasi
                                )
                                ->orWhereExists(
                                    function ($sub) use ($ruanganReservasi) {
                                        $sub
                                            ->select(DB::raw(1))
                                            ->from(
                                                'antrianpasiendiperiksa_t as apd_match'
                                            )
                                            ->whereColumn(
                                                'apd_match.noregistrasifk',
                                                'pd.norec'
                                            )
                                            ->where(
                                                'apd_match.statusenabled',
                                                true
                                            )
                                            ->where(
                                                'apd_match.objectruanganfk',
                                                $ruanganReservasi
                                            );
                                    }
                                );
                        });
                    }
                )
                ->orderByDesc('pd.tglregistrasi')
                ->first();
        }

        /*
    |--------------------------------------------------------------------------
    | Safety Check Closing
    |--------------------------------------------------------------------------
    |
    | Walaupun sudah difilter pada query APR, cek lagi setelah PD ditemukan.
    | Terutama untuk fallback registrasi langsung.
    |
    */
        if (
            $registrasi &&
            !empty(data_get(
                $registrasi,
                'tglclosing'
            ))
        ) {
            return response()->json([
                'total' => 0,
                'data' => [],
                'as' => '@epic',
            ], 200);
        }

        /*
    |--------------------------------------------------------------------------
    | RESEP / ORDER FARMASI
    |--------------------------------------------------------------------------
    */
        $resep = null;
        $riwayatResep = collect();

        if (
            $registrasi &&
            !empty($registrasi->noregistrasi)
        ) {
            /*
        |--------------------------------------------------------------------------
        | Cek apakah status resep sudah 6
        |--------------------------------------------------------------------------
        |
        | Jika ada status = 6, pelayanan farmasi dianggap sudah selesai
        | dan reservasi tidak perlu ditampilkan lagi.
        |
        */
            $resepSelesai = DB::connection('pgsql')
                ->table(
                    'antrianapotik_t as aa'
                )

                ->where(
                    'aa.noregistrasi',
                    $registrasi->noregistrasi
                )

                ->where(
                    'aa.status',
                    6
                )

                ->exists();

            if ($resepSelesai) {
                return response()->json([
                    'total' => 0,
                    'data' => [],
                    'as' => '@epic',
                ], 200);
            }

            /*
        |--------------------------------------------------------------------------
        | Ambil Resep Yang Masih Aktif
        |--------------------------------------------------------------------------
        */
            $riwayatResep = DB::connection('pgsql')
                ->table(
                    'antrianapotik_t as aa'
                )

                ->leftJoin(
                    'statuspengerjaan_m as st',
                    'st.id',
                    '=',
                    'aa.status'
                )

                ->select(
                    /*
                | No antrean farmasi
                */
                    'aa.noantri as aanoantri',

                    /*
                | A = Non Racikan
                | B = Racikan
                */
                    'aa.jenis as aajenis',

                    /*
                | Status ID
                */
                    'aa.status as status_order_id',

                    /*
                | Status pengerjaan
                */
                    'st.statuspengerjaan as statusorder',

                    /*
                | Tanggal resep
                */
                    'aa.tglresep'
                )

                ->where(
                    'aa.noregistrasi',
                    $registrasi->noregistrasi
                )

                ->where(
                    'aa.status',
                    '!=',
                    6
                )

                ->orderByDesc(
                    'aa.tglresep'
                )

                ->orderBy(
                    'aa.noantri',
                    'asc'
                )

                ->get();

            /*
        |--------------------------------------------------------------------------
        | Resep Utama / Terbaru
        |--------------------------------------------------------------------------
        */
            $resep =
                $riwayatResep->first();
        }

        /*
    |--------------------------------------------------------------------------
    | Cari APD
    |--------------------------------------------------------------------------
    |
    | APD harus berasal dari PD yang ditemukan dan, jika ruangan reservasi
    | tersedia, wajib cocok dengan ruangan reservasi. Ini mencegah APD poli
    | lain pada registrasi yang sama ikut dipakai.
    |
    */
        $apd = null;
        $ruanganReservasi = (int) (
            $reservasi->objectruanganfk ?? 0
        );

        if ($registrasi) {
            $apdQuery = DB::connection('pgsql')
                ->table('antrianpasiendiperiksa_t as apd')
                ->select(
                    'apd.norec as norec_apd',
                    'apd.noregistrasifk',
                    'apd.objectruanganfk',
                    'apd.objectpegawaifk',
                    'apd.noantrian',
                    'apd.status',
                    'apd.iskonsul as konsul',
                    'apd.tglregistrasi',
                    'apd.tglkeluar',
                    'apd.iscppt_perawat',
                    'apd.iscppt_dokter'
                )
                ->where(
                    'apd.noregistrasifk',
                    $registrasi->norec_pd
                )
                ->where(
                    'apd.statusenabled',
                    true
                );

            if ($ruanganReservasi > 0) {
                $apdQuery->where(
                    'apd.objectruanganfk',
                    $ruanganReservasi
                );
            }

            $apd = $apdQuery
                ->orderByRaw("
                CASE
                    WHEN apd.tglkeluar IS NULL
                         AND LOWER(
                            COALESCE(apd.status, '')
                         ) NOT LIKE '%selesai%'
                    THEN 0
                    ELSE 1
                END ASC
            ")
                ->orderByDesc('apd.tglregistrasi')
                ->first();
        }

        /*
    |--------------------------------------------------------------------------
    | Fallback Nama Ruangan / Dokter
    |--------------------------------------------------------------------------
    |
    | Jika join APR tidak memperoleh nama ruangan/dokter, gunakan referensi
    | dari APD/PD agar response tidak menghasilkan null padahal FK tersedia.
    |
    */
        $ruanganAktif = (int) (
            data_get($apd, 'objectruanganfk')
            ?: $ruanganReservasi
            ?: data_get($registrasi, 'objectruanganlastfk')
            ?: 0
        );

        if (
            empty($reservasi->namaruangan) &&
            $ruanganAktif > 0
        ) {
            $reservasi->namaruangan = DB::connection('pgsql')
                ->table('ruangan_m')
                ->where('id', $ruanganAktif)
                ->value('namaruangan');
        }

        $dokterAktif = (int) (
            data_get($apd, 'objectpegawaifk')
            ?: data_get($registrasi, 'objectpegawaifk')
            ?: ($reservasi->objectpegawaifk ?? 0)
        );

        if (
            empty($reservasi->dokter) &&
            $dokterAktif > 0
        ) {
            $reservasi->dokter = DB::connection('pgsql')
                ->table('pegawai_m')
                ->where('id', $dokterAktif)
                ->value('namalengkap');
        }

        /*
    |--------------------------------------------------------------------------
    | Perhitungan Antrean
    |--------------------------------------------------------------------------
    |
    | Prinsip:
    | 1. Pasien sendiri tidak boleh ikut dihitung.
    | 2. Pasien yang sedang dilayani tidak dihitung sebagai "di depan".
    | 3. Reservasi yang sudah menjadi registrasi tidak dihitung dua kali.
    | 4. Hitung berdasarkan record unik:
    |    - APR belum registrasi
    |    - PD/APD dari reservasi
    |    - PD/APD registrasi langsung
    |
    */
        $noAntrianPasien = (int) (
            data_get($apd, 'noantrian')
            ?: ($reservasi->noantrian ?? 0)
        );

        /*
    |--------------------------------------------------------------------------
    | Cari Pasien Yang Sedang Dilayani
    |--------------------------------------------------------------------------
    */
        $sedangDilayani = null;

        if (
            $ruanganAktif > 0 &&
            $noAntrianPasien > 0
        ) {
            $sedangDilayani = DB::connection('pgsql')
                ->table('antrianpasiendiperiksa_t as apd_now')
                ->join(
                    'pasiendaftar_t as pd_now',
                    'pd_now.norec',
                    '=',
                    'apd_now.noregistrasifk'
                )
                ->leftJoin(
                    'pasien_m as pm_now',
                    'pm_now.id',
                    '=',
                    'pd_now.nocmfk'
                )
                ->select(
                    'apd_now.norec as norec_apd',
                    'apd_now.noantrian',
                    'apd_now.status',
                    'pm_now.namapasien',
                    'pd_now.istindakan',
                    'apd_now.iskonsul'
                )
                ->where('apd_now.statusenabled', true)
                ->where('pd_now.statusenabled', true)
                ->where('apd_now.objectruanganfk', $ruanganAktif)
                ->whereDate('pd_now.tglregistrasi', $tanggalReservasi)
                ->whereNull('pd_now.tglclosing')
                ->whereNull('apd_now.tglkeluar')
                ->where(
                    'apd_now.noantrian',
                    '<',
                    $noAntrianPasien
                )
                ->where(function ($status) {
                    $status
                        ->whereRaw("
                        LOWER(
                            COALESCE(apd_now.status, '')
                        ) IN (
                            'sedang diperiksa',
                            'sedang dilayani',
                            'dipanggil',
                            'sedang pelayanan'
                        )
                    ")
                        ->orWhereRaw("
                            LOWER(
                                TRIM(
                                    COALESCE(
                                        pd_now.istindakan::text,
                                        ''
                                    )
                                )
                            ) IN (
                                '1',
                                'true',
                                't',
                                'yes',
                                'y'
                            )
                        ")
                        ->orWhereRaw("
                            LOWER(
                                TRIM(
                                    COALESCE(
                                        apd_now.iskonsul::text,
                                        ''
                                    )
                                )
                            ) IN (
                                '1',
                                'true',
                                't',
                                'yes',
                                'y'
                            )
                        ");
                })
                ->orderByDesc('apd_now.noantrian')
                ->first();
        }

        $noAntrianSedangDilayani = (int) (
            data_get($sedangDilayani, 'noantrian') ?: 0
        );

        /*
    |--------------------------------------------------------------------------
    | Helper Range Antrean
    |--------------------------------------------------------------------------
    */
        $applyRangeAntrean = static function (
            $query,
            string $kolomNoAntrian,
            int $noAntrianPasien,
            int $noAntrianSedangDilayani
        ) {
            $query->where(
                $kolomNoAntrian,
                '<',
                $noAntrianPasien
            );

            if ($noAntrianSedangDilayani > 0) {
                $query->where(
                    $kolomNoAntrian,
                    '>',
                    $noAntrianSedangDilayani
                );
            }

            return $query;
        };

        $reservasiBelumTeregistrasi = 0;
        $registrasiDariReservasi = 0;
        $registrasiLangsung = 0;

        if (
            $ruanganAktif > 0 &&
            $noAntrianPasien > 0
        ) {
            /*
        |--------------------------------------------------------------------------
        | 1. Reservasi Belum Teregistrasi
        |--------------------------------------------------------------------------
        |
        | NOT EXISTS PD memastikan reservasi yang sudah registrasi tidak
        | dihitung lagi sebagai APR.
        |
        */
            $queryReservasiBelumRegistrasi =
                DB::connection('pgsql')
                ->table('antrianpasienregistrasi_t as apr_q')
                ->where('apr_q.statusenabled', true)
                ->where('apr_q.kdprofile', $kdProfile)
                ->where(
                    'apr_q.objectruanganfk',
                    $ruanganAktif
                )
                ->whereDate(
                    'apr_q.tanggalreservasi',
                    $tanggalReservasi
                )
                ->whereNotNull('apr_q.noreservasi')
                ->where('apr_q.noreservasi', '!=', '-')
                ->whereNotNull('apr_q.noantrian')
                ->whereNotExists(function ($sub) {
                    $sub
                        ->select(DB::raw(1))
                        ->from('pasiendaftar_t as pd_q')
                        ->whereColumn(
                            'pd_q.antrianpasienregistrasifk',
                            'apr_q.norec'
                        )
                        ->where(
                            'pd_q.statusenabled',
                            true
                        );
                });

            /*
             * Pada registrasi langsung norec APR bisa NULL.
             * Jangan membuat kondisi "apr_q.norec != NULL" karena PostgreSQL
             * akan menghasilkan UNKNOWN dan seluruh APR lain ikut hilang.
             */
            if (!empty(data_get($reservasi, 'norec'))) {
                $queryReservasiBelumRegistrasi->where(
                    'apr_q.norec',
                    '!=',
                    data_get($reservasi, 'norec')
                );
            }

            $applyRangeAntrean(
                $queryReservasiBelumRegistrasi,
                'apr_q.noantrian',
                $noAntrianPasien,
                $noAntrianSedangDilayani
            );

            $reservasiBelumTeregistrasi = (int)
            $queryReservasiBelumRegistrasi
                ->distinct()
                ->count('apr_q.norec');

            /*
        |--------------------------------------------------------------------------
        | Query Dasar Registrasi/APD Yang Masih Menunggu
        |--------------------------------------------------------------------------
        */
            $buatQueryRegistrasi = function () use (
                $ruanganAktif,
                $tanggalReservasi,
                $noAntrianPasien,
                $noAntrianSedangDilayani,
                $applyRangeAntrean,
                $registrasi,
                $apd
            ) {
                $query = DB::connection('pgsql')
                    ->table('antrianpasiendiperiksa_t as apd_q')
                    ->join(
                        'pasiendaftar_t as pd_q',
                        'pd_q.norec',
                        '=',
                        'apd_q.noregistrasifk'
                    )
                    ->where('apd_q.statusenabled', true)
                    ->where('pd_q.statusenabled', true)
                    ->where(
                        'apd_q.objectruanganfk',
                        $ruanganAktif
                    )
                    ->whereDate(
                        'pd_q.tglregistrasi',
                        $tanggalReservasi
                    )
                    ->whereNull('pd_q.tglclosing')
                    ->whereNull('apd_q.tglkeluar')
                    ->whereNotNull('apd_q.noantrian')
                    ->whereRaw("
                    LOWER(
                        COALESCE(apd_q.status, '')
                    ) NOT LIKE '%selesai%'
                ");

                if (
                    $registrasi &&
                    !empty($registrasi->norec_pd)
                ) {
                    $query->where(
                        'pd_q.norec',
                        '!=',
                        $registrasi->norec_pd
                    );
                }

                if (
                    $apd &&
                    !empty($apd->norec_apd)
                ) {
                    $query->where(
                        'apd_q.norec',
                        '!=',
                        $apd->norec_apd
                    );
                }

                $applyRangeAntrean(
                    $query,
                    'apd_q.noantrian',
                    $noAntrianPasien,
                    $noAntrianSedangDilayani
                );

                return $query;
            };

            /*
        |--------------------------------------------------------------------------
        | 2. Registrasi Dari Reservasi
        |--------------------------------------------------------------------------
        */
            $queryDariReservasi = $buatQueryRegistrasi()
                ->whereNotNull(
                    'pd_q.antrianpasienregistrasifk'
                );

            $registrasiDariReservasi = (int)
            $queryDariReservasi
                ->distinct()
                ->count('pd_q.norec');

            /*
        |--------------------------------------------------------------------------
        | 3. Registrasi Langsung
        |--------------------------------------------------------------------------
        */
            $queryRegistrasiLangsung = $buatQueryRegistrasi()
                ->whereNull(
                    'pd_q.antrianpasienregistrasifk'
                );

            $registrasiLangsung = (int)
            $queryRegistrasiLangsung
                ->distinct()
                ->count('pd_q.norec');
        }

        /*
    |--------------------------------------------------------------------------
    | Rekap Antrean Unik
    |--------------------------------------------------------------------------
    */
        $totalReservasi =
            $reservasiBelumTeregistrasi
            + $registrasiDariReservasi;

        $totalTeregistrasi =
            $registrasiDariReservasi
            + $registrasiLangsung;

        $totalAntreanDepan =
            $reservasiBelumTeregistrasi
            + $registrasiDariReservasi
            + $registrasiLangsung;

        /*
    |--------------------------------------------------------------------------
    | Helper Boolean
    |--------------------------------------------------------------------------
    */
        $nilaiAktif = static function ($value): bool {
            if ($value === null) {
                return false;
            }

            if (is_bool($value)) {
                return $value;
            }

            if (
                is_int($value) ||
                is_float($value)
            ) {
                return (int) $value !== 0;
            }

            $value = strtolower(
                trim(
                    (string) $value
                )
            );

            return !in_array(
                $value,
                [
                    '',
                    '0',
                    'false',
                    'f',
                    'no',
                    'n',
                    'null',
                ],
                true
            );
        };

        /*
    |--------------------------------------------------------------------------
    | Tentukan Status Pasien
    |--------------------------------------------------------------------------
    */
        if (!$registrasi) {
            $statusPasien =
                'Belum Teregistrasi';

            $labelStatus =
                'Belum Teregistrasi';

            $classStatus =
                'is-info';
        } else {
            $tglClosing = data_get(
                $registrasi,
                'tglclosing'
            );

            $isTindakan = $nilaiAktif(
                data_get(
                    $registrasi,
                    'istindakan'
                )
            );

            $isAsmed = $nilaiAktif(
                data_get(
                    $registrasi,
                    'isasmed'
                )
            );

            $isCpptDokter = $nilaiAktif(
                data_get(
                    $apd,
                    'iscppt_dokter'
                )
            );

            $isKonsul = $nilaiAktif(
                data_get(
                    $apd,
                    'konsul'
                )
            );

            /*
        |--------------------------------------------------------------------------
        | Sudah Closing
        |--------------------------------------------------------------------------
        */
            if (!empty($tglClosing)) {
                $statusPasien =
                    'Selesai Dilayani';

                $labelStatus =
                    'Sudah Closing';

                $classStatus =
                    'is-success';

                /*
        |--------------------------------------------------------------------------
        | Selesai Pemeriksaan
        |--------------------------------------------------------------------------
        */
            } elseif (
                $isAsmed ||
                $isCpptDokter
            ) {
                $statusPasien =
                    'Selesai Dilayani';

                $labelStatus =
                    'Selesai';

                $classStatus =
                    'is-warning';

                /*
        |--------------------------------------------------------------------------
        | Sedang Diperiksa
        |--------------------------------------------------------------------------
        */
            } elseif (
                $isTindakan ||
                $isKonsul
            ) {
                $statusPasien =
                    'Sedang Diperiksa';

                $labelStatus =
                    'Sedang Diperiksa';

                $classStatus =
                    'is-primary';

                /*
        |--------------------------------------------------------------------------
        | Sudah Registrasi Tetapi APD Belum Ada
        |--------------------------------------------------------------------------
        */
            } elseif (!$apd) {
                $statusPasien =
                    'Sudah Teregistrasi';

                $labelStatus =
                    'Sudah Teregistrasi';

                $classStatus =
                    'is-info';

                /*
        |--------------------------------------------------------------------------
        | Menunggu Pelayanan
        |--------------------------------------------------------------------------
        */
            } else {
                $statusPasien =
                    'Menunggu Pelayanan';

                $labelStatus =
                    'Menunggu Pelayanan';

                $classStatus =
                    'is-danger';
            }
        }

        /*
    |--------------------------------------------------------------------------
    | Informasi Registrasi
    |--------------------------------------------------------------------------
    */
        $reservasi->sudah_teregistrasi =
            $registrasi ? true : false;

        $reservasi->status_registrasi =
            $registrasi
            ? 'Sudah Teregistrasi'
            : 'Belum Teregistrasi';

        $reservasi->asal_registrasi =
            $registrasi
            ? (
                !empty(data_get(
                    $registrasi,
                    'antrianpasienregistrasifk'
                ))
                ? 'Dari Reservasi'
                : 'Registrasi Langsung'
            )
            : 'Belum Teregistrasi';

        /*
    |--------------------------------------------------------------------------
    | Identitas Registrasi
    |--------------------------------------------------------------------------
    */
        $reservasi->noregistrasi = data_get(
            $registrasi,
            'noregistrasi'
        );

        $reservasi->norec_pd = data_get(
            $registrasi,
            'norec_pd'
        );

        $reservasi->norec_apd = data_get(
            $apd,
            'norec_apd'
        );

        /*
    |--------------------------------------------------------------------------
    | Status Pasien
    |--------------------------------------------------------------------------
    */
        $reservasi->status_pasien =
            $statusPasien;

        $reservasi->label_statusperiksa =
            $labelStatus;

        $reservasi->class_statusperiksa =
            $classStatus;

        /*
    |--------------------------------------------------------------------------
    | Status Asli APD
    |--------------------------------------------------------------------------
    */
        $reservasi->status_asli = data_get(
            $apd,
            'status'
        );

        /*
    |--------------------------------------------------------------------------
    | Informasi Antrean
    |--------------------------------------------------------------------------
    */
        $reservasi->tanggal_antrean =
            $tanggalReservasi;

        $reservasi->data_hari_ini =
            $tanggalReservasi === now()->format('Y-m-d');

        /*
         * Penanda sumber data untuk debugging:
         * - reservasi   : berasal dari antrianpasienregistrasi_t (APR)
         * - registrasi : fallback dari pasiendaftar_t + antrianpasiendiperiksa_t
         */
        $reservasi->sumber_data = $sumberData;

        /*
         * Pastikan field antrianloket selalu tersedia pada response.
         * APR sudah menghasilkan field ini dari SQL. Bagian ini menjadi
         * safety fallback bila query/versi data lama belum membawanya.
         */
        if (empty($reservasi->antrianloket)) {
            $jenisLoket = trim((string) data_get($reservasi, 'jenis', ''));
            $nomorLoket = data_get($reservasi, 'noantrian');

            /*
             * Jangan pernah membentuk antrean loket dari nomor APD saja.
             * apd.noantrian adalah antrean poli. Antrean loket membutuhkan
             * jenis APR + nomor antrean APR.
             */
            if (
                $jenisLoket !== ''
                && $nomorLoket !== null
                && $nomorLoket !== ''
            ) {
                $nomorLoketText = trim((string) $nomorLoket);

                if (ctype_digit($nomorLoketText)) {
                    $reservasi->antrianloket =
                        $jenisLoket
                        . str_pad($nomorLoketText, 3, '0', STR_PAD_LEFT);
                } else {
                    $reservasi->antrianloket =
                        $jenisLoket . $nomorLoketText;
                }
            } else {
                $reservasi->antrianloket = null;
            }
        }

        $reservasi->ruanganfk =
            $ruanganAktif > 0
            ? $ruanganAktif
            : null;

        $reservasi->norec_apr =
            $reservasi->norec ?? null;

        $reservasi->noantrian_apd = data_get(
            $apd,
            'noantrian'
        );

        $reservasi->sisa_pasien_di_depan =
            $totalAntreanDepan;

        $reservasi->sisa_reservasi_di_depan =
            $totalReservasi;

        $reservasi->sisa_teregistrasi_di_depan =
            $totalTeregistrasi;

        $reservasi->rincian_antrean_di_depan = [
            'reservasi_belum_teregistrasi' =>
            $reservasiBelumTeregistrasi,

            'registrasi_dari_reservasi' =>
            $registrasiDariReservasi,

            'registrasi_langsung' =>
            $registrasiLangsung,

            /*
        | Statistik overlap:
        | total_reservasi dan total_teregistrasi tidak boleh
        | dijumlahkan untuk mendapatkan total antrean.
        */
            'total_reservasi' =>
            $totalReservasi,

            'total_teregistrasi' =>
            $totalTeregistrasi,

            'belum_teregistrasi' =>
            $reservasiBelumTeregistrasi,

            'sudah_teregistrasi' =>
            $totalTeregistrasi,

            'masih_reservasi' =>
            $reservasiBelumTeregistrasi,

            /*
        | Total unik antrean di depan.
        */
            'total' =>
            $totalAntreanDepan,
        ];

        $reservasi->sedang_dilayani =
            $sedangDilayani
            ? [
                'noantrian' => data_get(
                    $sedangDilayani,
                    'noantrian'
                ),

                'noantrian_apd' => data_get(
                    $sedangDilayani,
                    'noantrian'
                ),

                'namapasien' => data_get(
                    $sedangDilayani,
                    'namapasien'
                ),

                'label_statusperiksa' =>
                'Sedang Diperiksa',

                'status' =>
                'Sedang Diperiksa',
            ]
            : null;

        /*
    |--------------------------------------------------------------------------
    | Informasi Proses Pemeriksaan
    |--------------------------------------------------------------------------
    */
        $reservasi->tglregistrasi = data_get(
            $registrasi,
            'tglregistrasi'
        );

        $reservasi->tglclosing = data_get(
            $registrasi,
            'tglclosing'
        );

        $reservasi->istindakan = data_get(
            $registrasi,
            'istindakan'
        );

        $reservasi->isasmed = data_get(
            $registrasi,
            'isasmed'
        );

        $reservasi->iscppt_dokter = data_get(
            $apd,
            'iscppt_dokter'
        );

        $reservasi->konsul = data_get(
            $apd,
            'konsul'
        );

        /*
    |--------------------------------------------------------------------------
    | INFORMASI RESEP / FARMASI
    |--------------------------------------------------------------------------
    */

        /*
    | Apakah ada resep
    */
        $reservasi->ada_resep =
            $resep ? true : false;

        /*
    | No antrean farmasi
    */
        $reservasi->aanoantri = data_get(
            $resep,
            'aanoantri'
        );

        $reservasi->noantrian_resep = data_get(
            $resep,
            'aanoantri'
        );

        /*
    |--------------------------------------------------------------------------
    | Jenis Resep
    |--------------------------------------------------------------------------
    |
    | A = Non Racikan
    | B = Racikan
    |
    */
        $kodeJenisResep = strtoupper(
            trim(
                (string) data_get(
                    $resep,
                    'aajenis',
                    ''
                )
            )
        );

        $reservasi->aajenis =
            $kodeJenisResep !== ''
            ? $kodeJenisResep
            : null;

        if ($kodeJenisResep === 'A') {
            $reservasi->jenis_resep =
                'Non Racikan';
        } elseif ($kodeJenisResep === 'B') {
            $reservasi->jenis_resep =
                'Racikan';
        } else {
            $reservasi->jenis_resep =
                $kodeJenisResep !== ''
                ? $kodeJenisResep
                : null;
        }

        /*
    |--------------------------------------------------------------------------
    | Status Resep
    |--------------------------------------------------------------------------
    */
        $reservasi->status_order_id = data_get(
            $resep,
            'status_order_id'
        );

        $reservasi->statusorder = data_get(
            $resep,
            'statusorder'
        );

        $reservasi->status_resep = data_get(
            $resep,
            'statusorder'
        );

        /*
    |--------------------------------------------------------------------------
    | Tanggal Resep
    |--------------------------------------------------------------------------
    */
        $reservasi->tglresep = data_get(
            $resep,
            'tglresep'
        );

        /*
    |--------------------------------------------------------------------------
    | Riwayat Resep
    |--------------------------------------------------------------------------
    */
        $reservasi->riwayat_resep =
            $riwayatResep
            ->map(function ($item) {
                $kode = strtoupper(
                    trim(
                        (string) data_get(
                            $item,
                            'aajenis',
                            ''
                        )
                    )
                );

                if ($kode === 'A') {
                    $jenisResep =
                        'Non Racikan';
                } elseif ($kode === 'B') {
                    $jenisResep =
                        'Racikan';
                } else {
                    $jenisResep =
                        $kode !== ''
                        ? $kode
                        : null;
                }

                return [
                    'aanoantri' => data_get(
                        $item,
                        'aanoantri'
                    ),

                    'noantrian_resep' => data_get(
                        $item,
                        'aanoantri'
                    ),

                    'aajenis' =>
                    $kode !== ''
                        ? $kode
                        : null,

                    'jenis_resep' =>
                    $jenisResep,

                    'status_order_id' => data_get(
                        $item,
                        'status_order_id'
                    ),

                    'statusorder' => data_get(
                        $item,
                        'statusorder'
                    ),

                    'status_resep' => data_get(
                        $item,
                        'statusorder'
                    ),

                    'tglresep' => data_get(
                        $item,
                        'tglresep'
                    ),
                ];
            })

            ->values();

        /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */
        return response()->json([
            'total' => 1,

            'data' => [
                $reservasi,
            ],

            'as' => '@epic',
        ], 200);
    }
}
