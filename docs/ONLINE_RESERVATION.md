<!-- Jhon | 2026-11-18 15:39 WITA | TAMBAH: Pemetaan sumber dan kontrak reservasi online yang diadaptasi. -->
# Migrasi alur e-reservasi ke API-SIMRS

## Sumber yang dibaca, tidak diubah

- simrsbm-tm/e-reservasi/src/app/page/modules/reservasi/reservasi-online/reservasi-online.component.ts dan .html.
- backend/routes/web.php untuk mapping endpoint.
- Reservasi/ReservasiMobileCtrl: getComboReservasi, getSlottingByRuanganDokter.
- Registrasi/RegistrasiRuanganCtrl: listDokterPagingWeb.
- EMR/ProfilePasienCtrl: saveRiwayatKontrolWeb.
- Traits/Valet: sequence_counters dan seqnumber_t untuk nosuratkontrol.

## Mapping endpoint baru (semua api.signature)

| Kebutuhan sumber | Endpoint baru |
| --- | --- |
| Master get-list-data/find-ruangan-rajal | GET /api/service/reservasi-online/master |
| dokter-paging-web | GET /api/service/reservasi-online/dokter?poli_id=... |
| get-slotting-rev | GET /api/service/reservasi-online/slot?poli_id=...&dokter_id=...&tanggal=YYYY-MM-DD |
| create-riwayat-kontrol-web | POST /api/service/reservasi-online |
| Lookup pasien lama | Endpoint pasien-lama yang sudah ada; identitas dicocokkan oleh NADI |
| Bukti/history | Bukti dikembalikan saat simpan; daftar Cek Reservasi NADI tetap memakai endpoint yang sudah ada |

Respons berhasil: `{"data": ...}`. Validasi: HTTP 422 JSON, errors.reservasi berisi alasan bisnis bila ada. Konfigurasi/schema/koneksi gagal: 503 tanpa detail SQL atau data pasien. Tidak perlu memasang kredensial backend e-reservasi atau membuka endpoint backend lama.

POST: patient_id dan nocm adalah identitas akun NADI tervalidasi (bukan identitas bebas dari browser), request_id UUID stabil, jenis_pasien `lama|baru`, poli_id, dokter_id, tanggal, jam HH:mm, penjamin_id, no_hp, asuransi_id bila kelompok 3, catatan opsional. Pasien baru menambah nama, nik (16 digit), tanggal_lahir, tempat_lahir, alamat, jenis_kelamin_id, agama_id, kebangsaan_id. Pasien lama selalu diambil dari pasangan id+nocm pada profil 1.

Signature: METHOD + newline + REQUEST_URI termasuk query/nonce + newline + timestamp + newline + SHA256(raw body). GET memakai body kosong; POST menandatangani byte JSON persis yang dikirim. Jangan menyalin token/secret ke frontend atau grup.

## Aturan yang dipertahankan dan diperbaiki

- PostgreSQL, kdprofile 1, poli departemen 18, pengecualian poli dan VIP mengikuti sumber.
- Master penjamin mengecualikan ID 2 sesuai frontend e-reservasi aktif. Ini **bukan** migrasi bridging BPJS/SEP; peserta dan rujukan tidak dibuat/dianggap tervalidasi secara palsu.
- Pasien baru membuat APR bertipe BARU, bukan langsung membuat pasien_m atau No. RM. Pasien lama membuat APR dan riwayatkontrol_t dalam transaksi yang sama.
- Setting isRentangReservasi/maxJamReservasi, hari Minggu/libur, jadwal dokter, slot terpakai ruangan, dan larangan poli/tanggal ganda diperiksa kembali saat menyimpan. Tanggal jadwal spesifik diprioritaskan sesuai tanggalnya, hari berulang dipakai jika tanggal kosong. Durasi slot tidak boleh nol.
- Format kode booking W + 7 karakter, antrean poli dan kategori RB/RA/RL mengikuti sumber; QR lokal hanya memuat kode booking.
- Jam kunjungan disimpan sebagai jam terpilih, bukan jam komputer saat tombol simpan ditekan seperti sumber lama.
- Semua nilai query terikat; pesan error SQL tidak diteruskan ke browser.
- request_id diturunkan menjadi norec UUID deterministik terikat akun. Retry permintaan sama mengembalikan bukti lama tanpa duplikasi. Penggunaan ID sama untuk layanan/jadwal/penjamin berbeda ditolak.
- PostgreSQL advisory lock menserialkan request endpoint baru pada pasien/request dan poli/tanggal. Lock ini tidak dengan sendirinya mengatur penulis eksternal yang tidak memakai protokol lock ini; uji konkurensi lintas kanal SIMRS/JKN tetap diperlukan.
- Nomor kontrol memakai sequence_counters/seqnumber_t yang sama, tidak membuat mekanisme nomor terpisah.

## Preflight dan UAT host (BELUM dijalankan oleh agent)

```sh
php tools/check-online-reservasi.php
php artisan test --filter=OnlineReservationValidationTest
```

Preflight hanya metadata schema, tanpa membaca baris pasien. Periksa setting dokter `kdPsikologDokter` / `idJenisPegawaiDokter`, isRentangReservasi, maxJamReservasi dan idRuanganKanker pada database target. Tidak ada migrasi tabel baru atau perubahan backend sumber. Jangan menganggap parser PHP sebagai pemeriksaan schema.

Uji staging dengan data dummy: master, dokter per poli, hari libur, tanggal di luar rentang/cutoff, jadwal tanggal spesifik dan berulang, slot kosong/penuh, konfirmasi, retry request sama (termasuk timeout setelah commit), duplikasi poli/tanggal, ID sama dengan rincian berubah, NIK sudah terdaftar, serta dua pengguna berebut slot. Pastikan rollback APR bila penulisan riwayatkontrol/sequence gagal. Cek antrean/hasil pada SIMRS dan pembacaan daftar NADI, QR dan cetak bukti di browser.

Halaman NADI tetap membutuhkan login pasien. Pasien baru dapat dimasukkan melalui akun yang sedang login; APR baru belum memiliki nocmfk, sehingga bukti segera disajikan setelah simpan tetapi belum tentu muncul pada daftar berbasis RM akun lama sampai dipadankan petugas. Kanal pendaftaran umum tanpa login, pembatalan, kartu pasien elektronik, e-billing dan bridging BPJS/SEP bukan bagian endpoint simpan ini; fitur lama tidak dihapus atau disalin tanpa integrasi yang sesuai.

Status verifikasi agent: pemeriksaan sintaks/parser dan simulasi JavaScript saja; PHP/database, kirim reservasi, konkurensi lintas kanal dan UAT browser belum dilakukan. Tidak ada deployment atau penulisan pasien nyata.

<!-- Jhon | 2026-11-25 08:28 WITA | UBAH: Kontrak dokter berdasarkan tanggal. -->
## Lookup dokter tanggal pelayanan
GET /api/service/reservasi-online/dokter sekarang mewajibkan poli_id dan tanggal YYYY-MM-DD (signature existing tetap). Tanpa tanggal422. Dokter aktif dan mapping poli/profil difilter lagi dengan jadwaldokter_m aktif: tanggal khusus exact atau hari mingguan sesuai, quota>0, jamakhir>jammulai. dateAllowed existing membatasi rentang/libur/cutoff. Slot memakai predikat jadwal sama; dokter tanggal lain ditolak. Jadwal ada tidak menjamin slot tersisa. Tidak mengubah schema maupun aturan simpan dan penjamin. Dokumen tes: OnlineDoctorDateTest, SQLite dummy, bukan query pasien produksi.
