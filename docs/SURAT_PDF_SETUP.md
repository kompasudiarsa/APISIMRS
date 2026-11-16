<!-- Jhon | 2026-11-13 20:13 WITA | TAMBAH: prasyarat PDF server dan QR verifikasi mengikuti cetakEMR. -->
# PDF surat langsung

GET endpoint surat dengan detail=1,format=pdf,collection,emrpasienfk,nocmfk dan signature. API memfilter pemilik/profil/master sebelum render. Nadi link memakai format=pdf, meneruskan PDF binary inline ke tab baru, tanpa window.print. HTML preview tetap dapat dibuka tanpa parameter format.

Dependensi API belum terpasang: Dompdf ^3.0 dan Endroid QR ^4.6 (API v4 sesuai backend; Composer memilih rilis yang cocok dengan PHP host), ekstensi GD/DOM/mbstring. Jalankan tools/install-surat-pdf.cmd pada terminal host lokal; Composer memeriksa platform dan keamanan, tidak menggunakan ignore-platform-reqs. Jika PHP/ekstensi tidak cocok, jangan paksa versi atau abaikan pemeriksaan platform. Linux setara: composer require "dompdf/dompdf:3.*" "endroid/qr-code:4.*" --no-scripts. Backup Composer sebelum menjalankan. Tidak ada pemasangan/restart dari container.

Konfigurasi aplikasi SIMRS_ERM_VERIFICATION_URL wajib URL lengkap route dokumen.signature backend, tanpa key/token/query. Tidak mengubah .env otomatis. QR mengikuti base64(collection+semicolon+Mongo _id), query key dan a=0, PNG125 margin5 correctionLow. Payload ini bukan enkripsi/signature kriptografis baru, melainkan tautan verifikasi yang dibaca backend. Tidak menyalin APP_KEY antarproyek. Alamat salah/missing, _id kosong, atau dependency belum siap menghasilkan JSON503/422, bukan PDF kosong. Verifikasi route backend menerima QR, dan otorisasi informasi verifikasi di sistem asal.

Adapter 15 view tersimpan di API/resources/views/Report untuk PDF; template disesuaikan ke aset lokal public/img tanpa akses jaringan. Report asli Nadi tidak ditimpa. PHP renderer belum dieksekusi dari container; lakukan UAT pasien sah melihat application/pdf inline dengan %PDF-, pasien lain ditolak, key QR cocok dengan _id dokumen, kop/font/halaman benar dan tidak ada navigasi browser di PDF.

<!-- Jhon | 2026-11-13 21:50 WITA | UBAH: gunakan constraint wildcard 3.* dan 4.* pada Windows agar tidak ada pemrosesan caret ganda. -->
Jika Composer melaporkan ^^3.0, gunakan skrip terbaru; tidak perlu meng-escape caret. Paket dipilih dalam major 3 dan 4 dengan pemeriksaan platform aktif.
