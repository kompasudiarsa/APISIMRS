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

        /*
    |--------------------------------------------------------------------------
    | Subquery Jadwal Dokter
    |--------------------------------------------------------------------------
    |
    | Satu jadwal per:
    | - tanggal
    | - ruangan
    | - dokter
    |
    | Tujuannya agar reservasi tidak muncul duplikat apabila pada tabel
    | jadwaldokter_m terdapat lebih dari satu jadwal dokter di tanggal,
    | ruangan, dan dokter yang sama.
    |
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
            ->groupByRaw('CAST(tanggal AS DATE)');

        /*
    |--------------------------------------------------------------------------
    | Query Utama Reservasi
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

            /*
        |--------------------------------------------------------------------------
        | Join Jadwal Dokter
        |--------------------------------------------------------------------------
        |
        | Dibanding versi sebelumnya:
        |
        | sebelumnya:
        | ruangan + tanggal
        |
        | sekarang:
        | ruangan + dokter + tanggal
        |
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

            /*
        |--------------------------------------------------------------------------
        | Select
        |--------------------------------------------------------------------------
        */
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
            | Nomor Identitas
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
            | Nomor BPJS
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
            | Nomor Telepon
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
}
