<?php
// Jhon | 2026-11-19 18:51 WITA | TAMBAH: daftar booking baca-saja untuk ringkasan petugas; bukan pemeriksaan kelayakan kunjungan.
namespace App\Http\Controllers\Api\Service;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Log,Validator};
class KioskPatientSummaryController extends Controller
{
 public function __invoke(Request $request)
 {
  $v=Validator::make($request->all(),[
   'patient_id'=>['required','string','max:64','regex:/\A[A-Za-z0-9.-]+\z/D','not_in:0'],
   'identifier'=>['required','string','min:3','max:64','regex:/\A[A-Za-z0-9.-]+\z/D'],
   'include_details'=>['sometimes','boolean'],'include_visit_fields'=>['sometimes','boolean'],
   'nonce'=>['required','string','regex:/\A[a-f0-9]{32}\z/D']]);
  if(strlen($request->getContent())>4096||$v->fails()||$request->query->count()||array_diff(array_keys($request->all()),['patient_id','identifier','nonce','include_details','include_visit_fields']))
   return response()->json(['message'=>'Input tidak valid.'],422)->header('Cache-Control','private, no-store');
  try{
   $db=DB::connection('pgsql');$id=$request->input('patient_id');
   if(!$db->table('pasien_m')->where('id',$id)->where('kdprofile',1)->where('statusenabled',true)->exists())
    return response()->json(['message'=>'Pasien tidak tersedia.'],404)->header('Cache-Control','private, no-store');
   $today=\Carbon\Carbon::now('Asia/Makassar')->toDateString();$identifier=$request->input('identifier');
   // Jhon | 2026-11-20 12:13 WITA | UBAH: label reservasi baca-saja tanpa identitas lengkap untuk layar pasien.
   $query=$db->table('antrianpasienregistrasi_t as a')->where('a.kdprofile',1)->where('a.nocmfk',$id)
    ->whereNotNull('noreservasi')->whereNotIn('noreservasi',['','-'])
    ->where(function($q)use($today,$identifier){$q->whereDate('tanggalreservasi','>=',$today)->orWhere('noreservasi',$identifier);})
    ;
   $cols=['a.noreservasi','a.tanggalreservasi','a.statusenabled'];
   if($request->input('include_visit_fields',false)){
    foreach(['r'=>['ruangan_m','objectruanganfk'],'d'=>['pegawai_m','objectpegawaifk'],'p'=>['kelompokpasien_m','objectkelompokpasienfk']]as$alias=>$join)$query->leftJoin($join[0].' as '.$alias,function($j)use($alias,$join){$j->on($alias.'.id','=','a.'.$join[1])->on($alias.'.kdprofile','=','a.kdprofile');});
    $cols=array_merge($cols,['r.namaruangan as poli','d.namalengkap as doctor','p.kelompokpasien as payer','a.jam as time']);
   }
   $rows=$query->select($cols)->orderBy('tanggalreservasi')->limit(51)->get();
   if(count($rows)>50)throw new \RuntimeException('bounded_result');
   $bookings=[];
   foreach($rows as$row){
    $date=substr((string)$row->tanggalreservasi,0,10);
    if(!preg_match('/\A\d{4}-\d{2}-\d{2}\z/D',$date)||!is_string($row->noreservasi)||strlen($row->noreservasi)>64)throw new \RuntimeException('invalid_row');
    $active=in_array($row->statusenabled,[true,1,'1','t','true'],true);
    $b=['code'=>$row->noreservasi,'service_date'=>$date,'state'=>$active?'ACTIVE':'CANCELLED'];
    if($request->input('include_visit_fields',false))foreach(['poli','doctor','payer','time']as$key)$b[$key]=$row->$key===null?null:(string)$row->$key;
    $bookings[]=$b;
   }
   // Jhon | 2026-11-19 19:10 WITA | TAMBAH: detail minimum diminta backend hanya sesudah grant pasien; tidak masuk log/URL.
   $details=null;
   if($request->input('include_details',false)){
    $p=$db->table('pasien_m')->where('id',$id)->where('kdprofile',1)->where('statusenabled',true)->select(['namapasien','nocm','alamatlengkap','tempatlahir','tgllahir'])->first();
    if(!$p)throw new \RuntimeException('patient_changed');
    $details=['name'=>$p->namapasien,'record_number'=>$p->nocm,'address'=>$p->alamatlengkap,'birth_place'=>$p->tempatlahir,'birth_date'=>$p->tgllahir===null?null:substr((string)$p->tgllahir,0,10)];
   }
   return response()->json(['data'=>['patient_id'=>$id,'nonce'=>$request->input('nonce'),'bookings'=>$bookings,'from_date'=>$today,'patient'=>$details]])->header('Cache-Control','private, no-store');
  }catch(\Throwable $e){
   Log::warning('kiosk_staff_summary_unavailable',['category'=>'upstream_read']);
   return response()->json(['message'=>'Booking belum dapat diperiksa.'],503)->header('Cache-Control','private, no-store');
  }
 }
}
