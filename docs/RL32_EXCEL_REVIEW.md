# Pemeriksaan RL3.2 berdasarkan Excel pengguna

Acuan: RL 3.2 REKAPITULASI KEGIATAN PELAYANAN RAWAT INAP (12).xlsx, 25 November 2026. Catatan ditemukan di `xl/drawings/drawing1.xml`, bukan worksheet/comments. Angka 459 adalah nilai contoh workbook, bukan konstanta yang ditanam.

Catatan: nonspesifik boleh Umum; khusus sesuai SK harus terpisah; periksa angka kematian terkait anomali NDR/GDR; total rincian hari per kelas harus sama dengan hari perawatan.

Patch API khusus RL3.2:
- Kelompok khusus memakai mapping ruangan dari mapproduktolaporanrl_m → kelompoklaporan_m, jenis KdJenisLapRanap, bukan menaruh semua baris paksa Umum. Kategori nonspesifik tetap Umum. Mapping khusus ambigu ditolak; ruangan bernama khusus tanpa mapping menghasilkan peringatan kelengkapan. Daftar ICU/HCU/ICCU-ICVCU/RICU/NICU/PICU/Isolasi masih perlu disahkan terhadap SK, terutama batas arrow Isolasi pada workbook.
- Deduplikasi episode_id sebelum agregasi; join data pendukung dibuat left join agar data tanpa ruangan asal/pegawai tidak hilang diam-diam.
- Kelas menambah lamadirawat, bukan ++ pasien. Kelas kosong masuk rincian khusus dengan jumlah episode belum terpetakan ditampilkan, bukan disamarkan sebagai klasifikasi yang sudah sah.
- Kematian berdasarkan timestamp meninggal/master status, bukan ID 5/6 yang diasumsikan. Ambang 48 jam dihitung dari timestamp masuk sampai meninggal, tepat48 masuk >=48. Keluaran hanya dihitung apabila terjadi dalam rentang laporan dan dideduplikasi per registrasi per kelompok. Pasien pada tanggal1/akhir bulan tidak lagi langsung return sebelum klasifikasi kematian. Data tidak lengkap dihitung sebagai belum terklasifikasi, bukan otomatis <48 atau perempuan.
- Kolom jumlah sekarang jumlah bed aktif dari ruangan unik pada hasil, bukan jumlah baris kunjungan. Ini BUKAN snapshot alokasi SK awal tahun; UI menampilkan batas tersebut.

Sumber mapping frontend yang terverifikasi: `/module/laporan/rekam-medis/map-kelompok-laporan` → sysadmin/save-map-laporan-rl → MapKelompokLaporanCtrl::SaveMappingRl → mapproduktolaporanrl_m, objectkontenlaporanfk. Pilih jenis laporan rawat inap yang sesuai KdJenisLapRanap, kelompok/konten layanan, dan ruangan. Tidak ada data mapping atau transaksi pasien diubah oleh patch.

Batas penting: cohort dan total lamadirawat masih mengikuti source v1 (registrasi dalam rentang; durasi agregasi sumber bukan sensus midnight per episode). Belum menghitung ulang sensus pasien awal/akhir lintas periode, hari perawatan per perpindahan kelas, maupun indikator RL3.1 NDR/GDR. Kematian lintas kelompok perlu verifikasi distribusi episode. Patch tidak menjamin angka459 tetap sama atau angka database benar tanpa perbandingan data sumber dan kebijakan sensus yang disahkan. Snapshot SK dan mapping pasti masih perlu tersedia.

Pemeriksaan parser PHP lulus; fixture PHPUnit tersedia `tests/Feature/SirsRl32CalculationTest.php` (hari kelas, kelas kosong, 47:59:59 vs48jam, dedup kematian) namun belum dieksekusi karena runtime PHP tidak tersedia pada container. Jalankan di runtime API-SIMRS: `php artisan test --filter=SirsRl32CalculationTest`. UAT wajib membandingkan pasien keluar hidup/mati, duplikasi transfer, layanan khusus, dan sumkelas=total HP pada rentang sama. Backup service sebelum patch: `/tmp/rl32-excel-review/SirsRl32V1Service.php`.

## Keputusan pengguna 26 November 2026

Pasek menetapkan ruangan RAWAT INAP PICU/NICU ID320 dan alokasi bed-nya masuk NICU untuk RL3.2. Override diterapkan hanya pada pembaca laporan (sebelum grouping), bukan perubahan master mapping SIMRS, bukan klasifikasi umur, dan tidak berlaku bagi ruangan PICU lain. Setiap episode ruang320 dihitung sekali pada NICU; alokasi bed memakai room ID unik yang sama sehingga tidak masuk PICU sekaligus. Perlu UAT data aktual setelah patch.
