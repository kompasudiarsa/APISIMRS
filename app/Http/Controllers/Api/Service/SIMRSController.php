<?php

namespace App\Http\Controllers\Api\Service;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class SIMRSController extends Controller
{
    //

    public function GetData(Request $request)
    {
        $data = DB::connection('pgsql')
            ->table('pasien_m')
            ->limit(10)
            ->get();
        return response()->json([
            'status' => true,
            'message' => 'Data berhasil diambil',
            'data' => $data
        ]);
    }
    public function cekAntreanPasien(Request $request)
    {

        $validated = $request->validate([
            'rm' => ['required', 'string', 'max:30'],
            'tanggal_lahir' => ['required', 'date_format:Y-m-d'],
            'tanggal_antrean' => ['nullable', 'date_format:Y-m-d'],
        ], [
            'rm.required' => 'Nomor RM, NIK, atau nomor BPJS wajib diisi.',
            'tanggal_lahir.required' => 'Tanggal lahir wajib diisi.',
            'tanggal_lahir.date_format' =>
            'Format tanggal lahir harus YYYY-MM-DD.',
            'tanggal_antrean.date_format' =>
            'Format tanggal antrean harus YYYY-MM-DD.',
        ]);

        $keyword = trim($validated['rm']);
        $tanggalLahir = $validated['tanggal_lahir'];
        $tanggalAntreanDiminta = $validated['tanggal_antrean'] ?? null;
        $tanggalAntrean = $tanggalAntreanDiminta
            ?: Carbon::today()->format('Y-m-d');

        /*
        |--------------------------------------------------------------------------
        | 1. Cari pasien
        |--------------------------------------------------------------------------
        */

        $pasien = DB::connection('pgsql')->table('pasien_m as ps')
            ->leftJoin(
                'jeniskelamin_m as jk',
                'jk.id',
                '=',
                'ps.objectjeniskelaminfk'
            )
            ->select(
                'ps.id as nocmfk',
                'ps.nocm',
                'ps.namapasien',
                'ps.nobpjs',
                'ps.noidentitas',
                'ps.tgllahir',
                'ps.notelepon',
                'ps.tempatlahir',
                'ps.objectjeniskelaminfk',
                'jk.jeniskelamin'
            )
            ->where('ps.statusenabled', true)
            ->whereDate('ps.tgllahir', $tanggalLahir)
            ->where(function ($query) use ($keyword) {
                $query->where('ps.nocm', $keyword)
                    ->orWhere('ps.noidentitas', $keyword)
                    ->orWhere('ps.nobpjs', $keyword);
            })
            ->first();

        if (!$pasien) {
            return response()->json([
                'metaData' => [
                    'code' => 404,
                    'message' => 'Data pasien tidak ditemukan.',
                ],
                'response' => null,
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Cari tanggal antrean, reservasi, registrasi, dan APD pasien
        |--------------------------------------------------------------------------
        |
        | Registrasi (PD) dan antrean pemeriksaan (APD) dicari terpisah.
        | Dengan demikian pasien registrasi langsung tanpa APR tetap ditemukan.
        |
        | Jika tanggal_antrean tidak dikirim dan tidak ada data hari ini, sistem
        | menggunakan tanggal reservasi/registrasi terakhir pasien.
        |
        */

        $ambilReservasiPasien = function (string $tanggal) use ($pasien) {
            return DB::connection('pgsql')->table('antrianpasienregistrasi_t as apr')
                ->join(
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
                ->leftJoin('pasiendaftar_t as pd', function ($join) {
                    $join->on(
                        'pd.antrianpasienregistrasifk',
                        '=',
                        'apr.norec'
                    )->where('pd.statusenabled', '=', true);
                })
                ->select(
                    'apr.norec as norec_apr',
                    'apr.nocmfk',
                    'apr.objectruanganfk as ruanganfk',
                    'apr.objectpegawaifk as dokterfk',
                    'apr.noreservasi',
                    'apr.tanggalreservasi',
                    'apr.noantrianpoli',
                    'apr.noantrian as nomor_loket',
                    'apr.jenis',
                    'apr.jam',
                    'apr.tglinput',
                    'apr.namapasien as nama_reservasi',
                    'apr.nobpjs as bpjs_reservasi',
                    'ru.namaruangan',
                    'ru.kdinternal as kdpoli',
                    'pg.namalengkap as dokter_reservasi',
                    'pd.norec as norec_pd_terhubung',
                    'pd.noregistrasi as noregistrasi_terhubung',
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
                            ELSE COALESCE(apr.jenis, '')
                                || apr.noantrian::varchar
                        END AS antrianloket
                    "),
                    DB::raw("
                        CASE
                            WHEN apr.noantrianpoli::varchar ~ '^[0-9]+$'
                                THEN apr.noantrianpoli::integer
                            ELSE 0
                        END AS nomor_urut
                    ")
                )
                // ->where('apr.kdprofile', $this->kdProfile)
                ->where('apr.statusenabled', true)
                ->where('apr.nocmfk', $pasien->nocmfk)
                ->whereDate('apr.tanggalreservasi', $tanggal)
                ->where('apr.noreservasi', '!=', '-')
                ->orderByDesc('apr.tglinput')
                ->first();
        };

        $ambilRegistrasiPasien = function (string $tanggal) use ($pasien) {
            return DB::connection('pgsql')->table('pasiendaftar_t as pd')
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
                // ->where('pd.kdprofile', $this->kdProfile)
                ->where('pd.statusenabled', true)
                ->where('pd.nocmfk', $pasien->nocmfk)
                ->whereDate('pd.tglregistrasi', $tanggal)
                ->orderByDesc('pd.tglregistrasi')
                ->first();
        };

        $reservasiPasien = $ambilReservasiPasien($tanggalAntrean);
        $registrasiPasien = $ambilRegistrasiPasien($tanggalAntrean);

        /*
         * Fallback ke tanggal data terakhir hanya ketika tanggal_antrean
         * tidak dikirim secara eksplisit.
         */
        if (
            !$tanggalAntreanDiminta &&
            !$reservasiPasien &&
            !$registrasiPasien
        ) {
            $reservasiTerakhir = DB::table(
                'antrianpasienregistrasi_t as apr'
            )
                ->selectRaw(
                    'DATE(apr.tanggalreservasi) as tanggal'
                )
                // ->where('apr.kdprofile', $this->kdProfile)
                ->where('apr.statusenabled', true)
                ->where('apr.nocmfk', $pasien->nocmfk)
                ->where('apr.noreservasi', '!=', '-')
                ->whereNotNull('apr.tanggalreservasi')
                ->orderByDesc('apr.tanggalreservasi')
                ->first();

            $registrasiTerakhir = DB::table(
                'pasiendaftar_t as pd'
            )
                ->selectRaw(
                    'DATE(pd.tglregistrasi) as tanggal'
                )
                // ->where('pd.kdprofile', $this->kdProfile)
                ->where('pd.statusenabled', true)
                ->where('pd.nocmfk', $pasien->nocmfk)
                ->whereNotNull('pd.tglregistrasi')
                ->orderByDesc('pd.tglregistrasi')
                ->first();

            $tanggalReservasiTerakhir = data_get(
                $reservasiTerakhir,
                'tanggal'
            );

            $tanggalRegistrasiTerakhir = data_get(
                $registrasiTerakhir,
                'tanggal'
            );

            $tanggalKandidat = collect([
                $tanggalReservasiTerakhir,
                $tanggalRegistrasiTerakhir,
            ])
                ->filter()
                ->sortDesc()
                ->first();

            if ($tanggalKandidat) {
                $tanggalAntrean = Carbon::parse(
                    $tanggalKandidat
                )->format('Y-m-d');

                $reservasiPasien = $ambilReservasiPasien(
                    $tanggalAntrean
                );
                $registrasiPasien = $ambilRegistrasiPasien(
                    $tanggalAntrean
                );
            }
        }

        /*
         * Cari APD secara terpisah berdasarkan pd.norec. Prioritas pertama
         * adalah APD yang masih aktif, lalu APD yang sesuai dengan ruangan
         * reservasi atau objectruanganlastfk pada pasiendaftar_t.
         */
        $apdPasien = null;

        if ($registrasiPasien) {
            $ruanganReservasi = (int) data_get(
                $reservasiPasien,
                'ruanganfk',
                0
            );

            $ruanganTerakhirPd = (int) data_get(
                $registrasiPasien,
                'objectruanganlastfk',
                0
            );

            $apdPasien = DB::connection('pgsql')->table(
                'antrianpasiendiperiksa_t as apd'
            )
                ->leftJoin(
                    'ruangan_m as ru',
                    'ru.id',
                    '=',
                    'apd.objectruanganfk'
                )
                ->leftJoin(
                    'pasiendaftar_t as pd_apd',
                    'pd_apd.norec',
                    '=',
                    'apd.noregistrasifk'
                )
                ->leftJoin(
                    'pegawai_m as pg_apd',
                    'pg_apd.id',
                    '=',
                    'apd.objectpegawaifk'
                )
                ->leftJoin(
                    'pegawai_m as pg_pd',
                    'pg_pd.id',
                    '=',
                    'pd_apd.objectpegawaifk'
                )
                ->select(
                    'apd.norec as norec_apd',
                    'apd.noregistrasifk',
                    'apd.objectruanganfk as ruanganfk',
                    'apd.objectpegawaifk',
                    'apd.noantrian',
                    'apd.status',
                    'apd.iskonsul as konsul',
                    'apd.tglregistrasi',
                    'apd.tglkeluar',
                    'apd.iscppt_perawat',
                    'apd.iscppt_dokter',
                    'ru.namaruangan',
                    'ru.kdinternal as kdpoli',
                    DB::raw("
                        COALESCE(
                            pg_pd.namalengkap,
                            pg_apd.namalengkap
                        ) AS dokter
                    "),
                    DB::raw("
                        CASE
                            WHEN apd.noantrian::varchar ~ '^[0-9]+$'
                                THEN apd.noantrian::integer
                            ELSE 0
                        END AS nomor_urut
                    ")
                )
                ->where(
                    'apd.noregistrasifk',
                    $registrasiPasien->norec_pd
                )
                ->where('apd.statusenabled', true)
                ->whereDate('apd.tglregistrasi', $tanggalAntrean)
                ->whereNotNull('apd.noantrian')
                ->orderByRaw("
                    CASE
                        WHEN apd.tglkeluar IS NULL
                             AND LOWER(COALESCE(apd.status, ''))
                                 NOT LIKE '%selesai%'
                            THEN 0
                        ELSE 1
                    END ASC
                ")
                ->orderByRaw(
                    'CASE WHEN apd.objectruanganfk = ? THEN 0 ELSE 1 END ASC',
                    [$ruanganReservasi > 0
                        ? $ruanganReservasi
                        : -1]
                )
                ->orderByRaw(
                    'CASE WHEN apd.objectruanganfk = ? THEN 0 ELSE 1 END ASC',
                    [$ruanganTerakhirPd > 0
                        ? $ruanganTerakhirPd
                        : -1]
                )
                ->orderByDesc('apd.tglregistrasi')
                ->first();

            if ($apdPasien) {
                foreach ((array) $apdPasien as $key => $value) {
                    $registrasiPasien->{$key} = $value;
                }
            }
        }

        if (!$reservasiPasien && !$registrasiPasien) {
            return response()->json([
                'metaData' => [
                    'code' => 404,
                    'message' =>
                    'Pasien tidak memiliki reservasi atau registrasi rawat jalan pada tanggal ' . $tanggalAntrean . '.',
                ],
                'response' => null,
            ], 404);
        }

        $sudahTeregistrasi = !empty($registrasiPasien);

        $ruanganFkPasien = (int) ($sudahTeregistrasi
            ? data_get($registrasiPasien, 'ruanganfk')
            : data_get($reservasiPasien, 'ruanganfk'));

        $nomorAntreanPasien = (int) ($sudahTeregistrasi
            ? data_get($registrasiPasien, 'nomor_urut')
            : data_get($reservasiPasien, 'nomor_urut'));

        if ($ruanganFkPasien <= 0 || $nomorAntreanPasien <= 0) {
            return response()->json([
                'metaData' => [
                    'code' => 404,
                    'message' =>
                    'Poli atau nomor antrean pasien belum tersedia.',
                ],
                'response' => null,
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Ambil pasien yang masih reservasi pada poli yang sama
        |--------------------------------------------------------------------------
        |
        | Hanya APR yang belum mempunyai pasangan aktif pada pasiendaftar_t.
        |
        */

        $dataReservasi = DB::connection('pgsql')->table('antrianpasienregistrasi_t as apr')
            ->leftJoin('pasiendaftar_t as pd', function ($join) {
                $join->on(
                    'pd.antrianpasienregistrasifk',
                    '=',
                    'apr.norec'
                )->where('pd.statusenabled', '=', true);
            })
            ->leftJoin(
                'pasien_m as ps',
                'ps.id',
                '=',
                'apr.nocmfk'
            )
            ->leftJoin(
                'pegawai_m as pg',
                'pg.id',
                '=',
                'apr.objectpegawaifk'
            )
            ->select(
                'apr.norec as norec_apr',
                'apr.nocmfk',
                'apr.objectruanganfk',
                'apr.objectpegawaifk',
                'apr.noreservasi',
                'apr.noantrianpoli',
                'apr.noantrian as nomor_loket',
                'apr.jenis',
                'apr.tanggalreservasi',
                DB::raw("
                COALESCE(
                    apr.namapasien,
                    ps.namapasien
                ) AS namapasien
            "),
                DB::raw("
                COALESCE(
                    apr.nobpjs,
                    ps.nobpjs
                ) AS nobpjs
            "),
                'ps.nocm',
                'pg.namalengkap as dokter',
                DB::raw("'Belum Teregistrasi' AS label_statusperiksa"),
                DB::raw("'is-info' AS class_statusperiksa"),
                DB::raw("'reservasi' AS sumber_antrean"),
                DB::raw("
                CASE
                    WHEN apr.noantrianpoli::varchar ~ '^[0-9]+$'
                        THEN apr.noantrianpoli::integer
                    ELSE 0
                END AS nomor_urut
            ")
            )
            // ->where('apr.kdprofile', $this->kdProfile)
            ->where('apr.statusenabled', true)
            ->where('apr.objectruanganfk', $ruanganFkPasien)
            ->whereDate('apr.tanggalreservasi', $tanggalAntrean)
            ->where('apr.noreservasi', '!=', '-')
            ->whereNotNull('apr.noantrianpoli')
            ->whereNull('pd.norec')
            ->orderByRaw("
            CASE
                WHEN apr.noantrianpoli::varchar ~ '^[0-9]+$'
                    THEN apr.noantrianpoli::integer
                ELSE 0
            END ASC
        ")
            ->get();

        /*
        |--------------------------------------------------------------------------
        | 5. Ambil pasien yang sudah teregistrasi pada poli yang sama
        |--------------------------------------------------------------------------
        |
        | APD dibuat left join agar pasien yang sudah masuk pasiendaftar_t tetapi
        | APD belum terbentuk tetap masuk kategori sudah teregistrasi.
        |
        */

        $dataTeregistrasi = DB::connection('pgsql')
            ->table('antrianpasiendiperiksa_t as apd')
            ->join(
                'pasiendaftar_t as pd',
                'pd.norec',
                '=',
                'apd.noregistrasifk'
            )
            ->join(
                'pasien_m as ps',
                'ps.id',
                '=',
                'pd.nocmfk'
            )
            ->leftJoin(
                'antrianpasienregistrasi_t as apr',
                'apr.norec',
                '=',
                'pd.antrianpasienregistrasifk'
            )
            ->leftJoin(
                'pegawai_m as pg',
                'pg.id',
                '=',
                'apd.objectpegawaifk'
            )
            ->leftJoin(
                'pegawai_m as pg1',
                'pg1.id',
                '=',
                'pd.objectpegawaifk'
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
                'pd.antrianpasienregistrasifk',

                'apd.norec as norec_apd',
                'apd.noantrian',
                'apd.status',
                'apd.iskonsul as konsul',
                'apd.tglkeluar',
                'apd.iscppt_perawat',
                'apd.iscppt_dokter',
                'apd.objectruanganfk',

                'apr.norec as norec_apr',
                'apr.noantrianpoli',
                'apr.noantrian as nomor_loket',
                'apr.jenis',

                'ps.nocm',
                'ps.namapasien',
                'ps.nobpjs',
                'pg.namalengkap as dokter_apd',
                'pg1.namalengkap as dpjp',
                DB::raw("'teregistrasi' AS sumber_antrean"),
                DB::raw("
                    CASE
                        WHEN pd.antrianpasienregistrasifk IS NULL
                            THEN 'registrasi_langsung'
                        ELSE 'dari_reservasi'
                    END AS asal_registrasi
                "),
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
                        ELSE COALESCE(apr.jenis, '')
                            || apr.noantrian::varchar
                    END AS antrianloket
                "),
                DB::raw("
                    CASE
                        WHEN apd.noantrian::varchar ~ '^[0-9]+$'
                            THEN apd.noantrian::integer
                        ELSE 0
                    END AS nomor_urut
                ")
            )
            // ->where('pd.kdprofile', $this->kdProfile)
            ->where('pd.statusenabled', true)
            ->where('ps.statusenabled', true)
            ->where('apd.statusenabled', true)
            ->whereDate('apd.tglregistrasi', $tanggalAntrean)
            ->where('apd.objectruanganfk', $ruanganFkPasien)
            ->whereNotNull('apd.noantrian')
            ->orderByRaw("
                CASE
                    WHEN apd.noantrian::varchar ~ '^[0-9]+$'
                        THEN apd.noantrian::integer
                    ELSE 0
                END ASC
            ")
            ->get()
            ->unique('norec_pd')
            ->values();

        /*
        |--------------------------------------------------------------------------
        | 6. Bentuk status pasien yang sudah teregistrasi
        |--------------------------------------------------------------------------
        */

        /*
         * Field acuan status pemeriksaan:
         * - pasiendaftar_t.istindakan
         * - antrianpasiendiperiksa_t.iscppt_dokter
         * - pasiendaftar_t.tglclosing
         * - antrianpasiendiperiksa_t.iskonsul AS konsul
         * - pasiendaftar_t.isasmed
         *
         * Urutan status wajib dari kondisi paling akhir/kuat:
         * 1. Sudah Closing
         * 2. Selesai
         * 3. Sedang Diperiksa
         * 4. Sudah Teregistrasi / Menunggu Pelayanan
         */
        $nilaiAktif = static function ($value): bool {
            if ($value === null) {
                return false;
            }

            if (is_bool($value)) {
                return $value;
            }

            if (is_int($value) || is_float($value)) {
                return (int) $value !== 0;
            }

            $value = Str::lower(trim((string) $value));

            return !in_array($value, [
                '',
                '0',
                'false',
                'f',
                'no',
                'n',
                'null',
            ], true);
        };

        $tentukanStatusPemeriksaan = static function ($item) use (
            $nilaiAktif
        ): array {
            $tglClosing = data_get($item, 'tglclosing');

            $isTindakan = $nilaiAktif(
                data_get($item, 'istindakan')
            );

            $isCpptDokter = $nilaiAktif(
                data_get($item, 'iscppt_dokter')
            );

            $isAsmed = $nilaiAktif(
                data_get($item, 'isasmed')
            );

            $isKonsul = $nilaiAktif(
                data_get(
                    $item,
                    'konsul',
                    data_get($item, 'iskonsul')
                )
            );

            $punyaApd = !empty(data_get($item, 'norec_apd'));

            // Closing adalah status paling akhir, sehingga diperiksa dahulu.
            if (!empty($tglClosing)) {
                return [
                    'label' => 'Sudah Closing',
                    'class' => 'is-success',
                ];
            }

            // Asesmen medis atau CPPT dokter menandakan pelayanan selesai.
            if ($isAsmed || $isCpptDokter) {
                return [
                    'label' => 'Selesai',
                    'class' => 'is-warning',
                ];
            }

            /*
             * Tindakan atau proses konsultasi menandakan pasien sudah mulai
             * diperiksa, tetapi dokumentasi dokter belum selesai.
             */
            if ($isTindakan || $isKonsul) {
                return [
                    'label' => 'Sedang Diperiksa',
                    'class' => 'is-primary',
                ];
            }

            if (!$punyaApd) {
                return [
                    'label' => 'Sudah Teregistrasi',
                    'class' => 'is-info',
                ];
            }

            return [
                'label' => 'Menunggu Pelayanan',
                'class' => 'is-danger',
            ];
        };

        $dataTeregistrasi = $dataTeregistrasi->map(
            function ($item) use ($tentukanStatusPemeriksaan) {
                $status = $tentukanStatusPemeriksaan($item);

                $item->label_statusperiksa = $status['label'];
                $item->class_statusperiksa = $status['class'];

                return $item;
            }
        );

        $isSelesai = function ($item): bool {
            $label = Str::lower(
                trim(
                    (string) data_get(
                        $item,
                        'label_statusperiksa'
                    )
                )
            );

            return in_array($label, [
                'selesai',
                'sudah closing',
            ], true);
        };

        $isSedangDilayani = function ($item) use ($isSelesai): bool {
            if ($isSelesai($item)) {
                return false;
            }

            $label = Str::lower(
                trim((string) data_get(
                    $item,
                    'label_statusperiksa'
                ))
            );

            if (in_array($label, [
                'sedang diperiksa',
                'sedang dilayani',
            ], true)) {
                return true;
            }

            // Fallback untuk data lama yang masih mengandalkan apd.status.
            $statusAsli = Str::lower(
                trim((string) data_get($item, 'status'))
            );

            return Str::contains($statusAsli, [
                'sedang',
                'diperiksa',
                'dilayani',
            ]);
        };

        $getStatusPasien = function ($item) use (
            $isSelesai,
            $isSedangDilayani
        ): string {
            if ($isSelesai($item)) {
                return 'Selesai Dilayani';
            }

            if ($isSedangDilayani($item)) {
                return 'Sedang Diperiksa';
            }

            if (empty(data_get($item, 'norec_apd'))) {
                return 'Sudah Teregistrasi';
            }

            return 'Menunggu Pelayanan';
        };

        /*
        |--------------------------------------------------------------------------
        | 7. Bentuk data antrean pasien yang sedang dicek
        |--------------------------------------------------------------------------
        */

        if ($sudahTeregistrasi) {
            $pasienAntrean = $dataTeregistrasi->first(function ($item) use (
                $registrasiPasien
            ) {
                return (string) data_get($item, 'norec_pd') ===
                    (string) data_get($registrasiPasien, 'norec_pd');
            });

            if (!$pasienAntrean) {
                $pasienAntrean = (object) [
                    'norec_pd' => data_get(
                        $registrasiPasien,
                        'norec_pd'
                    ),
                    'norec_apd' => data_get(
                        $registrasiPasien,
                        'norec_apd'
                    ),
                    'norec_apr' => data_get(
                        $registrasiPasien,
                        'antrianpasienregistrasifk'
                    ),
                    'noregistrasi' => data_get(
                        $registrasiPasien,
                        'noregistrasi'
                    ),
                    'nocm' => data_get($pasien, 'nocm'),
                    'namapasien' => data_get(
                        $pasien,
                        'namapasien'
                    ),
                    'nobpjs' => data_get($pasien, 'nobpjs'),
                    'objectruanganfk' => $ruanganFkPasien,
                    'namaruangan' => data_get(
                        $registrasiPasien,
                        'namaruangan'
                    ),
                    'dokter' => data_get(
                        $registrasiPasien,
                        'dokter'
                    ),
                    'noantrian' => data_get(
                        $registrasiPasien,
                        'noantrian'
                    ),
                    'noantrianpoli' => data_get(
                        $reservasiPasien,
                        'noantrianpoli'
                    ),
                    'nomor_urut' => $nomorAntreanPasien,
                    'antrianloket' => data_get(
                        $reservasiPasien,
                        'antrianloket'
                    ),
                    'tglclosing' => data_get(
                        $registrasiPasien,
                        'tglclosing'
                    ),
                    'istindakan' => data_get(
                        $registrasiPasien,
                        'istindakan'
                    ),
                    'isasmed' => data_get(
                        $registrasiPasien,
                        'isasmed'
                    ),
                    'iscppt_dokter' => data_get(
                        $registrasiPasien,
                        'iscppt_dokter'
                    ),
                    'konsul' => data_get(
                        $registrasiPasien,
                        'konsul',
                        data_get($registrasiPasien, 'iskonsul')
                    ),
                    'status' => data_get(
                        $registrasiPasien,
                        'status'
                    ),
                ];

                $statusPemeriksaan =
                    $tentukanStatusPemeriksaan($pasienAntrean);

                $pasienAntrean->label_statusperiksa =
                    $statusPemeriksaan['label'];

                $pasienAntrean->class_statusperiksa =
                    $statusPemeriksaan['class'];
            }
        } else {
            $pasienAntrean = (object) [
                'norec_apr' => data_get(
                    $reservasiPasien,
                    'norec_apr'
                ),
                'norec_pd' => null,
                'norec_apd' => null,
                'noregistrasi' => null,
                'nocm' => data_get($pasien, 'nocm'),
                'namapasien' => data_get(
                    $reservasiPasien,
                    'nama_reservasi'
                ) ?: data_get($pasien, 'namapasien'),
                'nobpjs' => data_get(
                    $reservasiPasien,
                    'bpjs_reservasi'
                ) ?: data_get($pasien, 'nobpjs'),
                'objectruanganfk' => $ruanganFkPasien,
                'namaruangan' => data_get(
                    $reservasiPasien,
                    'namaruangan'
                ),
                'dokter' => data_get(
                    $reservasiPasien,
                    'dokter_reservasi'
                ),
                'noantrian' => $nomorAntreanPasien,
                'noantrianpoli' => data_get(
                    $reservasiPasien,
                    'noantrianpoli'
                ),
                'nomor_urut' => $nomorAntreanPasien,
                'antrianloket' => data_get(
                    $reservasiPasien,
                    'antrianloket'
                ),
                'label_statusperiksa' => 'Belum Teregistrasi',
                'class_statusperiksa' => 'is-info',
                'status' => 'Reservasi',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | 8. Hitung antrean di depan berdasarkan dua kondisi
        |--------------------------------------------------------------------------
        */

        /*
        | A. Reservasi yang belum masuk pasiendaftar_t.
        | B. Registrasi yang berasal dari reservasi.
        | C. Registrasi langsung tanpa reservasi.
        |
        | Total reservasi = A + B.
        | Total teregistrasi = B + C.
        | Total pasien unik = A + B + C.
        */

        $belumTeregistrasiDiDepan = $dataReservasi
            ->filter(function ($item) use (
                $nomorAntreanPasien,
                $pasienAntrean
            ) {
                $nomor = (int) data_get($item, 'nomor_urut');

                $bukanPasienSendiri =
                    (string) data_get($item, 'norec_apr') !==
                    (string) data_get(
                        $pasienAntrean,
                        'norec_apr'
                    );

                return $nomor > 0
                    && $nomor < $nomorAntreanPasien
                    && $bukanPasienSendiri;
            })
            ->unique('norec_apr')
            ->values();

        /*
         * Kelompok B: pasien reservasi yang sudah registrasi.
         */
        $registrasiDariReservasiDiDepan = $dataTeregistrasi
            ->filter(function ($item) use (
                $nomorAntreanPasien,
                $pasienAntrean,
                $isSelesai
            ) {
                $nomor = (int) data_get($item, 'nomor_urut');

                $bukanPasienSendiri =
                    (string) data_get($item, 'norec_pd') !==
                    (string) data_get(
                        $pasienAntrean,
                        'norec_pd'
                    );

                return $nomor > 0
                    && $nomor < $nomorAntreanPasien
                    && !empty(data_get(
                        $item,
                        'antrianpasienregistrasifk'
                    ))
                    && $bukanPasienSendiri
                    && !$isSelesai($item);
            })
            ->unique('norec_pd')
            ->values();

        /*
         * Kelompok C: pasien registrasi langsung/walk-in tanpa reservasi.
         */
        $registrasiLangsungDiDepan = $dataTeregistrasi
            ->filter(function ($item) use (
                $nomorAntreanPasien,
                $pasienAntrean,
                $isSelesai
            ) {
                $nomor = (int) data_get($item, 'nomor_urut');

                $bukanPasienSendiri =
                    (string) data_get($item, 'norec_pd') !==
                    (string) data_get(
                        $pasienAntrean,
                        'norec_pd'
                    );

                return $nomor > 0
                    && $nomor < $nomorAntreanPasien
                    && empty(data_get(
                        $item,
                        'antrianpasienregistrasifk'
                    ))
                    && $bukanPasienSendiri
                    && !$isSelesai($item);
            })
            ->unique('norec_pd')
            ->values();

        $sedangDilayani = $dataTeregistrasi
            ->filter(function ($item) use ($isSedangDilayani) {
                return $isSedangDilayani($item);
            })
            ->sortBy(function ($item) {
                return (int) data_get($item, 'nomor_urut');
            })
            ->first();

        $jumlahBelumTeregistrasiDiDepan =
            $belumTeregistrasiDiDepan->count();

        $jumlahRegistrasiDariReservasiDiDepan =
            $registrasiDariReservasiDiDepan->count();

        $jumlahRegistrasiLangsungDiDepan =
            $registrasiLangsungDiDepan->count();

        /*
         * Total reservasi terdiri dari:
         * A. reservasi belum registrasi
         * B. reservasi yang sudah registrasi
         */
        $jumlahReservasiDiDepan =
            $jumlahBelumTeregistrasiDiDepan +
            $jumlahRegistrasiDariReservasiDiDepan;

        /*
         * Total teregistrasi terdiri dari:
         * B. registrasi dari reservasi
         * C. registrasi langsung/walk-in
         */
        $jumlahTeregistrasiDiDepan =
            $jumlahRegistrasiDariReservasiDiDepan +
            $jumlahRegistrasiLangsungDiDepan;

        /*
         * Total pasien unik di depan = A + B + C.
         */
        $totalPasienDiDepan =
            $jumlahBelumTeregistrasiDiDepan +
            $jumlahRegistrasiDariReservasiDiDepan +
            $jumlahRegistrasiLangsungDiDepan;

        /*
        |--------------------------------------------------------------------------
        | 9. Hasil
        |--------------------------------------------------------------------------
        */

        $hasil = [
            'namapasien' => data_get(
                $pasienAntrean,
                'namapasien'
            ),
            'nocm' => data_get($pasienAntrean, 'nocm'),
            'nobpjs' => data_get($pasienAntrean, 'nobpjs'),
            'tgllahir' => data_get($pasien, 'tgllahir'),
            'jeniskelamin' => data_get(
                $pasien,
                'jeniskelamin'
            ),
            'tanggal_antrean' => $tanggalAntrean,
            'data_hari_ini' => $tanggalAntrean ===
                Carbon::today()->format('Y-m-d'),

            'status_registrasi' => $sudahTeregistrasi
                ? 'Sudah Teregistrasi'
                : 'Belum Teregistrasi',
            'sudah_teregistrasi' => $sudahTeregistrasi,
            'asal_registrasi' => $sudahTeregistrasi
                ? (!empty(data_get(
                    $registrasiPasien,
                    'antrianpasienregistrasifk'
                ))
                    ? 'Dari Reservasi'
                    : 'Registrasi Langsung')
                : 'Belum Teregistrasi',

            'noregistrasi' => data_get(
                $pasienAntrean,
                'noregistrasi'
            ),
            'norec_pd' => data_get($pasienAntrean, 'norec_pd'),
            'norec_apd' => data_get(
                $pasienAntrean,
                'norec_apd'
            ),
            'norec_apr' => data_get(
                $pasienAntrean,
                'norec_apr'
            ),

            'namaruangan' => data_get(
                $pasienAntrean,
                'namaruangan'
            ) ?: data_get($reservasiPasien, 'namaruangan'),
            'ruanganfk' => $ruanganFkPasien,
            'dokter' => data_get($pasienAntrean, 'dpjp')
                ?: data_get($pasienAntrean, 'dokter_apd')
                ?: data_get($pasienAntrean, 'dokter')
                ?: data_get(
                    $reservasiPasien,
                    'dokter_reservasi'
                ),

            // Nomor urut antrean poli yang dipakai untuk perbandingan.
            'noantrian' => $nomorAntreanPasien,
            'noantrian_apd' => data_get(
                $pasienAntrean,
                'noantrian'
            ),
            'noantrianpoli' => data_get(
                $pasienAntrean,
                'noantrianpoli'
            ) ?: data_get($reservasiPasien, 'noantrianpoli'),
            'antrianloket' => data_get(
                $pasienAntrean,
                'antrianloket'
            ) ?: data_get($reservasiPasien, 'antrianloket'),

            'label_statusperiksa' => data_get(
                $pasienAntrean,
                'label_statusperiksa'
            ),
            'class_statusperiksa' => data_get(
                $pasienAntrean,
                'class_statusperiksa'
            ),

            'status_pasien' => $sudahTeregistrasi
                ? $getStatusPasien($pasienAntrean)
                : 'Belum teregistrasi',
            'status_asli' => data_get(
                $pasienAntrean,
                'status'
            ),

            'sisa_pasien_di_depan' => $totalPasienDiDepan,
            'sisa_reservasi_di_depan' =>
            $jumlahReservasiDiDepan,
            'sisa_teregistrasi_di_depan' =>
            $jumlahTeregistrasiDiDepan,

            'rincian_antrean_di_depan' => [
                // A: APR belum menjadi pasiendaftar_t.
                'reservasi_belum_teregistrasi' =>
                $jumlahBelumTeregistrasiDiDepan,

                // B: mempunyai APR dan sudah menjadi pasiendaftar_t.
                'registrasi_dari_reservasi' =>
                $jumlahRegistrasiDariReservasiDiDepan,

                // C: pasiendaftar_t tanpa APR.
                'registrasi_langsung' =>
                $jumlahRegistrasiLangsungDiDepan,

                // Total A + B.
                'total_reservasi' =>
                $jumlahReservasiDiDepan,

                // Total B + C.
                'total_teregistrasi' =>
                $jumlahTeregistrasiDiDepan,

                // Kompatibilitas key lama.
                'belum_teregistrasi' =>
                $jumlahBelumTeregistrasiDiDepan,
                'sudah_teregistrasi' =>
                $jumlahTeregistrasiDiDepan,
                'masih_reservasi' =>
                $jumlahBelumTeregistrasiDiDepan,

                // Total pasien unik A + B + C.
                'total' => $totalPasienDiDepan,
            ],

            'sedang_dilayani' => $sedangDilayani
                ? [
                    'noantrian' => data_get(
                        $sedangDilayani,
                        'nomor_urut'
                    ),
                    'noantrian_apd' => data_get(
                        $sedangDilayani,
                        'noantrian'
                    ),
                    'namapasien' => data_get(
                        $sedangDilayani,
                        'namapasien'
                    ),
                    'label_statusperiksa' => data_get(
                        $sedangDilayani,
                        'label_statusperiksa'
                    ),
                    'status' => $getStatusPasien(
                        $sedangDilayani
                    ),
                ]
                : null,
        ];

        return response()->json([
            'metaData' => [
                'code' => 200,
                'message' => 'OK',
            ],
            'response' => $hasil,
        ]);
    }
    public function getBorLosToi(
        Request $request,
        $kdProfile = 1
    ) {

        /*
        |--------------------------------------------------------------------------
        | Validasi
        |--------------------------------------------------------------------------
        */

        $request->validate([
            'tglAwal' => [
                'required',
                'date_format:Y-m-d',
            ],
            'tglAkhir' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:tglAwal',
            ],
        ]);
        $detailPasien = filter_var(
            $request->get('detailPasien', false),
            FILTER_VALIDATE_BOOLEAN
        );

        /*
        |--------------------------------------------------------------------------
        | Profile & Setting
        |--------------------------------------------------------------------------
        */

        $idProfile = (int) $kdProfile;

        $idDepRanap = (int) static::settingDataFixed2(
            'idDepRawatInap',
            $idProfile
        );

        $idStatKelMeninggal = (int) static::settingDataFixed2(
            'KdStatKeluarMeninggal',
            $idProfile
        );

        $idKondisiPasienMeninggal = (int) static::settingDataFixed2(
            'KdKondisiPasienMeninggal',
            $idProfile
        );


        /*
        |--------------------------------------------------------------------------
        | Periode
        |--------------------------------------------------------------------------
        */

        $awal = Carbon::parse(
            $request->get('tglAwal')
        )->startOfDay();

        $akhir = Carbon::parse(
            $request->get('tglAkhir')
        )->endOfDay();

        $jumlahHari = $awal
            ->copy()
            ->startOfDay()
            ->diffInDays(
                $akhir
                    ->copy()
                    ->startOfDay()
            ) + 1;


        /*
        |--------------------------------------------------------------------------
        | Query Data Utama
        |--------------------------------------------------------------------------
        */

        $data = DB::connection('pgsql')->selectOne("
        WITH tempat_tidur AS (

            SELECT
                COUNT(tt.id) AS jumlah_tt

            FROM tempattidur_m tt

            INNER JOIN kamar_m kmr
                ON kmr.id = tt.objectkamarfk

            INNER JOIN ruangan_m ru
                ON ru.id = kmr.objectruanganfk

            WHERE
                tt.kdprofile = :profile_tt
                AND tt.statusenabled = true
                AND kmr.statusenabled = true
                AND ru.statusenabled = true
        ),

        /*
        |--------------------------------------------------------------------------
        | Tanggal periode laporan
        |--------------------------------------------------------------------------
        */

        periode_harian AS (

            SELECT
                generate_series(
                    :awal_series::date,
                    :akhir_series::date,
                    interval '1 day'
                )::date AS tanggal
        ),

        /*
        |--------------------------------------------------------------------------
        | Pasien Rawat Inap
        |--------------------------------------------------------------------------
        |
        | Kita batasi hanya pasien yang memiliki irisan dengan periode.
        |
        */

        pasien_rawat_inap AS (

            SELECT
                pd.norec,
                pd.tglregistrasi,
                pd.tglpulang,
                pd.objectstatuspulangfk,
                pd.objectkondisipasienfk

            FROM pasiendaftar_t pd

            INNER JOIN ruangan_m ru
                ON ru.id = pd.objectruanganlastfk

            WHERE
                pd.kdprofile = :profile_ri
                AND pd.statusenabled = true
                AND ru.objectdepartemenfk = :departemen_ri

                AND pd.tglregistrasi::date
                    <= :akhir_pasien::date

                AND (
                    pd.tglpulang IS NULL
                    OR pd.tglpulang::date
                        >= :awal_pasien::date
                )
        ),

        /*
        |--------------------------------------------------------------------------
        | Jumlah Pasien Dirawat Per Hari
        |--------------------------------------------------------------------------
        |
        | Pasien dianggap dirawat pada tanggal tersebut jika:
        |
        | tanggal masuk <= tanggal laporan
        | DAN
        | belum pulang / tanggal pulang >= tanggal laporan.
        |
        */

        perawatan_harian AS (

            SELECT
                ph.tanggal,

                COUNT(
                    pri.norec
                ) AS jumlah_pasien

            FROM periode_harian ph

            LEFT JOIN pasien_rawat_inap pri
                ON pri.tglregistrasi::date
                    <= ph.tanggal

                AND (
                    pri.tglpulang IS NULL
                    OR pri.tglpulang::date
                        >= ph.tanggal
                )

            GROUP BY
                ph.tanggal
        ),

        /*
        |--------------------------------------------------------------------------
        | Total Hari Perawatan
        |--------------------------------------------------------------------------
        */

        total_hari_perawatan AS (

            SELECT
                COALESCE(
                    SUM(jumlah_pasien),
                    0
                ) AS hari_perawatan

            FROM perawatan_harian
        ),

        /*
        |--------------------------------------------------------------------------
        | Statistik Pasien Keluar
        |--------------------------------------------------------------------------
        */

        rawat_inap AS (

            SELECT

                /*
                |--------------------------------------------------------------------------
                | Lama Rawat
                |--------------------------------------------------------------------------
                |
                | Total lama rawat pasien yang pulang pada periode.
                |
                */

                COALESCE(
                    SUM(
                        DATE_PART(
                            'DAY',
                            pd.tglpulang
                            - pd.tglregistrasi
                        )
                    ) FILTER (
                        WHERE pd.tglpulang
                            BETWEEN
                                :awal_lr
                                AND :akhir_lr
                    ),
                    0
                ) AS lama_rawat,


                /*
                |--------------------------------------------------------------------------
                | Pasien Pulang
                |--------------------------------------------------------------------------
                */

                COUNT(*) FILTER (
                    WHERE pd.tglpulang
                        BETWEEN
                            :awal_pp
                            AND :akhir_pp
                ) AS pasien_pulang,


                /*
                |--------------------------------------------------------------------------
                | Meninggal
                |--------------------------------------------------------------------------
                */

                COUNT(*) FILTER (
                    WHERE
                        pd.objectstatuspulangfk
                            = :status_meninggal

                        AND pd.tglpulang
                            BETWEEN
                                :awal_meninggal
                                AND :akhir_meninggal
                ) AS meninggal,


                /*
                |--------------------------------------------------------------------------
                | Meninggal >= 48 Jam
                |--------------------------------------------------------------------------
                */

                COUNT(*) FILTER (
                    WHERE
                        pd.objectstatuspulangfk
                            = :status_meninggal_48

                        AND pd.objectkondisipasienfk
                            = :kondisi_meninggal

                        AND pd.tglpulang
                            BETWEEN
                                :awal_48
                                AND :akhir_48
                ) AS mati_lebih_48

            FROM pasiendaftar_t pd

            INNER JOIN ruangan_m ru
                ON ru.id = pd.objectruanganlastfk

            WHERE
                pd.kdprofile = :profile_statistik
                AND pd.statusenabled = true
                AND ru.objectdepartemenfk = :departemen_statistik
        )

        SELECT
            tt.jumlah_tt,
            hp.hari_perawatan,

            ri.lama_rawat,
            ri.pasien_pulang,
            ri.meninggal,
            ri.mati_lebih_48

        FROM tempat_tidur tt

        CROSS JOIN total_hari_perawatan hp

        CROSS JOIN rawat_inap ri ", [

            /*
        |--------------------------------------------------------------------------
        | TT
        |--------------------------------------------------------------------------
        */

            'profile_tt' =>
            $idProfile,


            /*
        |--------------------------------------------------------------------------
        | Generate tanggal
        |--------------------------------------------------------------------------
        */

            'awal_series' =>
            $awal->format('Y-m-d'),

            'akhir_series' =>
            $akhir->format('Y-m-d'),


            /*
        |--------------------------------------------------------------------------
        | Pasien aktif dalam periode
        |--------------------------------------------------------------------------
        */

            'awal_pasien' =>
            $awal->format('Y-m-d'),

            'akhir_pasien' =>
            $akhir->format('Y-m-d'),

            'profile_ri' =>
            $idProfile,

            'departemen_ri' =>
            $idDepRanap,


            /*
        |--------------------------------------------------------------------------
        | Lama Rawat
        |--------------------------------------------------------------------------
        */

            'awal_lr' =>
            $awal,

            'akhir_lr' =>
            $akhir,


            /*
        |--------------------------------------------------------------------------
        | Pasien Pulang
        |--------------------------------------------------------------------------
        */

            'awal_pp' =>
            $awal,

            'akhir_pp' =>
            $akhir,


            /*
        |--------------------------------------------------------------------------
        | Meninggal
        |--------------------------------------------------------------------------
        */

            'status_meninggal' =>
            $idStatKelMeninggal,

            'awal_meninggal' =>
            $awal,

            'akhir_meninggal' =>
            $akhir,


            /*
        |--------------------------------------------------------------------------
        | Meninggal >= 48
        |--------------------------------------------------------------------------
        */

            'status_meninggal_48' =>
            $idStatKelMeninggal,

            'kondisi_meninggal' =>
            $idKondisiPasienMeninggal,

            'awal_48' =>
            $awal,

            'akhir_48' =>
            $akhir,


            /*
        |--------------------------------------------------------------------------
        | Statistik
        |--------------------------------------------------------------------------
        */

            'profile_statistik' =>
            $idProfile,

            'departemen_statistik' =>
            $idDepRanap,
        ]);


        /*
        |--------------------------------------------------------------------------
        | Detail Pasien Dirawat Per Hari
        |--------------------------------------------------------------------------
        |
        | Ini saya tambahkan agar angka hari perawatan dapat dicek.
        |
        */

        $rincianPasienHarian = DB::connection('pgsql')->select("
        WITH periode_harian AS (

            SELECT
                generate_series(
                    :awal::date,
                    :akhir::date,
                    interval '1 day'
                )::date AS tanggal
        ),

        pasien_rawat_inap AS (

            SELECT
                pd.norec,
                pd.noregistrasi,
                pd.nocmfk,
                pd.tglregistrasi,
                pd.tglpulang,

                ps.nocm,
                ps.namapasien,

                ru.namaruangan,

                pg.namalengkap AS dokter

            FROM pasiendaftar_t pd

            INNER JOIN pasien_m ps
                ON ps.id = pd.nocmfk

            INNER JOIN ruangan_m ru
                ON ru.id = pd.objectruanganlastfk

            LEFT JOIN pegawai_m pg
                ON pg.id = pd.objectpegawaifk

            WHERE
                pd.kdprofile = :profile
                AND pd.statusenabled = true

                AND ps.statusenabled = true

                AND ru.objectdepartemenfk = :departemen

                /*
                |--------------------------------------------------------------------------
                | Pasien harus overlap dengan periode laporan
                |--------------------------------------------------------------------------
                */

                AND pd.tglregistrasi::date
                    <= :akhir_filter::date

                AND (
                    pd.tglpulang IS NULL
                    OR pd.tglpulang::date
                        >= :awal_filter::date
                )
        )

        SELECT

            ph.tanggal,

            pri.norec,
            pri.noregistrasi,
            pri.nocm,
            pri.namapasien,
            pri.tglregistrasi,
            pri.tglpulang,
            pri.namaruangan,
            pri.dokter

        FROM periode_harian ph

        INNER JOIN pasien_rawat_inap pri
            ON pri.tglregistrasi::date
                <= ph.tanggal

            AND (
                pri.tglpulang IS NULL
                OR pri.tglpulang::date
                    >= ph.tanggal
            )

        ORDER BY
            ph.tanggal,
            pri.namaruangan,
            pri.namapasien

        ", [
            'awal' =>
            $awal->format('Y-m-d'),

            'akhir' =>
            $akhir->format('Y-m-d'),

            'awal_filter' =>
            $awal->format('Y-m-d'),

            'akhir_filter' =>
            $akhir->format('Y-m-d'),

            'profile' =>
            $idProfile,

            'departemen' =>
            $idDepRanap,
        ]);

        $groupPasienHarian = collect(
            $rincianPasienHarian
        )->groupBy(function ($item) {

            return Carbon::parse(
                $item->tanggal
            )->format('Y-m-d');
        });
        $detailPerHari = [];

        $current = $awal
            ->copy()
            ->startOfDay();

        $lastDate = $akhir
            ->copy()
            ->startOfDay();

        while ($current->lte($lastDate)) {

            $tanggal = $current->format('Y-m-d');

            /*
        |--------------------------------------------------------------------------
        | Ambil pasien pada tanggal tersebut
        |--------------------------------------------------------------------------
        */

            $pasienHariIni = $groupPasienHarian->get(
                $tanggal,
                collect()
            );


            /*
        |--------------------------------------------------------------------------
        | Kelompokkan berdasarkan ruangan
        |--------------------------------------------------------------------------
        */

            $perRuangan = $pasienHariIni
                ->groupBy(function ($item) {

                    $ruangan = trim(
                        (string) data_get(
                            $item,
                            'namaruangan'
                        )
                    );

                    return $ruangan !== ''
                        ? $ruangan
                        : 'RUANGAN TIDAK DIKETAHUI';
                })
                ->map(function (
                    $items,
                    $namaRuangan
                ) use ($detailPasien) {

                    /*
            |--------------------------------------------------------------------------
            | Detail Pasien
            |--------------------------------------------------------------------------
            */

                    $pasien = [];

                    if ($detailPasien) {

                        $pasien = $items
                            ->map(function ($item) {

                                return [
                                    'noregistrasi' =>
                                    data_get(
                                        $item,
                                        'noregistrasi'
                                    ),

                                    'nocm' =>
                                    data_get(
                                        $item,
                                        'nocm'
                                    ),

                                    'namapasien' =>
                                    data_get(
                                        $item,
                                        'namapasien'
                                    ),

                                    'tglregistrasi' =>
                                    data_get(
                                        $item,
                                        'tglregistrasi'
                                    )
                                        ? Carbon::parse(
                                            data_get(
                                                $item,
                                                'tglregistrasi'
                                            )
                                        )->format(
                                            'Y-m-d H:i:s'
                                        )
                                        : null,

                                    'tglpulang' =>
                                    data_get(
                                        $item,
                                        'tglpulang'
                                    )
                                        ? Carbon::parse(
                                            data_get(
                                                $item,
                                                'tglpulang'
                                            )
                                        )->format(
                                            'Y-m-d H:i:s'
                                        )
                                        : null,

                                    'dokter' =>
                                    data_get(
                                        $item,
                                        'dokter'
                                    ),
                                ];
                            })
                            ->values()
                            ->all();
                    }


                    /*
            |--------------------------------------------------------------------------
            | Hasil Per Ruangan
            |--------------------------------------------------------------------------
            */

                    return [
                        'ruangan' =>
                        $namaRuangan,

                        'jumlah_pasien' =>
                        $items->count(),

                        'pasien' =>
                        $pasien,
                    ];
                })

                /*
         * Urutkan ruangan berdasarkan nama.
         */
                ->sortBy('ruangan')

                ->values();


            /*
        |--------------------------------------------------------------------------
        | Hasil Per Hari
        |--------------------------------------------------------------------------
        */

            $detailPerHari[] = [

                'tanggal' =>
                $tanggal,

                'jumlah_pasien' =>
                $pasienHariIni->count(),

                'per_ruangan' =>
                $perRuangan->all(),
            ];


            $current->addDay();
        }
        /*
        |--------------------------------------------------------------------------
        | Ambil Nilai
        |--------------------------------------------------------------------------
        */

        $jumlahTT = (int) (
            $data->jumlah_tt ?? 0
        );

        $hariPerawatan = (int) (
            $data->hari_perawatan ?? 0
        );

        $lamaRawat = (float) (
            $data->lama_rawat ?? 0
        );

        $pasienPulang = (int) (
            $data->pasien_pulang ?? 0
        );

        $meninggal = (int) (
            $data->meninggal ?? 0
        );

        $matiLebih48 = (int) (
            $data->mati_lebih_48 ?? 0
        );


        /*
        |--------------------------------------------------------------------------
        | Indikator
        |--------------------------------------------------------------------------
        */

        if ($jumlahTT > 0) {

            /*
         * BOR
         */
            $bor = (
                $hariPerawatan
                /
                ($jumlahTT * $jumlahHari)
            ) * 100;


            /*
         * BTO
         */
            $bto =
                $pasienPulang / $jumlahTT;
        } else {

            $bor = 0;
            $bto = 0;
        }


        /*
     * ALOS
     */
        $alos = $pasienPulang > 0
            ? $lamaRawat / $pasienPulang
            : 0;


        /*
     * TOI
     */
        $toi = (
            $pasienPulang > 0 &&
            $jumlahTT > 0
        )
            ? (
                (
                    ($jumlahTT * $jumlahHari)
                    - $hariPerawatan
                )
                / $pasienPulang
            )
            : 0;


        /*
     * GDR
     */
        $gdr = $pasienPulang > 0
            ? (
                $meninggal * 1000
            ) / $pasienPulang
            : 0;


        /*
     * NDR
     */
        $ndr = $pasienPulang > 0
            ? (
                $matiLebih48 * 1000
            ) / $pasienPulang
            : 0;





        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return [[

            'tanggal_awal' =>
            $awal->format('Y-m-d'),

            'tanggal_akhir' =>
            $akhir->format('Y-m-d'),

            'jumlah_hari' =>
            $jumlahHari,

            'jumlah_tt' =>
            $jumlahTT,

            'lamarawat' =>
            $lamaRawat,

            'hariperawatan' =>
            $hariPerawatan,

            'pasienpulang' =>
            $pasienPulang,

            'meninggal' =>
            $meninggal,

            'matilebih48' =>
            $matiLebih48,

            'bor' =>
            round($bor, 2),

            'alos' =>
            round($alos, 2),

            'bto' =>
            round($bto, 2),

            'toi' =>
            round($toi, 2),

            'gdr' =>
            round($gdr, 2),

            'ndr' =>
            round($ndr, 2),

            /*
        |--------------------------------------------------------------------------
        | Detail Perawatan Harian
        |--------------------------------------------------------------------------
        */

            'perawatan_per_hari' =>
            $detailPerHari,
        ]];
    }
    // public function getDataRL3_2(Request $request, $kdProfile = 1)
    // {
    //     $bulan = (int) $request->input('bulan');
    //     $tahun = (int) $request->input('tahun');
    //     $kdProfile = (int) ($kdProfile ?: 1);

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Validasi parameter
    //     |--------------------------------------------------------------------------
    //     */

    //         if ($bulan < 1 || $bulan > 12 || $tahun < 2000) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Bulan atau tahun tidak valid.',
    //                 'data' => [],
    //             ], 422);
    //         }

    //         /*
    //     |--------------------------------------------------------------------------
    //     | Periode laporan
    //     |--------------------------------------------------------------------------
    //     */

    //     $periodeAwal = \Carbon\Carbon::create(
    //         $tahun,
    //         $bulan,
    //         1
    //     )->startOfDay();

    //     $periodeAkhirExclusive = $periodeAwal
    //         ->copy()
    //         ->addMonth();

    //     $periodeAkhir = $periodeAkhirExclusive
    //         ->copy()
    //         ->subDay()
    //         ->endOfDay();

    //     $tanggalAwal = $periodeAwal->format('Y-m-d');
    //     $tanggalAkhir = $periodeAkhir->format('Y-m-d');

    //     $tanggalAkhirExclusive =
    //         $periodeAkhirExclusive->format('Y-m-d');

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Jenis laporan
    //     |--------------------------------------------------------------------------
    //     */

    //     $settingJenisLaporan = $this->settingFix(
    //         'kdLaporanRekapRanap',
    //         $kdProfile
    //     );

    //     $idjenislaporan = collect(
    //         explode(',', (string) $settingJenisLaporan)
    //     )
    //         ->map(function ($item) {
    //             return (int) trim($item);
    //         })
    //         ->filter(function ($item) {
    //             return $item > 0;
    //         })
    //         ->unique()
    //         ->values()
    //         ->all();

    //     if (empty($idjenislaporan)) {
    //         return response()->json([
    //             'success' => false,
    //             'message' =>
    //             'Setting kdLaporanRekapRanap tidak ditemukan.',
    //             'debug' => [
    //                 'setting' => $settingJenisLaporan,
    //                 'kdprofile' => $kdProfile,
    //             ],
    //             'data' => [],
    //         ], 422);
    //     }

    //     $db = DB::connection('pgsql');

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Ambil tanggal pertama pasien masuk per ruangan
    //     |--------------------------------------------------------------------------
    //     |
    //     | Jangan difilter bulan di subquery ini.
    //     |
    //     | Pasien bisa masuk 31 Juli dan masih dirawat 1 Agustus.
    //     | Pasien tersebut tetap merupakan pasien awal bulan RL 3.2.
    //     |
    //     */

    //     $apdSub = $db
    //         ->table('antrianpasiendiperiksa_t')
    //         ->select(
    //             'noregistrasifk',
    //             'objectruanganfk'
    //         )
    //         ->selectRaw(
    //             'MIN(tglmasuk) AS tglmasuk'
    //         )
    //         ->whereNotNull('tglmasuk')
    //         ->groupBy(
    //             'noregistrasifk',
    //             'objectruanganfk'
    //         );

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Ekspresi tanggal keluar
    //     |--------------------------------------------------------------------------
    //     */

    //         $tanggalKeluarSql = "
    //         CASE
    //             WHEN pdt.tglmeninggal IS NOT NULL
    //                 THEN pdt.tglmeninggal
    //             ELSE pdt.tglpulang
    //         END
    //     ";

    //         /*
    //     |--------------------------------------------------------------------------
    //     | Hari Perawatan dalam periode laporan
    //     |--------------------------------------------------------------------------
    //     |
    //     | Contoh:
    //     |
    //     | masuk   : 28 Juli
    //     | pulang  : 3 Agustus
    //     | periode : Agustus
    //     |
    //     | HP Agustus = 1,2,3 Agustus = 3 hari.
    //     |
    //     */

    //     $hariPerawatanSql = "
    //     GREATEST(
    //         0,
    //         (
    //             LEAST(
    //                 COALESCE(
    //                     ({$tanggalKeluarSql})::date,
    //                     '{$tanggalAkhir}'::date
    //                 ),
    //                 '{$tanggalAkhir}'::date
    //             )
    //             -
    //             GREATEST(
    //                 apd.tglmasuk::date,
    //                 '{$tanggalAwal}'::date
    //             )
    //         ) + 1
    //     )
    //     ";

    //         /*
    //     |--------------------------------------------------------------------------
    //     | Data utama RL 3.2
    //     |--------------------------------------------------------------------------
    //     */

    //     $data = $db
    //         ->table('kelompoklaporanrl_t as klrl')

    //         ->join(
    //             'pasiendaftar_t as pdt',
    //             'pdt.norec',
    //             '=',
    //             'klrl.norecpd'
    //         )

    //         ->joinSub(
    //             $apdSub,
    //             'apd',
    //             function ($join) {
    //                 $join->on(
    //                     'apd.noregistrasifk',
    //                     '=',
    //                     'klrl.norecpd'
    //                 );

    //                 $join->on(
    //                     'apd.objectruanganfk',
    //                     '=',
    //                     'klrl.idruangan'
    //                 );
    //             }
    //         )

    //         ->join(
    //             'kelompoklaporan_m as kon',
    //             'kon.id',
    //             '=',
    //             'klrl.idkonten'
    //         )

    //         ->join(
    //             'pasien_m as pm',
    //             'pm.id',
    //             '=',
    //             'pdt.nocmfk'
    //         )

    //         ->select(
    //             'kon.id as idkonten',
    //             'kon.reportdisplay as jenisPelayanan'
    //         )

    //         /*
    //     |--------------------------------------------------------------------------
    //     | Pasien awal bulan
    //     |--------------------------------------------------------------------------
    //     |
    //     | Masuk sebelum tanggal 1,
    //     | tetapi pada tanggal 1 masih dirawat.
    //     |
    //     */

    //         ->selectRaw("
    //         COUNT(
    //             DISTINCT CASE
    //                 WHEN apd.tglmasuk < ?::timestamp
    //                  AND (
    //                         {$tanggalKeluarSql} IS NULL
    //                         OR {$tanggalKeluarSql} >= ?::timestamp
    //                      )
    //                 THEN pdt.norec
    //             END
    //         ) AS pasien_awal_bulan
    //     ", [
    //             $tanggalAwal,
    //             $tanggalAwal,
    //         ])

    //         /*
    //     |--------------------------------------------------------------------------
    //     | Pasien masuk bulan ini
    //     |--------------------------------------------------------------------------
    //     */

    //         ->selectRaw("
    //         COUNT(
    //             DISTINCT CASE
    //                 WHEN apd.tglmasuk >= ?::timestamp
    //                  AND apd.tglmasuk < ?::timestamp
    //                 THEN pdt.norec
    //             END
    //         ) AS pasien_masuk
    //     ", [
    //             $tanggalAwal,
    //             $tanggalAkhirExclusive,
    //         ])

    //         /*
    //     |--------------------------------------------------------------------------
    //     | Pasien keluar hidup
    //     |--------------------------------------------------------------------------
    //     |
    //     | Kode lama:
    //     |
    //     | pdt.tglmeninggal IS NULL
    //     |
    //     | SALAH karena pasien yang masih dirawat juga ikut dihitung.
    //     |
    //     */

    //         ->selectRaw("
    //         COUNT(
    //             DISTINCT CASE
    //                 WHEN pdt.tglpulang IS NOT NULL
    //                  AND pdt.tglmeninggal IS NULL
    //                  AND pdt.tglpulang >= ?::timestamp
    //                  AND pdt.tglpulang < ?::timestamp
    //                 THEN pdt.norec
    //             END
    //         ) AS pasien_keluar_hidup
    //     ", [
    //             $tanggalAwal,
    //             $tanggalAkhirExclusive,
    //         ])

    //         /*
    //     |--------------------------------------------------------------------------
    //     | Meninggal < 48 jam laki
    //     |--------------------------------------------------------------------------
    //     */

    //         ->selectRaw("
    //         COUNT(
    //             DISTINCT CASE
    //                 WHEN pdt.tglmeninggal IS NOT NULL
    //                  AND pdt.tglmeninggal >= ?::timestamp
    //                  AND pdt.tglmeninggal < ?::timestamp
    //                  AND (
    //                         pdt.tglmeninggal
    //                         -
    //                         pdt.tglregistrasi
    //                      ) < INTERVAL '48 hours'
    //                  AND pm.objectjeniskelaminfk = 1
    //                 THEN pdt.norec
    //             END
    //         ) AS pasien_meninggal_kurang_48_jam_laki
    //     ", [
    //             $tanggalAwal,
    //             $tanggalAkhirExclusive,
    //         ])

    //         /*
    //     |--------------------------------------------------------------------------
    //     | Meninggal < 48 jam perempuan
    //     |--------------------------------------------------------------------------
    //     */

    //         ->selectRaw("
    //         COUNT(
    //             DISTINCT CASE
    //                 WHEN pdt.tglmeninggal IS NOT NULL
    //                  AND pdt.tglmeninggal >= ?::timestamp
    //                  AND pdt.tglmeninggal < ?::timestamp
    //                  AND (
    //                         pdt.tglmeninggal
    //                         -
    //                         pdt.tglregistrasi
    //                      ) < INTERVAL '48 hours'
    //                  AND pm.objectjeniskelaminfk = 2
    //                 THEN pdt.norec
    //             END
    //         ) AS pasien_meninggal_kurang_48_jam_cewe
    //     ", [
    //             $tanggalAwal,
    //             $tanggalAkhirExclusive,
    //         ])

    //         /*
    //     |--------------------------------------------------------------------------
    //     | Meninggal >= 48 jam laki
    //     |--------------------------------------------------------------------------
    //     */

    //         ->selectRaw("
    //         COUNT(
    //             DISTINCT CASE
    //                 WHEN pdt.tglmeninggal IS NOT NULL
    //                  AND pdt.tglmeninggal >= ?::timestamp
    //                  AND pdt.tglmeninggal < ?::timestamp
    //                  AND (
    //                         pdt.tglmeninggal
    //                         -
    //                         pdt.tglregistrasi
    //                      ) >= INTERVAL '48 hours'
    //                  AND pm.objectjeniskelaminfk = 1
    //                 THEN pdt.norec
    //             END
    //         ) AS pasien_meninggal_lebih_48_jam_laki
    //     ", [
    //             $tanggalAwal,
    //             $tanggalAkhirExclusive,
    //         ])

    //         /*
    //     |--------------------------------------------------------------------------
    //     | Meninggal >= 48 jam perempuan
    //     |--------------------------------------------------------------------------
    //     */

    //         ->selectRaw("
    //         COUNT(
    //             DISTINCT CASE
    //                 WHEN pdt.tglmeninggal IS NOT NULL
    //                  AND pdt.tglmeninggal >= ?::timestamp
    //                  AND pdt.tglmeninggal < ?::timestamp
    //                  AND (
    //                         pdt.tglmeninggal
    //                         -
    //                         pdt.tglregistrasi
    //                      ) >= INTERVAL '48 hours'
    //                  AND pm.objectjeniskelaminfk = 2
    //                 THEN pdt.norec
    //             END
    //         ) AS pasien_meninggal_lebih_48_jam_cewe
    //     ", [
    //             $tanggalAwal,
    //             $tanggalAkhirExclusive,
    //         ])

    //         /*
    //     |--------------------------------------------------------------------------
    //     | Jumlah lama dirawat
    //     |--------------------------------------------------------------------------
    //     |
    //     | RL 3.2:
    //     |
    //     | tanggal keluar - tanggal masuk
    //     |
    //     | TANPA +1
    //     |
    //     */

    //         ->selectRaw("
    //         SUM(
    //             CASE
    //                 WHEN {$tanggalKeluarSql}
    //                         >= ?::timestamp
    //                  AND {$tanggalKeluarSql}
    //                         < ?::timestamp
    //                 THEN
    //                     GREATEST(
    //                         0,
    //                         (
    //                             ({$tanggalKeluarSql})::date
    //                             -
    //                             pdt.tglregistrasi::date
    //                         )
    //                     )
    //                 ELSE 0
    //             END
    //         ) AS jumlah_lama_dirawat
    //     ", [
    //             $tanggalAwal,
    //             $tanggalAkhirExclusive,
    //         ])

    //         /*
    //     |--------------------------------------------------------------------------
    //     | Jumlah Hari Perawatan
    //     |--------------------------------------------------------------------------
    //     */

    //         ->selectRaw("
    //         SUM(
    //             {$hariPerawatanSql}
    //         ) AS jumlah_hari_perawatan
    //     ")

    //         /*
    //     |--------------------------------------------------------------------------
    //     | Hari perawatan per kelas
    //     |--------------------------------------------------------------------------
    //     */

    //         ->selectRaw("
    //         SUM(
    //             CASE
    //                 WHEN pdt.objectkelasfk = 5
    //                 THEN {$hariPerawatanSql}
    //                 ELSE 0
    //             END
    //         ) AS kelas_vvip
    //     ")

    //         ->selectRaw("
    //         SUM(
    //             CASE
    //                 WHEN pdt.objectkelasfk = 4
    //                 THEN {$hariPerawatanSql}
    //                 ELSE 0
    //             END
    //         ) AS kelas_vip
    //     ")

    //         ->selectRaw("
    //         SUM(
    //             CASE
    //                 WHEN pdt.objectkelasfk = 3
    //                 THEN {$hariPerawatanSql}
    //                 ELSE 0
    //             END
    //         ) AS kelas_1
    //     ")

    //         ->selectRaw("
    //         SUM(
    //             CASE
    //                 WHEN pdt.objectkelasfk = 2
    //                 THEN {$hariPerawatanSql}
    //                 ELSE 0
    //             END
    //         ) AS kelas_2
    //     ")

    //         ->selectRaw("
    //         SUM(
    //             CASE
    //                 WHEN pdt.objectkelasfk = 1
    //                 THEN {$hariPerawatanSql}
    //                 ELSE 0
    //             END
    //         ) AS kelas_3
    //     ")

    //         ->selectRaw("
    //         SUM(
    //             CASE
    //                 WHEN pdt.objectkelasfk IS NULL
    //                   OR pdt.objectkelasfk NOT IN (1,2,3,4,5)
    //                 THEN {$hariPerawatanSql}
    //                 ELSE 0
    //             END
    //         ) AS kelas_khusus
    //     ")

    //         ->selectRaw("
    //         ARRAY_AGG(
    //             DISTINCT klrl.idruangan
    //         ) AS idruangan_list
    //     ")

    //         ->whereIn(
    //             'klrl.idjenislaporan',
    //             $idjenislaporan
    //         )

    //         ->where(
    //             'klrl.statusenabled',
    //             true
    //         )

    //         /*
    //     |--------------------------------------------------------------------------
    //     | FILTER YANG PENTING
    //     |--------------------------------------------------------------------------
    //     |
    //     | Jangan:
    //     |
    //     | whereMonth(apd.tglmasuk)
    //     |
    //     | tetapi cari seluruh pasien yang MASIH BERSINGGUNGAN
    //     | dengan periode laporan.
    //     |
    //     */

    //         ->where(
    //             'apd.tglmasuk',
    //             '<',
    //             $tanggalAkhirExclusive
    //         )

    //         ->where(function ($query) use (
    //             $tanggalAwal,
    //             $tanggalKeluarSql
    //         ) {
    //             $query
    //                 ->whereRaw(
    //                     "{$tanggalKeluarSql} IS NULL"
    //                 )
    //                 ->orWhereRaw(
    //                     "{$tanggalKeluarSql} >= ?::timestamp",
    //                     [$tanggalAwal]
    //                 );
    //         })

    //         /*
    //     |--------------------------------------------------------------------------
    //     | kdProfile
    //     |--------------------------------------------------------------------------
    //     |
    //     | Aktifkan jika kelompoklaporanrl_t memang memiliki kdprofile.
    //     |
    //     */

    //         // ->where('klrl.kdprofile', $kdProfile)

    //         ->groupBy(
    //             'kon.id',
    //             'kon.reportdisplay'
    //         )

    //         ->orderBy(
    //             'kon.id'
    //         )

    //         ->get();

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Debug kalau masih kosong
    //     |--------------------------------------------------------------------------
    //     */

    //     if ($data->isEmpty()) {

    //         $jumlahKlrl = $db
    //             ->table('kelompoklaporanrl_t')
    //             ->whereIn(
    //                 'idjenislaporan',
    //                 $idjenislaporan
    //             )
    //             ->where('statusenabled', true)
    //             ->count();

    //         $jumlahJoinRuangan = $db
    //             ->table('kelompoklaporanrl_t as klrl')
    //             ->joinSub(
    //                 $apdSub,
    //                 'apd',
    //                 function ($join) {
    //                     $join->on(
    //                         'apd.noregistrasifk',
    //                         '=',
    //                         'klrl.norecpd'
    //                     );

    //                     $join->on(
    //                         'apd.objectruanganfk',
    //                         '=',
    //                         'klrl.idruangan'
    //                     );
    //                 }
    //             )
    //             ->whereIn(
    //                 'klrl.idjenislaporan',
    //                 $idjenislaporan
    //             )
    //             ->where(
    //                 'klrl.statusenabled',
    //                 true
    //             )
    //             ->count();

    //         return response()->json([
    //             'success' => true,
    //             'message' =>
    //             'Data RL 3.2 kosong. Informasi debug disertakan.',
    //             'periode' => [
    //                 'bulan' => $bulan,
    //                 'tahun' => $tahun,
    //                 'tanggal_awal' => $tanggalAwal,
    //                 'tanggal_akhir' => $tanggalAkhir,
    //             ],
    //             'debug' => [
    //                 'kdprofile' => $kdProfile,

    //                 'setting_kdLaporanRekapRanap' =>
    //                 $settingJenisLaporan,

    //                 'idjenislaporan' =>
    //                 $idjenislaporan,

    //                 'jumlah_kelompoklaporanrl' =>
    //                 $jumlahKlrl,

    //                 'jumlah_setelah_join_ruangan' =>
    //                 $jumlahJoinRuangan,
    //             ],
    //             'data' => [],
    //         ], 200);
    //     }

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Parsing ruangan
    //     |--------------------------------------------------------------------------
    //     */

    //     foreach ($data as $item) {

    //         $idRuanganList =
    //             $item->idruangan_list ?? [];

    //         if (is_string($idRuanganList)) {

    //             $idRuanganList = trim(
    //                 $idRuanganList,
    //                 '{}'
    //             );

    //             if ($idRuanganList === '') {
    //                 $idRuanganList = [];
    //             } else {
    //                 $idRuanganList = explode(
    //                     ',',
    //                     $idRuanganList
    //                 );

    //                 $idRuanganList = array_values(
    //                     array_filter(
    //                         array_map(
    //                             'intval',
    //                             $idRuanganList
    //                         )
    //                     )
    //                 );
    //             }
    //         }

    //         $item->idruangan_array =
    //             $idRuanganList;

    //         /*
    //      * Jika method ini sudah tersedia.
    //      */

    //         if (
    //             method_exists(
    //                 $this,
    //                 'getBedCountForRooms'
    //             )
    //         ) {
    //             $item->jumlah_tempat_tidur =
    //                 $this->getBedCountForRooms(
    //                     $idRuanganList
    //                 );
    //         } else {
    //             $item->jumlah_tempat_tidur = 0;
    //         }
    //     }

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Data Pindahan
    //     |--------------------------------------------------------------------------
    //     |
    //     | Perbaikan:
    //     |
    //     | Jangan hardcode:
    //     |
    //     | idjenislaporan = 5
    //     |
    //     | Gunakan setting yang sama.
    //     |
    //     */

    //     $placeholders = implode(
    //         ',',
    //         array_fill(
    //             0,
    //             count($idjenislaporan),
    //             '?'
    //         )
    //     );

    //     $bindingsPindahan = $idjenislaporan;

    //     $bindingsPindahan[] = $tanggalAwal;
    //     $bindingsPindahan[] =
    //         $tanggalAkhirExclusive;

    //     $dataPindahan = $db->select("
    //     WITH perjalanan AS (
    //         SELECT
    //             k.norecpd,
    //             k.idkonten,
    //             k.created_at,

    //             LAG(k.idkonten) OVER (
    //                 PARTITION BY k.norecpd
    //                 ORDER BY k.created_at, k.id
    //             ) AS previous_idkonten,

    //             LEAD(k.idkonten) OVER (
    //                 PARTITION BY k.norecpd
    //                 ORDER BY k.created_at, k.id
    //             ) AS next_idkonten,

    //             LEAD(k.created_at) OVER (
    //                 PARTITION BY k.norecpd
    //                 ORDER BY k.created_at, k.id
    //             ) AS next_created_at

    //         FROM kelompoklaporanrl_t k

    //         WHERE k.statusenabled = TRUE

    //           AND k.idjenislaporan IN (
    //                 {$placeholders}
    //           )
    //     )

    //     SELECT
    //         p.idkonten,

    //         SUM(
    //             CASE
    //                 WHEN p.previous_idkonten IS NOT NULL
    //                  AND p.previous_idkonten <> p.idkonten
    //                  AND p.created_at >= ?::timestamp
    //                  AND p.created_at < ?::timestamp
    //                 THEN 1
    //                 ELSE 0
    //             END
    //         ) AS pasien_pindahan,

    //         SUM(
    //             CASE
    //                 WHEN p.next_idkonten IS NOT NULL
    //                  AND p.next_idkonten <> p.idkonten
    //                  AND p.next_created_at >= ?::timestamp
    //                  AND p.next_created_at < ?::timestamp
    //                 THEN 1
    //                 ELSE 0
    //             END
    //         ) AS pasien_dipindahkan

    //     FROM perjalanan p

    //     GROUP BY p.idkonten

    //     ORDER BY p.idkonten
    //     ", array_merge(
    //             $idjenislaporan,
    //             [
    //                 $tanggalAwal,
    //                 $tanggalAkhirExclusive,
    //                 $tanggalAwal,
    //                 $tanggalAkhirExclusive,
    //             ]
    //         ));

    //         $dataPindahanMap =
    //             collect($dataPindahan)
    //             ->keyBy('idkonten');

    //         /*
    //     |--------------------------------------------------------------------------
    //     | Final calculation
    //     |--------------------------------------------------------------------------
    //     */

    //     foreach ($data as $item) {

    //         $pindahanData =
    //             $dataPindahanMap->get(
    //                 $item->idkonten
    //             );

    //         $item->pasien_pindahan =
    //             (int) (
    //                 $pindahanData->pasien_pindahan
    //                 ?? 0
    //             );

    //         $item->pasien_dipindahkan =
    //             (int) (
    //                 $pindahanData->pasien_dipindahkan
    //                 ?? 0
    //             );

    //         /*
    //     |--------------------------------------------------------------------------
    //     | Total meninggal
    //     |--------------------------------------------------------------------------
    //     */

    //         $pasienMeninggalTotal =
    //             (int) (
    //                 $item
    //                 ->pasien_meninggal_kurang_48_jam_laki
    //                 ?? 0
    //             )
    //             +
    //             (int) (
    //                 $item
    //                 ->pasien_meninggal_kurang_48_jam_cewe
    //                 ?? 0
    //             )
    //             +
    //             (int) (
    //                 $item
    //                 ->pasien_meninggal_lebih_48_jam_laki
    //                 ?? 0
    //             )
    //             +
    //             (int) (
    //                 $item
    //                 ->pasien_meninggal_lebih_48_jam_cewe
    //                 ?? 0
    //             );

    //         $item->pasien_meninggal_total =
    //             $pasienMeninggalTotal;

    //         /*
    //     |--------------------------------------------------------------------------
    //     | Pasien akhir bulan
    //     |--------------------------------------------------------------------------
    //     */

    //         $item->pasien_akhir_bulan =
    //             (int) ($item->pasien_awal_bulan ?? 0)
    //             +
    //             (int) ($item->pasien_masuk ?? 0)
    //             +
    //             (int) ($item->pasien_pindahan ?? 0)
    //             -
    //             (int) ($item->pasien_keluar_hidup ?? 0)
    //             -
    //             $pasienMeninggalTotal
    //             -
    //             (int) ($item->pasien_dipindahkan ?? 0);

    //         /*
    //     |--------------------------------------------------------------------------
    //     | Validasi jumlah hari perawatan per kelas
    //     |--------------------------------------------------------------------------
    //     */

    //         $jumlahHpKelas =
    //             (int) ($item->kelas_vvip ?? 0)
    //             +
    //             (int) ($item->kelas_vip ?? 0)
    //             +
    //             (int) ($item->kelas_1 ?? 0)
    //             +
    //             (int) ($item->kelas_2 ?? 0)
    //             +
    //             (int) ($item->kelas_3 ?? 0)
    //             +
    //             (int) ($item->kelas_khusus ?? 0);

    //         $item->jumlah_hari_perawatan_kelas =
    //             $jumlahHpKelas;

    //         $item->valid_hari_perawatan =
    //             (int) (
    //                 $item->jumlah_hari_perawatan
    //                 ?? 0
    //             ) === $jumlahHpKelas;
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Success',
    //         'periode' => [
    //             'bulan' => $bulan,
    //             'tahun' => $tahun,
    //             'tanggal_awal' => $tanggalAwal,
    //             'tanggal_akhir' => $tanggalAkhir,
    //         ],
    //         'setting' => [
    //             'kdprofile' => $kdProfile,
    //             'idjenislaporan' =>
    //             $idjenislaporan,
    //         ],
    //         'data' => $data,
    //     ], 200);
    // }
    public function getDataRL3_2(Request $request)
    {
        $bulan = (int) $request->input('bulan');
        $tahun = (int) $request->input('tahun');

        $idJenisLaporan = array_values(
            array_filter(
                array_map(
                    'intval',
                    explode(',', $this->settingFix('kdLaporanRekapRanap'))
                )
            )
        );

        if (empty($idJenisLaporan)) {
            return $this->respond([
                'data' => [],
                'message' => 'Setting kdLaporanRekapRanap belum tersedia',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Periode laporan
        |--------------------------------------------------------------------------
        |
        | Contoh:
        | bulan = 8
        | tahun = 2026
        |
        | awal       = 2026-08-01
        | akhir      = 2026-08-31
        | akhirExcl  = 2026-09-01
        |
        */

        $periodeAwal = \Carbon\Carbon::create(
            $tahun,
            $bulan,
            1
        )->startOfDay();

        $periodeAkhirExclusive = $periodeAwal
            ->copy()
            ->addMonth();

        $periodeAkhir = $periodeAkhirExclusive
            ->copy()
            ->subDay()
            ->startOfDay();

        $tanggalAwal = $periodeAwal->format('Y-m-d');
        $tanggalAkhir = $periodeAkhir->format('Y-m-d');
        $tanggalAkhirExclusive = $periodeAkhirExclusive->format('Y-m-d');

        /*
        |--------------------------------------------------------------------------
        | Placeholder ID jenis laporan
        |--------------------------------------------------------------------------
        */

        $placeholders = implode(
            ',',
            array_fill(0, count($idJenisLaporan), '?')
        );

        /*
        |--------------------------------------------------------------------------
        | QUERY RL 3.2
        |--------------------------------------------------------------------------
        |
        | Konsep:
        |
        | 1. mapping
        |    Menentukan pasien + ruangan masuk ke jenis pelayanan RL mana.
        |
        | 2. room_event
        |    Mengambil tanggal pertama pasien masuk ke masing-masing ruangan.
        |
        | 3. raw_episode
        |    Menentukan perjalanan pasien antar ruangan.
        |
        | 4. segment_source
        |    Menghasilkan periode pasien berada di suatu ruangan.
        |
        | 5. segment_valid
        |    Menghilangkan ruangan antara apabila pasien pindah beberapa kali
        |    dalam hari yang sama.
        |
        |    Contoh:
        |
        |    10 Agustus:
        |    Ruang A 08:00
        |    Ruang B 10:00
        |    Ruang C 14:00
        |
        |    Maka untuk sensus tanggal 10 Agustus pasien dihitung pada Ruang C.
        |
        | 6. effective_episode
        |    Menentukan pasien masuk, pindahan dan dipindahkan.
        |
        | 7. calculated
        |    Menghitung hari perawatan dalam periode laporan.
        |
        */

        $sql = "
        WITH mapping AS (
            SELECT DISTINCT
                k.norecpd,
                k.idruangan,
                k.idkonten
            FROM kelompoklaporanrl_t k
            WHERE k.idjenislaporan IN ($placeholders)
              AND k.statusenabled = TRUE
        ),

        room_event AS (
            SELECT
                apd.noregistrasifk AS norecpd,
                apd.objectruanganfk AS idruangan,
                m.idkonten,
                MIN(apd.tglmasuk) AS tglmasuk
            FROM antrianpasiendiperiksa_t apd

            INNER JOIN mapping m
                ON m.norecpd = apd.noregistrasifk
               AND m.idruangan = apd.objectruanganfk

            WHERE apd.tglmasuk IS NOT NULL

            GROUP BY
                apd.noregistrasifk,
                apd.objectruanganfk,
                m.idkonten
        ),

        raw_episode AS (
            SELECT
                r.*,

                MIN(r.tglmasuk) OVER (
                    PARTITION BY r.norecpd
                ) AS tglmasuk_rs,

                LEAD(r.tglmasuk) OVER (
                    PARTITION BY r.norecpd
                    ORDER BY r.tglmasuk, r.idruangan
                ) AS next_tglmasuk

            FROM room_event r
        ),

        segment_source AS (
            SELECT
                r.norecpd,
                r.idruangan,
                r.idkonten,
                r.tglmasuk,
                r.tglmasuk_rs,
                r.next_tglmasuk,

                pdt.tglpulang,
                pdt.tglmeninggal,
                pdt.objectkelasfk,

                pm.objectjeniskelaminfk,

                COALESCE(
                    pdt.tglpulang,
                    pdt.tglmeninggal
                ) AS tglkeluar_rs,

                r.tglmasuk::date AS hari_mulai,

                CASE
                    /*
                     * Apabila pasien pindah ruangan,
                     * hari pindah menjadi milik ruangan berikutnya.
                     *
                     * Ini penting untuk ketentuan:
                     * pindah beberapa kali dalam sehari =
                     * dicatat pada pelayanan terakhir hari tersebut.
                     */
                    WHEN r.next_tglmasuk IS NOT NULL
                    THEN r.next_tglmasuk::date - 1

                    /*
                     * Episode terakhir dan pasien sudah keluar RS.
                     */
                    WHEN COALESCE(
                        pdt.tglpulang,
                        pdt.tglmeninggal
                    ) IS NOT NULL
                    THEN COALESCE(
                        pdt.tglpulang,
                        pdt.tglmeninggal
                    )::date

                    /*
                     * Pasien masih dirawat.
                     */
                    ELSE ?::date
                END AS hari_selesai

            FROM raw_episode r

            INNER JOIN pasiendaftar_t pdt
                ON pdt.norec = r.norecpd

            INNER JOIN pasien_m pm
                ON pm.id = pdt.nocmfk
        ),

        /*
        |--------------------------------------------------------------------------
        | Hilangkan episode 0 hari
        |--------------------------------------------------------------------------
        |
        | Contoh:
        | ruang A -> B -> C pada tanggal yang sama.
        |
        | Ruang A / B tidak menghasilkan hari rawat.
        | Ruang C menjadi pelayanan terakhir hari tersebut.
        |
        */

        segment_valid AS (
            SELECT *
            FROM segment_source
            WHERE hari_selesai >= hari_mulai
        ),

        effective_episode AS (
            SELECT
                s.*,

                ROW_NUMBER() OVER (
                    PARTITION BY s.norecpd
                    ORDER BY s.hari_mulai, s.tglmasuk, s.idruangan
                ) AS urutan_episode,

                COUNT(*) OVER (
                    PARTITION BY s.norecpd
                ) AS jumlah_episode,

                LAG(s.idkonten) OVER (
                    PARTITION BY s.norecpd
                    ORDER BY s.hari_mulai, s.tglmasuk, s.idruangan
                ) AS previous_idkonten,

                LEAD(s.idkonten) OVER (
                    PARTITION BY s.norecpd
                    ORDER BY s.hari_mulai, s.tglmasuk, s.idruangan
                ) AS next_idkonten,

                LEAD(s.hari_mulai) OVER (
                    PARTITION BY s.norecpd
                    ORDER BY s.hari_mulai, s.tglmasuk, s.idruangan
                ) AS next_hari_mulai

            FROM segment_valid s
        ),

        calculated AS (
            SELECT
                e.*,

                /*
                |--------------------------------------------------------------------------
                | Hari Perawatan dalam bulan laporan
                |--------------------------------------------------------------------------
                |
                | Rumus:
                |
                | tanggal keluar - tanggal masuk + 1
                |
                | Tetapi dibatasi hanya hari yang berada
                | dalam periode laporan.
                */

                CASE
                    WHEN e.hari_selesai >= ?::date
                     AND e.hari_mulai < ?::date
                    THEN
                        (
                            LEAST(
                                e.hari_selesai,
                                ?::date
                            )
                            -
                            GREATEST(
                                e.hari_mulai,
                                ?::date
                            )
                        ) + 1
                    ELSE 0
                END AS hari_perawatan_bulan

            FROM effective_episode e
        ),

        aggregate_rl AS (
            SELECT
                c.idkonten,

                /*
                |--------------------------------------------------------------------------
                | PASIEN AWAL BULAN
                |--------------------------------------------------------------------------
                |
                | Pasien yang sudah berada di pelayanan tersebut
                | saat memasuki hari pertama bulan laporan.
                */

                COUNT(
                    DISTINCT CASE
                        WHEN c.hari_mulai <= ?::date
                         AND c.hari_selesai >= ?::date
                        THEN c.norecpd
                    END
                ) AS pasien_awal_bulan,

                /*
                |--------------------------------------------------------------------------
                | PASIEN MASUK
                |--------------------------------------------------------------------------
                |
                | Episode pelayanan efektif pertama pasien.
                */

                COUNT(
                    DISTINCT CASE
                        WHEN c.urutan_episode = 1
                         AND c.hari_mulai >= ?::date
                         AND c.hari_mulai < ?::date
                        THEN c.norecpd
                    END
                ) AS pasien_masuk,

                /*
                |--------------------------------------------------------------------------
                | PASIEN PINDAHAN
                |--------------------------------------------------------------------------
                |
                | Masuk dari jenis pelayanan RL lainnya.
                |
                | Pindah kamar tetapi idkonten sama
                | TIDAK dihitung sebagai pindahan antar pelayanan.
                */

                SUM(
                    CASE
                        WHEN c.previous_idkonten IS NOT NULL
                         AND c.previous_idkonten <> c.idkonten
                         AND c.hari_mulai >= ?::date
                         AND c.hari_mulai < ?::date
                        THEN 1
                        ELSE 0
                    END
                ) AS pasien_pindahan,

                /*
                |--------------------------------------------------------------------------
                | PASIEN DIPINDAHKAN
                |--------------------------------------------------------------------------
                */

                SUM(
                    CASE
                        WHEN c.next_idkonten IS NOT NULL
                         AND c.next_idkonten <> c.idkonten
                         AND c.next_hari_mulai >= ?::date
                         AND c.next_hari_mulai < ?::date
                        THEN 1
                        ELSE 0
                    END
                ) AS pasien_dipindahkan,

                /*
                |--------------------------------------------------------------------------
                | PASIEN KELUAR HIDUP
                |--------------------------------------------------------------------------
                |
                | Harus:
                |
                | - episode terakhir
                | - benar-benar pulang dari RS
                | - tidak meninggal
                | - tanggal keluar berada dalam bulan laporan
                */

                COUNT(
                    DISTINCT CASE
                        WHEN c.urutan_episode = c.jumlah_episode
                         AND c.tglpulang IS NOT NULL
                         AND c.tglmeninggal IS NULL
                         AND c.tglpulang >= ?::timestamp
                         AND c.tglpulang < ?::timestamp
                        THEN c.norecpd
                    END
                ) AS pasien_keluar_hidup,

                /*
                |--------------------------------------------------------------------------
                | MENINGGAL < 48 JAM - LAKI-LAKI
                |--------------------------------------------------------------------------
                */

                COUNT(
                    DISTINCT CASE
                        WHEN c.urutan_episode = c.jumlah_episode
                         AND c.tglmeninggal IS NOT NULL
                         AND c.tglmeninggal >= ?::timestamp
                         AND c.tglmeninggal < ?::timestamp
                         AND c.objectjeniskelaminfk = 1
                         AND (
                             c.tglmeninggal - c.tglmasuk_rs
                         ) < INTERVAL '48 hours'
                        THEN c.norecpd
                    END
                ) AS pasien_meninggal_kurang_48_jam_laki,

                /*
                |--------------------------------------------------------------------------
                | MENINGGAL < 48 JAM - PEREMPUAN
                |--------------------------------------------------------------------------
                */

                COUNT(
                    DISTINCT CASE
                        WHEN c.urutan_episode = c.jumlah_episode
                         AND c.tglmeninggal IS NOT NULL
                         AND c.tglmeninggal >= ?::timestamp
                         AND c.tglmeninggal < ?::timestamp
                         AND c.objectjeniskelaminfk = 2
                         AND (
                             c.tglmeninggal - c.tglmasuk_rs
                         ) < INTERVAL '48 hours'
                        THEN c.norecpd
                    END
                ) AS pasien_meninggal_kurang_48_jam_cewe,

                /*
                |--------------------------------------------------------------------------
                | MENINGGAL >= 48 JAM - LAKI-LAKI
                |--------------------------------------------------------------------------
                */

                COUNT(
                    DISTINCT CASE
                        WHEN c.urutan_episode = c.jumlah_episode
                         AND c.tglmeninggal IS NOT NULL
                         AND c.tglmeninggal >= ?::timestamp
                         AND c.tglmeninggal < ?::timestamp
                         AND c.objectjeniskelaminfk = 1
                         AND (
                             c.tglmeninggal - c.tglmasuk_rs
                         ) >= INTERVAL '48 hours'
                        THEN c.norecpd
                    END
                ) AS pasien_meninggal_lebih_48_jam_laki,

                /*
                |--------------------------------------------------------------------------
                | MENINGGAL >= 48 JAM - PEREMPUAN
                |--------------------------------------------------------------------------
                */

                COUNT(
                    DISTINCT CASE
                        WHEN c.urutan_episode = c.jumlah_episode
                         AND c.tglmeninggal IS NOT NULL
                         AND c.tglmeninggal >= ?::timestamp
                         AND c.tglmeninggal < ?::timestamp
                         AND c.objectjeniskelaminfk = 2
                         AND (
                             c.tglmeninggal - c.tglmasuk_rs
                         ) >= INTERVAL '48 hours'
                        THEN c.norecpd
                    END
                ) AS pasien_meninggal_lebih_48_jam_cewe,

                /*
                |--------------------------------------------------------------------------
                | JUMLAH LAMA DIRAWAT
                |--------------------------------------------------------------------------
                |
                | Juknis:
                |
                | tanggal keluar - tanggal masuk
                |
                | TANPA + 1
                |
                | Hanya pasien yang benar-benar keluar RS
                | pada bulan laporan.
                */

                SUM(
                    CASE
                        WHEN c.urutan_episode = c.jumlah_episode
                         AND c.tglkeluar_rs IS NOT NULL
                         AND c.tglkeluar_rs >= ?::timestamp
                         AND c.tglkeluar_rs < ?::timestamp
                        THEN
                            GREATEST(
                                0,
                                (
                                    c.tglkeluar_rs::date
                                    -
                                    c.tglmasuk_rs::date
                                )
                            )
                        ELSE 0
                    END
                ) AS jumlah_lama_dirawat,

                /*
                |--------------------------------------------------------------------------
                | JUMLAH HARI PERAWATAN
                |--------------------------------------------------------------------------
                */

                SUM(
                    c.hari_perawatan_bulan
                ) AS jumlah_hari_perawatan,

                /*
                |--------------------------------------------------------------------------
                | HARI PERAWATAN PER KELAS
                |--------------------------------------------------------------------------
                |
                | CATATAN:
                |
                | Saat ini masih menggunakan:
                | pdt.objectkelasfk
                |
                | Jika kelas pasien dapat berubah saat pindah ruangan,
                | sebaiknya sumber objectkelasfk diambil dari episode/APD.
                */

                SUM(
                    CASE
                        WHEN c.objectkelasfk = 5
                        THEN c.hari_perawatan_bulan
                        ELSE 0
                    END
                ) AS kelas_vvip,

                SUM(
                    CASE
                        WHEN c.objectkelasfk = 4
                        THEN c.hari_perawatan_bulan
                        ELSE 0
                    END
                ) AS kelas_vip,

                SUM(
                    CASE
                        WHEN c.objectkelasfk = 3
                        THEN c.hari_perawatan_bulan
                        ELSE 0
                    END
                ) AS kelas_1,

                SUM(
                    CASE
                        WHEN c.objectkelasfk = 2
                        THEN c.hari_perawatan_bulan
                        ELSE 0
                    END
                ) AS kelas_2,

                SUM(
                    CASE
                        WHEN c.objectkelasfk = 1
                        THEN c.hari_perawatan_bulan
                        ELSE 0
                    END
                ) AS kelas_3,

                SUM(
                    CASE
                        WHEN c.objectkelasfk IS NULL
                          OR c.objectkelasfk NOT IN (1,2,3,4,5)
                        THEN c.hari_perawatan_bulan
                        ELSE 0
                    END
                ) AS kelas_khusus,

                /*
                |--------------------------------------------------------------------------
                | CENSUS AKHIR BULAN
                |--------------------------------------------------------------------------
                |
                | Digunakan sebagai validasi terhadap rumus RL 3.2.
                */

                COUNT(
                    DISTINCT CASE
                        WHEN c.hari_mulai <= ?::date
                         AND c.hari_selesai >= ?::date
                        THEN c.norecpd
                    END
                ) AS pasien_akhir_bulan_census

            FROM calculated c

            GROUP BY c.idkonten
        ),

        room_list AS (
            SELECT
                idkonten,
                ARRAY_AGG(
                    DISTINCT idruangan
                ) AS idruangan_list

            FROM mapping

            GROUP BY idkonten
        ),

        service_list AS (
            SELECT DISTINCT
                m.idkonten,
                km.reportdisplay AS jenis_pelayanan

            FROM mapping m

            INNER JOIN kelompoklaporan_m km
                ON km.id = m.idkonten
        )

        SELECT
            s.idkonten,

            s.jenis_pelayanan AS \"jenisPelayanan\",

            COALESCE(
                a.pasien_awal_bulan,
                0
            ) AS pasien_awal_bulan,

            COALESCE(
                a.pasien_masuk,
                0
            ) AS pasien_masuk,

            COALESCE(
                a.pasien_pindahan,
                0
            ) AS pasien_pindahan,

            COALESCE(
                a.pasien_dipindahkan,
                0
            ) AS pasien_dipindahkan,

            COALESCE(
                a.pasien_keluar_hidup,
                0
            ) AS pasien_keluar_hidup,

            COALESCE(
                a.pasien_meninggal_kurang_48_jam_laki,
                0
            ) AS pasien_meninggal_kurang_48_jam_laki,

            COALESCE(
                a.pasien_meninggal_kurang_48_jam_cewe,
                0
            ) AS pasien_meninggal_kurang_48_jam_cewe,

            COALESCE(
                a.pasien_meninggal_lebih_48_jam_laki,
                0
            ) AS pasien_meninggal_lebih_48_jam_laki,

            COALESCE(
                a.pasien_meninggal_lebih_48_jam_cewe,
                0
            ) AS pasien_meninggal_lebih_48_jam_cewe,

            COALESCE(
                a.jumlah_lama_dirawat,
                0
            ) AS jumlah_lama_dirawat,

            COALESCE(
                a.jumlah_hari_perawatan,
                0
            ) AS jumlah_hari_perawatan,

            COALESCE(
                a.kelas_vvip,
                0
            ) AS kelas_vvip,

            COALESCE(
                a.kelas_vip,
                0
            ) AS kelas_vip,

            COALESCE(
                a.kelas_1,
                0
            ) AS kelas_1,

            COALESCE(
                a.kelas_2,
                0
            ) AS kelas_2,

            COALESCE(
                a.kelas_3,
                0
            ) AS kelas_3,

            COALESCE(
                a.kelas_khusus,
                0
            ) AS kelas_khusus,

            COALESCE(
                a.pasien_akhir_bulan_census,
                0
            ) AS pasien_akhir_bulan_census,

            r.idruangan_list

        FROM service_list s

        LEFT JOIN aggregate_rl a
            ON a.idkonten = s.idkonten

        LEFT JOIN room_list r
            ON r.idkonten = s.idkonten

        ORDER BY s.idkonten
        ";

        /*
        |--------------------------------------------------------------------------
        | Binding
        |--------------------------------------------------------------------------
        */

        $bindings = [];

        /*
     * mapping.idjenislaporan
     */
        foreach ($idJenisLaporan as $id) {
            $bindings[] = $id;
        }

        /*
     * segment_source
     * fallback pasien masih dirawat
     */
        $bindings[] = $tanggalAkhir;

        /*
     * calculated hari perawatan
     */
        $bindings[] = $tanggalAwal;
        $bindings[] = $tanggalAkhirExclusive;
        $bindings[] = $tanggalAkhir;
        $bindings[] = $tanggalAwal;

        /*
     * pasien awal bulan
     */
        $bindings[] = $tanggalAwal;
        $bindings[] = $tanggalAwal;

        /*
     * pasien masuk
     */
        $bindings[] = $tanggalAwal;
        $bindings[] = $tanggalAkhirExclusive;

        /*
     * pasien pindahan
     */
        $bindings[] = $tanggalAwal;
        $bindings[] = $tanggalAkhirExclusive;

        /*
     * pasien dipindahkan
     */
        $bindings[] = $tanggalAwal;
        $bindings[] = $tanggalAkhirExclusive;

        /*
     * keluar hidup
     */
        $bindings[] = $tanggalAwal;
        $bindings[] = $tanggalAkhirExclusive;

        /*
     * meninggal <48 laki
     */
        $bindings[] = $tanggalAwal;
        $bindings[] = $tanggalAkhirExclusive;

        /*
     * meninggal <48 perempuan
     */
        $bindings[] = $tanggalAwal;
        $bindings[] = $tanggalAkhirExclusive;

        /*
     * meninggal >=48 laki
     */
        $bindings[] = $tanggalAwal;
        $bindings[] = $tanggalAkhirExclusive;

        /*
     * meninggal >=48 perempuan
     */
        $bindings[] = $tanggalAwal;
        $bindings[] = $tanggalAkhirExclusive;

        /*
     * jumlah lama dirawat
     */
        $bindings[] = $tanggalAwal;
        $bindings[] = $tanggalAkhirExclusive;

        /*
     * census pasien akhir bulan
     */
        $bindings[] = $tanggalAkhir;
        $bindings[] = $tanggalAkhir;

        $data = collect(
            DB::connection('pgsql')->select($sql, $bindings)
        );

        /*
        |--------------------------------------------------------------------------
        | Post processing
        |--------------------------------------------------------------------------
        */

        foreach ($data as $item) {

            /*
        |--------------------------------------------------------------------------
        | Parsing ID ruangan PostgreSQL
        |--------------------------------------------------------------------------
        */

            $idRuanganList = $item->idruangan_list ?? [];

            if (is_string($idRuanganList)) {
                $idRuanganList = trim(
                    $idRuanganList,
                    '{}'
                );

                if ($idRuanganList === '') {
                    $idRuanganList = [];
                } else {
                    $idRuanganList = array_values(
                        array_filter(
                            array_map(
                                'intval',
                                explode(',', $idRuanganList)
                            )
                        )
                    );
                }
            }

            $item->idruangan_array = $idRuanganList;

            /*
        |--------------------------------------------------------------------------
        | Jumlah tempat tidur
        |--------------------------------------------------------------------------
        |
        | Catatan:
        | RL 3.2 meminta alokasi TT yang berlaku AWAL BULAN.
        |
        | Kalau getBedCountForRooms() mengambil kondisi TT saat ini,
        | nanti sebaiknya fungsi tersebut ditambahkan parameter tanggal.
        */

            $item->jumlah_tempat_tidur =
                $this->getBedCountForRooms(
                    $item->idruangan_array
                );

            /*
        |--------------------------------------------------------------------------
        | Total meninggal
        |--------------------------------------------------------------------------
        */

            $meninggalTotal =
                (int) ($item->pasien_meninggal_kurang_48_jam_laki ?? 0)
                +
                (int) ($item->pasien_meninggal_kurang_48_jam_cewe ?? 0)
                +
                (int) ($item->pasien_meninggal_lebih_48_jam_laki ?? 0)
                +
                (int) ($item->pasien_meninggal_lebih_48_jam_cewe ?? 0);

            /*
        |--------------------------------------------------------------------------
        | Pasien akhir bulan
        |--------------------------------------------------------------------------
        |
        | Formula resmi RL 3.2:
        |
        | Awal
        | + Masuk
        | + Pindahan
        | - Keluar hidup
        | - Keluar mati
        | - Dipindahkan
        |
        */

            $item->pasien_akhir_bulan =
                (int) ($item->pasien_awal_bulan ?? 0)
                +
                (int) ($item->pasien_masuk ?? 0)
                +
                (int) ($item->pasien_pindahan ?? 0)
                -
                (int) ($item->pasien_keluar_hidup ?? 0)
                -
                $meninggalTotal
                -
                (int) ($item->pasien_dipindahkan ?? 0);

            /*
        |--------------------------------------------------------------------------
        | Validasi keseimbangan pasien akhir
        |--------------------------------------------------------------------------
        */

            $item->selisih_pasien_akhir =
                (int) $item->pasien_akhir_bulan
                -
                (int) ($item->pasien_akhir_bulan_census ?? 0);

            $item->valid_pasien_akhir =
                $item->selisih_pasien_akhir === 0;

            /*
        |--------------------------------------------------------------------------
        | Validasi Hari Perawatan
        |--------------------------------------------------------------------------
        |
        | Harus sama dengan:
        |
        | VVIP + VIP + I + II + III + Khusus
        */

            $totalHariPerawatanKelas =
                (int) ($item->kelas_vvip ?? 0)
                +
                (int) ($item->kelas_vip ?? 0)
                +
                (int) ($item->kelas_1 ?? 0)
                +
                (int) ($item->kelas_2 ?? 0)
                +
                (int) ($item->kelas_3 ?? 0)
                +
                (int) ($item->kelas_khusus ?? 0);

            $item->total_hari_perawatan_kelas =
                $totalHariPerawatanKelas;

            $item->valid_hari_perawatan =
                (int) ($item->jumlah_hari_perawatan ?? 0)
                === $totalHariPerawatanKelas;

            /*
        |--------------------------------------------------------------------------
        | Validasi HP >= LD
        |--------------------------------------------------------------------------
        */

            $item->valid_hp_ld =
                (int) ($item->jumlah_hari_perawatan ?? 0)
                >=
                (int) ($item->jumlah_lama_dirawat ?? 0);
        }

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'periode' => [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'tanggal_awal' => $tanggalAwal,
                'tanggal_akhir' => $tanggalAkhir,
            ],
            'data' => $data,
        ], 200);
    }

    public function settingDataFixed($NamaField, $KdProfile = null)
    {
        $Query = DB::connection('pgsql')->table('settingdatafixed_m')
            ->where('namafield', '=', $NamaField);
        if ($KdProfile) {
            $Query->where('kdprofile', '=', $KdProfile);
        }
        $settingDataFixed = $Query->first();
        if (!empty($settingDataFixed)) {
            return $settingDataFixed->nilaifield;
        } else {
            return null;
        }
    }
    public static function settingDataFixed2($NamaField, $KdProfile = null)
    {
        $Query = DB::connection('pgsql')->table('settingdatafixed_m')
            ->where('namafield', '=', $NamaField);
        if ($KdProfile) {
            $Query->where('kdprofile', '=', $KdProfile);
        }
        $settingDataFixed = $Query->first();
        if (!empty($settingDataFixed)) {
            return $settingDataFixed->nilaifield;
        } else {
            return null;
        }
    }

    public function settingFix($namaField, $kdProfile = 1)
    {
        $kdProfile = (int) ($kdProfile ?: 1);

        $cacheKey = "settingFix.{$kdProfile}.{$namaField}";

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(60),
            function () use ($namaField, $kdProfile) {

                $setting = DB::connection('pgsql')
                    ->table('settingdatafixed_m')
                    ->where('namafield', $namaField)
                    ->where('statusenabled', true)
                    ->where('kdprofile', $kdProfile)
                    ->first();

                return $setting
                    ? $setting->nilaifield
                    : 0;
            }
        );
    }
    private function getBedCountForRooms($roomIds)
    {
        return DB::connection('pgsql')->table('tempattidur_m as tm')
            ->join('kamar_m as km', 'km.id', '=', 'tm.objectkamarfk')
            ->join('ruangan_m as rm', 'rm.id', '=', 'km.objectruanganfk')
            ->whereIn('rm.id', $roomIds)
            ->where('tm.statusenabled', true)
            ->count();
    }
}
