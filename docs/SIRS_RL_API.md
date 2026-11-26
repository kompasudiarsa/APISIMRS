# API SIRS RL

GET `/api/service/sirs/rl`: daftar kode laporan.
GET `/api/service/sirs/rl/{report}`: hasil laporan JSON.

Kode: `rl3-1` s.d. `rl3-19`, `rl3-11-detail`, `rl4-1` s.d. `rl4-3`, `rl5-1` s.d. `rl5-3` (26 laporan).
Wajib `bulan` (1–12), `tahun` (1900–2100). Opsional `tglAwal`, `tglAkhir` format YYYY-MM-DD dalam bulan yang sama. Default seluruh bulan. Detail RL3.11 wajib `idkonten` integer positif.

Contoh URI: `/api/service/sirs/rl/rl3-1?bulan=11&tahun=2026`.
Gunakan header signature API-SIMRS existing dan canonical URI termasuk query persis seperti dikirim; kredensial hanya server-side. Route tetap dalam middleware `api.signature`; hasil dibatasi throttle 30/menit.

Sumber kalkulasi: SIMRSBM-TM `backend/app/Http/Controllers/RekamMedis/LaporanRekamMedisNewCtrl.php` (39 method termasuk helper). Query dipindahkan ke koneksi `pgsql`, profil 1 sesuai endpoint SIMRS existing. `settingdatafixed_m` aktif profil 1 wajib tersedia. Bentuk hasil mempertahankan `metaData` dan `response` sumber; bukan ekspor Excel. Endpoint baca-saja, tidak mengirim ke SIRS, tidak memodifikasi mapping.

Batas: bukan seluruh 33 kandidat frontend (modul lama, mapping/edit, detail pasien serta ekspor Excel belum dipindahkan). Integrasi tampilan `/sirs-online` monitoring belum dilakukan dalam patch API ini.

Verifikasi: parser PHP tersedia; runtime PHP tidak tersedia di container pemeriksa. Tes `tests/Feature/SirsRlReportTest.php` belum dieksekusi. Jalankan `php artisan test --filter=SirsRlReportTest` di runtime proyek. UAT: signature hilang ditolak; periode invalid 422; kode tidak dikenal 404; periode sah menghasilkan angka/kolom identik sumber untuk tiap laporan; setting atau database gagal menghasilkan 503, bukan data nol palsu. Uji profil database dan perbedaan periode sebelum dipakai live.
Cadangan route sebelum edit: `/tmp/rl-api-backup/api.php` di lingkungan pemeriksa.
