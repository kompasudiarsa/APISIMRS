# Kiosk reservation check — kontrak read-only v1

Jhon | 2026-11-18 21:30 WITA | TAMBAH.

GET `/api/service/kiosk/reservation-check` memakai middleware `api.signature` dan throttle 60/min. Parameter wajib: `patient_id` internal string (bukan No. RM), `service_date` YYYY-MM-DD; nonce mengikuti signature yang sudah ada. Profil dibatasi 1 seperti endpoint service yang ada; verifikasi tenant sebelum aktivasi.

Respons 200 `{data: {...}}` dengan schema_version 1, patient_id, service_date, patient_exists boolean, complete true, reservations, checked_in_visits, eligibility_checked boolean, offerings. Pasien tidak ada setelah query sukses menghasilkan patient_exists false, bukan HTTP error. Gangguan menghasilkan 503 JSON generik; validasi 422. Tidak ada data identitas lengkap pada respons.

Reservasi dicari persis berdasarkan internal pasien, tanggal, profil; termasuk batal dan check-in. Tidak memfilter statusenabled/noantrian sebelum normalisasi. ACTIVE, CANCELLED, CHECKED_IN, CONFLICT dinormalisasi; konflik tidak dapat diproses. Kelayakan jadwal/kuota hanya dihitung ketika tidak ada reservasi maupun check-in. Tidak ada penulisan registrasi/antrean. Penjamin master aktif belum membuktikan hak peserta BPJS.

Implementasi `KioskReservationFactsService`; pengujian `KioskReservationFactsTest` memakai SQLite in-memory, bukan database pasien asli. Panduan integrasi lengkap berada di Kios-K/docs/fase-4-reservation-checking.md. Tidak ada migrasi yang dibutuhkan dan tidak ada deployment dijalankan.
