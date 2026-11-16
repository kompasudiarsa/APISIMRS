<?php
// Jhon | 2026-11-13 21:55 WITA | TAMBAH: diagnosis prasyarat PDF CLI, tanpa membaca dokumen pasien atau menampilkan URL/kredensial.
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
echo 'PHP '.PHP_VERSION.PHP_EOL;
$ok=true;
foreach (['gd','dom','mbstring','mongodb'] as $ext) {
    $loaded=extension_loaded($ext);echo ($loaded?'OK':'GAGAL').' ekstensi '.$ext.PHP_EOL;$ok=$ok&&$loaded;
}
try {
    $root=dirname(__DIR__);require $root.'/vendor/autoload.php';
    foreach (['Dompdf\\Dompdf','Endroid\\QrCode\\QrCode'] as $class) {
        $found=class_exists($class);echo ($found?'OK':'GAGAL').' library '.$class.PHP_EOL;$ok=$ok&&$found;
    }
    $app=require $root.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $url=(string)$app->make('config')->get('surat_keterangan.verification_url','');
    // Jhon | 2026-11-13 22:00 WITA | TAMBAH: cek juga URL hasil sinkronisasi named-route backend.
    if ($url==='' && is_file($root.'/storage/app/simrs-verification-url.json')) {
        $saved=json_decode(file_get_contents($root.'/storage/app/simrs-verification-url.json'),true);
        $url=is_array($saved)&&is_string($saved['verification_url']??null)?$saved['verification_url']:'';
    }
    $valid=filter_var($url,FILTER_VALIDATE_URL)&&in_array(parse_url($url,PHP_URL_SCHEME),['http','https'],true)
        && !parse_url($url,PHP_URL_USER)&&!parse_url($url,PHP_URL_PASS)&&!parse_url($url,PHP_URL_QUERY)&&!parse_url($url,PHP_URL_FRAGMENT);
    echo ($valid?'OK':'GAGAL').' alamat verifikasi QR (nilai tidak ditampilkan)'.PHP_EOL;$ok=$ok&&$valid;
} catch (Throwable $exception) { echo 'GAGAL bootstrap aplikasi; detail internal tidak ditampilkan.'.PHP_EOL;$ok=false; }
exit($ok?0:1);
