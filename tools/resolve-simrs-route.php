<?php
// Jhon | 2026-11-14 16:23 WITA | TAMBAH: resolver subprocess CLI; output hanya URL tervalidasi/kode gagal, bukan kredensial atau exception mentah.
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
error_reporting(0);ini_set('display_errors','0');
ob_start();$completed=false;
function finishRoute(array $result, int $status=0): void {
    global $completed;$completed=true;
    while (ob_get_level()>0) ob_end_clean();
    echo json_encode($result,JSON_UNESCAPED_SLASHES);exit($status);
}
register_shutdown_function(function () use (&$completed) {
    if (!$completed) {
        while (ob_get_level()>0) ob_end_clean();
        echo json_encode(['code'=>'BACKEND_FATAL']);
    }
});
try {
    $root=$argv[1]??'';
    if (!is_file($root.'/vendor/autoload.php') || !is_file($root.'/bootstrap/app.php')) finishRoute(['code'=>'BACKEND_PATH'],1);
    require $root.'/vendor/autoload.php';
    $app=require $root.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $base=(string)$app->make('config')->get('app.url');
    if ($base==='' || rtrim($base,'/')==='http://localhost') finishRoute(['code'=>'APP_URL_UNSET'],2);
    if (!$app->make('router')->has('dokumen.signature')) finishRoute(['code'=>'ROUTE_MISSING'],3);
    $app->make('url')->forceRootUrl($base);
    $url=route('dokumen.signature');
    finishRoute(['verification_url'=>$url]);
} catch (Throwable $exception) { finishRoute(['code'=>'BOOTSTRAP_FAILED'],4); }
