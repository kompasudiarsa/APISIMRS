<?php
// Jhon | 2026-11-18 15:36 WITA | TAMBAH: Preflight read-only, tidak membaca baris pasien atau mencetak kredensial.
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$required=[
 'pasien_m'=>['id','nocm','kdprofile','statusenabled','tgllahir','noidentitas','objectpendidikanfk'],
 'antrianpasienregistrasi_t'=>['norec','noreservasi','nocmfk','tanggalreservasi','jam','objectruanganfk','objectpegawaifk','noantrianpoli','objectpenjaminfk','caradaftar','tipepasien','type','jenis','created_at','updated_at'],
 'riwayatkontrol_t'=>['norec','nobukti','nocmfk','antrianpasienregistrasifk','jam','tglkontrol','created_at','updated_at'],
 'jadwaldokter_m'=>['id','tanggal','hari','jammulai','jamakhir','quota','objectruanganfk','objectpegawaifk'],
 'settingdatafixed_m'=>['kdprofile','namafield','nilaifield','statusenabled'],
 'sequence_counters'=>['key','last_value'],'seqnumber_t'=>['norec','seqname','seqnumber','tgljamseq','kdprofile','created_at','updated_at'],
 'slottinglibur_m'=>['tgllibur','kdprofile','statusenabled'],
 'mappegawaitoruangan_m'=>['objectpegawaifk','objectruanganfk','kdprofile','statusenabled'],
 'mapkelompokpasientopenjamin_m'=>['objectkelompokpasienfk','kdpenjaminpasien','statusenabled'],
 'ruangan_m'=>['id','namaruangan','objectdepartemenfk'],'pegawai_m'=>['id','namalengkap','objectjenispegawaifk'],
 'kelompokpasien_m'=>['id','kelompokpasien'],'agama_m'=>['id','agama'],'kebangsaan_m'=>['id','name'],'jeniskelamin_m'=>['id','jeniskelamin'],'rekanan_m'=>['id','namarekanan']
];$failed=false;
try{foreach($required as $table=>$columns){$actual=Illuminate\Support\Facades\Schema::connection('pgsql')->getColumnListing($table);$missing=array_diff($columns,$actual);echo ($missing?'FAIL ':'OK ').$table.($missing?' missing: '.implode(', ',$missing):'').PHP_EOL;$failed=$failed||count($missing)>0;}}catch(Throwable $e){echo 'FAIL: koneksi atau akses metadata PostgreSQL belum tersedia.'.PHP_EOL;$failed=true;}
exit($failed?1:0);
