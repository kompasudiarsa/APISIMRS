<?php
namespace App\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
// Jhon | 2026-11-25 21:16 WITA | TAMBAH: RL3.2 versi kegiatan-pelayanan-ranap-v1; helper sumber terisolasi.
class SirsRl32V1Service
{
    private $kdProfile = 1;
    private $periodStart;
    private $periodEnd;
    private $unmappedSpecialRooms = [];
    private const STATUS_MATI = [5, 6];
    private const KELAS_MAP = [5=>"rincianvvip",4=>"rincianvip",3=>"rinciankel1",2=>"rinciankel2",1=>"rinciankel3"];
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
        if ($value === null) throw new \DomainException((string) $name);
        return $value;
    }
    
    // Jhon | 2026-11-25 21:16 WITA | TAMBAH: getDataRL31RawatInap sesuai controller sumber v1.
    public function getDataRL31RawatInap(Request $request)
    {
        $deptIds     = explode(',', $this->settingFix('KdListDepartemen'));
        $kdJenisLap  = $this->settingFix('KdJenisLapRanap');
        $tglAwal     = substr($request['tglAwal'], 0, 10) . ' 00:00:00';
        $tglAkhir    = substr($request['tglAkhir'], 0, 10) . ' 23:59:59';

        $this->periodStart = strtotime($tglAwal);
        $this->periodEnd = strtotime($tglAkhir);
        $rowsData = $this->fetchRawData($deptIds, $kdJenisLap, $tglAwal, $tglAkhir);
        $rows = $this->normalizeSpesialisasi($rowsData);
        $grouped = $this->groupBySpesialisasi($rows);
        $result  = $this->mergeWithMasterSpesialisasi($grouped, $kdJenisLap);

        $respons = array(
            'data' => $result,
            'warnings' => [
                'ruangan_khusus_belum_mapping' => count($this->unmappedSpecialRooms),
                'kelas_belum_terpetakan' => array_sum(array_column($result, 'kelas_belum_terpetakan')),
                'kematian_belum_terklasifikasi' => array_sum(array_column($result, 'kematian_belum_terklasifikasi')),
                'alokasi_bed' => 'Jumlah bed aktif saat ini, belum snapshot SK awal tahun.',
            ],
            'message' => 'Sukses',
        );

        return $this->respond($respons);
    }

    // Jhon | 2026-11-25 21:16 WITA | TAMBAH: fetchRawData sesuai controller sumber v1.
    private function fetchRawData(array $deptIds, int $kdJenisLap, string $tglAwal, string $tglAkhir): \Illuminate\Support\Collection
    {
        return DB::connection('pgsql')->table('antrianpasiendiperiksa_t as app')
            ->join('pasiendaftar_t as pd',    'pd.norec',             '=', 'app.noregistrasifk')
            ->leftJoin('asalrujukan_m as ar',     'ar.id',                '=', 'app.objectasalrujukanfk')
            ->join('pasien_m as pm',          'pm.id',                '=', 'pd.nocmfk')
            ->join('ruangan_m as ru',         'ru.id',                '=', 'app.objectruanganfk')
            ->leftJoin('ruangan_m as ru1',        'ru1.id',               '=', 'app.objectruanganasalfk')
            ->join('departemen_m as dpm',     'dpm.id',               '=', 'ru.objectdepartemenfk')
            ->leftJoin('pegawai_m as pg',         'pg.id',                '=', 'pd.objectpegawaifk')
            ->leftJoin('kelas_m as kls',          'kls.id',               '=', 'app.objectkelasfk')
            ->leftJoin('statuskeluar_m as sk','sk.id',                '=', 'pd.objectstatuskeluarfk')
            ->join('jeniskelamin_m as jk',    'jk.id',                '=', 'pm.objectjeniskelaminfk')
            ->select(
                // Jhon | 2026-11-25 21:27 WITA | TAMBAH: kunci episode dan timestamp asli untuk deduplikasi dan durasi kematian dalam jam.
                'app.norec as episode_id', 'pd.norec as registration_id',
                'pd.tglregistrasi as tanggal_masuk_asli', 'pd.tglpulang', 'pd.tglmeninggal', 'sk.statuskeluar',
                'pd.tglregistrasi',
                'pm.nocm',
                'pd.noregistrasi',
                'pm.namapasien',
                'app.objectasalrujukanfk',
                'ar.asalrujukan',
                'ru.id as idRuangan',
                'ru.namaruangan',
                'ru1.namaruangan as ruanganasal',
                'ru1.objectdepartemenfk as deptasal',
                'dpm.namadepartemen',
                'pg.namalengkap',
                'pm.objectjeniskelaminfk',
                'ru.objectdepartemenfk',
                'pd.objectstatuskeluarfk',
                'pd.objectruanganasalfk',
                'pd.objectruanganlastfk',
                'pd.objectkelasfk',
                DB::raw("
                    CASE
                        WHEN pd.tglpulang IS NULL
                            THEN DATE_PART('day', now() - pd.tglregistrasi::timestamp)
                        ELSE
                            DATE_PART('day', pd.tglpulang::timestamp - pd.tglregistrasi::timestamp)
                    END AS lamadirawat,
                    to_char(pd.tglregistrasi, 'MM')         AS bulanregistrasi,
                    to_char(pd.tglregistrasi, 'DD')         AS hariregistrasi,
                    to_char(pd.tglregistrasi, 'YYYY-MM-DD') AS tglregistrasi
                ")
            )
            ->where('app.kdprofile', $this->kdProfile)
            ->whereRaw("pd.tglregistrasi::date BETWEEN ? AND ?", [$tglAwal, $tglAkhir])
            ->whereIn('dpm.id', $deptIds)
            // ->whereExists(function ($query) use ($kdJenisLap) {
            //     $query->select(DB::raw(1))
            //         ->from('mapproduktolaporanrl_m as mprl')
            //         ->whereColumn('mprl.ruanganfk', 'pd.objectruanganlastfk')
            //         ->where('mprl.objectjenislaporanfk', $kdJenisLap);
            // })
            ->get();
    }

    // Jhon | 2026-11-25 21:16 WITA | TAMBAH: normalizeSpesialisasi sesuai controller sumber v1.
    private function normalizeSpesialisasi(\Illuminate\Support\Collection $rows): \Illuminate\Support\Collection
    {
        // // Jhon | 2026-11-25 21:27 WITA | UBAH: : gunakan mapping ruangan existing, layanan nonspesifik masuk Umum sesuai catatan Excel.
        $kind = (int) $this->settingFix('KdJenisLapRanap');
        $mapping = DB::connection('pgsql')->table('mapproduktolaporanrl_m as map')
            ->join('kelompoklaporan_m as kl','kl.id','=','map.objectkontenlaporanfk')
            // Jhon | 2026-11-26 13:00 WITA | UBAH: samakan scope profil mapping dengan pembaca SIMRS sumber.
            ->where('map.kdprofile', $this->kdProfile)
            ->where('map.objectjenislaporanfk',$kind)->where('map.statusenabled',true)
            ->where('kl.statusenabled',true)->select('map.ruanganfk','kl.kelompoklaporan')->distinct()->get()->groupBy('ruanganfk');
        // Jhon | 2026-11-26 21:51 WITA | UBAH: pertahankan mapping Perinatologi sebagai baris tersendiri, bukan dialihkan ke Umum.
        $special = ['ICU','HCU','ICCU/ICVCU','RICU','NICU','PICU','ISOLASI','PERINATOLOGI'];
        return $rows->unique('episode_id')->map(function ($item) use ($mapping,$special) {
            $labels = collect($mapping->get($item->idRuangan, []))->pluck('kelompoklaporan')->filter(function ($name) use ($special) {
                return in_array(strtoupper(trim($name)), $special, true);
            })->map(function ($name) { return trim($name); })->unique(function ($name) { return strtoupper($name); })->values();
            // Jhon | 2026-11-26 13:00 WITA | TAMBAH: konflik asli membawa identitas ruangan/master saja, tanpa data pasien.
            // Jhon | 2026-11-26 13:02 WITA | UBAH: konflik dipilih hanya jika tepat satu kandidat cocok token layanan pada nama ruangan.
            // Jhon | 2026-11-26 13:08 WITA | UBAH: arahan Pasek, seluruh layanan/bed ruang PICU-NICU320 dilaporkan NICU; bukan aturan umur pasien.
            if ((int) $item->idRuangan === 320) $labels = collect(['NICU']);
            if ($labels->count() > 1) {
                $resolved = $this->resolveServiceFromRoomName((string) $item->namaruangan, $labels->all());
                if ($resolved !== null) $labels = collect([$resolved]);
            }
            if ($labels->count() > 1) throw new \App\Exceptions\RlMappingConflictException(
                (int) $item->idRuangan, (string) $item->namaruangan, $labels->all());
            // Jhon | 2026-11-26 21:51 WITA | UBAH: tandai pula ruangan Perinatologi bila mapping aktif belum tersedia.
            if (!$labels->count() && preg_match('/\b(ICU|HCU|ICCU|ICVCU|RICU|NICU|PICU|ISOLASI|PERINATOLOGI)\b/i', (string) $item->namaruangan)) $this->unmappedSpecialRooms[$item->idRuangan] = true;
            $item->jenis_spesialisasi = $labels->first() ?? 'Umum';
            return $item;
        })->values();
    }

    // Jhon | 2026-11-25 21:16 WITA | TAMBAH: groupBySpesialisasi sesuai controller sumber v1.
    private function groupBySpesialisasi(\Illuminate\Support\Collection $rows): array
    {
        $grouped = [];

        foreach ($rows as $item) {
            $key = $item->jenis_spesialisasi;

            if (!isset($grouped[$key])) {
                $grouped[$key] = $this->buildEmptyRow($item->jenis_spesialisasi, $item->idRuangan);
            }

            // Jhon | 2026-11-25 21:27 WITA | UBAH: jumlah adalah alokasi bed ruangan unik, bukan jumlah baris kunjungan.
            if (!isset($grouped[$key]['_rooms'][$item->idRuangan])) {
                $grouped[$key]['jumlah'] += $this->getBedCountForRooms($item->idRuangan);
                $grouped[$key]['_rooms'][$item->idRuangan] = true;
            }
            $grouped[$key]['lamadirawat']      += (float) $item->lamadirawat;
            $grouped[$key]['jumlahharidirawat'] += (float) $item->lamadirawat;

            $this->accumulatePasienStatus($grouped[$key], $item);
            $this->accumulateKelas($grouped[$key], $item);
        }

        // Jhon | 2026-11-26 22:05 WITA | UBAH: saldo akhir mengikuti seluruh baris acuan Oktober, bukan tanggal pasien mendaftar.
        foreach ($grouped as &$row) $row['pasienakhirbulan'] = $this->calculateClosingBalance($row);
        unset($row);
        return $grouped;
    }

    // Jhon | 2026-11-25 21:16 WITA | TAMBAH: mergeWithMasterSpesialisasi sesuai controller sumber v1.
    private function mergeWithMasterSpesialisasi(array $grouped, int $kdJenisLap): array
    {
        $masterList = DB::connection('pgsql')->select(
            "SELECT * FROM kelompoklaporan_m
            WHERE idjenislaporanfk = ?
              AND statusenabled = true
            ORDER BY kodeexternal::int4 ASC",
            [$kdJenisLap]
        );

        $result = [];

        foreach ($masterList as $master) {
            $key  = $master->kelompoklaporan;
            $data = $grouped[$key] ?? $this->buildEmptyRow($key, 0);

            $result[] = [
                'jenis_spesialisasi' => $key,
                'pasienawalbulan'    => $data['pasienawalbulan'],
                'pasienmasuk'        => $data['pasienmasuk'],
                'pasienpindahan'     => $data['pasienpindahan'],
                'pasiendipindahkan'  => $data['pasiendipindahkan'],
                'pasienkeluarhidup'  => $data['pasienkeluarhidup'],
                'matilebih48jaml'    => $data['matilebih48jaml'],
                'matilebih48jamp'    => $data['matilebih48jamp'],
                'matikurang48jaml'   => $data['matikurang48jaml'],
                'matikurang48jamp'   => $data['matikurang48jamp'],
                'pasienakhirbulan'   => $data['pasienakhirbulan'],
                'lamadirawat'        => $data['lamadirawat'],
                'jumlahlamadirawat'  => $data['jumlahlamadirawat'],
                'jumlahharidirawat'  => $data['jumlahharidirawat'],
                'rincianvvip'        => $data['rincianvvip'],
                'rincianvip'         => $data['rincianvip'],
                'rinciankel1'        => $data['rinciankel1'],
                'rinciankel2'        => $data['rinciankel2'],
                'rinciankel3'        => $data['rinciankel3'],
                'rinciankelaskhusus' => $data['rinciankelaskhusus'],
                'jumlah'             => $data['jumlah'],
                'kelas_belum_terpetakan' => $data['_kelas_belum_terpetakan'] ?? 0,
                'kematian_belum_terklasifikasi' => $data['_kematian_belum_terklasifikasi'] ?? 0,
            ];
        }

        return $result;
    }

    // Jhon | 2026-11-25 21:16 WITA | TAMBAH: buildEmptyRow sesuai controller sumber v1.
    private function buildEmptyRow(string $spesialisasi, int $ruanganId): array
    {
        return [
            'jenis_spesialisasi'  => $spesialisasi,
            'pasienawalbulan'     => 0,
            'pasienmasuk'         => 0,
            'pasienpindahan'      => 0,
            'pasiendipindahkan'   => 0,
            'pasienkeluarhidup'   => 0,
            'matilebih48jaml'     => 0,
            'matilebih48jamp'     => 0,
            'matikurang48jaml'    => 0,
            'matikurang48jamp'    => 0,
            'pasienakhirbulan'    => 0,
            'lamadirawat'         => 0,
            'jumlahlamadirawat'   => 0,
            'jumlahharidirawat'   => 0,
            'rincianvvip'         => 0,
            'rincianvip'          => 0,
            'rinciankel1'         => 0,
            'rinciankel2'         => 0,
            'rinciankel3'         => 0,
            'rinciankelaskhusus'  => 0,
            'jumlah'              => 0,
            '_jumlahbed'          => $this->getBedCountForRooms($ruanganId),
        ];
    }

    // Jhon | 2026-11-25 21:16 WITA | TAMBAH: accumulatePasienStatus sesuai controller sumber v1.
    private function accumulatePasienStatus(array &$row, object $item): void
    {
        $hari      = (int) date('d', strtotime($item->tglregistrasi));
        $lastDay   = (int) date('t', strtotime($item->tglregistrasi));
        $statusOut = (int) $item->objectstatuskeluarfk;
        $gender    = (int) $item->objectjeniskelaminfk;
        $lama      = (float) $item->lamadirawat;
        $deptNow   = (int) $item->objectdepartemenfk;
        $deptAsal  = (int) $item->deptasal;

        // Pasien awal bulan
        if ($hari === 1) {
            $row['pasienawalbulan']++;
        }

        // Jhon | 2026-11-26 22:05 WITA | UBAH: saldo akhir dihitung sesudah seluruh mutasi layanan dijumlahkan.

        // Pasien masuk (dari IGD / dept 16)
        if ($deptNow === 16) {
            $row['pasienmasuk']++;
        }

        // Pasien pindahan (pindah ruangan dalam rumah sakit)
        $ruanganAsal  = $item->objectruanganasalfk;
        $ruanganLast  = $item->objectruanganlastfk;
        if (!empty($ruanganAsal) && $ruanganAsal !== $ruanganLast) {
            $row['pasienpindahan']++;
        }

        // Pasien dipindahkan (antar departemen)
        if ($deptAsal === 16 && $deptNow === 16) {
            $row['pasiendipindahkan']++;
        }

        // // Jhon | 2026-11-25 21:27 WITA | UBAH: : hitung keluar satu kali per registrasi, hanya yang benar-benar pulang pada periode laporan.
        $exit = $item->tglmeninggal ?: $item->tglpulang;
        $registration = (string) $item->registration_id;
        if (!$exit || isset($row['_keluar_terhitung'][$registration])) return;
        $exitTime = strtotime($exit);
        if ($exitTime === false || $exitTime < $this->periodStart || $exitTime > $this->periodEnd) return;
        $row['_keluar_terhitung'][$registration] = true;
        $dead = !empty($item->tglmeninggal) || preg_match('/meninggal|mati/i', (string) $item->statuskeluar);
        if (!$dead) { $row['pasienkeluarhidup']++; return; }
        $entered = strtotime((string) $item->tanggal_masuk_asli);
        if ($entered === false || $exitTime < $entered || !in_array($gender, [1,2], true)) {
            $row['_kematian_belum_terklasifikasi'] = ($row['_kematian_belum_terklasifikasi'] ?? 0) + 1;
            return;
        }
        $hours = ($exitTime - $entered) / 3600;
        $key = ($hours < 48 ? 'matikurang48jam' : 'matilebih48jam').($gender === 1 ? 'l' : 'p');
        $row[$key]++;
    }

    // Jhon | 2026-11-25 21:16 WITA | TAMBAH: accumulateKelas sesuai controller sumber v1.
    private function accumulateKelas(array &$row, object $item): void
    {
        $kelas = (int) $item->objectkelasfk;

        if (isset(self::KELAS_MAP[$kelas])) {
            // Jhon | 2026-11-25 21:27 WITA | UBAH: satuan kelas sama dengan jumlah hari perawatan, bukan jumlah pasien.
            $row[self::KELAS_MAP[$kelas]] += (float) $item->lamadirawat;
        } else {
            $row['rinciankelaskhusus'] += (float) $item->lamadirawat;
            if ($kelas < 1) $row['_kelas_belum_terpetakan'] = ($row['_kelas_belum_terpetakan'] ?? 0) + 1;
        }
    }

    // Jhon | 2026-11-25 21:16 WITA | TAMBAH: getBedCountForRooms sesuai controller sumber v1.
    private function getBedCountForRooms($roomIds)
    {
        return DB::connection('pgsql')->table('tempattidur_m as tm')
            ->join('kamar_m as km', 'km.id', '=', 'tm.objectkamarfk')
            ->join('ruangan_m as rm', 'rm.id', '=', 'km.objectruanganfk')
            // ->whereIn('rm.id', $roomIds)
            ->where('rm.id', $roomIds)
            ->where('tm.statusenabled', true)
            ->count();
    }
    // Jhon | 2026-11-26 22:05 WITA | TAMBAH: jangan clamp saldo negatif karena itu menunjukkan ketidaksesuaian komponen sensus.
    private function calculateClosingBalance(array $row): int
    {
        return (int) $row['pasienawalbulan'] + (int) $row['pasienmasuk'] + (int) $row['pasienpindahan']
            - (int) $row['pasiendipindahkan'] - (int) $row['pasienkeluarhidup']
            - (int) $row['matikurang48jaml'] - (int) $row['matilebih48jaml']
            - (int) $row['matikurang48jamp'] - (int) $row['matilebih48jamp'];
    }

    // Jhon | 2026-11-26 13:02 WITA | TAMBAH: pencocokan token penuh mencegah ICU cocok dengan ICCU/PICU/NICU atau RICU.
    private function resolveServiceFromRoomName(string $room, array $labels): ?string
    {
        $matched = [];
        foreach ($labels as $label) {
            $aliases = preg_split('~\s*/\s*~', strtoupper(trim($label)));
            foreach ($aliases as $alias) {
                if ($alias !== '' && preg_match('~(?<![A-Z0-9])'.preg_quote($alias, '~').'(?![A-Z0-9])~i', $room)) {
                    $matched[] = $label;
                    break;
                }
            }
        }
        return count($matched) === 1 ? $matched[0] : null;
    }
}
