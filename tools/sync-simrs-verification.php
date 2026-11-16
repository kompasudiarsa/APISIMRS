<?php
// Jhon | 2026-11-13 21:59 WITA | TAMBAH: ambil URL named-route dari runtime Laravel backend dalam proses terpisah, bukan dari file .env mentah.
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
$root=dirname(__DIR__);
$backend=dirname($root).'/simrsbm-tm/backend';
if (!is_file($backend.'/artisan') || !is_file($backend.'/vendor/autoload.php')) {
    fwrite(STDERR,'GAGAL: folder backend SIMRS/vendor tidak ditemukan pada folder sejajar API-SIMRS.'.PHP_EOL);exit(1);
}
if (!function_exists('proc_open')) { fwrite(STDERR,'GAGAL: proc_open tidak tersedia pada PHP CLI host.'.PHP_EOL);exit(1); }
// Jhon | 2026-11-14 16:23 WITA | UBAH: file PHP terpisah menggantikan kode inline -r, agar quoting Windows tidak merusak bootstrap.
$process=proc_open([PHP_BINARY,__DIR__.'/resolve-simrs-route.php',$backend],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$backend,null,['bypass_shell'=>true]);
if (!is_resource($process)) { fwrite(STDERR,'GAGAL: proses backend tidak dapat dimulai.'.PHP_EOL);exit(1); }
fclose($pipes[0]);$output=stream_get_contents($pipes[1]);fclose($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[2]);$exit=proc_close($process);
$data=json_decode($output,true);$url=is_array($data)?($data['verification_url']??''):'';
if ($exit!==0 || !is_array($data) || isset($data['code'])) {
    $messages=[
        'APP_URL_UNSET'=>'APP_URL backend kosong atau masih http://localhost. Tetapkan alamat backend lokal yang benar sebelum sinkronisasi.',
        'ROUTE_MISSING'=>'Named-route dokumen.signature belum termuat pada runtime backend.',
        'BOOTSTRAP_FAILED'=>'Bootstrap Laravel backend gagal pada PHP CLI ini. PHP API siap, tetapi backend belum berhasil dimuat.',
        'BACKEND_FATAL'=>'Runtime backend berhenti sebelum route selesai dimuat. Periksa kompatibilitas PHP/ekstensi backend.',
        'BACKEND_PATH'=>'Lokasi backend atau vendor tidak dapat dibaca.',
    ];
    $code=is_array($data)?($data['code']??'PROCESS_OUTPUT_INVALID'):'PROCESS_OUTPUT_INVALID';
    fwrite(STDERR,'GAGAL ['.$code.']: '.($messages[$code]??'Proses PHP backend belum menghasilkan JSON yang valid; tidak menampilkan output internal.').PHP_EOL);exit(1);
}
if ($exit!==0 || !is_string($url) || !filter_var($url,FILTER_VALIDATE_URL)
    || !in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),['http','https'],true)
    || parse_url($url,PHP_URL_USER) || parse_url($url,PHP_URL_PASS) || parse_url($url,PHP_URL_QUERY) || parse_url($url,PHP_URL_FRAGMENT)) {
    fwrite(STDERR,'GAGAL: named-route backend belum dapat diresolusi. Periksa runtime backend dan APP_URL; nilai rahasia tidak ditampilkan.'.PHP_EOL);exit(1);
}
// Jhon | 2026-11-13 21:59 WITA | TAMBAH: simpan URL saja di storage privat dan cadangkan hasil sinkronisasi sebelumnya.
$dir=$root.'/storage/app';if (!is_dir($dir)) mkdir($dir,0700,true);
$file=$dir.'/simrs-verification-url.json';
if (is_file($file) && !copy($file,$file.'.backup-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)))) {
    fwrite(STDERR,'GAGAL: pencadangan URL lama tidak berhasil.'.PHP_EOL);exit(1);
}
if (file_put_contents($file,json_encode(['verification_url'=>$url],JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX)===false) {
    fwrite(STDERR,'GAGAL: URL tidak dapat disimpan.'.PHP_EOL);exit(1);
}
echo 'OK: URL dokumen.signature dari runtime backend tersimpan untuk generator PDF API-SIMRS. Tidak menyalin kredensial atau APP_KEY.'.PHP_EOL;
