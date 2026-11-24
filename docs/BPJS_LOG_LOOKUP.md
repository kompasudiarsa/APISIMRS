<!-- Jhon | 2026-11-24 14:00 WITA | TAMBAH: kontrak lookup log dan contoh Postman tanpa kredensial nyata. -->
# Lookup log BPJS

Dua endpoint POST baca-saja di grup api.signature, throttle 30/menit. Tidak memanggil BPJS, mengulang antrean, membuat surat atau memperbarui log. Koneksi pgsql mengikuti service API-SIMRS. Ini akses diagnostik server-to-server memakai kredensial service existing, bukan endpoint publik/pasien. Tabel log tidak diasumsikan memiliki kdprofile: tidak mengklaim isolasi tenant yang belum terbukti pada schema. Gunakan hanya service RS berotorisasi.

## Postman
POST {{base_url}}/api/service/bpjs-log/antrean?nonce={{nonce}}
Body raw JSON: {"kodebooking":"dummy123","limit":20}

POST {{base_url}}/api/service/bpjs-log/surat-kontrol?nonce={{nonce}}
Body raw JSON: {"noSEP":"DUMMYSEP001","limit":20}

Headers: Accept: application/json, Content-Type: application/json, X-Token: {{api_token}}, X-Timestamp: {{generated_timestamp}}, X-Signature: {{generated_signature}}. Gunakan pembuat signature API-SIMRS yang sudah ada dengan METHOD POST dan SHA256 body raw persis yang dikirim (bukan body kosong). Canonical: POST + newline + URI lengkap termasuk query nonce + newline + timestamp + newline + SHA256 body. HMAC-SHA256 memakai secret service. Ganti nonce setiap request dan buat ulang signature setelah mengubah body/URI. Jangan membagikan token, secret atau log pasien ke grup.

## Predikat
Antrean: url persis https://new-apijkn.bpjs-kesehatan.go.id/antreanrs/antrean/add, method POST, pasangan JSON kodebooking cocok penuh tanpa membedakan kapital.
Surat kontrol: url persis https://new-apijkn.bpjs-kesehatan.go.id/vclaim-rest//RencanaKontrol/insert (dua slash dipertahankan sesuai query), method POST, pasangan JSON noSEP cocok penuh, response mengandung pasangan JSON response:null. SQL NULL atau string "null" bukan JSON null dan tidak disamakan.

Regex PostgreSQL toleran whitespace sekitar titik dua dan nilai dibinding, tidak memakai wildcard input. Data tidak dicast jsonb karena log teks malformed dapat membuat seluruh query gagal. Ini pencarian fragmen pasangan JSON sebagaimana query asal, bukan verifikasi struktur JSON top-level. URL satu slash, host lain, metode selain POST tidak termasuk.

## Respons
200: data array berisi hanya url, method, data, response; meta count, limit, truncated. Default20/maks100; limit+1 mendeteksi hasil terpotong. Tidak ada pagination/urutan terbaru yang dijanjikan karena kolom timestamp/PK belum diverifikasi. Bukan SELECT *; kolom header/token lain tidak disertakan. Isi data/response tetap data diagnostik sensitif.
Kosong: data:[] (pencarian berhasil). 422: parameter salah/hilang, identitas harus string ASCII alnum/dot/hyphen maksimal64; 401: autentikasi; 503: query gagal, bukan hasil kosong. Respons controller private/no-store. Tidak mencatat SQL, payload atau exception mentah.

## Verifikasi
Tes fixture/mock menguji SQL PostgreSQL, bindings, URL/method tetap, filter response null, limit/truncated, signature, input salah, hasil kosong dan error aman. Tidak membaca log pasien nyata. Query PostgreSQL terhadap schema nyata belum dijalankan. Tidak ada migrasi/deployment.
