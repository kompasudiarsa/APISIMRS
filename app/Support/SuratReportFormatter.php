<?php
namespace App\Support;
// Jhon | 2026-11-13 19:42 WITA | TAMBAH: formatter tanggal lokal, adaptasi Valet::fmtTgl backend tanpa dependensi transaksi/terjemahan jaringan.
class SuratReportFormatter
{
    public static function date($value): string
    {
        if (!is_scalar($value) || trim((string)$value)==='') return '-';
        try { return \Carbon\Carbon::parse($value)->timezone('Asia/Makassar')->locale('id')->translatedFormat('d F Y'); }
        catch (\Throwable $exception) { return '-'; }
    }
}
