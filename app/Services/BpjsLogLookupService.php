<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
// Jhon | 2026-11-24 13:55 WITA | TAMBAH: Dua lookup log baca-saja; URL fixed dan nilai dibinding, tidak menerima SQL/filter bebas.
class BpjsLogLookupService
{
    const URLS = [
        'antrean' => 'https://new-apijkn.bpjs-kesehatan.go.id/antreanrs/antrean/add',
        'surat-kontrol' => 'https://new-apijkn.bpjs-kesehatan.go.id/vclaim-rest//RencanaKontrol/insert',
    ];
    public function search(string $kind, string $value, int $limit): array
    {
        if (!isset(self::URLS[$kind])) throw new \InvalidArgumentException('Invalid lookup');
        $key = $kind === 'antrean' ? 'kodebooking' : 'noSEP';
        // Jhon | 2026-11-24 13:55 WITA | TAMBAH: Regex pasangan JSON exact, toleran spasi/case seperti ILIKE; tidak cast JSON karena log lama bisa malformed.
        $pattern = '"'.$key.'"[[:space:]]*:[[:space:]]*"'.preg_quote($value, '~').'"';
        $q = DB::connection('pgsql')->table('log_bpjs_t')
            // Jhon | 2026-11-27 07:31 WITA | UBAH: user adalah nama pegawai yang disimpan penulis log SIMRS, bukan akun monitoring.
            ->select(['url', 'method', 'data', 'response', 'user'])
            ->where('url', self::URLS[$kind])->where('method', 'POST')
            ->whereRaw('CAST("data" AS TEXT) ~* ?', [$pattern]);
        if ($kind === 'surat-kontrol') {
            $q->whereRaw('CAST("response" AS TEXT) ~* ?', ['"response"[[:space:]]*:[[:space:]]*null([[:space:]]*[,}])']);
        }
        // Jhon | 2026-11-24 13:55 WITA | TAMBAH: Batas hasil tanpa SELECT * (tidak mengembalikan header/kredensial kolom lain).
        $rows = $q->limit($limit + 1)->get();
        return ['data' => $rows->take($limit)->values()->all(), 'meta' => [
            'count' => min($rows->count(), $limit), 'limit' => $limit,
            'truncated' => $rows->count() > $limit,
        ]];
    }
}
