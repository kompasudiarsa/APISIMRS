<!-- Jhon | 2026-11-13 12:06 WITA | TAMBAH: pemulihan driver MongoDB untuk API-SIMRS Laravel 8. -->
# MongoDB lokal API-SIMRS

Folder proyek menggunakan Laravel 8.83.29. Dependency MongoDB belum tercantum pada composer.json/lock maupun vendor. Gunakan seri jenssegers/mongodb 3.8 (metadata upstream mendukung Illuminate ^8.0), bukan 3.9 (Illuminate ^9.0) atau rilis terbaru untuk Laravel baru.

## Terminal host yang menjalankan API
Jangan menjalankan di live. Gunakan PHP yang sama dengan proses `artisan serve` pada port 8000. PHP CLI dalam container Jhon bukan PHP host tersebut.

Windows: dari root API-SIMRS jalankan `tools\install-mongodb.cmd`.
Linux/macOS: dari root API-SIMRS jalankan berurutan:

```sh
php tools/check-mongodb.php --preflight
php tools/check-mongodb.php --backup
composer require 'jenssegers/mongodb:3.8.*' --no-scripts --no-interaction
php artisan package:discover --ansi
php tools/check-mongodb.php
```

Hentikan bila salah satu perintah gagal. Skrip Windows otomatis berhenti. Tidak menggunakan ignore-platform-reqs, update framework, perubahan php.ini, restart, atau vendor publish. Composer mencocokkan versi library dengan ekstensi PHP yang sebenarnya; perubahan composer.json/lock dicadangkan sebelum pemasangan. Jangan bagikan output kredensial/DSN/config mentah.

Jika ekstensi mongodb belum aktif: pengelola host harus memasang/mengaktifkan ekstensi yang cocok dengan versi PHP, arsitektur dan TS/NTS host. Ini tidak bisa diselesaikan dengan menyalin DLL Linux/Windows sembarangan. Catat versi PHP/OS terlebih dahulu. Perubahan php.ini/restart tetap perlu persetujuan pemilik; pemasang ini tidak melakukannya.

Check CLI hanya menampilkan versi PHP/ekstensi, keberadaan provider dan hasil ping; tidak membaca dokumen pasien. Tidak dapat diakses dari browser. Timeout diagnosis lima detik hanya berlaku dalam proses pemeriksaan. Keberhasilan ping belum membuktikan hak baca collection; lanjutkan UAT daftar pasien sah, pasien tanpa surat, dan isolasi dua pasien. PHP check serta pemasang belum dieksekusi dalam container karena PHP/Composer tidak tersedia dan tidak ada kontrol host.

Sumber kompatibilitas: https://github.com/mongodb/laravel-mongodb/blob/v3.8.6/composer.json
