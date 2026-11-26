<?php
namespace App\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
// Jhon | 2026-11-25 19:41 WITA | TAMBAH: Query dan kalkulasi RL dari LaporanRekamMedisNewCtrl SIMRSBM-TM; baca-saja.
class SirsRlReportService
{
    private $kdProfile = 1;
    public function run(string $method, Request $request)
    {
        // Jhon | 2026-11-25 21:16 WITA | UBAH: khusus RL3.2 mengikuti versi v1 yang dipilih pengguna.
        if ($method === 'getDataRL3_2') return app(SirsRl32V1Service::class)->getDataRL31RawatInap($request);
        return $this->{$method}($request);
    }
    private function respond($data, $status = 200)
    {
        if ($status >= 400) throw new \RuntimeException('Laporan gagal dihitung.');
        return ['metaData' => ['code' => $status, 'message' => 'Success'], 'response' => $data];
    }
    private function settingFix($name)
    {
        $value = DB::connection('pgsql')->table('settingdatafixed_m')
            ->where('kdprofile', $this->kdProfile)->where('statusenabled', true)
            ->where('namafield', $name)->value('nilaifield');
        // Jhon | 2026-11-25 21:04 WITA | UBAH: identifikasi setting sumber yang hilang tanpa menampilkan isi setting.
        if ($value === null) throw new \DomainException((string) $name);
        return $value;
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: getDataRL3_1; pertahankan struktur hasil sumber.
    private function getDataRL3_1(Request $request)
    {
        $bulan = $request['bulan'];
        $tahun = $request['tahun'];
        $idjenislaporan = explode(',', $this->settingFix('kdLaporanRekapRanap'));

        // Get service type IDs from settings
        $kdicu = explode(',', $this->settingFix('kdicu'));
        $kdnicu = explode(',', $this->settingFix('kdnicu'));
        $kdpicu = explode(',', $this->settingFix('kdpicu'));
        $kdintensiflainnya = explode(',', $this->settingFix('kdintensiflainnya'));

        // Define service type categories
        $kategoriBelakang = [
            'ICU' => $kdicu,
            'NICU' => $kdnicu,
            'PICU' => $kdpicu,
            'Intensif lainnya' => $kdintensiflainnya
        ];

        // Get days in the selected month
        $jumlahHariDalamBulan = cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);

        // Main query to get base data
        $data = DB::connection('pgsql')->table('kelompoklaporanrl_t as klrl')
            ->join('pasiendaftar_t as pdt', 'pdt.norec', '=', 'klrl.norecpd')
            ->join(
                DB::raw("(SELECT 
                                noregistrasifk, 
                                objectruanganfk, 
                                MIN(tglmasuk) as tglmasuk 
                            FROM antrianpasiendiperiksa_t 
                            GROUP BY noregistrasifk, objectruanganfk) as apd"),
                function ($join) {
                    $join->on('apd.noregistrasifk', '=', 'klrl.norecpd')
                        ->on('apd.objectruanganfk', '=', 'klrl.idruangan');
                }
            )
            ->join('kelompoklaporan_m as kon', 'kon.id', '=', 'klrl.idkonten')
            ->join('pasien_m AS pm', 'pm.id', '=', 'pdt.nocmfk')
            ->select(
                'kon.id as idkonten',
                'kon.reportdisplay as jenisPelayanan',
                DB::raw('COUNT(*) as pasien_masuk'),
                DB::raw('SUM(CASE WHEN pdt.tglmeninggal IS NOT NULL AND pdt.tglmeninggal - pdt.tglregistrasi < INTERVAL \'48 hours\' THEN 1 ELSE 0 END) as pasien_mati_kurang_48_jam'),
                DB::raw('SUM(CASE WHEN pdt.tglmeninggal IS NOT NULL THEN 1 ELSE 0 END) as total_pasien_mati'),
                DB::raw('SUM(DATE_PART(\'day\', pdt.tglpulang - pdt.tglregistrasi) + 1) as jumlah_hari_perawatan'),
                DB::raw('SUM(CASE WHEN pdt.objectstatuskeluarfk IN (5,6) THEN 1 ELSE 0 END) as pasien_keluar'),
                DB::raw('ARRAY_AGG(DISTINCT klrl.idruangan) as idruangan_list')
            )
            ->whereMonth('apd.tglmasuk', $bulan)
            ->whereYear('apd.tglmasuk', $tahun)
            ->whereIn('klrl.idjenislaporan', $idjenislaporan)
            ->groupBy('kon.id', 'kon.reportdisplay')
            ->get();

        // Prepare flat list of all defined category IDs
        $definedCategoryIds = collect($kategoriBelakang)->flatten()->toArray();

        // Aggregate data by service type categories
        $hasilAkhir = [];

        // Combine predefined categories and non-intensive
        $kategoriUntukDiproses = array_merge(
            $kategoriBelakang,
            ['Non Intensif' => []]  // Empty array for non-intensive
        );

        foreach ($kategoriUntukDiproses as $kategori => $kategoriIds) {
            // For non-intensive, filter out all predefined category IDs
            if ($kategori === 'Non Intensif') {
                $dataKategori = $data->filter(function ($item) use ($definedCategoryIds) {
                    return !in_array($item->idkonten, $definedCategoryIds);
                });
            } else {
                // For other categories, match specific IDs
                $dataKategori = $data->whereIn('idkonten', $kategoriIds);
            }

            // Skip if no data for this category
            if ($dataKategori->isEmpty()) {
                $hasilAkhir[] = [
                    'jenis_pelayanan' => $kategori,
                    'jumlah_tempat_tidur' => 0,
                    'pasien_masuk' => 0,
                    'total_hari_perawatan' => 0,
                    'total_pasien_keluar_mati' => 0,
                    'pasien_mati_kurang_48_jam' => 0,
                    'bor' => 0,
                    'alos' => 0,
                    'bto' => 0,
                    'toi' => 0,
                    'ndr' => 0,
                    'gdr' => 0
                ];
                continue;
            }

            // Collect all room IDs for this category
            $allRoomIds = $dataKategori->flatMap(function ($item) {
                $roomList = is_string($item->idruangan_list)
                    ? explode(',', str_replace(['{', '}'], '', $item->idruangan_list))
                    : $item->idruangan_list;
                return $roomList;
            })->unique()->values()->all();

            // Get total bed count for these rooms
            $jumlahTempatTidur = DB::connection('pgsql')->table('tempattidur_m as tm')
                ->join('kamar_m as km', 'km.id', '=', 'tm.objectkamarfk')
                ->join('ruangan_m as rm', 'rm.id', '=', 'km.objectruanganfk')
                ->whereIn('rm.id', $allRoomIds)
                ->where('tm.statusenabled', true)
                ->count();

            $totalPasienMasuk = $dataKategori->sum('pasien_masuk');
            $totalHariPerawatan = $dataKategori->sum('jumlah_hari_perawatan');
            $totalPasienKeluarMati = $dataKategori->sum('total_pasien_mati');
            $pasienMatiKurang48Jam = $dataKategori->sum('pasien_mati_kurang_48_jam');
            $totalPasienKeluar = $dataKategori->sum('pasien_keluar') + $totalPasienKeluarMati;

            // Calculation helper function to prevent division by zero
            $safeDiv = function ($numerator, $denominator, $defaultValue = 0) {
                return $denominator != 0 ? $numerator / $denominator : $defaultValue;
            };

            // Calculate indicators
            $bor = $jumlahTempatTidur > 0
                ? ($safeDiv($totalHariPerawatan, $jumlahTempatTidur * $jumlahHariDalamBulan) * 100)
                : 0;

            $alos = $safeDiv($totalHariPerawatan, $totalPasienKeluar);

            $bto = $safeDiv($totalPasienKeluar, $jumlahTempatTidur);

            $toi = $jumlahTempatTidur > 0
                ? $safeDiv(($jumlahTempatTidur * $jumlahHariDalamBulan) - $totalHariPerawatan, $totalPasienKeluar)
                : 0;

            // NDR & GDR Calculations (per 1000 patient discharges)
            $ndr = $safeDiv($pasienMatiKurang48Jam, $totalPasienKeluar) * 1000;

            $gdr = $safeDiv($totalPasienKeluarMati, $totalPasienKeluar) * 1000;

            $hasilAkhir[] = [
                'jenis_pelayanan' => $kategori,
                'jumlah_tempat_tidur' => $jumlahTempatTidur,
                'pasien_masuk' => $totalPasienMasuk,
                'total_hari_perawatan' => $totalHariPerawatan,
                'total_pasien_keluar_mati' => $totalPasienKeluarMati,
                'pasien_mati_kurang_48_jam' => $pasienMatiKurang48Jam,
                'bor' => round($bor, 2),
                'alos' => round($alos, 2),
                'bto' => round($bto, 2),
                'toi' => round($toi, 2),
                'ndr' => round($ndr, 2),
                'gdr' => round($gdr, 2)
            ];
        }

        return $this->respond(['data' => $hasilAkhir, 'message' => 'Success']);
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: getDataRL3_2; pertahankan struktur hasil sumber.
    
private function getDataRL3_2(Request $request)
{
    /*
    |--------------------------------------------------------------------------
    | 1. Validasi parameter laporan
    |--------------------------------------------------------------------------
    */

    $request->validate([
        'bulan' => 'required|integer|min:1|max:12',
        'tahun' => 'required|integer|min:2000|max:2100',
    ]);

    $bulan = (int) $request['bulan'];
    $tahun = (int) $request['tahun'];
    $kdProfile = (int) $this->kdProfile;

    $idjenislaporan = array_values(
        array_unique(
            array_filter(
                array_map(
                    'intval',
                    explode(
                        ',',
                        $this->settingFix('kdLaporanRekapRanap')
                    )
                ),
                function ($id) {
                    return $id > 0;
                }
            )
        )
    );

    if (empty($idjenislaporan)) {
        return $this->respond([
            'data' => [],
            'message' => 'Konfigurasi jenis laporan belum tersedia.'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Menentukan periode laporan
    |--------------------------------------------------------------------------
    */

    $awalBulan = Carbon::create(
        $tahun,
        $bulan,
        1,
        0,
        0,
        0,
        'Asia/Makassar'
    );

    $akhirBulan = $awalBulan->copy()->addMonth();

    $sekarang = Carbon::now('Asia/Makassar');

    if ($awalBulan->gt($sekarang)) {
        return $this->respond([
            'data' => [],
            'message' => 'Periode laporan belum berjalan.'
        ]);
    }

    /*
     * Jika laporan bulan berjalan,
     * gunakan waktu saat ini sebagai batas laporan.
     */
    $batasLaporan = $akhirBulan->gt($sekarang)
        ? $sekarang->copy()
        : $akhirBulan->copy();

    $placeholders = implode(
        ',',
        array_fill(0, count($idjenislaporan), '?')
    );

    /*
    |--------------------------------------------------------------------------
    | 3. Query utama RL 3.2
    |--------------------------------------------------------------------------
    */

    $sql = "

        WITH periode AS (
            SELECT
                CAST(? AS TIMESTAMP) AS awal_bulan,
                CAST(? AS TIMESTAMP) AS akhir_bulan
        ),

        /* ==================================================
           PEMETAAN KELOMPOK LAPORAN
        ================================================== */

        mapping_raw AS (
            SELECT DISTINCT
                klrl.norecpd,
                klrl.idruangan,
                klrl.idkonten

            FROM kelompoklaporanrl_t klrl

            WHERE klrl.statusenabled = TRUE

                AND klrl.idjenislaporan IN ($placeholders)

                AND klrl.norecpd IS NOT NULL

                AND klrl.idruangan IS NOT NULL

                AND klrl.idkonten IS NOT NULL
        ),

        /* ==================================================
           MENENTUKAN KANDIDAT PASIEN PERIODE LAPORAN

           Pasien bulan sebelumnya tetap diambil apabila
           belum keluar sebelum awal periode laporan.
        ================================================== */

        kandidat AS (
            SELECT DISTINCT
                pdt.norec AS norecpd

            FROM pasiendaftar_t pdt

            CROSS JOIN periode pr

            WHERE pdt.tglregistrasi < pr.akhir_bulan

                AND (
                    COALESCE(
                        pdt.tglmeninggal,
                        pdt.tglpulang
                    ) >= pr.awal_bulan

                    OR (
                        pdt.tglpulang IS NULL
                        AND pdt.tglmeninggal IS NULL
                    )
                )

                AND EXISTS (
                    SELECT 1
                    FROM mapping_raw m
                    WHERE m.norecpd = pdt.norec
                )
        ),

        /* ==================================================
           CEK KONFLIK PEMETAAN

           Satu pasien dalam satu ruangan tidak boleh
           terhitung pada beberapa kelompok sekaligus.
        ================================================== */

        mapping_konflik AS (
            SELECT
                m.norecpd,
                m.idruangan

            FROM mapping_raw m

            INNER JOIN kandidat kd
                ON kd.norecpd = m.norecpd

            GROUP BY
                m.norecpd,
                m.idruangan

            HAVING COUNT(DISTINCT m.idkonten) > 1
        ),

        registrasi_konflik AS (
            SELECT DISTINCT
                norecpd

            FROM mapping_konflik
        ),

        mapping AS (
            SELECT
                m.norecpd,
                m.idruangan,
                m.idkonten

            FROM mapping_raw m

            INNER JOIN kandidat kd
                ON kd.norecpd = m.norecpd

            WHERE NOT EXISTS (
                SELECT 1
                FROM registrasi_konflik mk
                WHERE mk.norecpd = m.norecpd
            )
        ),

        /* ==================================================
           MENGAMBIL RIWAYAT RAWAT INAP
        ================================================== */

        riwayat_raw AS (
            SELECT
                apd.norec AS norec_apd,

                apd.noregistrasifk,

                apd.objectruanganfk,

                apd.tglmasuk,

                apd.tglkeluar,

                m.idkonten,

                pdt.noregistrasi,

                pdt.nocmfk,

                pdt.tglregistrasi,

                pdt.tglpulang,

                pdt.tglmeninggal,

                pdt.objectstatuskeluarfk,

                pdt.objectkelasfk,

                pm.objectjeniskelaminfk,

                COALESCE(
                    pdt.tglmeninggal,
                    pdt.tglpulang
                ) AS waktu_keluar_rs,

                ROW_NUMBER() OVER (
                    PARTITION BY
                        apd.noregistrasifk,
                        apd.objectruanganfk,
                        apd.tglmasuk

                    ORDER BY
                        apd.tglkeluar DESC NULLS LAST,
                        apd.norec
                ) AS nomor_duplikat

            FROM mapping m

            INNER JOIN antrianpasiendiperiksa_t apd
                ON apd.noregistrasifk = m.norecpd
                AND apd.objectruanganfk = m.idruangan

            INNER JOIN pasiendaftar_t pdt
                ON pdt.norec = apd.noregistrasifk

            LEFT JOIN pasien_m pm
                ON pm.id = pdt.nocmfk

            WHERE apd.tglmasuk IS NOT NULL
        ),

        /* ==================================================
           MENGHILANGKAN DUPLIKAT RIWAYAT APD
        ================================================== */

        riwayat AS (
            SELECT *
            FROM riwayat_raw
            WHERE nomor_duplikat = 1
        ),

        /* ==================================================
           MENYUSUN KRONOLOGI PERAWATAN PASIEN
        ================================================== */

        kronologi AS (
            SELECT
                r.*,

                MIN(r.tglmasuk) OVER (
                    PARTITION BY r.noregistrasifk
                ) AS awal_rawat_inap,

                LAG(r.idkonten) OVER (
                    PARTITION BY r.noregistrasifk

                    ORDER BY
                        r.tglmasuk,
                        r.norec_apd
                ) AS kelompok_sebelumnya,

                LEAD(r.idkonten) OVER (
                    PARTITION BY r.noregistrasifk

                    ORDER BY
                        r.tglmasuk,
                        r.norec_apd
                ) AS kelompok_selanjutnya,

                LEAD(r.tglmasuk) OVER (
                    PARTITION BY r.noregistrasifk

                    ORDER BY
                        r.tglmasuk,
                        r.norec_apd
                ) AS waktu_masuk_selanjutnya

            FROM riwayat r
        ),

        /* ==================================================
           MENANDAI PERPINDAHAN KELOMPOK PELAYANAN
        ================================================== */

        penanda_episode AS (
            SELECT
                k.*,

                CASE
                    WHEN k.kelompok_sebelumnya IS NULL
                        THEN 1

                    WHEN k.kelompok_sebelumnya
                        IS DISTINCT FROM k.idkonten
                        THEN 1

                    ELSE 0

                END AS episode_baru

            FROM kronologi k
        ),

        nomor_episode AS (
            SELECT
                p.*,

                SUM(p.episode_baru) OVER (
                    PARTITION BY p.noregistrasifk

                    ORDER BY
                        p.tglmasuk,
                        p.norec_apd

                    ROWS BETWEEN
                        UNBOUNDED PRECEDING
                        AND CURRENT ROW
                ) AS episode_id

            FROM penanda_episode p
        ),

        /* ==================================================
           MENGGABUNGKAN RUANGAN DENGAN KELOMPOK SAMA
        ================================================== */

        episode_dasar AS (
            SELECT
                noregistrasifk,

                noregistrasi,

                nocmfk,

                idkonten,

                episode_id,

                MIN(tglmasuk)
                    AS mulai_episode,

                MAX(awal_rawat_inap)
                    AS awal_rawat_inap,

                MAX(tglregistrasi)
                    AS tglregistrasi,

                MAX(tglpulang)
                    AS tglpulang,

                MAX(tglmeninggal)
                    AS tglmeninggal,

                MAX(waktu_keluar_rs)
                    AS waktu_keluar_rs,

                MAX(objectstatuskeluarfk)
                    AS objectstatuskeluarfk,

                MAX(objectkelasfk)
                    AS objectkelasfk,

                MAX(objectjeniskelaminfk)
                    AS objectjeniskelaminfk

            FROM nomor_episode

            GROUP BY
                noregistrasifk,
                noregistrasi,
                nocmfk,
                idkonten,
                episode_id
        ),

        /* ==================================================
           MENENTUKAN ASAL DAN TUJUAN MUTASI
        ================================================== */

        episode_kronologi AS (
            SELECT
                e.*,

                LAG(e.idkonten) OVER (
                    PARTITION BY e.noregistrasifk
                    ORDER BY e.episode_id
                ) AS asal_kelompok,

                LEAD(e.idkonten) OVER (
                    PARTITION BY e.noregistrasifk
                    ORDER BY e.episode_id
                ) AS tujuan_kelompok,

                LEAD(e.mulai_episode) OVER (
                    PARTITION BY e.noregistrasifk
                    ORDER BY e.episode_id
                ) AS mulai_episode_selanjutnya

            FROM episode_dasar e
        ),

        /* ==================================================
           MENENTUKAN TANGGAL AKHIR EPISODE
        ================================================== */

        episode AS (
            SELECT
                e.*,

                LEAST(
                    e.mulai_episode_selanjutnya,
                    e.waktu_keluar_rs
                ) AS akhir_episode

            FROM episode_kronologi e
        ),

        /* ==================================================
           MEMBATASI EPISODE PADA PERIODE LAPORAN
        ================================================== */

        episode_periode AS (
            SELECT
                e.*,

                pr.awal_bulan,

                pr.akhir_bulan,

                GREATEST(
                    e.mulai_episode,
                    pr.awal_bulan
                ) AS mulai_hitung,

                LEAST(
                    COALESCE(
                        e.akhir_episode,
                        pr.akhir_bulan
                    ),
                    pr.akhir_bulan
                ) AS akhir_hitung

            FROM episode e

            CROSS JOIN periode pr

            WHERE e.mulai_episode < pr.akhir_bulan

                AND (
                    e.akhir_episode >= pr.awal_bulan
                    OR e.akhir_episode IS NULL
                )

                AND (
                    e.akhir_episode >= e.mulai_episode
                    OR e.akhir_episode IS NULL
                )
        ),

        /* ==================================================
           MENGHITUNG INDIKATOR PELAYANAN RAWAT INAP
        ================================================== */

        indikator AS (
            SELECT
                ep.*,

                /* ------------------------------------------
                   PASIEN AWAL BULAN
                ------------------------------------------ */

                CASE
                    WHEN ep.mulai_episode < ep.awal_bulan

                        AND (
                            ep.akhir_episode >= ep.awal_bulan
                            OR ep.akhir_episode IS NULL
                        )

                    THEN 1
                    ELSE 0

                END AS pasien_awal_bulan,

                /* ------------------------------------------
                   PASIEN MASUK BARU
                ------------------------------------------ */

                CASE
                    WHEN ep.asal_kelompok IS NULL

                        AND ep.mulai_episode >= ep.awal_bulan

                        AND ep.mulai_episode < ep.akhir_bulan

                    THEN 1
                    ELSE 0

                END AS pasien_masuk,

                /* ------------------------------------------
                   PASIEN PINDAHAN
                ------------------------------------------ */

                CASE
                    WHEN ep.asal_kelompok IS NOT NULL

                        AND ep.mulai_episode >= ep.awal_bulan

                        AND ep.mulai_episode < ep.akhir_bulan

                    THEN 1
                    ELSE 0

                END AS pasien_pindahan,

                /* ------------------------------------------
                   PASIEN DIPINDAHKAN
                ------------------------------------------ */

                CASE
                    WHEN ep.tujuan_kelompok IS NOT NULL

                        AND ep.akhir_episode =
                            ep.mulai_episode_selanjutnya

                        AND ep.akhir_episode >= ep.awal_bulan

                        AND ep.akhir_episode < ep.akhir_bulan

                        AND (
                            ep.waktu_keluar_rs IS NULL
                            OR ep.akhir_episode <
                                ep.waktu_keluar_rs
                        )

                    THEN 1
                    ELSE 0

                END AS pasien_dipindahkan,

                /* ------------------------------------------
                   PASIEN KELUAR HIDUP
                ------------------------------------------ */

                CASE
                    WHEN ep.tujuan_kelompok IS NULL

                        AND ep.tglmeninggal IS NULL

                        AND ep.tglpulang >= ep.awal_bulan

                        AND ep.tglpulang < ep.akhir_bulan

                    THEN 1
                    ELSE 0

                END AS pasien_keluar_hidup,

                /* ------------------------------------------
                   PASIEN KELUAR MENINGGAL
                ------------------------------------------ */

                CASE
                    WHEN ep.tujuan_kelompok IS NULL

                        AND ep.tglmeninggal >= ep.awal_bulan

                        AND ep.tglmeninggal < ep.akhir_bulan

                    THEN 1
                    ELSE 0

                END AS pasien_keluar_mati,

                /* ------------------------------------------
                   PASIEN AKHIR PERIODE BERDASARKAN EPISODE
                ------------------------------------------ */

                CASE
                    WHEN ep.mulai_episode < ep.akhir_bulan

                        AND (
                            ep.akhir_episode >= ep.akhir_bulan
                            OR ep.akhir_episode IS NULL
                        )

                    THEN 1
                    ELSE 0

                END AS pasien_akhir_episode,

                /* ------------------------------------------
                   JUMLAH LAMA DIRAWAT

                   Menghitung seluruh lama rawat pasien
                   yang keluar rumah sakit pada periode.
                ------------------------------------------ */

                CASE
                    WHEN ep.tujuan_kelompok IS NULL

                        AND ep.waktu_keluar_rs >= ep.awal_bulan

                        AND ep.waktu_keluar_rs < ep.akhir_bulan

                        AND ep.waktu_keluar_rs >= ep.awal_rawat_inap

                    THEN GREATEST(
                        ep.waktu_keluar_rs::DATE
                        - ep.awal_rawat_inap::DATE,
                        1
                    )

                    ELSE 0

                END AS lama_dirawat,

                /* ------------------------------------------
                   JUMLAH LAMA EPISODE PELAYANAN

                   Kolom alternatif untuk analisis lama
                   rawat setiap kelompok pelayanan.
                ------------------------------------------ */

                CASE
                    WHEN ep.akhir_episode >= ep.awal_bulan

                        AND ep.akhir_episode < ep.akhir_bulan

                    THEN GREATEST(
                        ep.akhir_episode::DATE
                        - ep.mulai_episode::DATE,
                        1
                    )

                    ELSE 0

                END AS lama_episode,

                /* ------------------------------------------
                   JUMLAH HARI PERAWATAN

                   Hanya menghitung hari pada periode
                   laporan. Hari berjalan yang belum
                   selesai tidak dihitung.
                ------------------------------------------ */

                CASE
                    WHEN ep.akhir_hitung < ep.mulai_hitung
                        THEN 0

                    WHEN ep.tujuan_kelompok IS NULL

                        AND ep.waktu_keluar_rs >= ep.awal_bulan

                        AND ep.waktu_keluar_rs < ep.akhir_bulan

                        AND ep.awal_rawat_inap::DATE =
                            ep.waktu_keluar_rs::DATE

                        AND ep.awal_rawat_inap >= ep.awal_bulan

                    THEN 1

                    ELSE GREATEST(
                        ep.akhir_hitung::DATE
                        - ep.mulai_hitung::DATE,
                        0
                    )

                END AS hari_perawatan

            FROM episode_periode ep
        ),

        /* ==================================================
           REKAPITULASI BERDASARKAN JENIS PELAYANAN
        ================================================== */

        rekap AS (
            SELECT
                idkonten,

                SUM(pasien_awal_bulan)
                    AS pasien_awal_bulan,

                SUM(pasien_masuk)
                    AS pasien_masuk,

                SUM(pasien_pindahan)
                    AS pasien_pindahan,

                SUM(pasien_dipindahkan)
                    AS pasien_dipindahkan,

                SUM(pasien_keluar_hidup)
                    AS pasien_keluar_hidup,

                SUM(pasien_keluar_mati)
                    AS pasien_keluar_mati,

                /* ------------------------------------------
                   MENINGGAL KURANG 48 JAM LAKI-LAKI
                ------------------------------------------ */

                COUNT(*) FILTER (
                    WHERE pasien_keluar_mati = 1

                        AND objectjeniskelaminfk = 1

                        AND tglmeninggal >= awal_rawat_inap

                        AND tglmeninggal - awal_rawat_inap
                            < INTERVAL '48 hours'

                ) AS pasien_meninggal_kurang_48_jam_laki,

                /* ------------------------------------------
                   MENINGGAL KURANG 48 JAM PEREMPUAN
                ------------------------------------------ */

                COUNT(*) FILTER (
                    WHERE pasien_keluar_mati = 1

                        AND objectjeniskelaminfk = 2

                        AND tglmeninggal >= awal_rawat_inap

                        AND tglmeninggal - awal_rawat_inap
                            < INTERVAL '48 hours'

                ) AS pasien_meninggal_kurang_48_jam_cewe,

                /* ------------------------------------------
                   MENINGGAL MINIMAL 48 JAM LAKI-LAKI
                ------------------------------------------ */

                COUNT(*) FILTER (
                    WHERE pasien_keluar_mati = 1

                        AND objectjeniskelaminfk = 1

                        AND tglmeninggal - awal_rawat_inap
                            >= INTERVAL '48 hours'

                ) AS pasien_meninggal_lebih_48_jam_laki,

                /* ------------------------------------------
                   MENINGGAL MINIMAL 48 JAM PEREMPUAN
                ------------------------------------------ */

                COUNT(*) FILTER (
                    WHERE pasien_keluar_mati = 1

                        AND objectjeniskelaminfk = 2

                        AND tglmeninggal - awal_rawat_inap
                            >= INTERVAL '48 hours'

                ) AS pasien_meninggal_lebih_48_jam_cewe,

                /* ------------------------------------------
                   JUMLAH LAMA DIRAWAT
                ------------------------------------------ */

                SUM(lama_dirawat)
                    AS jumlah_lama_dirawat,

                SUM(lama_episode)
                    AS jumlah_lama_episode,

                /* ------------------------------------------
                   JUMLAH HARI PERAWATAN
                ------------------------------------------ */

                SUM(hari_perawatan)
                    AS jumlah_hari_perawatan,

                /* ------------------------------------------
                   HARI PERAWATAN BERDASARKAN KELAS
                ------------------------------------------ */

                SUM(
                    CASE
                        WHEN objectkelasfk = 5
                            THEN hari_perawatan
                        ELSE 0
                    END
                ) AS kelas_vvip,

                SUM(
                    CASE
                        WHEN objectkelasfk = 4
                            THEN hari_perawatan
                        ELSE 0
                    END
                ) AS kelas_vip,

                SUM(
                    CASE
                        WHEN objectkelasfk = 3
                            THEN hari_perawatan
                        ELSE 0
                    END
                ) AS kelas_1,

                SUM(
                    CASE
                        WHEN objectkelasfk = 2
                            THEN hari_perawatan
                        ELSE 0
                    END
                ) AS kelas_2,

                SUM(
                    CASE
                        WHEN objectkelasfk = 1
                            THEN hari_perawatan
                        ELSE 0
                    END
                ) AS kelas_3,

                SUM(
                    CASE
                        WHEN objectkelasfk NOT IN (1,2,3,4,5)
                            THEN hari_perawatan
                        ELSE 0
                    END
                ) AS kelas_khusus,

                SUM(
                    CASE
                        WHEN objectkelasfk IS NULL
                            THEN hari_perawatan
                        ELSE 0
                    END
                ) AS kelas_tidak_diketahui,

                SUM(pasien_akhir_episode)
                    AS pasien_akhir_episode

            FROM indikator

            GROUP BY idkonten
        ),

        /* ==================================================
           SENSUS AKTUAL BERDASARKAN RIWAYAT APD
        ================================================== */

        sensus_dasar AS (
            SELECT
                k.*,

                LEAST(
                    k.tglkeluar,
                    k.waktu_masuk_selanjutnya,
                    k.waktu_keluar_rs
                ) AS batas_keluar_apd

            FROM kronologi k
        ),

        sensus_aktual AS (
            SELECT
                s.idkonten,

                COUNT(
                    DISTINCT s.noregistrasifk
                ) FILTER (

                    WHERE s.tglmasuk < pr.awal_bulan

                        AND (
                            s.batas_keluar_apd >= pr.awal_bulan

                            OR s.batas_keluar_apd IS NULL
                        )

                ) AS pasien_awal_bulan_aktual,

                COUNT(
                    DISTINCT s.noregistrasifk
                ) FILTER (

                    WHERE s.tglmasuk < pr.akhir_bulan

                        AND (
                            s.batas_keluar_apd >= pr.akhir_bulan

                            OR s.batas_keluar_apd IS NULL
                        )

                ) AS pasien_akhir_bulan_aktual

            FROM sensus_dasar s

            CROSS JOIN periode pr

            GROUP BY s.idkonten
        ),

        /* ==================================================
           DAFTAR RUANGAN PER KELOMPOK PELAYANAN

           Digunakan oleh getBedCountForRooms().
        ================================================== */

        daftar_ruangan AS (
            SELECT
                idkonten,

                ARRAY_AGG(
                    DISTINCT idruangan
                    ORDER BY idruangan
                ) AS idruangan_list

            FROM mapping_raw

            GROUP BY idkonten
        ),

        /* ==================================================
           AUDIT KUALITAS DATA
        ================================================== */

        audit_mapping AS (
            SELECT
                COUNT(*) AS jumlah_mapping_konflik

            FROM registrasi_konflik
        ),

        audit_riwayat AS (
            SELECT
                COUNT(*) FILTER (
                    WHERE nomor_duplikat > 1
                ) AS jumlah_apd_duplikat

            FROM riwayat_raw
        ),

        audit_kronologi AS (
            SELECT
                COUNT(*) FILTER (
                    WHERE tglkeluar < tglmasuk

                        OR waktu_keluar_rs < tglmasuk

                        OR (
                            waktu_masuk_selanjutnya IS NOT NULL

                            AND waktu_keluar_rs IS NOT NULL

                            AND waktu_masuk_selanjutnya
                                >= waktu_keluar_rs
                        )

                ) AS jumlah_kronologi_tidak_valid,

                COUNT(*) FILTER (
                    WHERE tglkeluar IS NOT NULL

                        AND waktu_masuk_selanjutnya IS NOT NULL

                        AND tglkeluar < waktu_masuk_selanjutnya

                ) AS jumlah_jeda_riwayat,

                COUNT(*) FILTER (
                    WHERE tglkeluar IS NOT NULL

                        AND waktu_masuk_selanjutnya IS NOT NULL

                        AND tglkeluar > waktu_masuk_selanjutnya

                ) AS jumlah_riwayat_tumpang_tindih

            FROM kronologi
        ),

        audit_apd AS (
            SELECT
                COUNT(*) AS jumlah_kandidat_tanpa_apd

            FROM kandidat kd

            WHERE NOT EXISTS (
                SELECT 1
                FROM registrasi_konflik mk
                WHERE mk.norecpd = kd.norecpd
            )

            AND NOT EXISTS (
                SELECT 1
                FROM riwayat r
                WHERE r.noregistrasifk = kd.norecpd
            )
        )

        /* ==================================================
           HASIL AKHIR LAPORAN RL 3.2
        ================================================== */

        SELECT
            kon.id AS idkonten,

            kon.reportdisplay AS \"jenisPelayanan\",

            COALESCE(r.pasien_awal_bulan, 0)
                AS pasien_awal_bulan,

            COALESCE(r.pasien_masuk, 0)
                AS pasien_masuk,

            COALESCE(r.pasien_pindahan, 0)
                AS pasien_pindahan,

            COALESCE(r.pasien_dipindahkan, 0)
                AS pasien_dipindahkan,

            COALESCE(r.pasien_keluar_hidup, 0)
                AS pasien_keluar_hidup,

            COALESCE(r.pasien_keluar_mati, 0)
                AS pasien_keluar_mati,

            COALESCE(
                r.pasien_meninggal_kurang_48_jam_laki,
                0
            ) AS pasien_meninggal_kurang_48_jam_laki,

            COALESCE(
                r.pasien_meninggal_kurang_48_jam_cewe,
                0
            ) AS pasien_meninggal_kurang_48_jam_cewe,

            COALESCE(
                r.pasien_meninggal_lebih_48_jam_laki,
                0
            ) AS pasien_meninggal_lebih_48_jam_laki,

            COALESCE(
                r.pasien_meninggal_lebih_48_jam_cewe,
                0
            ) AS pasien_meninggal_lebih_48_jam_cewe,

            COALESCE(r.jumlah_lama_dirawat, 0)
                AS jumlah_lama_dirawat,

            COALESCE(r.jumlah_lama_episode, 0)
                AS jumlah_lama_episode,

            COALESCE(r.jumlah_hari_perawatan, 0)
                AS jumlah_hari_perawatan,

            COALESCE(r.kelas_vvip, 0)
                AS kelas_vvip,

            COALESCE(r.kelas_vip, 0)
                AS kelas_vip,

            COALESCE(r.kelas_1, 0)
                AS kelas_1,

            COALESCE(r.kelas_2, 0)
                AS kelas_2,

            COALESCE(r.kelas_3, 0)
                AS kelas_3,

            COALESCE(r.kelas_khusus, 0)
                AS kelas_khusus,

            COALESCE(r.kelas_tidak_diketahui, 0)
                AS kelas_tidak_diketahui,

            COALESCE(r.pasien_akhir_episode, 0)
                AS pasien_akhir_episode,

            COALESCE(sa.pasien_awal_bulan_aktual, 0)
                AS pasien_awal_bulan_aktual,

            COALESCE(sa.pasien_akhir_bulan_aktual, 0)
                AS pasien_akhir_bulan_aktual,

            dr.idruangan_list,

            am.jumlah_mapping_konflik,

            ar.jumlah_apd_duplikat,

            ak.jumlah_kronologi_tidak_valid,

            ak.jumlah_jeda_riwayat,

            ak.jumlah_riwayat_tumpang_tindih,

            aa.jumlah_kandidat_tanpa_apd

        FROM daftar_ruangan dr

        INNER JOIN kelompoklaporan_m kon
            ON kon.id = dr.idkonten

        LEFT JOIN rekap r
            ON r.idkonten = kon.id

        LEFT JOIN sensus_aktual sa
            ON sa.idkonten = kon.id

        CROSS JOIN audit_mapping am

        CROSS JOIN audit_riwayat ar

        CROSS JOIN audit_kronologi ak

        CROSS JOIN audit_apd aa

        ORDER BY kon.id
    ";

    /*
    |--------------------------------------------------------------------------
    | 4. Eksekusi query
    |--------------------------------------------------------------------------
    */

    $bindings = array_merge(
        [
            $awalBulan->format('Y-m-d H:i:s'),
            $batasLaporan->format('Y-m-d H:i:s')
        ],
        $idjenislaporan
    );

    $data = collect(
        DB::connection('pgsql')->select(
            $sql,
            $bindings
        )
    );

    /*
    |--------------------------------------------------------------------------
    | 5. Pengolahan hasil query
    |--------------------------------------------------------------------------
    */

    $bedCache = [];

    $numericFields = [
        'pasien_awal_bulan',
        'pasien_masuk',
        'pasien_pindahan',
        'pasien_dipindahkan',
        'pasien_keluar_hidup',
        'pasien_keluar_mati',
        'pasien_meninggal_kurang_48_jam_laki',
        'pasien_meninggal_kurang_48_jam_cewe',
        'pasien_meninggal_lebih_48_jam_laki',
        'pasien_meninggal_lebih_48_jam_cewe',
        'jumlah_lama_dirawat',
        'jumlah_lama_episode',
        'jumlah_hari_perawatan',
        'kelas_vvip',
        'kelas_vip',
        'kelas_1',
        'kelas_2',
        'kelas_3',
        'kelas_khusus',
        'kelas_tidak_diketahui',
        'pasien_awal_bulan_aktual',
        'pasien_akhir_bulan_aktual',
        'pasien_akhir_episode',
        'jumlah_mapping_konflik',
        'jumlah_apd_duplikat',
        'jumlah_kronologi_tidak_valid',
        'jumlah_jeda_riwayat',
        'jumlah_riwayat_tumpang_tindih',
        'jumlah_kandidat_tanpa_apd'
    ];

    foreach ($data as $item) {

        /*
        |----------------------------------------------
        | Konversi hasil agregasi menjadi integer
        |----------------------------------------------
        */

        foreach ($numericFields as $field) {
            $item->{$field} = (int) ($item->{$field} ?? 0);
        }

        /*
        |----------------------------------------------
        | Konversi array ruangan PostgreSQL
        |----------------------------------------------
        */

        $idRuanganList = $item->idruangan_list ?? [];

        if (is_string($idRuanganList)) {

            $idRuanganList = str_replace(
                ['{', '}'],
                '',
                $idRuanganList
            );

            $idRuanganList = explode(
                ',',
                $idRuanganList
            );
        }

        $idRuanganList = array_values(
            array_unique(
                array_filter(
                    array_map(
                        'intval',
                        $idRuanganList
                    ),
                    function ($id) {
                        return $id > 0;
                    }
                )
            )
        );

        sort($idRuanganList);

        $item->idruangan_array = $idRuanganList;

        /*
        |----------------------------------------------
        | Menghitung jumlah tempat tidur
        |
        | Tetap menggunakan fungsi yang sebelumnya
        | sudah tersedia pada controller.
        |----------------------------------------------
        */

        $cacheKey = implode(',', $idRuanganList);

        if (!array_key_exists($cacheKey, $bedCache)) {

            $bedCache[$cacheKey] = empty($idRuanganList)
                ? 0
                : $this->getBedCountForRooms($idRuanganList);
        }

        $item->jumlah_tempat_tidur =
            (int) $bedCache[$cacheKey];

        /*
        |----------------------------------------------
        | Hitung pasien meninggal berdasarkan kategori
        |----------------------------------------------
        */

        $pasienMeninggalKategori =

            $item->pasien_meninggal_kurang_48_jam_laki

            + $item->pasien_meninggal_kurang_48_jam_cewe

            + $item->pasien_meninggal_lebih_48_jam_laki

            + $item->pasien_meninggal_lebih_48_jam_cewe;

        /*
         * Total pasien meninggal diambil langsung dari
         * jumlah pasien keluar mati agar pasien dengan
         * jenis kelamin tidak valid tidak terlewat.
         */

        $item->pasien_meninggal_total =
            $item->pasien_keluar_mati;

        $item->pasien_meninggal_kategori =
            $pasienMeninggalKategori;

        $item->selisih_kategori_meninggal =
            $item->pasien_keluar_mati
            - $pasienMeninggalKategori;

        /*
        |----------------------------------------------
        | Menghitung pasien akhir bulan
        |----------------------------------------------
        */

        $item->pasien_akhir_bulan =

            $item->pasien_awal_bulan

            + $item->pasien_masuk

            + $item->pasien_pindahan

            - $item->pasien_keluar_hidup

            - $item->pasien_keluar_mati

            - $item->pasien_dipindahkan;

        /*
        |----------------------------------------------
        | Validasi sensus awal bulan
        |----------------------------------------------
        */

        $item->selisih_sensus_awal =

            $item->pasien_awal_bulan

            - $item->pasien_awal_bulan_aktual;

        /*
        |----------------------------------------------
        | Validasi sensus akhir bulan
        |----------------------------------------------
        */

        $item->selisih_sensus =

            $item->pasien_akhir_bulan

            - $item->pasien_akhir_bulan_aktual;

        $item->selisih_episode =

            $item->pasien_akhir_bulan

            - $item->pasien_akhir_episode;

        /*
        |----------------------------------------------
        | Validasi hari perawatan berdasarkan kelas
        |----------------------------------------------
        */

        $item->total_hari_perawatan_kelas =

            $item->kelas_vvip

            + $item->kelas_vip

            + $item->kelas_1

            + $item->kelas_2

            + $item->kelas_3

            + $item->kelas_khusus

            + $item->kelas_tidak_diketahui;

        $item->selisih_hari_perawatan =

            $item->jumlah_hari_perawatan

            - $item->total_hari_perawatan_kelas;

        /*
        |----------------------------------------------
        | Menentukan status validasi setiap pelayanan
        |----------------------------------------------
        */

        $item->status_validasi = (

            $item->selisih_sensus_awal === 0

            && $item->selisih_sensus === 0

            && $item->selisih_episode === 0

            && $item->selisih_hari_perawatan === 0

            && $item->selisih_kategori_meninggal === 0

            && $item->kelas_tidak_diketahui === 0

            && $item->pasien_akhir_bulan >= 0

        ) ? 'VALIDASI_INTERNAL_OK' : 'PERLU_VERIFIKASI';
    }

    /*
    |--------------------------------------------------------------------------
    | 6. Rekapitulasi keseluruhan laporan
    |--------------------------------------------------------------------------
    */

    $summary = [

        'pasien_awal_bulan' =>
            (int) $data->sum('pasien_awal_bulan'),

        'pasien_masuk' =>
            (int) $data->sum('pasien_masuk'),

        'pasien_pindahan' =>
            (int) $data->sum('pasien_pindahan'),

        'pasien_dipindahkan' =>
            (int) $data->sum('pasien_dipindahkan'),

        'pasien_keluar_hidup' =>
            (int) $data->sum('pasien_keluar_hidup'),

        'pasien_keluar_mati' =>
            (int) $data->sum('pasien_keluar_mati'),

        'pasien_akhir_bulan' =>
            (int) $data->sum('pasien_akhir_bulan'),

        'pasien_awal_bulan_aktual' =>
            (int) $data->sum('pasien_awal_bulan_aktual'),

        'pasien_akhir_bulan_aktual' =>
            (int) $data->sum('pasien_akhir_bulan_aktual'),

        'jumlah_lama_dirawat' =>
            (int) $data->sum('jumlah_lama_dirawat'),

        'jumlah_lama_episode' =>
            (int) $data->sum('jumlah_lama_episode'),

        'jumlah_hari_perawatan' =>
            (int) $data->sum('jumlah_hari_perawatan'),

        'kelas_vvip' =>
            (int) $data->sum('kelas_vvip'),

        'kelas_vip' =>
            (int) $data->sum('kelas_vip'),

        'kelas_1' =>
            (int) $data->sum('kelas_1'),

        'kelas_2' =>
            (int) $data->sum('kelas_2'),

        'kelas_3' =>
            (int) $data->sum('kelas_3'),

        'kelas_khusus' =>
            (int) $data->sum('kelas_khusus'),

        'kelas_tidak_diketahui' =>
            (int) $data->sum('kelas_tidak_diketahui'),

        'selisih_sensus_awal' =>
            (int) $data->sum('selisih_sensus_awal'),

        'selisih_sensus' =>
            (int) $data->sum('selisih_sensus'),

        'selisih_mutasi' =>

            (int) $data->sum('pasien_pindahan')

            - (int) $data->sum('pasien_dipindahkan'),

        'jumlah_kelompok' =>
            $data->count(),

        'jumlah_kelompok_tidak_valid' =>

            $data->filter(function ($item) {

                return $item->status_validasi
                    !== 'VALIDASI_INTERNAL_OK';

            })->count(),

        /*
         * Gunakan MAX karena hasil audit merupakan
         * data global yang sama pada setiap baris.
         */

        'jumlah_mapping_konflik' =>
            (int) $data->max('jumlah_mapping_konflik'),

        'jumlah_apd_duplikat' =>
            (int) $data->max('jumlah_apd_duplikat'),

        'jumlah_kronologi_tidak_valid' =>
            (int) $data->max('jumlah_kronologi_tidak_valid'),

        'jumlah_jeda_riwayat' =>
            (int) $data->max('jumlah_jeda_riwayat'),

        'jumlah_riwayat_tumpang_tindih' =>
            (int) $data->max('jumlah_riwayat_tumpang_tindih'),

        'jumlah_kandidat_tanpa_apd' =>
            (int) $data->max('jumlah_kandidat_tanpa_apd'),
    ];

    /*
    |--------------------------------------------------------------------------
    | 7. Validasi keseluruhan laporan
    |--------------------------------------------------------------------------
    */

    $summary['status_validasi'] = (

        $data->isNotEmpty()

        && $summary['selisih_mutasi'] === 0

        && $summary['selisih_sensus_awal'] === 0

        && $summary['selisih_sensus'] === 0

        && $summary['jumlah_kelompok_tidak_valid'] === 0

        && $summary['jumlah_mapping_konflik'] === 0

        && $summary['jumlah_kronologi_tidak_valid'] === 0

        && $summary['jumlah_apd_duplikat'] === 0

        && $summary['jumlah_jeda_riwayat'] === 0

        && $summary['jumlah_riwayat_tumpang_tindih'] === 0

        && $summary['jumlah_kandidat_tanpa_apd'] === 0

    ) ? 'VALIDASI_INTERNAL_OK' : 'PERLU_VERIFIKASI';

    /*
    |--------------------------------------------------------------------------
    | 8. Response API
    |--------------------------------------------------------------------------
    */

    return $this->respond([

        'data' => $data->values(),

        'summary' => $summary,

        'periode' => [

            'bulan' => $bulan,

            'tahun' => $tahun,

            'awal_bulan' =>
                $awalBulan->format('Y-m-d H:i:s'),

            'akhir_bulan' =>
                $akhirBulan->format('Y-m-d H:i:s'),

            'batas_laporan' =>
                $batasLaporan->format('Y-m-d H:i:s'),

            'bulan_berjalan' =>
                $batasLaporan->lt($akhirBulan)
        ],

        'message' => 'Success'
    ]);
}


    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: getBedCountForRooms; pertahankan struktur hasil sumber.
    private function getBedCountForRooms($roomIds)
    {
        return DB::connection('pgsql')->table('tempattidur_m as tm')
            ->join('kamar_m as km', 'km.id', '=', 'tm.objectkamarfk')
            ->join('ruangan_m as rm', 'rm.id', '=', 'km.objectruanganfk')
            ->whereIn('rm.id', $roomIds)
            ->where('tm.statusenabled', true)
            ->count();
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: getDataRL3_3; pertahankan struktur hasil sumber.
    private function getDataRL3_3(Request $request)
    {


        $tglAwal = $request['tglAwal'];
        $tglAkhir = $request['tglAkhir'];
        // $idjenislaporan = explode(',', 6);
        $idjenislaporan = explode(',', $this->settingFix('kdLaporanGawatDarurat'));


        $data = DB::connection('pgsql')->table('kelompoklaporanrl_t as klrl')
            ->join('pasiendaftar_t as pdt', 'pdt.norec', '=', 'klrl.norecpd')
            ->join('antrianpasiendiperiksa_t as apd', function ($join) {
                $join->on('apd.noregistrasifk', '=', 'klrl.norecpd')
                    ->on('apd.objectruanganfk', '=', 'klrl.idruangan');
            })
            ->join('kelompoklaporan_m as kon', 'kon.id', '=', 'klrl.idkonten')
            ->join('pasien_m as pm', 'pm.id', '=', 'pdt.nocmfk')
            ->select('pdt.objectstatuspulangfk', 'klrl.idkonten', 'pdt.noregistrasi', 'pm.objectjeniskelaminfk', 'apd.objectruanganfk as ruangan_sekarang', 'apd.norec as norec_apd', 'kon.reportdisplay', 'pdt.tglregistrasi',
              'pdt.objectkelasfk', 'pdt.tglpulang', 'pdt.tglmeninggal', 'klrl.id', 'klrl.nocmfk', 'klrl.idkonten', 'klrl.idruangan', 'klrl.idjenislaporan', 'klrl.norecpd', 'klrl.keterangan', 'pdt.objectkelompokpasienlastfk')
            ->whereIn('klrl.idjenislaporan', $idjenislaporan)
            ->where('klrl.statusenabled', true)
            //->where ('pdt.objectstatuspulangfk',16)
            ->whereBetween('apd.tglregistrasi', [$tglAwal, $tglAkhir])
            ->get();

        $response = [];
        $no = 1;
        foreach ($data as $item) {
            //cek norujukan jika pasien bpjs pasti ada norujukan kecuali pasien dari IGD
            //nomor ruangan IGD 322, 323
            if ($item->objectkelompokpasienlastfk == 2 && $item->ruangan_sekarang != 322 && $item->ruangan_sekarang != 323) {
                $item->norujukan = 'ada';
            } else if ($item->objectkelompokpasienlastfk == 2 && $item->ruangan_sekarang == 322 || $item->ruangan_sekarang == 323) {
                $item->norujukan = null;
            } else {
                $item->norujukan = null;
            }
            $jenispelayanan = $item->reportdisplay;
            $idkonten = $item->idkonten;
            $tglregistrasi = $item->tglregistrasi;
            $tglpulang = $item->tglpulang;
            $tglmeninggal = $item->tglmeninggal;
            $objectkelasfk = $item->objectkelasfk;
            $norujukan = $item->norujukan;
            $statuspulang = $item->objectstatuspulangfk;
            $jeniskelamin = $item->objectjeniskelaminfk;
            $keterangan = $item->keterangan;

            if (!array_key_exists($jenispelayanan, $response)) {
                $response[$jenispelayanan] = [
                    'no' => $no++,
                    'jenis_pelayanan' => $jenispelayanan,
                    'idkonten' => $idkonten,
                    'pasien_masuk' => 0,
                    'rujukan' => 0,
                    'non_rujukan' => 0,
                    'dirawat' => 0,
                    'dirujuk' => 0,
                    'pulang' => 0,
                    'meninggalLaki' => 0,
                    'meninggalCewe' => 0,
                    'doaLaki' => 0,
                    'doaCewe' => 0,
                    'lukaLaki' => 0,
                    'lukaCewe' => 0,
                    'false' => 0,
                    'details' => [],
                ];
            }

            if ($norujukan) {
                $response[$jenispelayanan]['rujukan']++;
            }

            if (!$norujukan) {
                $response[$jenispelayanan]['non_rujukan']++;
            }

            if ($statuspulang == 1) {
                $response[$jenispelayanan]['dirawat']++;
            }

            if ($statuspulang == 10) {
                $response[$jenispelayanan]['dirujuk']++;
            }

            if ($statuspulang == 3) {
                $response[$jenispelayanan]['pulang']++;
            }

            if ($statuspulang == 4 && $jeniskelamin == 1) {
                $response[$jenispelayanan]['meninggalLaki']++;
            }

            if ($statuspulang == 4 && $jeniskelamin == 2) {
                $response[$jenispelayanan]['meninggalCewe']++;
            }

            if (($keterangan == 'doa' || $statuspulang == 22) && $jeniskelamin == 1) {
                $response[$jenispelayanan]['doaLaki']++;
            }

            if (($keterangan == 'doa' || $statuspulang == 22) && $jeniskelamin == 2) {
                $response[$jenispelayanan]['doaCewe']++;
            }

            if ($keterangan == 'luka' && $jeniskelamin == 1) {
                $response[$jenispelayanan]['lukaLaki']++;
            }

            if ($keterangan == 'luka' && $jeniskelamin == 2) {
                $response[$jenispelayanan]['lukaCewe']++;
            }

            if ($keterangan == 'false') {
                $response[$jenispelayanan]['false']++;
            }


            $response[$jenispelayanan]['details'][] = [
                'tglregistrasi' => $item->tglregistrasi,
                'tglpulang' => $item->tglpulang,
                'ruangan_sekarang' => $item->ruangan_sekarang,
                'noregistrasi' => $item->noregistrasi,
                'id' => $item->id,
                'nocmfk' => $item->nocmfk,
                'idkonten' => $item->idkonten,
                'idruangan' => $item->idruangan,
                'norecpd' => $item->norecpd,
                'norec_apd' => $item->norec_apd,
                'statuspulangfk' => $item->objectstatuspulangfk
            ];
        }

        $data = array_values($response);

        return $this->respond($data);
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: getDataRL3_4; pertahankan struktur hasil sumber.
    private function getDataRL3_4(Request $request)
    {
        $kdProfile = (int) $this->kdProfile;
        $data = DB::connection('pgsql')->table('pasiendaftar_t as pd')
            ->select(DB::raw("count(pd.norec) as jumlah, 
                CASE 
                    WHEN pd.statuspasien = 'LAMA' THEN 'Pengunjung Lama'
                    WHEN pd.statuspasien = 'BARU' THEN 'Pengunjung Baru'
                    ELSE pd.statuspasien 
                END as statuspasien"))
            ->where('pd.kdprofile', $kdProfile)
            ->where('pd.statusenabled', true);

        if (isset($request['tglAwal']) && $request['tglAwal'] != "" && $request['tglAwal'] != "undefined") {
            $data = $data->where('pd.tglregistrasi', '>=', $request['tglAwal']);
        }
        if (isset($request['tglAkhir']) && $request['tglAkhir'] != "" && $request['tglAkhir'] != "undefined") {
            $data = $data->where('pd.tglregistrasi', '<=', $request['tglAkhir']);
        }

        try {
            $data = $data->whereNull('pd.tanggalpembatalan')
                ->groupBy('pd.statuspasien')
                ->get();

            return $this->respond([
                'data' => $data,
                'message' => 'success',
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'data' => null,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: getDataRL3_5; pertahankan struktur hasil sumber.
    private function getDataRL3_5(Request $request)
    {
        $dateRange = [$request->tglAwal, $request->tglAkhir];
        
        $idjenislaporan = explode(',', $this->settingFix('kdLaporanRekapKunjungan'));
        $KdKotaKabupaten = explode(',', $this->settingFix('KdKotaKabupaten'));
        
        // Get the base data first
        $data = DB::connection('pgsql')->table('pasiendaftar_t as pd')
            ->join('pasien_m as ps', 'ps.id', '=', 'pd.nocmfk')
            ->leftJoin('alamat_m as alm', 'alm.nocmfk', '=', 'ps.id')
            ->join('kelompoklaporanrl_t as kt', 'kt.norecpd', '=', 'pd.norec')
            ->join('kelompoklaporan_m as kl', 'kl.id', '=', 'kt.idkonten')
            ->join('jenislaporan_m as jl', 'jl.id', '=', 'kt.idjenislaporan')
            ->leftJoin('desakelurahan_m as dsk', 'dsk.id', '=', 'alm.objectdesakelurahanfk')
            ->leftJoin('kecamatan_m as kcm', 'kcm.id', '=', 'alm.objectkecamatanfk')
            ->leftJoin('kotakabupaten_m as kkb', 'kkb.id', '=', 'alm.objectkotakabupatenfk')
            ->leftJoin('propinsi_m as prp', 'prp.id', '=', 'alm.objectpropinsifk')
            ->select(
                'jl.jenislaporan',
                'kl.kelompoklaporan',
                'kt.idkonten',
                DB::raw('COUNT(kt.id) as jumlah'),
                DB::raw('SUM(CASE WHEN kkb.id IN (' . implode(',', $KdKotaKabupaten) . ') AND ps.objectjeniskelaminfk = 1 THEN 1 ELSE 0 END) as dalam_kota_laki'),
                DB::raw('SUM(CASE WHEN kkb.id IN (' . implode(',', $KdKotaKabupaten) . ') AND ps.objectjeniskelaminfk = 2 THEN 1 ELSE 0 END) as dalam_kota_perempuan'),
                DB::raw('SUM(CASE WHEN (kkb.id NOT IN (' . implode(',', $KdKotaKabupaten) . ') OR kkb.id IS NULL) AND ps.objectjeniskelaminfk = 1 THEN 1 ELSE 0 END) as luar_kota_laki'),
                DB::raw('SUM(CASE WHEN (kkb.id NOT IN (' . implode(',', $KdKotaKabupaten) . ') OR kkb.id IS NULL) AND ps.objectjeniskelaminfk = 2 THEN 1 ELSE 0 END) as luar_kota_perempuan')
            )
            ->where('pd.kdprofile', $this->kdProfile)
            ->where('pd.statusenabled', true)
            ->whereIn('kt.idjenislaporan', $idjenislaporan)
            ->whereBetween(DB::raw('CAST(kt.created_at as Date)'), $dateRange)
            ->groupBy('jl.jenislaporan', 'kl.kelompoklaporan', 'kt.idkonten')
            ->get();
    
        // Get the count of unique days per clinic
        $openDays = DB::connection('pgsql')->table('pasiendaftar_t as pd')
            ->join('kelompoklaporanrl_t as kt', 'kt.norecpd', '=', 'pd.norec')
            ->join('kelompoklaporan_m as kl', 'kl.id', '=', 'kt.idkonten')
            ->select(
                'kt.idkonten',
                DB::raw('COUNT(DISTINCT CAST(kt.created_at as Date)) as jumlah_hari_buka')
            )
            ->where('pd.kdprofile', $this->kdProfile)
            ->where('pd.statusenabled', true)
            ->whereIn('kt.idjenislaporan', $idjenislaporan)
            ->whereBetween(DB::raw('CAST(kt.created_at as Date)'), $dateRange)
            ->groupBy('kt.idkonten')
            ->get()
            ->keyBy('idkonten');
            
        // Get distinct days where each group has data, with gender and location breakdown
        $openDaysDetailed = DB::connection('pgsql')->table('pasiendaftar_t as pd')
            ->join('pasien_m as ps', 'ps.id', '=', 'pd.nocmfk')
            ->leftJoin('alamat_m as alm', 'alm.nocmfk', '=', 'ps.id')
            ->join('kelompoklaporanrl_t as kt', 'kt.norecpd', '=', 'pd.norec')
            ->leftJoin('kotakabupaten_m as kkb', 'kkb.id', '=', 'alm.objectkotakabupatenfk')
            ->select(
                'kt.idkonten',
                DB::raw('COUNT(DISTINCT CASE WHEN kkb.id IN (' . implode(',', $KdKotaKabupaten) . ') AND ps.objectjeniskelaminfk = 1 THEN CAST(kt.created_at as Date) ELSE NULL END) as hari_buka_dalam_kota_laki'),
                DB::raw('COUNT(DISTINCT CASE WHEN kkb.id IN (' . implode(',', $KdKotaKabupaten) . ') AND ps.objectjeniskelaminfk = 2 THEN CAST(kt.created_at as Date) ELSE NULL END) as hari_buka_dalam_kota_perempuan'),
                DB::raw('COUNT(DISTINCT CASE WHEN (kkb.id NOT IN (' . implode(',', $KdKotaKabupaten) . ') OR kkb.id IS NULL) AND ps.objectjeniskelaminfk = 1 THEN CAST(kt.created_at as Date) ELSE NULL END) as hari_buka_luar_kota_laki'),
                DB::raw('COUNT(DISTINCT CASE WHEN (kkb.id NOT IN (' . implode(',', $KdKotaKabupaten) . ') OR kkb.id IS NULL) AND ps.objectjeniskelaminfk = 2 THEN CAST(kt.created_at as Date) ELSE NULL END) as hari_buka_luar_kota_perempuan')
            )
            ->where('pd.kdprofile', $this->kdProfile)
            ->where('pd.statusenabled', true)
            ->whereIn('kt.idjenislaporan', $idjenislaporan)
            ->whereBetween(DB::raw('CAST(kt.created_at as Date)'), $dateRange)
            ->groupBy('kt.idkonten')
            ->get()
            ->keyBy('idkonten');
    
        // Count total clinics
        $totalKlinik = count($data->groupBy('idkonten'));
        
        // Initialize totals
        $totalDalamKotaLaki = 0;
        $totalDalamKotaPerempuan = 0;
        $totalLuarKotaLaki = 0;
        $totalLuarKotaPerempuan = 0;
        
        $totalHariBukaDalamKotaLaki = 0;
        $totalHariBukaDalamKotaPerempuan = 0;
        $totalHariBukaLuarKotaLaki = 0;
        $totalHariBukaLuarKotaPerempuan = 0;
        
        // Sum up the counts
        foreach ($data as $item) {
            $totalDalamKotaLaki += $item->dalam_kota_laki;
            $totalDalamKotaPerempuan += $item->dalam_kota_perempuan;
            $totalLuarKotaLaki += $item->luar_kota_laki;
            $totalLuarKotaPerempuan += $item->luar_kota_perempuan;
            
            if (isset($openDaysDetailed[$item->idkonten])) {
                $detail = $openDaysDetailed[$item->idkonten];
                $totalHariBukaDalamKotaLaki += $detail->hari_buka_dalam_kota_laki;
                $totalHariBukaDalamKotaPerempuan += $detail->hari_buka_dalam_kota_perempuan;
                $totalHariBukaLuarKotaLaki += $detail->hari_buka_luar_kota_laki;
                $totalHariBukaLuarKotaPerempuan += $detail->hari_buka_luar_kota_perempuan;
            }
        }
        
        // Calculate averages
        $rataRataHariBukaDalamKotaLaki = $totalKlinik > 0 ? $totalHariBukaDalamKotaLaki / $totalKlinik : 0;
        $rataRataHariBukaDalamKotaPerempuan = $totalKlinik > 0 ? $totalHariBukaDalamKotaPerempuan / $totalKlinik : 0;
        $rataRataHariBukaLuarKotaLaki = $totalKlinik > 0 ? $totalHariBukaLuarKotaLaki / $totalKlinik : 0;
        $rataRataHariBukaLuarKotaPerempuan = $totalKlinik > 0 ? $totalHariBukaLuarKotaPerempuan / $totalKlinik : 0;
        
        // Calculate average visits per day
        $rataRataKunjunganPerHariDalamKotaLaki = $totalHariBukaDalamKotaLaki > 0 ? $totalDalamKotaLaki / $totalHariBukaDalamKotaLaki : 0;
        $rataRataKunjunganPerHariDalamKotaPerempuan = $totalHariBukaDalamKotaPerempuan > 0 ? $totalDalamKotaPerempuan / $totalHariBukaDalamKotaPerempuan : 0;
        $rataRataKunjunganPerHariLuarKotaLaki = $totalHariBukaLuarKotaLaki > 0 ? $totalLuarKotaLaki / $totalHariBukaLuarKotaLaki : 0;
        $rataRataKunjunganPerHariLuarKotaPerempuan = $totalHariBukaLuarKotaPerempuan > 0 ? $totalLuarKotaPerempuan / $totalHariBukaLuarKotaPerempuan : 0;
    
        // Prepare the result
        $result = [
            'openDays' => $openDays,
            'totalKlinik' => $totalKlinik,
            'rataRataHariPoliklinikBuka_DalamKotaLaki' => round($rataRataHariBukaDalamKotaLaki, 0),
            'rataRataHariPoliklinikBuka_DalamKotaPerempuan' => round($rataRataHariBukaDalamKotaPerempuan, 0),
            'rataRataHariPoliklinikBuka_LuarKotaLaki' => round($rataRataHariBukaLuarKotaLaki, 0),
            'rataRataHariPoliklinikBuka_LuarKotaPerempuan' => round($rataRataHariBukaLuarKotaPerempuan, 0),
            'rataRataKunjunganPerHari_DalamKotaLaki' => round($rataRataKunjunganPerHariDalamKotaLaki, 0),
            'rataRataKunjunganPerHari_DalamKotaPerempuan' => round($rataRataKunjunganPerHariDalamKotaPerempuan, 0),
            'rataRataKunjunganPerHari_LuarKotaLaki' => round($rataRataKunjunganPerHariLuarKotaLaki, 0),
            'rataRataKunjunganPerHari_LuarKotaPerempuan' => round($rataRataKunjunganPerHariLuarKotaPerempuan, 0),
            'data' => $data,
            'message' => 'Data retrieved successfully'
        ];
    
        return $this->respond($result);
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: getDataRL3_6; pertahankan struktur hasil sumber.
    private function getDataRL3_6(Request $request)
    {

        $tglAwal = $request['tglAwal'];
        $tglAkhir = $request['tglAkhir'];
        // $idjenislaporan = explode(',', 8);
        $idjenislaporan = explode(',', $this->settingFix('kdLaporanKebidanan'));

        $data = DB::connection('pgsql')->table('kelompoklaporanrl_t as klrl')
            ->join('pasiendaftar_t as pdt', 'pdt.norec', '=', 'klrl.norecpd')
            ->join('asalrujukan_m as asj', 'asj.id', '=', 'pdt.asalrujukanfk')
            ->join('kelompoklaporan_m as kon', 'kon.id', '=', 'klrl.idkonten')
            ->select('kon.reportdisplay', 'pdt.tglregistrasi', 'pdt.objectkelasfk', 'pdt.tglpulang', 'pdt.tglmeninggal', 'klrl.id', 'klrl.nocmfk', 'klrl.idkonten', 'klrl.idruangan', 'klrl.idjenislaporan', 'klrl.norecpd', 'pdt.objectstatuspulangfk', 'asj.reportdisplay as namarujukan', 'pdt.asalrujukanfk as idrujukan', 'pdt.rujuk')
            ->whereIn('klrl.idjenislaporan', $idjenislaporan)
            ->where('klrl.statusenabled', true)
            ->whereBetween('klrl.created_at', [$tglAwal, $tglAkhir])
            ->get();


        $response = [];
        $no = 1;
        foreach ($data as $item) {
            $jenispelayanan = $item->reportdisplay;
            $tglregistrasi = $item->tglregistrasi;
            $tglpulang = $item->tglpulang;
            $idkonten = $item->idkonten;
            $tglmeninggal = $item->tglmeninggal;
            $objectkelasfk = $item->objectkelasfk;
            // $norujukan = $item->norujukan;
            $idrujukan = $item->idrujukan;
            $statuspulang = $item->objectstatuspulangfk;
            $dirujuk = $item->rujuk;

            if (!array_key_exists($jenispelayanan, $response)) {
                $response[$jenispelayanan] = [
                    'no' => $no++,
                    'jenis_pelayanan' => $jenispelayanan,
                    'idkonten' => $idkonten,
                    'rm_puskesmas' => 0,
                    'rm_rumasakit' => 0,
                    'rm_bidan' => 0,
                    'rm_lain' => 0,
                    'rm_jml_hidup' => 0,
                    'rm_jml_mati' => 0,
                    'rm_jml_total' => 0,
                    'rnm_jml_hidup' => 0,
                    'rnm_jml_mati' => 0,
                    'rnm_jml_total' => 0,
                    'nonrm_jml_hidup' => 0,
                    'nonrm_jml_mati' => 0,
                    'nonrm_jml_total' => 0,
                    'dirujuk' => 0,
                    'details' => [],

                ];
            }

            $ismati = $statuspulang == 4;


            if ($idrujukan == 1 || $idrujukan == 20) {
                $response[$jenispelayanan]['rm_puskesmas']++;
            }

            if ($idrujukan == 2) {
                $response[$jenispelayanan]['rm_rumasakit']++;
            }


            if ($idrujukan == 5) {
                $response[$jenispelayanan]['nonrm_jml_total']++;
                if ($ismati) {
                    $response[$jenispelayanan]['nonrm_jml_mati']++;;
                } else {
                    $response[$jenispelayanan]['nonrm_jml_hidup']++;;
                }
            } else if ($idrujukan == 26) {
                $response[$jenispelayanan]['rnm_jml_total']++;
                if ($ismati) {
                    $response[$jenispelayanan]['rnm_jml_mati']++;;
                } else {
                    $response[$jenispelayanan]['rnm_jml_hidup']++;;
                }
            } else {
                $response[$jenispelayanan]['rm_jml_total']++;
                if ($ismati) {
                    $response[$jenispelayanan]['rm_jml_mati']++;;
                } else {
                    $response[$jenispelayanan]['rm_jml_hidup']++;;
                }
            }


            if ($dirujuk) {
                $response[$jenispelayanan]['dirujuk']++;
            }


            $response[$jenispelayanan]['details'][] = [
                'tglregistrasi' => $item->tglregistrasi,
                'tglpulang' => $item->tglpulang,
                'id' => $item->id,
                'nocmfk' => $item->nocmfk,
                'idkonten' => $item->idkonten,
                'idruangan' => $item->idruangan,
                'norecpd' => $item->norecpd,
                'statuspulangfk' => $item->objectstatuspulangfk,
                'asalrujukanfk' => $item->namarujukan,
                'idrujukan' => $item->idrujukan
            ];
        }


        $data = array_values($response);

        return $this->respond($data);
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: getDataRL3_7; pertahankan struktur hasil sumber.
    private function getDataRL3_7(Request $request)
    {

        $tglAwal = $request['tglAwal'];
        $tglAkhir = $request['tglAkhir'];
        $idjenislaporan = explode(',', $this->settingFix('kdLaporanKegiatanNeonatal'));
        // $idjenislaporan = explode(',', 14);

        $data = DB::connection('pgsql')->table('kelompoklaporanrl_t as klrl')
            ->join('pasiendaftar_t as pdt', 'pdt.norec', '=', 'klrl.norecpd')
            ->join('asalrujukan_m as asj', 'asj.id', '=', 'pdt.asalrujukanfk')
            ->join('kelompoklaporan_m as kon', 'kon.id', '=', 'klrl.idkonten')
            ->select('kon.reportdisplay', 'pdt.tglregistrasi', 'pdt.objectkelasfk', 'pdt.tglpulang', 'pdt.tglmeninggal', 'klrl.id', 'klrl.nocmfk', 'klrl.idkonten', 'klrl.idruangan', 'klrl.idjenislaporan', 'klrl.norecpd', 'pdt.objectstatuspulangfk', 'asj.reportdisplay as namarujukan', 'pdt.asalrujukanfk as asalrujukan', 'pdt.rujuk')
            ->whereIn('klrl.idjenislaporan', $idjenislaporan)
            // ->where('asj.id', '!=', $iddatangsendiri)
            ->where('klrl.statusenabled', true)
            ->whereBetween('klrl.created_at', [$tglAwal, $tglAkhir])
            ->get();


        $response = [];
        $no = 1;
        foreach ($data as $item) {
            $jenispelayanan = $item->reportdisplay;
            $tglregistrasi = $item->tglregistrasi;
            $tglpulang = $item->tglpulang;
            $idkonten = $item->idkonten;
            $tglmeninggal = $item->tglmeninggal;
            $objectkelasfk = $item->objectkelasfk;
            $asalrujukan = $item->asalrujukan;
            $statuspulang = $item->objectstatuspulangfk;
            $dirujuk = $item->rujuk;

            if (!array_key_exists($jenispelayanan, $response)) {
                $response[$jenispelayanan] = [
                    'no' => $no++,
                    'jenis_spealisasi' => $jenispelayanan,
                    'idkonten' => $idkonten,
                    'rujukan_medis_rs' => 0,
                    'rujukan_medis_bidan' => 0,
                    'rujukan_medis_puskesmas' => 0,
                    'rujukan_medis_faskes_lain' => 0,
                    'rujukan_medis_hidup' => 0,
                    'rujukan_medis_mati' => 0,
                    'rujukan_medis_total' => 0,
                    'rujukan_non_medis_hidup' => 0,
                    'rujukan_non_medis_mati' => 0,
                    'rujukan_non_medis_total' => 0,
                    'non_rujukan_hidup' => 0,
                    'non_rujukan_mati' => 0,
                    'non_rujukan_total' => 0,
                    'dirujuk' => 0,
                    'details' => [],

                ];
            }

            $ismati = $statuspulang == 4;

            if ($asalrujukan != 5) {

                $response[$jenispelayanan]['rujukan_medis_total']++;

                if ($asalrujukan == 2) {
                    $response[$jenispelayanan]['rujukan_medis_rs']++;
                }

                if ($asalrujukan == 2222) {
                    $response[$jenispelayanan]['rujukan_medis_bidan']++;
                }

                if ($asalrujukan == 20) {
                    $response[$jenispelayanan]['rujukan_medis_puskesmas']++;
                }

                if ($asalrujukan == 26) {
                    $response[$jenispelayanan]['rujukan_medis_faskes_lain']++;
                }


                if ($ismati) {
                    $response[$jenispelayanan]['rujukan_medis_mati']++;;
                } else {
                    $response[$jenispelayanan]['rujukan_medis_hidup']++;;
                }
            } else if ($asalrujukan == 5) {

                if ($ismati) {
                    $response[$jenispelayanan]['non_rujukan_mati']++;;
                } else {
                    $response[$jenispelayanan]['non_rujukan_hidup']++;;
                }

                $response[$jenispelayanan]['non_rujukan_total']++;
            } else if ($asalrujukan == 5555) {
                //caramasuk non medis belum diketahui

                if ($ismati) {
                    $response[$jenispelayanan]['rujukan_non_medis_mati']++;;
                } else {
                    $response[$jenispelayanan]['rujukan_non_medis_hidup']++;;
                }

                $response[$jenispelayanan]['rujukan_non_medis_total']++;
            }

            if ($statuspulang == 17) {
                $response[$jenispelayanan]['dirujuk']++;;
            }

            $response[$jenispelayanan]['details'][] = [
                'tglregistrasi' => $item->tglregistrasi,
                'tglpulang' => $item->tglpulang,
                'id' => $item->id,
                'nocmfk' => $item->nocmfk,
                'idkonten' => $item->idkonten,
                'idruangan' => $item->idruangan,
                'norecpd' => $item->norecpd,
                'statuspulangfk' => $item->objectstatuspulangfk,
                'asalrujukanfk' => $item->asalrujukan,
            ];
        }


        $data = array_values($response);

        return $this->respond($data);
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: getDataRL3_8; pertahankan struktur hasil sumber.
    private function getDataRL3_8(Request $request)
    {
        // Get category product IDs
        $kdProdukPatologiKlinik = explode(',', $this->settingFix('kdProdukPatologiKlinik'));
        $kdProdukMikrobiologiKlinik = explode(',', $this->settingFix('kdProdukMikrobiologiKlinik'));
        $kdProdukParasitologiKlinik = explode(',', $this->settingFix('KdProdukParasitologiKlinik'));
        $kdProdukPatalogiAnatomi = explode(',', $this->settingFix('KdProdukPatalogiAnatomi'));

        $bulan = $request['bulan'];
        $tahun = $request['tahun'];

        // Define category names
        $categories = [
            'A' => ['name' => 'Patologi Klinik', 'ids' => $kdProdukPatologiKlinik],
            'B' => ['name' => 'Mikrobiologi Klinik', 'ids' => $kdProdukMikrobiologiKlinik],
            'C' => ['name' => 'Parasitologi Klinik', 'ids' => $kdProdukParasitologiKlinik],
            'D' => ['name' => 'Patalogi Anatomi', 'ids' => $kdProdukPatalogiAnatomi],
            'E' => ['name' => 'Lain-lain', 'ids' => []] // Others category
        ];

        $dateRange = [$request->tglAwal, $request->tglAkhir];

        $results = DB::connection('pgsql')->table('hasillaboratorium_t as ht')
            ->join('produkdetaillaboratorium_m as p', 'ht.produkdetaillabfk', '=', 'p.id')
            ->join('produk_m as pmproduk', 'ht.produkfk', '=', 'pmproduk.id')
            ->join('pelayananpasien_t as pp', 'pp.norec', '=', 'ht.norecpelayanan')
            ->join('antrianpasiendiperiksa_t as apd', 'ht.noregistrasifk', '=', 'apd.norec')
            ->join('pasiendaftar_t as pd', 'apd.noregistrasifk', '=', 'pd.norec')
            ->join('pasien_m as psm', 'pd.nocmfk', '=', 'psm.norec')
            ->select(
                'pmproduk.id as produk_id',
                'pmproduk.namaproduk',
                'p.detailpemeriksaan',
                DB::raw("CASE 
                        WHEN psm.objectjeniskelaminfk = 1 THEN 'L' 
                        WHEN psm.objectjeniskelaminfk = 2 THEN 'P' 
                        ELSE NULL 
                     END AS jeniskelamin"),
                DB::raw("CAST(REPLACE(ht.hasil, ',', '.') AS DECIMAL(10,2)) as hasil")
            )
            ->whereRaw("REPLACE(ht.hasil, ',', '.') ~ '^[0-9]+(\\.[0-9]+)?$'")
            // ->whereBetween(DB::raw("CAST(pp.tglpelayanan as Date)"), $dateRange)
            ->whereMonth('pp.tglpelayanan', $bulan)
            ->whereYear('pp.tglpelayanan', $tahun)
            ->where('p.statusenabled', true)
            ->get();

        // First pass - count items and categorize products
        $produkToCategory = [];
        $produkToPemeriksaanCount = [];
        $grouped = [];

        // Initial grouping and counting
        foreach ($results as $row) {
            $produkId = $row->produk_id;
            $produk = $row->namaproduk;
            $pemeriksaan = $row->detailpemeriksaan;

            // Count unique pemeriksaan per produk
            if (!isset($produkToPemeriksaanCount[$produkId][$pemeriksaan])) {
                $produkToPemeriksaanCount[$produkId][$pemeriksaan] = true;
            }

            // Determine which category this product belongs to
            if (!isset($produkToCategory[$produkId])) {
                $categoryFound = false;
                foreach ($categories as $categoryId => $category) {
                    if (in_array($produkId, $category['ids'])) {
                        $produkToCategory[$produkId] = $categoryId;
                        $categoryFound = true;
                        break;
                    }
                }

                // If not found in any category, assign to "Others"
                if (!$categoryFound) {
                    $produkToCategory[$produkId] = 'E';
                }
            }

            $categoryId = $produkToCategory[$produkId];
            $gender = $row->jeniskelamin;
            $hasil = (float) $row->hasil;

            if (!isset($grouped[$categoryId][$produkId][$pemeriksaan])) {
                $grouped[$categoryId][$produkId][$pemeriksaan] = [
                    'namaproduk' => $produk,
                    'detailpemeriksaan' => $pemeriksaan,
                    'jumlah_laki' => null,
                    'jumlah_perempuan' => null,
                    'total_laki' => null,
                    'total_perempuan' => null,
                ];
            }

            if ($gender === 'L') {
                $grouped[$categoryId][$produkId][$pemeriksaan]['jumlah_laki']++;
                $grouped[$categoryId][$produkId][$pemeriksaan]['total_laki'] += $hasil;
            } elseif ($gender === 'P') {
                $grouped[$categoryId][$produkId][$pemeriksaan]['jumlah_perempuan']++;
                $grouped[$categoryId][$produkId][$pemeriksaan]['total_perempuan'] += $hasil;
            }
        }

        $final = [];

        // Process each category
        foreach ($categories as $categoryId => $categoryInfo) {
            if (isset($grouped[$categoryId])) {
                // Add category header
                $final[] = [
                    'id' => $categoryId,
                    'namaproduk' => null,
                    'label_pemeriksaan' => $categoryInfo['name'],
                    'jumlah_laki' => null,
                    'jumlah_perempuan' => null,
                    'rata2_laki' => null,
                    'rata2_perempuan' => null,
                ];

                $produkIndex = 1;

                // Process products in this category
                foreach ($grouped[$categoryId] as $produkId => $pemeriksaanList) {
                    $produkName = reset($pemeriksaanList)['namaproduk'];
                    $pemeriksaanCount = count($pemeriksaanList);

                    // If product has only one examination, display it directly
                    if ($pemeriksaanCount === 1) {
                        $pemeriksaan = key($pemeriksaanList);
                        $stat = current($pemeriksaanList);

                        $final[] = [
                            'id' => "{$produkIndex}",
                            'namaproduk' => $produkName,
                            'label_pemeriksaan' => $pemeriksaan,
                            'jumlah_laki' => isset($stat['jumlah_laki']) ? $stat['jumlah_laki'] : 0,
                            'jumlah_perempuan' => isset($stat['jumlah_perempuan']) ? $stat['jumlah_perempuan'] : 0,
                            'rata2_laki' => $stat['jumlah_laki'] > 0 ? round($stat['total_laki'] / $stat['jumlah_laki'], 2) : 0,
                            'rata2_perempuan' => $stat['jumlah_perempuan'] > 0 ? round($stat['total_perempuan'] / $stat['jumlah_perempuan'], 2) : 0,
                        ];
                    }
                    // If product has multiple examinations, create a header and child items
                    else {
                        // Add product header
                        $final[] = [
                            'id' => "{$produkIndex}",
                            'namaproduk' => $produkName,
                            'label_pemeriksaan' => $produkName,
                            'jumlah_laki' => null,
                            'jumlah_perempuan' => null,
                            'rata2_laki' => null,
                            'rata2_perempuan' => null,
                        ];

                        $detailIndex = 1;
                        // Process examinations for this product
                        foreach ($pemeriksaanList as $pemeriksaan => $stat) {
                            $final[] = [
                                'id' => "{$produkIndex}.{$detailIndex}",
                                'namaproduk' => $produkName,
                                'label_pemeriksaan' => $pemeriksaan,
                                'jumlah_laki' => isset($stat['jumlah_laki']) ? $stat['jumlah_laki'] : 0,
                                'jumlah_perempuan' => isset($stat['jumlah_perempuan']) ? $stat['jumlah_perempuan'] : 0,
                                'rata2_laki' => $stat['jumlah_laki'] > 0 ? round($stat['total_laki'] / $stat['jumlah_laki'], 2) : 0,
                                'rata2_perempuan' => $stat['jumlah_perempuan'] > 0 ? round($stat['total_perempuan'] / $stat['jumlah_perempuan'], 2) : 0,
                            ];
                            $detailIndex++;
                        }
                    }
                    $produkIndex++;
                }
            }
        }

        $result = array(
            'data' => $final,
            'message' => 'er@epic',

        );

        return $this->respond($result);
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: getDataRL3_9; pertahankan struktur hasil sumber.
    private function getDataRL3_9(Request $request)
    {

        $KdJenisLapRadiologi = (int) $this->settingFix('kdLaporanKegiatanRadiologi');
        $dateRange = [$request->tglAwal, $request->tglAkhir];

        //////With Mapping****
        $data = DB::connection('pgsql')->table('antrianpasiendiperiksa_t as apd')
            ->join('ruangan_m as ru', 'ru.id', '=', 'apd.objectruanganfk')
            ->join('departemen_m as dpm', 'dpm.id', '=', 'ru.objectdepartemenfk')
            ->join('pelayananpasien_t as pp', 'pp.noregistrasifk', '=', 'apd.norec')
            ->join('produk_m as pr', 'pr.id', '=', 'pp.produkfk')
            ->join('mapproduktolaporanrl_m as mpptrl', 'mpptrl.produkfk', '=', 'pr.id')
            ->join('kelompoklaporan_m as kl', 'kl.id', '=', 'mpptrl.objectkontenlaporanfk')
            ->join('jenislaporan_m as jl', 'jl.id', '=', 'mpptrl.objectjenislaporanfk')
            ->select(
                'jl.jenislaporan',
                'kl.kelompoklaporan as namaproduk',
                'kl.id as idkonten',
                (DB::raw('COUNT(mpptrl.objectkontenlaporanfk) as jmltindakan'))
            )
            ->where('apd.kdprofile', $this->kdProfile)
            ->where('jl.id', $KdJenisLapRadiologi)
            ->groupBy('mpptrl.objectkontenlaporanfk', 'jl.jenislaporan', 'kl.kelompoklaporan', 'kl.id')
            ->whereBetween(DB::raw("CAST(pp.tglpelayanan as Date)"), $dateRange)
            ->get();

        $result = array(
            'data' => $data,
            'message' => 'er@epic',

        );

        return $this->respond($result);
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: getDataRL3_10; pertahankan struktur hasil sumber.
    private function getDataRL3_10(Request $request)
    {

        $tglAwal = $request['tglAwal'];
        $tglAkhir = $request['tglAkhir'];
        $idjenislaporan = explode(',', $this->settingFix('kdLaporanKegiatanRujukan'));
        $iddatangsendiri = 5;

        $data = DB::connection('pgsql')->table('kelompoklaporanrl_t as klrl')
            ->join('pasiendaftar_t as pdt', 'pdt.norec', '=', 'klrl.norecpd')
            ->join('asalrujukan_m as asj', 'asj.id', '=', 'pdt.asalrujukanfk')
            ->join('kelompoklaporan_m as kon', 'kon.id', '=', 'klrl.idkonten')
            ->select('kon.reportdisplay', 'pdt.tglregistrasi', 'pdt.objectkelasfk', 'pdt.tglpulang', 'pdt.tglmeninggal', 'klrl.id', 'klrl.nocmfk', 'klrl.idkonten', 'klrl.idruangan', 'klrl.idjenislaporan', 'klrl.norecpd', 'pdt.objectstatuspulangfk', 'asj.reportdisplay as namarujukan', 'pdt.asalrujukanfk as asalrujukan', 'pdt.rujuk')
            ->whereIn('klrl.idjenislaporan', $idjenislaporan)
            // ->where('asj.id', '!=', $iddatangsendiri)
            ->where('klrl.statusenabled', true)
            ->whereBetween('klrl.created_at', [$tglAwal, $tglAkhir])
            ->get();


        $response = [];
        $no = 1;
        foreach ($data as $item) {
            $jenispelayanan = $item->reportdisplay;
            $tglregistrasi = $item->tglregistrasi;
            $tglpulang = $item->tglpulang;
            $idkonten = $item->idkonten;
            $tglmeninggal = $item->tglmeninggal;
            $objectkelasfk = $item->objectkelasfk;
            $asalrujukan = $item->asalrujukan;
            $statuspulang = $item->objectstatuspulangfk;
            $dirujuk = $item->rujuk;

            if (!array_key_exists($jenispelayanan, $response)) {
                $response[$jenispelayanan] = [
                    'no' => $no++,
                    'jenis_spealisasi' => $jenispelayanan,
                    'idkonten' => $idkonten,
                    'rm_puskesmas' => 0,
                    'rm_rs_lain' => 0,
                    'rm_faskes_lain' => 0,
                    'rm_total_rujukan_masuk' => 0,
                    'rm_kembali_puskesmas' => 0,
                    'rm_kembali_rs_lain' => 0,
                    'rm_kembali_faskes_lain' => 0,
                    'rm_kembali_total_rujukan_masuk' => 0,
                    'kel_rujukan' => 0,
                    'kel_datang_sendiri' => 0,
                    'kel_dirujuk_total' => 0,
                    'kel_diterima_kembali' => 0,
                    'details' => [],

                ];
            }

            if ($asalrujukan != 5) {

                $response[$jenispelayanan]['rm_total_rujukan_masuk']++;

                if ($asalrujukan == 20) {
                    $response[$jenispelayanan]['rm_puskesmas']++;
                }

                if ($asalrujukan == 2) {
                    $response[$jenispelayanan]['rm_rs_lain']++;
                }

                if ($asalrujukan == 26) {
                    $response[$jenispelayanan]['rm_faskes_lain']++;
                }

                if ($statuspulang) {
                    if ($statuspulang == 2) {
                        $response[$jenispelayanan]['rm_kembali_puskesmas']++;
                    } else if ($statuspulang == 8) {
                        $response[$jenispelayanan]['rm_kembali_rs_lain']++;
                    } else if ($statuspulang == 5) {
                        $response[$jenispelayanan]['rm_kembali_faskes_lain']++;
                    } else if ($statuspulang == 17) {
                        $response[$jenispelayanan]['kel_rujukan']++;
                    }
                    $response[$jenispelayanan]['rm_kembali_total_rujukan_masuk']++;
                }
            } else {
                if ($statuspulang == 17) {
                    $response[$jenispelayanan]['kel_datang_sendiri']++;
                }
            }
            // test commit
            if ($statuspulang == 17) {
                $response[$jenispelayanan]['kel_dirujuk_total']++;
            }

            if ($asalrujukan == 6) {
                $response[$jenispelayanan]['kel_diterima_kembali']++;
            }

            $response[$jenispelayanan]['details'][] = [
                'tglregistrasi' => $item->tglregistrasi,
                'tglpulang' => $item->tglpulang,
                'id' => $item->id,
                'nocmfk' => $item->nocmfk,
                'idkonten' => $item->idkonten,
                'idruangan' => $item->idruangan,
                'norecpd' => $item->norecpd,
                'statuspulangfk' => $item->objectstatuspulangfk,
                'asalrujukanfk' => $item->asalrujukan,
            ];
        }


        $data = array_values($response);

        return $this->respond($data);
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: getDataRL3_11; pertahankan struktur hasil sumber.
    private function getDataRL3_11(Request $request)
    {
        $dateRange = [$request->tglAwal, $request->tglAkhir];

        $KdJenisLapGigiMulut = (int) $this->settingFix('KdJenisLapGigiMulut');

        $data = DB::connection('pgsql')->table('pasiendaftar_t as pd')
            ->join('kelompoklaporanrl_t as kt', 'kt.norecpd', '=', 'pd.norec')
            ->join('kelompoklaporan_m as kl', 'kl.id', '=', 'kt.idkonten')
            ->join('jenislaporan_m as jl', 'jl.id', '=', 'kt.idjenislaporan')
            ->select(
                'jl.jenislaporan',
                'kl.kelompoklaporan',
                'kt.idkonten',
                DB::raw('COUNT(kt.id) as jumlah')
            )
            ->where('pd.kdprofile', $this->kdProfile)
            ->where('pd.statusenabled', true)
            ->where('kt.idjenislaporan', $KdJenisLapGigiMulut)
            ->whereBetween(DB::raw('CAST(kt.created_at as Date)'), $dateRange)
            ->groupBy('jl.jenislaporan', 'kl.kelompoklaporan', 'kt.idkonten')
            ->get();


        $result = array(
            'data' => $data,
            'message' => 'kabayan',
        );

        return $this->respond($result);
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: getDataRL3_11_detail; pertahankan struktur hasil sumber.
    private function getDataRL3_11_detail(Request $request)
    {
        $dateRange = [$request->tglAwal, $request->tglAkhir];

        $KdJenisLapGigiMulut = (int) $this->settingFix('KdJenisLapGigiMulut');

        $data = DB::connection('pgsql')->table('antrianpasiendiperiksa_t as apd')
            ->leftjoin('pasiendaftar_t as pd', 'pd.norec', '=', 'apd.noregistrasifk')
            ->join('pasien_m as pm', 'pm.id', '=', 'pd.nocmfk')
            ->leftjoin('rekanan_m as rk', 'rk.id', '=', 'pd.objectrekananfk')
            ->join('kelompoklaporanrl_t as kt', 'kt.norecpd', '=', 'pd.norec')
            ->join('kelompoklaporan_m as kl', 'kl.id', '=', 'kt.idkonten')
            ->join('jenislaporan_m as jl', 'jl.id', '=', 'kt.idjenislaporan')
            ->join('ruangan_m as ru', 'ru.id', '=', 'kt.idruangan')
            ->select(
                'jl.jenislaporan',
                'kl.kelompoklaporan',
                'pd.nocmfk',
                'pm.namapasien',
                'pm.tgllahir',
                'pm.nocm',
                'pd.noregistrasi',
                'kt.idruangan',
                'ru.namaruangan',
                DB::raw('MIN(apd.tglregistrasi) as tglregistrasi'),
                DB::raw('MIN(apd.statuskunjungan) as statuskunjungan'),
                DB::raw('CASE WHEN pd.objectrekananfk = 2551 THEN \'BPJS KESEHATAN\' ELSE rk.namarekanan END as namarekanan')
            )
            ->where('pd.kdprofile', $this->kdProfile)
            ->where('pd.statusenabled', true)
            ->where('kt.idjenislaporan', $KdJenisLapGigiMulut)
            ->where('kt.idkonten', $request->idkonten)
            ->whereBetween(DB::raw('CAST(kt.created_at as Date)'), $dateRange)
            ->groupBy(
                'ru.namaruangan',
                'pm.id',
                'jl.jenislaporan',
                'kl.kelompoklaporan',
                'pd.nocmfk',
                'pm.namapasien',
                'pm.tgllahir',
                'pm.nocm',
                'pd.noregistrasi',
                'kt.idruangan',
                'pd.objectrekananfk',
                'rk.namarekanan',
            )
            ->get();


        $result = array(
            'data' => $data,
            'message' => 'waw ada',
        );

        return $this->respond($result);
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: getDataRL3_12; pertahankan struktur hasil sumber.
    private function getDataRL3_12(Request $request)
    {

        $tglAwal = $request['tglAwal'];
        $tglAkhir = $request['tglAkhir'];
        // $idjenislaporan = explode(',', 10);
        $idjenislaporan = explode(',', $this->settingFix('kdLaporanPembedahan'));

        $data = DB::connection('pgsql')->table('kelompoklaporanrl_t as klrl')
            ->join('pasiendaftar_t as pdt', 'pdt.norec', '=', 'klrl.norecpd')
            ->join('kelompoklaporan_m as kon', 'kon.id', '=', 'klrl.idkonten')
            ->select('kon.reportdisplay', 'pdt.tglregistrasi', 'klrl.idkonten', 'pdt.objectkelasfk', 'pdt.tglpulang', 'pdt.tglmeninggal', 'klrl.id', 'klrl.nocmfk', 'klrl.idkonten', 'klrl.idruangan', 'klrl.idjenislaporan', 'klrl.norecpd', 'pdt.objectstatuspulangfk', 'klrl.keterangan')
            ->whereIn('klrl.idjenislaporan', $idjenislaporan)
            ->where('klrl.statusenabled', true)
            //->where ('pdt.objectstatuspulangfk',16)
            ->whereBetween('klrl.created_at', [$tglAwal, $tglAkhir])
            ->get();

        $response = [];
        $no = 1;
        foreach ($data as $item) {
            $jenispelayanan = $item->reportdisplay;
            $tglregistrasi = $item->tglregistrasi;
            $tglpulang = $item->tglpulang;
            $tglmeninggal = $item->tglmeninggal;
            $objectkelasfk = $item->objectkelasfk;
            $statuspulang = $item->objectstatuspulangfk;
            $keterangan = $item->keterangan;
            $idkonten = $item->idkonten;

            if (!array_key_exists($jenispelayanan, $response)) {
                $response[$jenispelayanan] = [
                    'no' => $no++,
                    'jenis_pelayanan' => $jenispelayanan,
                    'idkonten' => $idkonten,
                    'jumlah' => 0,
                    'khusus' => 0,
                    'besar' => 0,
                    'sedang' => 0,
                    'kecil' => 0,

                ];
            }


            $response[$jenispelayanan]['jumlah']++;

            if ($keterangan == 'khusus') {
                $response[$jenispelayanan]['khusus']++;
            }

            if ($keterangan == 'besar') {
                $response[$jenispelayanan]['besar']++;
            }


            if ($keterangan == 'sedang') {
                $response[$jenispelayanan]['sedang']++;
            }

            if ($keterangan == 'kecil') {
                $response[$jenispelayanan]['kecil']++;
            }
        }


        $data = array_values($response);

        return $this->respond($data);
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: getDataRL3_13; pertahankan struktur hasil sumber.
    private function getDataRL3_13(Request $request)
    {

        $tglAwal = $request['tglAwal'];
        $tglAkhir = $request['tglAkhir'];
        $KdJenisLapRehab = $this->settingFix('kdLaporanRehabMedik');

        $data = DB::connection('pgsql')->table('kelompoklaporanrl_t as klrl')
            ->join('pasiendaftar_t as pdt', 'pdt.norec', '=', 'klrl.norecpd')
            ->join('antrianpasiendiperiksa_t as apd', function ($join) {
                $join->on('apd.noregistrasifk', '=', 'klrl.norecpd')
                    ->on('apd.objectruanganfk', '=', 'klrl.idruangan');
            })
            ->join('kelompoklaporan_m as kon', 'kon.id', '=', 'klrl.idkonten')
            ->select('kon.reportdisplay', 'pdt.tglregistrasi', 'pdt.objectkelasfk', 'pdt.tglpulang', 'pdt.tglmeninggal', 'klrl.id', 'klrl.nocmfk', 'klrl.idkonten', 'klrl.idruangan', 'klrl.idjenislaporan', 'klrl.norecpd', 'pdt.objectstatuspulangfk')
            ->where('klrl.idjenislaporan', $KdJenisLapRehab)
            ->where('klrl.statusenabled', true)
            // ->whereBetween('klrl.created_at', [$tglAwal, $tglAkhir])
            ->whereBetween('apd.tglregistrasi', [$tglAwal, $tglAkhir])
            ->get();


        $response = [];
        $no = 1;
        foreach ($data as $item) {
            $jenispelayanan = $item->reportdisplay;
            $tglregistrasi = $item->tglregistrasi;
            $tglpulang = $item->tglpulang;
            $tglmeninggal = $item->tglmeninggal;
            $objectkelasfk = $item->objectkelasfk;
            $statuspulang = $item->objectstatuspulangfk;
            $idkonten = $item->idkonten;

            if (!array_key_exists($jenispelayanan, $response)) {
                $response[$jenispelayanan] = [
                    'no' => $no++,
                    'namaproduk' => $jenispelayanan,
                    'jmltindakan' => 0,
                    'idkonten' => $idkonten,

                ];
            }

            $response[$jenispelayanan]['jmltindakan']++;
        }


        $data = array_values($response);

        return $this->respond($data);
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: getDataRL3_14; pertahankan struktur hasil sumber.
    private function getDataRL3_14(Request $request)
    {

        $tglAwal = $request['tglAwal'];
        $tglAkhir = $request['tglAkhir'];
        $KdJenisPelayananKhusus = $this->settingFix('kdLaporanPelayananKhusus');

        $data = DB::connection('pgsql')->table('kelompoklaporanrl_t as klrl')
            ->join('pasiendaftar_t as pdt', 'pdt.norec', '=', 'klrl.norecpd')
            ->join('kelompoklaporan_m as kon', 'kon.id', '=', 'klrl.idkonten')
            ->select('kon.reportdisplay', 'pdt.tglregistrasi', 'pdt.objectkelasfk', 'pdt.tglpulang', 'pdt.tglmeninggal', 'klrl.id', 'klrl.nocmfk', 'klrl.idkonten', 'klrl.idruangan', 'klrl.idjenislaporan', 'klrl.norecpd', 'pdt.objectstatuspulangfk')
            ->where('klrl.idjenislaporan', $KdJenisPelayananKhusus)
            ->where('klrl.statusenabled', true)
            ->whereBetween('klrl.created_at', [$tglAwal, $tglAkhir])
            ->get();


        $response = [];
        $no = 1;
        foreach ($data as $item) {
            $jenispelayanan = $item->reportdisplay;
            $tglregistrasi = $item->tglregistrasi;
            $tglpulang = $item->tglpulang;
            $tglmeninggal = $item->tglmeninggal;
            $objectkelasfk = $item->objectkelasfk;
            $statuspulang = $item->objectstatuspulangfk;
            $idkonten = $item->idkonten;

            if (!array_key_exists($jenispelayanan, $response)) {
                $response[$jenispelayanan] = [
                    'no' => $no++,
                    'namaproduk' => $jenispelayanan,
                    'jmltindakan' => 0,
                    'idkonten' => $idkonten,

                ];
            }

            $response[$jenispelayanan]['jmltindakan']++;
        }


        $data = array_values($response);

        return $this->respond($data);
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: getDataRL3_15; pertahankan struktur hasil sumber.
    private function getDataRL3_15(Request $request)
    {

        $tglAwal = $request['tglAwal'];
        $tglAkhir = $request['tglAkhir'];
        $KdJenisKesehatanJiwa = $this->settingFix('kdLaporanKesehatanJiwa');

        $data = DB::connection('pgsql')->table('kelompoklaporanrl_t as klrl')
            ->join('pasiendaftar_t as pdt', 'pdt.norec', '=', 'klrl.norecpd')
            ->join('pasien_m AS pm', 'pm.id', '=', 'pdt.nocmfk')
            ->join('jeniskelamin_m AS jk', 'jk.id', '=', 'pm.objectjeniskelaminfk')
            ->join('kelompoklaporan_m as kon', 'kon.id', '=', 'klrl.idkonten')
            ->select('kon.reportdisplay', 'pdt.tglregistrasi', 'pm.objectjeniskelaminfk', 'pdt.objectkelasfk', 'pdt.tglpulang', 'pdt.tglmeninggal', 'klrl.id', 'klrl.nocmfk', 'klrl.idkonten', 'klrl.idruangan', 'klrl.idjenislaporan', 'klrl.norecpd', 'pdt.objectstatuspulangfk')
            ->where('klrl.idjenislaporan', $KdJenisKesehatanJiwa)
            ->where('klrl.statusenabled', true)
            ->whereBetween('klrl.created_at', [$tglAwal, $tglAkhir])
            ->get();


        $response = [];
        $no = 1;
        foreach ($data as $item) {
            $jenispelayanan = $item->reportdisplay;
            $tglregistrasi = $item->tglregistrasi;
            $tglpulang = $item->tglpulang;
            $tglmeninggal = $item->tglmeninggal;
            $objectkelasfk = $item->objectkelasfk;
            $statuspulang = $item->objectstatuspulangfk;
            $jeniskelamin = $item->objectjeniskelaminfk;
            $idkonten = $item->idkonten;

            if (!array_key_exists($jenispelayanan, $response)) {
                $response[$jenispelayanan] = [
                    'no' => $no++,
                    'namaproduk' => $jenispelayanan,
                    'jmllaki' => 0,
                    'jmlcewe' => 0,
                    'jmltindakan' => 0,
                    'idkonten' => $idkonten,

                ];
            }

            $response[$jenispelayanan]['jmltindakan']++;

            if ($jeniskelamin == 1) {
                $response[$jenispelayanan]['jmllaki']++;
            }

            if ($jeniskelamin == 2) {
                $response[$jenispelayanan]['jmlcewe']++;
            }
        }


        $data = array_values($response);

        return $this->respond($data);
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: getDataRL3_16; pertahankan struktur hasil sumber.
    private function getDataRL3_16(Request $request)
    {

        $tglAwal = $request['tglAwal'];
        $tglAkhir = $request['tglAkhir'];
        $kdJenisLaporanKB = $this->settingFix('kdLaporanKB');
        $dateRange = [$request->tglAwal, $request->tglAkhir];


        $data = DB::connection('pgsql')->table('kelompoklaporanrl_t as klrl')
            ->join('pasiendaftar_t as pdt', 'pdt.norec', '=', 'klrl.norecpd')
            ->join('pasien_m AS pm', 'pm.id', '=', 'pdt.nocmfk')
            ->join('jeniskelamin_m AS jk', 'jk.id', '=', 'pm.objectjeniskelaminfk')
            ->join('kelompoklaporan_m as kon', 'kon.id', '=', 'klrl.idkonten')
            ->select('kon.reportdisplay', 'pdt.tglregistrasi', 'pm.objectjeniskelaminfk', 'pdt.objectkelasfk', 'pdt.tglpulang', 'pdt.tglmeninggal', 'klrl.id', 'klrl.nocmfk', 'klrl.idkonten', 'klrl.idruangan', 'klrl.idjenislaporan', 'klrl.norecpd', 'pdt.objectstatuspulangfk', 'klrl.keterangan', 'klrl.keterangan_kb')
            ->where('klrl.idjenislaporan', $kdJenisLaporanKB)
            ->where('klrl.statusenabled', true)
            ->whereBetween('klrl.created_at', [$tglAwal, $tglAkhir])
            ->get();


        $response = [];
        $no = 1;
        foreach ($data as $item) {
            $jenispelayanan = $item->reportdisplay;
            $tglregistrasi = $item->tglregistrasi;
            $tglpulang = $item->tglpulang;
            $tglmeninggal = $item->tglmeninggal;
            $objectkelasfk = $item->objectkelasfk;
            $statuspulang = $item->objectstatuspulangfk;
            $keterangan = $item->keterangan;
            $keterangan_kb = $item->keterangan_kb;
            $jeniskelamin = $item->objectjeniskelaminfk;
            $idkonten = $item->idkonten;

            if (!array_key_exists($jenispelayanan, $response)) {
                $response[$jenispelayanan] = [
                    'no' => $no++,
                    'jenispelayanan' => $jenispelayanan,
                    'total' => 0,
                    'pasca_persalinan' => 0,
                    'pasca_keguguran' => 0,
                    'interval' => 0,
                    'komplikasi' => 0,
                    'kegagalan' => 0,
                    'efek_samping' => 0,
                    'drop_out' => 0,
                    'idkonten' => $idkonten,

                ];
            }

            $response[$jenispelayanan]['total']++;


            if ($keterangan_kb != 'TidakAda') {
                if ($keterangan_kb == 'PascaPersalinan') {
                    $response[$jenispelayanan]['pasca_persalinan']++;
                }

                if ($keterangan_kb == 'PascaKeguguran') {
                    $response[$jenispelayanan]['pasca_keguguran']++;
                }

                if ($keterangan_kb == 'Interval') {
                    $response[$jenispelayanan]['interval']++;
                }
            }

            if ($keterangan != 'TidakAdaKB') {
                if ($keterangan == 'KomplikasiKB') {
                    $response[$jenispelayanan]['komplikasi']++;
                }

                if ($keterangan == 'KegagalanKB') {
                    $response[$jenispelayanan]['kegagalan']++;
                }

                if ($keterangan == 'EfekSamping') {
                    $response[$jenispelayanan]['efek_samping']++;
                }

                if ($keterangan == 'DropOut') {
                    $response[$jenispelayanan]['drop_out']++;
                }
            }
        }


        $data = array_values($response);

        return $this->respond($data);
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: getDataRL3_17; pertahankan struktur hasil sumber.
    private function getDataRL3_17(Request $request)
    {

        $tglAwal = $request['tglAwal'];
        $tglAkhir = $request['tglAkhir'];
        $KdJenisFarmasi = $this->settingFix('kdLaporanFarmasi');

        $data = DB::connection('pgsql')->table('mapproduktolaporanrl_m as mpl')
            ->join('produk_m as pm', 'pm.id', '=', 'mpl.produkfk')
            ->join('stokprodukdetail_t as spd', 'spd.objectprodukfk', '=', 'pm.id')
            ->join('kelompoklaporan_m as kl', 'kl.id', '=', 'mpl.objectkontenlaporanfk')
            ->select(
                'kl.reportdisplay as jenis_pelayanan',
                'mpl.objectkontenlaporanfk',
                DB::raw('SUM(spd.qtyproduk) as jumlahtersedia'),
                DB::raw('COUNT(DISTINCT pm.id) as jumlah') // Count unique pm.id
            )
            ->where('mpl.objectjenislaporanfk', $KdJenisFarmasi)
            ->where('mpl.statusenabled', true)
            ->groupBy('kl.reportdisplay', 'mpl.objectkontenlaporanfk')
            ->get();



        $response = [];
        $no = 1;
        foreach ($data as $item) {
            $jenispelayanan = $item->jenis_pelayanan;
            $jumlahtersedia = $item->jumlahtersedia;
            $jumlah = $item->jumlah;

            if (!array_key_exists($jenispelayanan, $response)) {
                $response[$jenispelayanan] = [
                    'no' => $no++,
                    'jenis_pelayanan' => $jenispelayanan,
                    'jumlah' => $jumlah,
                    'jumlahtersedia' => $jumlahtersedia,
                    'jumlahfortersedia' => 0,


                ];
            }
        }


        $data = array_values($response);


        return $this->respond($data);
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: getDataRL3_18; pertahankan struktur hasil sumber.
    private function getDataRL3_18(Request $request)
    {

        $tglAwal = $request['tglAwal'];
        $tglAkhir = $request['tglAkhir'];
        $KdJenisFarmasi = $this->settingFix('kdLaporanFarmasi');
        $KdDeptRajalAll = explode(',', $this->settingFix('kdDepartemenRawatJalanFix'));
        $kdDeptRanapAll = explode(',', $this->settingFix('kdDepartemenRanapFix'));


        $data = DB::connection('pgsql')->table('mapproduktolaporanrl_m as mpl')
            ->join('produk_m as pm', 'pm.id', '=', 'mpl.produkfk')
            ->leftjoin('pelayananpasien_t as pst', 'pst.produkfk', '=', 'pm.id')
            ->join('strukresep_t as stp', 'stp.norec', '=', 'pst.strukresepfk')
            ->join('strukorder_t as sto', 'sto.norec', '=', 'stp.orderfk')
            ->join('ruangan_m as ru', 'ru.id', '=', 'sto.objectruanganfk')
            ->join('departemen_m as dp', 'dp.id', '=', 'ru.objectdepartemenfk')
            ->join('kelompoklaporan_m as kl', 'kl.id', '=', 'mpl.objectkontenlaporanfk')
            ->select(
                'kl.reportdisplay as jenis_pelayanan',
                'mpl.objectkontenlaporanfk',
                'sto.objectruanganfk',
                'dp.id as idDepartmen',
                'pm.namaexternal as namaproduk',
                'pst.strukresepfk',
                'pst.jumlah as jumlahobat'
            )
            ->where('mpl.objectjenislaporanfk', $KdJenisFarmasi)
            ->where('mpl.statusenabled', true)
            ->where('pst.keteranganlain', null)
            ->whereBetween('pst.tglpelayanan', [$tglAwal, $tglAkhir])
            ->get();



        $response = [];
        $no = 1;
        foreach ($data as $item) {
            $jenispelayanan = $item->jenis_pelayanan;
            $namaproduk = $item->namaproduk;
            $objectruanganfk = $item->objectruanganfk;
            $idDepartmen = $item->idDepartmen;
            $strukresepfk = $item->strukresepfk;
            $jumlahobat = $item->jumlahobat;

            if (!array_key_exists($jenispelayanan, $response)) {
                $response[$jenispelayanan] = [
                    'no' => $no++,
                    'jenis_pelayanan' => $jenispelayanan,
                    'rajal' => 0,
                    'igd' => 0,
                    'ranap' => 0,
                    'details' => [],


                ];
            }

            if ($idDepartmen == 18) {
                $response[$jenispelayanan]['rajal'] += $jumlahobat;
            }

            if ($idDepartmen == 9) {
                $response[$jenispelayanan]['igd'] += $jumlahobat;
            }

            if ($idDepartmen == 16) {
                $response[$jenispelayanan]['ranap'] += $jumlahobat;
            }


            $response[$jenispelayanan]['details'][] = [
                'namaproduk' => $item->namaproduk,
                'strukresepfk' => $item->strukresepfk,
                'objectruanganfk' => $item->objectruanganfk,
                'idDepartmen' => $item->idDepartmen,
                'jumlahobat' => $item->jumlahobat
            ];
        }



        $data = array_values($response);

        return $this->respond($data);
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: getDataRL3_19; pertahankan struktur hasil sumber.
    private function getDataRL3_19(Request $request)
    {
        $kdProfile = $this->kdProfile;
        $KdJenisLapCaraBayar = $this->settingFix('KdJenisLapCaraBayar');
        $dateRange = [$request->tglAwal, $request->tglAkhir];

        $departmentSettings = [
            'kdRanap' => $this->settingFix('idDepRawatInap'),
            'kdDepRad' => $this->settingFix('idDepartemenRadiologi'),
            'kdDepLab' => $this->settingFix('KdDepartemenInstalasiLaboratorium')
        ];

        $query = DB::connection('pgsql')->table('pasiendaftar_t AS pd')
            ->join('pasien_m AS pm', 'pm.id', '=', 'pd.nocmfk')
            ->join('jeniskelamin_m AS jk', 'jk.id', '=', 'pm.objectjeniskelaminfk')
            ->join('ruangan_m AS ru', 'ru.id', '=', 'pd.objectruanganlastfk')
            ->leftJoin('rekanan_m AS rk', 'rk.id', '=', 'pd.objectrekananfk')
            ->leftJoin('kelompokpasien_m AS km', 'km.id', '=', 'pd.objectkelompokpasienlastfk')
            ->join('mapcarapembayaranrltorekanan_m as mr', function ($join) {
                $join->on(function ($query) {
                    $query->where(function ($q) {
                        $q->where('pd.objectkelompokpasienlastfk', '!=', 2)
                            ->whereColumn('mr.objectrekananfk', '=', 'pd.objectrekananfk');
                    })
                        ->orWhere(function ($q) {
                            $q->where('pd.objectkelompokpasienlastfk', '=', 2)
                                ->where('mr.id', '=', 1);
                        });
                });
            })
            ->leftJoin('carapembayaranrl_m as cp', 'cp.id', '=', 'mr.objectcarabayarfk')
            ->select(
                'pd.statuspasien',
                'pd.noregistrasi',
                'pd.tglpulang',
                'rk.namarekanan',
                'km.kelompokpasien',
                'ru.objectdepartemenfk AS iddepartementdaftar',
                'ru.objectdepartemenfk as kddepartemen',
                'cp.carapembayaran as golbayar',
                'cp.kodeexternal as idbayar',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY pd.noregistrasi ORDER BY pd.tglregistrasi) AS rn')
            )

            ->where('pd.kdprofile', $kdProfile)
            ->where('pd.statusenabled', 1)
            ->whereBetween(DB::raw("CAST(pd.tglregistrasi AS DATE)"), $dateRange)
            ->orderBy('pd.tglregistrasi', 'DESC')
            ->get();

        $processedData = $this->countPatientsByDepartment($query, $departmentSettings);

        $result = [
            'data' => $processedData,
            'data_pasien' => $query,
            'message' => 'er@epic',
        ];

        return $this->respond($result);
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: countPatientsByDepartment; pertahankan struktur hasil sumber.
    private function countPatientsByDepartment($data, $departmentSettings)
    {
        $departmentCounts = [];

        foreach ($data as $item) {
            $golbayar = $item->golbayar;
            $idbayar = $item->idbayar;
            $departmentId = $item->kddepartemen;

            // Initialize the entry if not exists
            if (!isset($departmentCounts[$golbayar])) {
                $departmentCounts[$golbayar] = [
                    'golbayar' => $golbayar,
                    'idbayar' => $idbayar,
                    'total' => 0,
                    'pasienKeluarRI' => 0,
                    'pasienDirawatRI' => 0,
                    'pasienRawatJalan' => 0,
                    'pasienLab' => 0,
                    'pasienRad' => 0,
                    'pasienLain' => 0
                ];
            }

            // Increment total count
            $departmentCounts[$golbayar]['total']++;

            // Count patients by department type
            if ($departmentId == $departmentSettings['kdRanap']) {
                // Inpatient department
                $item->tglpulang == null
                    ? $departmentCounts[$golbayar]['pasienDirawatRI']++
                    : $departmentCounts[$golbayar]['pasienKeluarRI']++;
            } elseif ($departmentId == $departmentSettings['kdDepRad']) {
                // Radiology department
                $departmentCounts[$golbayar]['pasienRad']++;
                $departmentCounts[$golbayar]['pasienRawatJalan']++;
            } elseif ($departmentId == $departmentSettings['kdDepLab']) {
                // Laboratory department
                $departmentCounts[$golbayar]['pasienLab']++;
                $departmentCounts[$golbayar]['pasienRawatJalan']++;
            } elseif (!in_array($departmentId, [
                $departmentSettings['kdRanap'],
                $departmentSettings['kdDepRad'],
                $departmentSettings['kdDepLab']
            ])) {
                // Other departments
                $departmentCounts[$golbayar]['pasienLain']++;
                $departmentCounts[$golbayar]['pasienRawatJalan']++;
            }
        }

        // Convert to numeric array and sort by idbayar
        $processedCounts = array_values($departmentCounts);
        usort($processedCounts, function ($a, $b) {
            return $a['idbayar'] <=> $b['idbayar'];
        });

        return $processedCounts;
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: getDataRL4_1; pertahankan struktur hasil sumber.
    private function getDataRL4_1(Request $request)
    {
        $bulan = $request['bulan'];
        $tahun = $request['tahun'];
        $kdDeptRanapAll = explode(',', $this->settingFix('kdDepartemenRanapFix'));
        $data = DB::connection('pgsql')->table('pasiendaftar_t as pd')
            ->join('antrianpasiendiperiksa_t as app', 'app.noregistrasifk', '=', 'pd.norec')
            ->leftjoin('diagnosapasien_t as dp', 'dp.noregistrasifk', '=', 'app.norec')
            ->leftjoin('detaildiagnosapasien_t as ddp', 'ddp.objectdiagnosapasienfk', '=', 'dp.norec')
            ->leftjoin('diagnosa_m as dm', 'ddp.objectdiagnosafk', '=', 'dm.id')
            ->join('pasien_m as ps', 'ps.id', '=', 'pd.nocmfk')
            ->join('jeniskelamin_m as jk', 'ps.objectjeniskelaminfk', '=', 'jk.id')
            ->join('ruangan_m as ru', 'ru.id', '=', 'pd.objectruanganlastfk')
            ->join('departemen_m as dpm', 'dpm.id', '=', 'ru.objectdepartemenfk')
            ->select(
                'pd.tglregistrasi',
                'app.statuspenyakit',
                'dm.kddiagnosa',
                'dm.namadiagnosa',
                'ps.tgllahir as tglLahir',
                'ps.objectjeniskelaminfk',
                'pd.objectstatuskeluarfk',
                'pd.tglmeninggal',
                'dp.iskasusbaru',
                'dp.iskasuslama',
                DB::raw('EXTRACT(YEAR from AGE(pd.tglregistrasi, ps.tgllahir)) as umuryear,
                EXTRACT(MONTH from AGE(pd.tglregistrasi, ps.tgllahir)) as umurmonth,
                EXTRACT(DAY from AGE(pd.tglregistrasi, ps.tgllahir)) as umurday')
            )
            ->whereIn('dpm.id', $kdDeptRanapAll)
            ->where('dm.kdprofile', $this->kdProfile)
            ->where(function ($query) {
                $query->whereRaw("dm.kddiagnosa < 'R00' OR dm.kddiagnosa > 'R99'");
            })
            ->where(function ($query) {
                $query->whereRaw("dm.kddiagnosa < 'Z23' OR dm.kddiagnosa > 'Z27'");
            })
            ->whereMonth('pd.tglregistrasi', $bulan)
            ->whereYear('pd.tglregistrasi', $tahun);

        $data = $data->orderBy('dm.kddiagnosa')->get();

        $groupedData = [];

        foreach ($data as $item) {
            $key = $item->kddiagnosa; // Grouping by diagnosis code

            if (!isset($groupedData[$key])) {
                $groupedData[$key] = [
                    'kddiagnosa' => $item->kddiagnosa,
                    'namadiagnosa' => $item->namadiagnosa,
                    'no_urut' => count($groupedData) + 1, // Auto-increment number

                    // Initialize counters for all age groups
                    'jml1JamL' => 0,
                    'jml1JamP' => 0,
                    'jml1_23JamL' => 0,
                    'jml1_23JamP' => 0,
                    'jml1HariL' => 0,
                    'jml1HariP' => 0,
                    'jml8HariL' => 0,
                    'jml8HariP' => 0,
                    'jml29HariL' => 0,
                    'jml29HariP' => 0,
                    'jml3BlnL' => 0,
                    'jml3BlnP' => 0,
                    'jml6BlnL' => 0,
                    'jml6BlnP' => 0,
                    'jml1ThnL' => 0,
                    'jml1ThnP' => 0,
                    'jml5ThnL' => 0,
                    'jml5ThnP' => 0,
                    'jml10ThnL' => 0,
                    'jml10ThnP' => 0,
                    'jml15ThnL' => 0,
                    'jml15ThnP' => 0,
                    'jml20ThnL' => 0,
                    'jml20ThnP' => 0,
                    'jml25ThnL' => 0,
                    'jml25ThnP' => 0,
                    'jml30ThnL' => 0,
                    'jml30ThnP' => 0,
                    'jml35ThnL' => 0,
                    'jml35ThnP' => 0,
                    'jml40ThnL' => 0,
                    'jml40ThnP' => 0,
                    'jml45ThnL' => 0,
                    'jml45ThnP' => 0,
                    'jml50ThnL' => 0,
                    'jml50ThnP' => 0,
                    'jml55ThnL' => 0,
                    'jml55ThnP' => 0,
                    'jml60ThnL' => 0,
                    'jml60ThnP' => 0,
                    'jml65ThnL' => 0,
                    'jml65ThnP' => 0,
                    'jml70ThnL' => 0,
                    'jml70ThnP' => 0,
                    'jml75ThnL' => 0,
                    'jml75ThnP' => 0,
                    'jml80ThnL' => 0,
                    'jml80ThnP' => 0,
                    'jml85ThnL' => 0,
                    'jml85ThnP' => 0,

                    //pasien mati
                    'jmlMatiL' => 0,
                    'jmlMatiP' => 0,
                    'jmlMati' => 0,

                    //pasien keluar (mati dan pulang)
                    'jmlKeluarL' => 0,
                    'jmlKeluarP' => 0,
                    'jmlKeluar' => 0,

                    'jmlBaruL' => 0,
                    'jmlBaruP' => 0,
                    'jmlBaru' => 0,

                    'jmlLamaL' => 0,
                    'jmlLamaP' => 0,
                    'jmlLama' => 0,

                    'totalAll' => 0
                ];
            }

            // Increment data based on age and gender
            $this->incrementCounters($groupedData[$key], $item);
        }

        foreach ($groupedData as &$group) {
            $this->calculateTotals($group);
        }

        return $this->respond([
            'data' => array_values($groupedData),
            'dept' => $kdDeptRanapAll,
            'message' => 'er@epic'
        ]);
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: getDataRL4_2; pertahankan struktur hasil sumber.
    private function getDataRL4_2(Request $request)
    {
        $bulan = $request['bulan'];
        $tahun = $request['tahun'];
        $kdDeptRanapAll = explode(',', $this->settingFix('kdDepartemenRanapFix'));
        $data = DB::connection('pgsql')->table('pasiendaftar_t as pd')
            ->join('antrianpasiendiperiksa_t as app', 'app.noregistrasifk', '=', 'pd.norec')
            ->leftjoin('diagnosapasien_t as dp', 'dp.noregistrasifk', '=', 'app.norec')
            ->leftjoin('detaildiagnosapasien_t as ddp', 'ddp.objectdiagnosapasienfk', '=', 'dp.norec')
            ->leftjoin('diagnosa_m as dm', 'ddp.objectdiagnosafk', '=', 'dm.id')
            ->leftjoin('mapdiagnosadtd_m as mm', 'mm.kddiagnosa', '=', 'dm.kddiagnosa')
            ->leftjoin('golongansebabpenyakit_m as gm', 'gm.id', '=', 'mm.objectgolongansebabpenyakitfk')
            ->join('pasien_m as ps', 'ps.id', '=', 'pd.nocmfk')
            ->join('jeniskelamin_m as jk', 'ps.objectjeniskelaminfk', '=', 'jk.id')
            ->join('ruangan_m as ru', 'ru.id', '=', 'pd.objectruanganlastfk')
            ->join('departemen_m as dpm', 'dpm.id', '=', 'ru.objectdepartemenfk')
            ->select(
                'pd.tglregistrasi',
                'app.statuspenyakit',
                'mm.nodtd',
                'dm.kddiagnosa',
                'gm.golongansebabpenyakit',
                'ps.tgllahir as tglLahir',
                'ps.objectjeniskelaminfk',
                'pd.objectstatuskeluarfk',
                'pd.tglmeninggal',
                DB::raw('EXTRACT(YEAR from AGE(pd.tglregistrasi, ps.tgllahir)) as umuryear,
                    EXTRACT(MONTH from AGE(pd.tglregistrasi, ps.tgllahir)) as umurmonth,
                    EXTRACT(DAY from AGE(pd.tglregistrasi, ps.tgllahir)) as umurday')
            )
            ->whereIn('dpm.id', $kdDeptRanapAll)
            ->where('mm.statusenabled', true)
            ->where('mm.kdprofile', $this->kdProfile)
            ->where(function ($query) {
                $query->whereRaw("dm.kddiagnosa < 'R00' OR dm.kddiagnosa > 'R99'");
            })
            ->where(function ($query) {
                $query->whereRaw("dm.kddiagnosa < 'Z23' OR dm.kddiagnosa > 'Z27'");
            })
            ->whereMonth('pd.tglregistrasi', $bulan)
            ->whereYear('pd.tglregistrasi', $tahun);

        $data = $data->orderBy('mm.nodtd')->get();

        $groupedData = [];

        foreach ($data as $item) {
            $key = $item->nodtd; // Grouping by DTD code

            if (!isset($groupedData[$key])) {
                $groupedData[$key] = [
                    'nodtd' => $item->nodtd,
                    'golongansebabpenyakit' => $item->golongansebabpenyakit,
                    'diagnosa_codes' => [$item->kddiagnosa],
                    'no_urut' => count($groupedData) + 1,

                    // For RL 4.2, we only need gender-separated totals
                    'totalHidupL' => 0,
                    'totalHidupP' => 0,
                    'totalHidup' => 0,

                    'totalMatiL' => 0,
                    'totalMatiP' => 0,
                    'totalMati' => 0,

                    'totalKeluarL' => 0,
                    'totalKeluarP' => 0,
                    'totalKeluar' => 0
                ];
            } elseif (!in_array($item->kddiagnosa, $groupedData[$key]['diagnosa_codes'])) {
                $groupedData[$key]['diagnosa_codes'][] = $item->kddiagnosa;
            }

            // Increment the counters for RL 4.2
            $this->incrementCountersRL4_2($groupedData[$key], $item);
        }

        // Calculate totals for each group
        foreach ($groupedData as &$group) {
            $this->calculateTotalsRL4_2($group);
            $group['kddiagnosa'] = implode(',', $group['diagnosa_codes']);
            unset($group['diagnosa_codes']);
        }

        // Sort by total patients (living + dead) in descending order
        usort($groupedData, function ($a, $b) {
            return $b['totalKeluar'] - $a['totalKeluar'];
        });

        // Get only the top 10
        $topTen = array_slice($groupedData, 0, 10);

        // Reindex the no_urut for top 10
        foreach ($topTen as $index => &$item) {
            $item['no_urut'] = $index + 1;
        }

        return $this->respond([
            'data' => $topTen,
            'dept' => $kdDeptRanapAll,
            'message' => 'er@epic'
        ]);
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: incrementCountersRL4_2; pertahankan struktur hasil sumber.
    private function incrementCountersRL4_2(&$group, $item)
    {
        $isLaki = $item->objectjeniskelaminfk == 1;

        // Track deaths
        if ($item->tglmeninggal) {
            if ($isLaki) {
                $group['totalMatiL']++;
            } else {
                $group['totalMatiP']++;
            }
        } else {
            // Living patients
            if ($isLaki) {
                $group['totalHidupL']++;
            } else {
                $group['totalHidupP']++;
            }
        }

        // Track all discharges (both alive and dead)
        if ($isLaki) {
            $group['totalKeluarL']++;
        } else {
            $group['totalKeluarP']++;
        }
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: calculateTotalsRL4_2; pertahankan struktur hasil sumber.
    private function calculateTotalsRL4_2(&$group)
    {
        // Calculate total living patients
        $group['totalHidup'] = $group['totalHidupL'] + $group['totalHidupP'];

        // Calculate total deaths
        $group['totalMati'] = $group['totalMatiL'] + $group['totalMatiP'];

        // Calculate total patients (both living and dead)
        $group['totalKeluar'] = $group['totalKeluarL'] + $group['totalKeluarP'];
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: getDataRL4_3; pertahankan struktur hasil sumber.
    private function getDataRL4_3(Request $request)
    {
        $bulan = $request['bulan'];
        $tahun = $request['tahun'];
        $kdDeptRanapAll = explode(',', $this->settingFix('kdDepartemenRanapFix'));
        $data = DB::connection('pgsql')->table('pasiendaftar_t as pd')
            ->join('antrianpasiendiperiksa_t as app', 'app.noregistrasifk', '=', 'pd.norec')
            ->leftjoin('diagnosapasien_t as dp', 'dp.noregistrasifk', '=', 'app.norec')
            ->leftjoin('detaildiagnosapasien_t as ddp', 'ddp.objectdiagnosapasienfk', '=', 'dp.norec')
            ->leftjoin('diagnosa_m as dm', 'ddp.objectdiagnosafk', '=', 'dm.id')
            ->leftjoin('mapdiagnosadtd_m as mm', 'mm.kddiagnosa', '=', 'dm.kddiagnosa')
            ->leftjoin('golongansebabpenyakit_m as gm', 'gm.id', '=', 'mm.objectgolongansebabpenyakitfk')
            ->join('pasien_m as ps', 'ps.id', '=', 'pd.nocmfk')
            ->join('jeniskelamin_m as jk', 'ps.objectjeniskelaminfk', '=', 'jk.id')
            ->join('ruangan_m as ru', 'ru.id', '=', 'pd.objectruanganlastfk')
            ->join('departemen_m as dpm', 'dpm.id', '=', 'ru.objectdepartemenfk')
            ->select(
                'pd.tglregistrasi',
                'app.statuspenyakit',
                'mm.nodtd',
                'dm.kddiagnosa',
                'gm.golongansebabpenyakit',
                'ps.tgllahir as tglLahir',
                'ps.objectjeniskelaminfk',
                'pd.objectstatuskeluarfk',
                'pd.tglmeninggal',
                DB::raw('EXTRACT(YEAR from AGE(pd.tglregistrasi, ps.tgllahir)) as umuryear,
                    EXTRACT(MONTH from AGE(pd.tglregistrasi, ps.tgllahir)) as umurmonth,
                    EXTRACT(DAY from AGE(pd.tglregistrasi, ps.tgllahir)) as umurday')
            )
            ->whereIn('dpm.id', $kdDeptRanapAll)
            ->where('mm.statusenabled', true)
            ->where('dm.kdprofile', $this->kdProfile)
            ->where(function ($query) {
                $query->whereRaw("dm.kddiagnosa < 'R00' OR dm.kddiagnosa > 'R99'");
            })
            ->where(function ($query) {
                $query->whereRaw("dm.kddiagnosa < 'Z23' OR dm.kddiagnosa > 'Z27'");
            })
            ->whereMonth('pd.tglregistrasi', $bulan)
            ->whereYear('pd.tglregistrasi', $tahun);

        $data = $data->orderBy('mm.nodtd')->get();

        $groupedData = [];

        foreach ($data as $item) {
            $key = $item->nodtd; // Grouping by DTD code

            if (!isset($groupedData[$key])) {
                $groupedData[$key] = [
                    'nodtd' => $item->nodtd,
                    'golongansebabpenyakit' => $item->golongansebabpenyakit,
                    'diagnosa_codes' => [$item->kddiagnosa],
                    'no_urut' => count($groupedData) + 1,

                    // For RL 4.3, we track deceased patients by gender
                    'matiL' => 0,
                    'matiP' => 0,
                    'totalMati' => 0,

                    // Also track total patients (for comparison)
                    'totalKeluarL' => 0,
                    'totalKeluarP' => 0,
                    'totalKeluar' => 0
                ];
            } elseif (!in_array($item->kddiagnosa, $groupedData[$key]['diagnosa_codes'])) {
                $groupedData[$key]['diagnosa_codes'][] = $item->kddiagnosa;
            }

            // Increment the counters for RL 4.3
            $this->incrementCountersRL4_3($groupedData[$key], $item);
        }

        // Calculate totals for each group
        foreach ($groupedData as &$group) {
            $this->calculateTotalsRL4_3($group);
            $group['kddiagnosa'] = implode(',', $group['diagnosa_codes']);
            unset($group['diagnosa_codes']);
        }

        // Sort by total deaths in descending order
        usort($groupedData, function ($a, $b) {
            return $b['totalMati'] - $a['totalMati'];
        });

        // Get only the top 10
        $topTen = array_slice($groupedData, 0, 10);

        // Reindex the no_urut for top 10
        foreach ($topTen as $index => &$item) {
            $item['no_urut'] = $index + 1;
        }

        return $this->respond([
            'data' => $topTen,
            'dept' => $kdDeptRanapAll,
            'message' => 'er@epic'
        ]);
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: incrementCountersRL4_3; pertahankan struktur hasil sumber.
    private function incrementCountersRL4_3(&$group, $item)
    {
        $isLaki = $item->objectjeniskelaminfk == 1;

        // Track deaths
        if ($item->tglmeninggal) {
            if ($isLaki) {
                $group['matiL']++;
            } else {
                $group['matiP']++;
            }
        }

        // Track all discharges (both alive and dead)
        if ($isLaki) {
            $group['totalKeluarL']++;
        } else {
            $group['totalKeluarP']++;
        }
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: calculateTotalsRL4_3; pertahankan struktur hasil sumber.
    private function calculateTotalsRL4_3(&$group)
    {
        // Calculate total deaths
        $group['totalMati'] = $group['matiL'] + $group['matiP'];

        // Calculate total patients (both living and dead)
        $group['totalKeluar'] = $group['totalKeluarL'] + $group['totalKeluarP'];
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: getDataRL5_1; pertahankan struktur hasil sumber.
    private function getDataRL5_1(Request $request)
    {
        $bulan = $request['bulan'];
        $tahun = $request['tahun'];
        $kdDeptRajalAll = explode(',', $this->settingFix('kdDepartemenRawatJalanFix'));
        $data = DB::connection('pgsql')->table('pasiendaftar_t as pd')
            ->join('antrianpasiendiperiksa_t as app', 'app.noregistrasifk', '=', 'pd.norec')
            ->leftjoin('diagnosapasien_t as dp', 'dp.noregistrasifk', '=', 'app.norec')
            ->leftjoin('detaildiagnosapasien_t as ddp', 'ddp.objectdiagnosapasienfk', '=', 'dp.norec')
            ->leftjoin('diagnosa_m as dm', 'ddp.objectdiagnosafk', '=', 'dm.id')
            ->join('pasien_m as ps', 'ps.id', '=', 'pd.nocmfk')
            ->join('jeniskelamin_m as jk', 'ps.objectjeniskelaminfk', '=', 'jk.id')
            ->join('ruangan_m as ru', 'ru.id', '=', 'pd.objectruanganlastfk')
            ->join('departemen_m as dpm', 'dpm.id', '=', 'ru.objectdepartemenfk')
            ->select(
                'pd.tglregistrasi',
                'app.statuspenyakit',
                'dm.kddiagnosa',
                'dm.namadiagnosa',
                'ps.tgllahir as tglLahir',
                'ps.objectjeniskelaminfk',
                'pd.objectstatuskeluarfk',
                'pd.tglmeninggal',
                'dp.iskasusbaru',
                'dp.iskasuslama',
                DB::raw('EXTRACT(YEAR from AGE(pd.tglregistrasi, ps.tgllahir)) as umuryear,
                EXTRACT(MONTH from AGE(pd.tglregistrasi, ps.tgllahir)) as umurmonth,
                EXTRACT(DAY from AGE(pd.tglregistrasi, ps.tgllahir)) as umurday')
            )
            ->whereIn('dpm.id', $kdDeptRajalAll)
            ->where('dm.kdprofile', $this->kdProfile)
            ->where(function ($query) {
                $query->whereRaw("dm.kddiagnosa < 'O60' OR dm.kddiagnosa > 'O75'");
            })
            ->where(function ($query) {
                $query->whereRaw("dm.kddiagnosa < 'O80' OR dm.kddiagnosa > 'O84'");
            })
            ->where(function ($query) {
                $query->whereRaw("dm.kddiagnosa < 'O85' OR dm.kddiagnosa > 'O92'");
            })
            ->where('dm.kddiagnosa', '!=', 'P95')
            ->where('dm.kddiagnosa', '!=', 'Z38')
            ->whereMonth('pd.tglregistrasi', $bulan)
            ->whereYear('pd.tglregistrasi', $tahun);

        $data = $data->orderBy('dm.kddiagnosa')->get();

        $groupedData = [];

        foreach ($data as $item) {
            $key = $item->kddiagnosa; // Grouping by diagnosis code

            if (!isset($groupedData[$key])) {
                $groupedData[$key] = [
                    'kddiagnosa' => $item->kddiagnosa,
                    'namadiagnosa' => $item->namadiagnosa,
                    'no_urut' => count($groupedData) + 1, // Auto-increment number

                    // Initialize counters for all age groups
                    'jml1JamL' => 0,
                    'jml1JamP' => 0,
                    'jml1_23JamL' => 0,
                    'jml1_23JamP' => 0,
                    'jml1HariL' => 0,
                    'jml1HariP' => 0,
                    'jml8HariL' => 0,
                    'jml8HariP' => 0,
                    'jml29HariL' => 0,
                    'jml29HariP' => 0,
                    'jml3BlnL' => 0,
                    'jml3BlnP' => 0,
                    'jml6BlnL' => 0,
                    'jml6BlnP' => 0,
                    'jml1ThnL' => 0,
                    'jml1ThnP' => 0,
                    'jml5ThnL' => 0,
                    'jml5ThnP' => 0,
                    'jml10ThnL' => 0,
                    'jml10ThnP' => 0,
                    'jml15ThnL' => 0,
                    'jml15ThnP' => 0,
                    'jml20ThnL' => 0,
                    'jml20ThnP' => 0,
                    'jml25ThnL' => 0,
                    'jml25ThnP' => 0,
                    'jml30ThnL' => 0,
                    'jml30ThnP' => 0,
                    'jml35ThnL' => 0,
                    'jml35ThnP' => 0,
                    'jml40ThnL' => 0,
                    'jml40ThnP' => 0,
                    'jml45ThnL' => 0,
                    'jml45ThnP' => 0,
                    'jml50ThnL' => 0,
                    'jml50ThnP' => 0,
                    'jml55ThnL' => 0,
                    'jml55ThnP' => 0,
                    'jml60ThnL' => 0,
                    'jml60ThnP' => 0,
                    'jml65ThnL' => 0,
                    'jml65ThnP' => 0,
                    'jml70ThnL' => 0,
                    'jml70ThnP' => 0,
                    'jml75ThnL' => 0,
                    'jml75ThnP' => 0,
                    'jml80ThnL' => 0,
                    'jml80ThnP' => 0,
                    'jml85ThnL' => 0,
                    'jml85ThnP' => 0,

                    //pasien mati
                    'jmlMatiL' => 0,
                    'jmlMatiP' => 0,
                    'jmlMati' => 0,

                    //pasien keluar (mati dan pulang)
                    'jmlKeluarL' => 0,
                    'jmlKeluarP' => 0,
                    'jmlKeluar' => 0,

                    'jmlBaruL' => 0,
                    'jmlBaruP' => 0,
                    'jmlBaru' => 0,

                    'jmlLamaL' => 0,
                    'jmlLamaP' => 0,
                    'jmlLama' => 0,

                    'totalAll' => 0
                ];
            }

            // Increment data based on age and gender
            $this->incrementCountersKasusBaru($groupedData[$key], $item);
        }

        foreach ($groupedData as &$group) {
            $this->calculateTotals($group);
        }

        return $this->respond([
            'data' => array_values($groupedData),
            'dept' => $kdDeptRajalAll,
            'message' => 'er@epic'
        ]);
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: getDataRL5_2; pertahankan struktur hasil sumber.
    private function getDataRL5_2(Request $request)
    {
        $bulan = $request['bulan'];
        $tahun = $request['tahun'];
        $kdDeptRajalFix = explode(',', $this->settingFix('kdDepartemenRawatJalanFix'));
        $data = DB::connection('pgsql')->table('pasiendaftar_t as pd')
            ->join('antrianpasiendiperiksa_t as app', 'app.noregistrasifk', '=', 'pd.norec')
            ->leftjoin('diagnosapasien_t as dp', 'dp.noregistrasifk', '=', 'app.norec')
            ->leftjoin('detaildiagnosapasien_t as ddp', 'ddp.objectdiagnosapasienfk', '=', 'dp.norec')
            ->leftjoin('diagnosa_m as dm', 'ddp.objectdiagnosafk', '=', 'dm.id')
            ->leftjoin('mapdiagnosadtd_m as mm', 'mm.kddiagnosa', '=', 'dm.kddiagnosa')
            ->leftjoin('golongansebabpenyakit_m as gm', 'gm.id', '=', 'mm.objectgolongansebabpenyakitfk')
            ->join('pasien_m as ps', 'ps.id', '=', 'pd.nocmfk')
            ->join('jeniskelamin_m as jk', 'ps.objectjeniskelaminfk', '=', 'jk.id')
            ->join('ruangan_m as ru', 'ru.id', '=', 'pd.objectruanganlastfk')
            ->join('departemen_m as dpm', 'dpm.id', '=', 'ru.objectdepartemenfk')
            ->select(
                'pd.tglregistrasi',
                'app.statuspenyakit',
                'mm.nodtd',
                'dm.kddiagnosa',
                'gm.golongansebabpenyakit',
                'ps.tgllahir as tglLahir',
                'ps.objectjeniskelaminfk',
                'pd.objectstatuskeluarfk',
                'pd.tglmeninggal',
                'dp.iskasusbaru',
                'dp.iskasuslama',
                DB::raw('EXTRACT(YEAR from AGE(pd.tglregistrasi, ps.tgllahir)) as umuryear,
                    EXTRACT(MONTH from AGE(pd.tglregistrasi, ps.tgllahir)) as umurmonth,
                    EXTRACT(DAY from AGE(pd.tglregistrasi, ps.tgllahir)) as umurday')
            )
            ->whereIn('dpm.id', $kdDeptRajalFix)
            ->where('mm.statusenabled', true)
            ->where('mm.kdprofile', $this->kdProfile)
            ->where('dm.kdprofile', $this->kdProfile)
            ->where(function ($query) {
                $query->whereRaw("dm.kddiagnosa < 'O60' OR dm.kddiagnosa > 'O75'");
            })
            ->where(function ($query) {
                $query->whereRaw("dm.kddiagnosa < 'O80' OR dm.kddiagnosa > 'O84'");
            })
            ->where(function ($query) {
                $query->whereRaw("dm.kddiagnosa < 'O85' OR dm.kddiagnosa > 'O92'");
            })
            ->where('dm.kddiagnosa', '!=', 'P95')
            ->where('dm.kddiagnosa', '!=', 'Z38')
            ->whereMonth('pd.tglregistrasi', $bulan)
            ->whereYear('pd.tglregistrasi', $tahun);

        $data = $data->orderBy('mm.nodtd')->get();

        $groupedData = [];

        foreach ($data as $item) {
            $key = $item->nodtd; // Grouping by DTD code

            if (!isset($groupedData[$key])) {
                $groupedData[$key] = [
                    'nodtd' => $item->nodtd,
                    'golongansebabpenyakit' => $item->golongansebabpenyakit,
                    'diagnosa_codes' => [$item->kddiagnosa],
                    'no_urut' => count($groupedData) + 1,

                    // For RL 4.3, we track deceased patients by gender
                    'baruL' => 0,
                    'baruP' => 0,
                    'totalBaru' => 0,

                    // Also track total patients (for comparison)
                    'totalKeluarL' => 0,
                    'totalKeluarP' => 0,
                    'totalKeluar' => 0
                ];
            } elseif (!in_array($item->kddiagnosa, $groupedData[$key]['diagnosa_codes'])) {
                $groupedData[$key]['diagnosa_codes'][] = $item->kddiagnosa;
            }

            // Increment the counters for RL 4.3
            $this->incrementCountersRL5_2($groupedData[$key], $item);
        }

        // Calculate totals for each group
        foreach ($groupedData as &$group) {
            $this->calculateTotalsRL5_2($group);
            $group['kddiagnosa'] = implode(',', $group['diagnosa_codes']);
            unset($group['diagnosa_codes']);
        }

        // Sort by total deaths in descending order
        usort($groupedData, function ($a, $b) {
            return $b['totalBaru'] - $a['totalBaru'];
        });

        // Get only the top 10
        $topTen = array_slice($groupedData, 0, 10);

        // Reindex the no_urut for top 10
        foreach ($topTen as $index => &$item) {
            $item['no_urut'] = $index + 1;
        }

        return $this->respond([
            'data' => $topTen,
            'dept' => $kdDeptRajalFix,
            'message' => 'er@epic'
        ]);
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: incrementCountersRL5_2; pertahankan struktur hasil sumber.
    private function incrementCountersRL5_2(&$group, $item)
    {
        $isLaki = $item->objectjeniskelaminfk == 1;

        // Track deaths

        if ($item->iskasusbaru == true || $item->iskasusbaru == 1) {
            if ($isLaki) {
                $group['baruL']++;
            } else {
                $group['baruP']++;
            }
        }

        // Track all discharges (both alive and dead)
        if ($isLaki) {
            $group['totalKeluarL']++;
        } else {
            $group['totalKeluarP']++;
        }
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: calculateTotalsRL5_2; pertahankan struktur hasil sumber.
    private function calculateTotalsRL5_2(&$group)
    {
        // Calculate total deaths
        $group['totalBaru'] = $group['baruL'] + $group['baruP'];

        // Calculate total patients (both living and dead)
        $group['totalKeluar'] = $group['totalKeluarL'] + $group['totalKeluarP'];
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: getDataRL5_3; pertahankan struktur hasil sumber.
    private function getDataRL5_3(Request $request)
    {
        $bulan = $request['bulan'];
        $tahun = $request['tahun'];
        $kdDeptRajalFix = explode(',', $this->settingFix('kdDepartemenRawatJalanFix'));
        $data = DB::connection('pgsql')->table('pasiendaftar_t as pd')
            ->join('antrianpasiendiperiksa_t as app', 'app.noregistrasifk', '=', 'pd.norec')
            ->leftjoin('diagnosapasien_t as dp', 'dp.noregistrasifk', '=', 'app.norec')
            ->leftjoin('detaildiagnosapasien_t as ddp', 'ddp.objectdiagnosapasienfk', '=', 'dp.norec')
            ->leftjoin('diagnosa_m as dm', 'ddp.objectdiagnosafk', '=', 'dm.id')
            ->leftjoin('mapdiagnosadtd_m as mm', 'mm.kddiagnosa', '=', 'dm.kddiagnosa')
            ->leftjoin('golongansebabpenyakit_m as gm', 'gm.id', '=', 'mm.objectgolongansebabpenyakitfk')
            ->join('pasien_m as ps', 'ps.id', '=', 'pd.nocmfk')
            ->join('jeniskelamin_m as jk', 'ps.objectjeniskelaminfk', '=', 'jk.id')
            ->join('ruangan_m as ru', 'ru.id', '=', 'pd.objectruanganlastfk')
            ->join('departemen_m as dpm', 'dpm.id', '=', 'ru.objectdepartemenfk')
            ->select(
                'pd.tglregistrasi',
                'app.statuspenyakit',
                'mm.nodtd',
                'dm.kddiagnosa',
                'gm.golongansebabpenyakit',
                'ps.tgllahir as tglLahir',
                'ps.objectjeniskelaminfk',
                'pd.objectstatuskeluarfk',
                'pd.tglmeninggal',
                'dp.iskasusbaru',
                'dp.iskasuslama',
                DB::raw('EXTRACT(YEAR from AGE(pd.tglregistrasi, ps.tgllahir)) as umuryear,
                    EXTRACT(MONTH from AGE(pd.tglregistrasi, ps.tgllahir)) as umurmonth,
                    EXTRACT(DAY from AGE(pd.tglregistrasi, ps.tgllahir)) as umurday')
            )
            ->whereIn('dpm.id', $kdDeptRajalFix)
            ->where('mm.statusenabled', true)
            ->where('mm.kdprofile', $this->kdProfile)
            ->where('dm.kdprofile', $this->kdProfile)
            ->where(function ($query) {
                $query->whereRaw("dm.kddiagnosa < 'O60' OR dm.kddiagnosa > 'O75'");
            })
            ->where(function ($query) {
                $query->whereRaw("dm.kddiagnosa < 'O80' OR dm.kddiagnosa > 'O84'");
            })
            ->where(function ($query) {
                $query->whereRaw("dm.kddiagnosa < 'O85' OR dm.kddiagnosa > 'O92'");
            })
            ->where('dm.kddiagnosa', '!=', 'P95')
            ->where('dm.kddiagnosa', '!=', 'Z38')
            ->whereMonth('pd.tglregistrasi', $bulan)
            ->whereYear('pd.tglregistrasi', $tahun);

        $data = $data->orderBy('mm.nodtd')->get();

        $groupedData = [];

        foreach ($data as $item) {
            $key = $item->nodtd; // Grouping by DTD code

            if (!isset($groupedData[$key])) {
                $groupedData[$key] = [
                    'nodtd' => $item->nodtd,
                    'golongansebabpenyakit' => $item->golongansebabpenyakit,
                    'diagnosa_codes' => [$item->kddiagnosa],
                    'no_urut' => count($groupedData) + 1,

                    // For RL 4.3, we track deceased patients by gender
                    'baruL' => 0,
                    'baruP' => 0,
                    'totalBaru' => 0,

                    // Also track total patients (for comparison)
                    'totalKeluarL' => 0,
                    'totalKeluarP' => 0,
                    'totalKeluar' => 0
                ];
            } elseif (!in_array($item->kddiagnosa, $groupedData[$key]['diagnosa_codes'])) {
                $groupedData[$key]['diagnosa_codes'][] = $item->kddiagnosa;
            }

            // Increment the counters for RL 4.3
            $this->incrementCountersRL5_3($groupedData[$key], $item);
        }

        // Calculate totals for each group
        foreach ($groupedData as &$group) {
            $this->calculateTotalsRL5_3($group);
            $group['kddiagnosa'] = implode(',', $group['diagnosa_codes']);
            unset($group['diagnosa_codes']);
        }

        // Sort by total deaths in descending order
        usort($groupedData, function ($a, $b) {
            return $b['totalKeluar'] - $a['totalKeluar'];
        });

        // Get only the top 10
        $topTen = array_slice($groupedData, 0, 10);

        // Reindex the no_urut for top 10
        foreach ($topTen as $index => &$item) {
            $item['no_urut'] = $index + 1;
        }

        return $this->respond([
            'data' => $topTen,
            'dept' => $kdDeptRajalFix,
            'message' => 'er@epic'
        ]);
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: incrementCountersRL5_3; pertahankan struktur hasil sumber.
    private function incrementCountersRL5_3(&$group, $item)
    {
        $isLaki = $item->objectjeniskelaminfk == 1;

        // Track deaths

        if ($item->iskasusbaru == true || $item->iskasusbaru == 1) {
            if ($isLaki) {
                $group['baruL']++;
            } else {
                $group['baruP']++;
            }
        }

        // Track all discharges (both alive and dead)
        if ($isLaki) {
            $group['totalKeluarL']++;
        } else {
            $group['totalKeluarP']++;
        }
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: calculateTotalsRL5_3; pertahankan struktur hasil sumber.
    private function calculateTotalsRL5_3(&$group)
    {
        // Calculate total deaths
        $group['totalBaru'] = $group['baruL'] + $group['baruP'];

        // Calculate total patients (both living and dead)
        $group['totalKeluar'] = $group['totalKeluarL'] + $group['totalKeluarP'];
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: incrementCountersKasusBaru; pertahankan struktur hasil sumber.
    private function incrementCountersKasusBaru(&$group, $item)
    {
        $group['totalAll']++;

        $isLaki = $item->objectjeniskelaminfk == 1;

        if ($item->iskasusbaru == true || $item->iskasusbaru == 1) {
            // Complete age groups matching the requirements
            if ($item->umuryear >= 85) {
                $isLaki ? $group['jml85ThnL']++ : $group['jml85ThnP']++;
            } elseif ($item->umuryear >= 80) {
                $isLaki ? $group['jml80ThnL']++ : $group['jml80ThnP']++;
            } elseif ($item->umuryear >= 75) {
                $isLaki ? $group['jml75ThnL']++ : $group['jml75ThnP']++;
            } elseif ($item->umuryear >= 70) {
                $isLaki ? $group['jml70ThnL']++ : $group['jml70ThnP']++;
            } elseif ($item->umuryear >= 65) {
                $isLaki ? $group['jml65ThnL']++ : $group['jml65ThnP']++;
            } elseif ($item->umuryear >= 60) {
                $isLaki ? $group['jml60ThnL']++ : $group['jml60ThnP']++;
            } elseif ($item->umuryear >= 55) {
                $isLaki ? $group['jml55ThnL']++ : $group['jml55ThnP']++;
            } elseif ($item->umuryear >= 50) {
                $isLaki ? $group['jml50ThnL']++ : $group['jml50ThnP']++;
            } elseif ($item->umuryear >= 45) {
                $isLaki ? $group['jml45ThnL']++ : $group['jml45ThnP']++;
            } elseif ($item->umuryear >= 40) {
                $isLaki ? $group['jml40ThnL']++ : $group['jml40ThnP']++;
            } elseif ($item->umuryear >= 35) {
                $isLaki ? $group['jml35ThnL']++ : $group['jml35ThnP']++;
            } elseif ($item->umuryear >= 30) {
                $isLaki ? $group['jml30ThnL']++ : $group['jml30ThnP']++;
            } elseif ($item->umuryear >= 25) {
                $isLaki ? $group['jml25ThnL']++ : $group['jml25ThnP']++;
            } elseif ($item->umuryear >= 20) {
                $isLaki ? $group['jml20ThnL']++ : $group['jml20ThnP']++;
            } elseif ($item->umuryear >= 15) {
                $isLaki ? $group['jml15ThnL']++ : $group['jml15ThnP']++;
            } elseif ($item->umuryear >= 10) {
                $isLaki ? $group['jml10ThnL']++ : $group['jml10ThnP']++;
            } elseif ($item->umuryear >= 5) {
                $isLaki ? $group['jml5ThnL']++ : $group['jml5ThnP']++;
            } elseif ($item->umuryear >= 1) {
                $isLaki ? $group['jml1ThnL']++ : $group['jml1ThnP']++;
            } elseif ($item->umuryear < 1) {
                if ($item->umurmonth >= 6) {
                    $isLaki ? $group['jml6BlnL']++ : $group['jml6BlnP']++;
                } elseif ($item->umurmonth >= 3) {
                    $isLaki ? $group['jml3BlnL']++ : $group['jml3BlnP']++;
                } elseif ($item->umurday >= 29) {
                    $isLaki ? $group['jml29HariL']++ : $group['jml29HariP']++;
                } elseif ($item->umurday >= 8) {
                    $isLaki ? $group['jml8HariL']++ : $group['jml8HariP']++;
                } elseif ($item->umurday >= 1) {
                    $isLaki ? $group['jml1HariL']++ : $group['jml1HariP']++;
                } else {
                    // Less than 1 day
                    if ($item->umurday == 0 && $item->umurmonth == 0) {
                        $hoursSinceBirth = floor(($item->umuryear * 365 * 24) + ($item->umurday * 24));

                        if ($hoursSinceBirth >= 1 && $hoursSinceBirth <= 23) {
                            $isLaki ? $group['jml1_23JamL']++ : $group['jml1_23JamP']++;
                        } else {
                            $isLaki ? $group['jml1JamL']++ : $group['jml1JamP']++;
                        }
                    }
                }
            }

            $isLaki ? $group['jmlBaruL']++ : $group['jmlBaruP']++;
        } else {
            $isLaki ? $group['jmlLamaL']++ : $group['jmlLamaP']++;
        }

        // Track all discharges (both alive and dead)
        if ($isLaki) {
            $group['jmlKeluarL']++;
        } else {
            $group['jmlKeluarP']++;
        }

        // Track deaths separately
        if ($item->tglmeninggal) {
            if ($isLaki) {
                $group['jmlMatiL']++;
            } else {
                $group['jmlMatiP']++;
            }
        }
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: incrementCounters; pertahankan struktur hasil sumber.
    private function incrementCounters(&$group, $item)
    {
        $group['totalAll']++;

        $isLaki = $item->objectjeniskelaminfk == 1;

        // Complete age groups matching the requirements
        if ($item->umuryear >= 85) {
            $isLaki ? $group['jml85ThnL']++ : $group['jml85ThnP']++;
        } elseif ($item->umuryear >= 80) {
            $isLaki ? $group['jml80ThnL']++ : $group['jml80ThnP']++;
        } elseif ($item->umuryear >= 75) {
            $isLaki ? $group['jml75ThnL']++ : $group['jml75ThnP']++;
        } elseif ($item->umuryear >= 70) {
            $isLaki ? $group['jml70ThnL']++ : $group['jml70ThnP']++;
        } elseif ($item->umuryear >= 65) {
            $isLaki ? $group['jml65ThnL']++ : $group['jml65ThnP']++;
        } elseif ($item->umuryear >= 60) {
            $isLaki ? $group['jml60ThnL']++ : $group['jml60ThnP']++;
        } elseif ($item->umuryear >= 55) {
            $isLaki ? $group['jml55ThnL']++ : $group['jml55ThnP']++;
        } elseif ($item->umuryear >= 50) {
            $isLaki ? $group['jml50ThnL']++ : $group['jml50ThnP']++;
        } elseif ($item->umuryear >= 45) {
            $isLaki ? $group['jml45ThnL']++ : $group['jml45ThnP']++;
        } elseif ($item->umuryear >= 40) {
            $isLaki ? $group['jml40ThnL']++ : $group['jml40ThnP']++;
        } elseif ($item->umuryear >= 35) {
            $isLaki ? $group['jml35ThnL']++ : $group['jml35ThnP']++;
        } elseif ($item->umuryear >= 30) {
            $isLaki ? $group['jml30ThnL']++ : $group['jml30ThnP']++;
        } elseif ($item->umuryear >= 25) {
            $isLaki ? $group['jml25ThnL']++ : $group['jml25ThnP']++;
        } elseif ($item->umuryear >= 20) {
            $isLaki ? $group['jml20ThnL']++ : $group['jml20ThnP']++;
        } elseif ($item->umuryear >= 15) {
            $isLaki ? $group['jml15ThnL']++ : $group['jml15ThnP']++;
        } elseif ($item->umuryear >= 10) {
            $isLaki ? $group['jml10ThnL']++ : $group['jml10ThnP']++;
        } elseif ($item->umuryear >= 5) {
            $isLaki ? $group['jml5ThnL']++ : $group['jml5ThnP']++;
        } elseif ($item->umuryear >= 1) {
            $isLaki ? $group['jml1ThnL']++ : $group['jml1ThnP']++;
        } elseif ($item->umuryear < 1) {
            if ($item->umurmonth >= 6) {
                $isLaki ? $group['jml6BlnL']++ : $group['jml6BlnP']++;
            } elseif ($item->umurmonth >= 3) {
                $isLaki ? $group['jml3BlnL']++ : $group['jml3BlnP']++;
            } elseif ($item->umurday >= 29) {
                $isLaki ? $group['jml29HariL']++ : $group['jml29HariP']++;
            } elseif ($item->umurday >= 8) {
                $isLaki ? $group['jml8HariL']++ : $group['jml8HariP']++;
            } elseif ($item->umurday >= 1) {
                $isLaki ? $group['jml1HariL']++ : $group['jml1HariP']++;
            } else {
                // Less than 1 day
                if ($item->umurday == 0 && $item->umurmonth == 0) {
                    $hoursSinceBirth = floor(($item->umuryear * 365 * 24) + ($item->umurday * 24));

                    if ($hoursSinceBirth >= 1 && $hoursSinceBirth <= 23) {
                        $isLaki ? $group['jml1_23JamL']++ : $group['jml1_23JamP']++;
                    } else {
                        $isLaki ? $group['jml1JamL']++ : $group['jml1JamP']++;
                    }
                }
            }
        }

        // Track all discharges (both alive and dead)
        if ($isLaki) {
            $group['jmlKeluarL']++;
        } else {
            $group['jmlKeluarP']++;
        }

        // Track deaths separately
        if ($item->tglmeninggal) {
            if ($isLaki) {
                $group['jmlMatiL']++;
            } else {
                $group['jmlMatiP']++;
            }
        }

        if ($item->iskasusbaru == true || $item->iskasusbaru == 1) {
            $isLaki ? $group['jmlBaruL']++ : $group['jmlBaruP']++;
        } else {
            $isLaki ? $group['jmlLamaL']++ : $group['jmlLamaP']++;
        }
    }

    // Jhon | 2026-11-25 19:41 WITA | TAMBAH: calculateTotals; pertahankan struktur hasil sumber.
    private function calculateTotals(&$group)
    {
        // Calculate total by gender
        $group['totalMenurutL'] =
            $group['jml1JamL'] + $group['jml1_23JamL'] + $group['jml1HariL'] + $group['jml8HariL'] +
            $group['jml29HariL'] + $group['jml3BlnL'] + $group['jml6BlnL'] +
            $group['jml1ThnL'] + $group['jml5ThnL'] + $group['jml10ThnL'] +
            $group['jml15ThnL'] + $group['jml20ThnL'] + $group['jml25ThnL'] +
            $group['jml30ThnL'] + $group['jml35ThnL'] + $group['jml40ThnL'] +
            $group['jml45ThnL'] + $group['jml50ThnL'] + $group['jml55ThnL'] +
            $group['jml60ThnL'] + $group['jml65ThnL'] + $group['jml70ThnL'] +
            $group['jml75ThnL'] + $group['jml80ThnL'] + $group['jml85ThnL'];

        $group['totalMenurutP'] =
            $group['jml1JamP'] + $group['jml1_23JamP'] + $group['jml1HariP'] + $group['jml8HariP'] +
            $group['jml29HariP'] + $group['jml3BlnP'] + $group['jml6BlnP'] +
            $group['jml1ThnP'] + $group['jml5ThnP'] + $group['jml10ThnP'] +
            $group['jml15ThnP'] + $group['jml20ThnP'] + $group['jml25ThnP'] +
            $group['jml30ThnP'] + $group['jml35ThnP'] + $group['jml40ThnP'] +
            $group['jml45ThnP'] + $group['jml50ThnP'] + $group['jml55ThnP'] +
            $group['jml60ThnP'] + $group['jml65ThnP'] + $group['jml70ThnP'] +
            $group['jml75ThnP'] + $group['jml80ThnP'] + $group['jml85ThnP'];

        // Calculate total patients
        $group['jmlPL'] = $group['totalMenurutL'] + $group['totalMenurutP'];

        // Calculate totals for deaths
        $group['jmlMati'] = $group['jmlMatiL'] + $group['jmlMatiP'];

        // Calculate totals for all discharges (both alive and dead)
        $group['jmlKeluar'] = $group['jmlKeluarL'] + $group['jmlKeluarP'];

        // Calculate totals for new and returning patients
        $group['jmlBaru'] = $group['jmlBaruL'] + $group['jmlBaruP'];
        $group['jmlLama'] = $group['jmlLamaL'] + $group['jmlLamaP'];
    }
}
