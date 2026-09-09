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

    public function getDataRiwayatReservasi(Request $request, $kdProfile = 1)
    {
        $kdProfile = (int) ($kdProfile ?: 1);

        $noreservasi = trim((string) $request->input('noReservasi', ''));
        $nocmnama = trim((string) $request->input('rm', ''));
        $tgllahir = trim((string) $request->input('tgllahir', ''));

        /*
    |--------------------------------------------------------------------------
    | Tanggal hari ini
    |--------------------------------------------------------------------------
    */
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
            ->groupByRaw('CAST(tanggal AS DATE)');

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

                /*
            |--------------------------------------------------------------------------
            | Tanggal tanpa jam
            |--------------------------------------------------------------------------
            */
                DB::raw("
                CAST(apr.tanggalreservasi AS DATE)
                AS tanggalreservasi
            "),

                /*
            |--------------------------------------------------------------------------
            | Jam layanan dokter
            |--------------------------------------------------------------------------
            */
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

                'apr.isconfirm',

                'apr.noantrian',

                'apr.noantrianpoli',

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

                'apr.jenis',

                /*
            |--------------------------------------------------------------------------
            | Status reservasi
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
            | Tempat lahir
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
            ->whereNotNull('apr.noreservasi')

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
        | Jangan tampilkan reservasi yang pelayanannya sudah selesai
        |--------------------------------------------------------------------------
        |
        | status_pasien dan label_statusperiksa bukan kolom database.
        | Keduanya dibentuk dari:
        | - pd.tglclosing       => Sudah Closing / Selesai Dilayani
        | - pd.isasmed          => Selesai Dilayani
        | - apd.iscppt_dokter   => Selesai Dilayani
        |
        | Karena itu filter dilakukan terhadap sumber statusnya.
        |
        */
            ->whereNotExists(function ($query) {
                $query
                    ->select(DB::raw(1))
                    ->from('pasiendaftar_t as pd_done')
                    ->whereColumn(
                        'pd_done.antrianpasienregistrasifk',
                        'apr.norec'
                    )
                    ->where(
                        'pd_done.statusenabled',
                        true
                    )
                    ->where(function ($status) {
                        $status
                            ->whereNotNull(
                                'pd_done.tglclosing'
                            )
                            ->orWhere(
                                'pd_done.isasmed',
                                true
                            );
                    });
            })

            ->whereNotExists(function ($query) {
                $query
                    ->select(DB::raw(1))
                    ->from('pasiendaftar_t as pd_cppt')
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
                            ->where(
                                'apd_done.iscppt_dokter',
                                true
                            )
                            ->orWhereRaw(
                                "LOWER(COALESCE(apd_done.status, '')) = 'selesai'"
                            );
                    });
            })

            /*
        |--------------------------------------------------------------------------
        | Hari ini dan berikutnya
        |--------------------------------------------------------------------------
        */
            ->whereRaw(
                'CAST(apr.tanggalreservasi AS DATE) >= ?',
                [$tanggalHariIni]
            );

        /*
    |--------------------------------------------------------------------------
    | Filter tanggal lahir
    |--------------------------------------------------------------------------
    */
        if (
            $tgllahir !== '' &&
            $tgllahir !== 'undefined' &&
            $tgllahir !== 'null' &&
            $tgllahir !== 'Invalid date'
        ) {
            $data->where(function ($query) use ($tgllahir) {
                $query
                    ->whereDate(
                        'pm.tgllahir',
                        $tgllahir
                    )
                    ->orWhereDate(
                        'apr.tgllahir',
                        $tgllahir
                    );
            });
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
            $data->where(function ($query) use ($nocmnama) {
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
            });
        }

        /*
    |--------------------------------------------------------------------------
    | Filter nomor reservasi
    |--------------------------------------------------------------------------
    */
        if (
            $noreservasi !== '' &&
            $noreservasi !== 'undefined' &&
            $noreservasi !== 'null'
        ) {
            $data->where(function ($query) use ($noreservasi) {
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
            });
        }

        /*
    |--------------------------------------------------------------------------
    | Ambil hanya 1 reservasi terdekat
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
    | Tidak ada reservasi
    |--------------------------------------------------------------------------
    */
        if (!$reservasi) {
            return response()->json([
                'total' => 0,
                'data' => [],
                'as' => '@epic',
            ], 200);
        }

        /*
    |--------------------------------------------------------------------------
    | Cari Registrasi / PD
    |--------------------------------------------------------------------------
    |
    | Prioritas:
    | 1. PD yang langsung terhubung dengan APR
    | 2. Fallback PD pasien pada tanggal yang sama
    |
    */

        $tanggalReservasi = (string) $reservasi->tanggalreservasi;

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
            ->where(
                'pd.statusenabled',
                true
            )
            ->where(
                'pd.antrianpasienregistrasifk',
                $reservasi->norec
            )
            ->orderByDesc(
                'pd.tglregistrasi'
            )
            ->first();

        /*
    |--------------------------------------------------------------------------
    | Fallback registrasi langsung
    |--------------------------------------------------------------------------
    |
    | Ini mengikuti konsep cekAntreanPasien() yang juga dapat mengenali
    | registrasi pasien walaupun tidak mempunyai FK APR.
    |
    */
        if (!$registrasi) {
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
                ->where(
                    'pd.statusenabled',
                    true
                )
                ->where(
                    'pd.nocmfk',
                    $reservasi->nocmfk
                )
                ->whereDate(
                    'pd.tglregistrasi',
                    $tanggalReservasi
                )
                ->orderByDesc(
                    'pd.tglregistrasi'
                )
                ->first();
        }

        /*
    |--------------------------------------------------------------------------
    | Cari APD
    |--------------------------------------------------------------------------
    */
        $apd = null;

        if ($registrasi) {
            $ruanganReservasi = (int) (
                $reservasi->objectruanganfk ?? 0
            );

            $apd = DB::connection('pgsql')
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
                )

                /*
            |--------------------------------------------------------------------------
            | Prioritaskan APD aktif
            |--------------------------------------------------------------------------
            */
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

                /*
            |--------------------------------------------------------------------------
            | Prioritaskan ruangan reservasi
            |--------------------------------------------------------------------------
            */
                ->orderByRaw(
                    '
                CASE
                    WHEN apd.objectruanganfk = ?
                    THEN 0
                    ELSE 1
                END ASC
                ',
                    [
                        $ruanganReservasi > 0
                            ? $ruanganReservasi
                            : -1,
                    ]
                )

                ->orderByDesc(
                    'apd.tglregistrasi'
                )

                ->first();
        }

        /*
    |--------------------------------------------------------------------------
    | Helper Boolean
    |--------------------------------------------------------------------------
    |
    | Mengikuti fungsi cekAntreanPasien karena beberapa field dapat berisi:
    |
    | true / false
    | 1 / 0
    | t / f
    | string
    |
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
                trim((string) $value)
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
    |
    | Urutan mengikuti cekAntreanPasien:
    |
    | 1. Sudah Closing
    | 2. Selesai
    | 3. Sedang Diperiksa
    | 4. Sudah Teregistrasi
    | 5. Menunggu Pelayanan
    |
    */

        if (!$registrasi) {

            /*
        |--------------------------------------------------------------------------
        | Belum masuk pasiendaftar_t
        |--------------------------------------------------------------------------
        */
            $statusPasien = 'Belum Teregistrasi';
            $labelStatus = 'Belum Teregistrasi';
            $classStatus = 'is-info';
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
        | 1. Sudah Closing
        |--------------------------------------------------------------------------
        */
            if (!empty($tglClosing)) {

                $statusPasien = 'Selesai Dilayani';
                $labelStatus = 'Sudah Closing';
                $classStatus = 'is-success';

                /*
        |--------------------------------------------------------------------------
        | 2. Selesai
        |--------------------------------------------------------------------------
        */
            } elseif (
                $isAsmed ||
                $isCpptDokter
            ) {

                $statusPasien = 'Selesai Dilayani';
                $labelStatus = 'Selesai';
                $classStatus = 'is-warning';

                /*
        |--------------------------------------------------------------------------
        | 3. Sedang diperiksa
        |--------------------------------------------------------------------------
        */
            } elseif (
                $isTindakan ||
                $isKonsul
            ) {

                $statusPasien = 'Sedang Diperiksa';
                $labelStatus = 'Sedang Diperiksa';
                $classStatus = 'is-primary';

                /*
        |--------------------------------------------------------------------------
        | 4. Sudah registrasi tetapi APD belum dibuat
        |--------------------------------------------------------------------------
        */
            } elseif (!$apd) {

                $statusPasien = 'Sudah Teregistrasi';
                $labelStatus = 'Sudah Teregistrasi';
                $classStatus = 'is-info';

                /*
        |--------------------------------------------------------------------------
        | 5. APD sudah ada, menunggu pelayanan
        |--------------------------------------------------------------------------
        */
            } else {

                $statusPasien = 'Menunggu Pelayanan';
                $labelStatus = 'Menunggu Pelayanan';
                $classStatus = 'is-danger';
            }
        }

        /*
    |--------------------------------------------------------------------------
    | Tambahkan informasi registrasi
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
    | Status asli APD
    |--------------------------------------------------------------------------
    */
        $reservasi->status_asli = data_get(
            $apd,
            'status'
        );

        /*
    |--------------------------------------------------------------------------
    | Informasi proses pemeriksaan
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
    | Response
    |--------------------------------------------------------------------------
    |
    | Tetap array agar kompatibel dengan response sebelumnya:
    |
    | data: [ {...} ]
    |
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
