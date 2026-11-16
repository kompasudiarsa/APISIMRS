<?php
// Jhon | 2026-11-13 12:06 WITA | TAMBAH: pemeriksaan CLI saja; tidak mencetak konfigurasi, exception, atau data pasien.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function failCheck($message) { fwrite(STDERR, $message . PHP_EOL); exit(1); }
echo 'PHP ' . PHP_VERSION . PHP_EOL;
if (!extension_loaded('mongodb')) {
    failCheck('GAGAL: ekstensi PHP mongodb belum aktif pada PHP CLI ini. Aktifkan pada PHP host yang menjalankan API; jangan gunakan --ignore-platform-reqs.');
}
echo 'OK: ekstensi mongodb ' . phpversion('mongodb') . PHP_EOL;
// Jhon | 2026-11-13 12:06 WITA | TAMBAH: preflight tidak memuat aplikasi atau menghubungi database.
if (in_array('--preflight', $argv, true)) { exit(0); }
$root = dirname(__DIR__);
// Jhon | 2026-11-13 12:06 WITA | TAMBAH: cadangan Composer sebelum pemasangan; direktori bukan public.
if (in_array('--backup', $argv, true)) {
    $backup = $root . '/storage/app/jhon-backups/mongodb-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4));
    if (!mkdir($backup, 0700, true)) { failCheck('GAGAL: direktori cadangan tidak dapat dibuat.'); }
    foreach (['composer.json', 'composer.lock'] as $file) {
        if (is_file($root . '/' . $file) && !copy($root . '/' . $file, $backup . '/' . $file)) {
            failCheck('GAGAL: pencadangan Composer tidak lengkap.');
        }
    }
    echo 'OK: composer.json dan composer.lock dicadangkan di storage/app/jhon-backups.' . PHP_EOL;
    exit(0);
}
try {
    require $root . '/vendor/autoload.php';
    if (!class_exists('Jenssegers\Mongodb\MongodbServiceProvider')) {
        failCheck('GAGAL: paket jenssegers/mongodb belum terpasang. Jalankan pemasang lokal tools/install-mongodb.cmd.');
    }
    $app = require $root . '/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    // Jhon | 2026-11-13 12:06 WITA | TAMBAH: timeout hanya dalam proses diagnosis ini, tidak mengubah konfigurasi layanan.
    $config = $app->make('config');
    if ($config->get('database.connections.mongodb.driver') !== 'mongodb') {
        failCheck('GAGAL: koneksi bernama mongodb belum memakai driver mongodb.');
    }
    $config->set('database.connections.mongodb.options.serverSelectionTimeoutMS', 5000);
    $config->set('database.connections.mongodb.options.connectTimeoutMS', 5000);
    $connection = $app->make('db')->connection('mongodb');
    $result = $connection->getMongoDB()->command(['ping' => 1])->toArray();
    if (empty($result) || empty($result[0]->ok)) { failCheck('GAGAL: ping MongoDB tidak berhasil.'); }
    echo 'OK: driver Laravel dikenali dan ping MongoDB berhasil. Tidak membaca dokumen pasien.' . PHP_EOL;
} catch (Throwable $exception) {
    failCheck('GAGAL: koneksi MongoDB belum berhasil. Periksa akses jaringan, konfigurasi koneksi dan hak akun pada host; detail rahasia tidak ditampilkan.');
}
