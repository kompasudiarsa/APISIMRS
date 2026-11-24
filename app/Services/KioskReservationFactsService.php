<?php
namespace App\Services;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
// Jhon | 2026-11-18 20:53 WITA | TAMBAH: Query read-only per ID internal + tanggal; tidak memakai latest(), tidak memfilter pembatalan/check-in keluar hasil.
class KioskReservationFactsService {
 // Jhon | 2026-11-20 12:06 WITA | TAMBAH: penanda tahap tanpa identitas atau query SQL.
 private $readStage='START';
 public function readStage():string{return $this->readStage;}
 private $profile=1;
 private function db(){return DB::connection('pgsql');}
 private function table($name){return $this->db()->table($name);}
 private function boolean($value){if(in_array($value,[true,1,'1','t','true'],true))return true;if(in_array($value,[false,0,'0','f','false'],true))return false;throw new \UnexpectedValueException('Unknown boolean');}
 private function component($id,$name,$enabled){return ['id'=>$id===null?null:(string)$id,'label'=>$name===null?null:(string)$name,'active'=>$enabled===null?false:$this->boolean($enabled)];}
 private function visits($patient,$date){return $this->table('pasiendaftar_t')->where('kdprofile',$this->profile)->where('nocmfk',$patient)->where('statusenabled',true)->whereDate('tglregistrasi',$date);}
 public function fetch(string $patient,string $date):array {
  // Jhon | 2026-11-20 12:06 WITA | TAMBAH: diagnosis tahap PATIENT_EXISTS.
  $this->readStage='PATIENT_EXISTS';
  $exists=$this->table('pasien_m')->where('kdprofile',$this->profile)->where('id',$patient)->where('statusenabled',true)->exists();
  $out=['schema_version'=>1,'patient_id'=>$patient,'service_date'=>$date,'patient_exists'=>$exists,'complete'=>true,'reservations'=>[],'checked_in_visits'=>[],'eligibility_checked'=>false,'offerings'=>[]];if(!$exists)return $out;
  // Jhon | 2026-11-20 12:06 WITA | TAMBAH: diagnosis tahap RESERVATIONS.
  $this->readStage='RESERVATIONS';
  $rows=$this->table('antrianpasienregistrasi_t as a')
   ->leftJoin('ruangan_m as r',function($j){$j->on('r.id','=','a.objectruanganfk')->on('r.kdprofile','=','a.kdprofile');})
   ->leftJoin('pegawai_m as d',function($j){$j->on('d.id','=','a.objectpegawaifk')->on('d.kdprofile','=','a.kdprofile');})
   ->leftJoin('kelompokpasien_m as p',function($j){$j->on('p.id','=','a.objectkelompokpasienfk')->on('p.kdprofile','=','a.kdprofile');})
   ->where('a.kdprofile',$this->profile)->where('a.nocmfk',$patient)->whereDate('a.tanggalreservasi',$date)->whereNotNull('a.noreservasi')->where('a.noreservasi','!=','-')
   // Jhon | 2026-11-19 21:29 WITA | UBAH: bawa kode booking existing untuk validasi per pilihan; tidak menambah endpoint atau operasi tulis.
   ->orderBy('a.norec')->limit(101)->get(['a.norec','a.noreservasi','a.nocmfk','a.tanggalreservasi','a.statusenabled','a.noantrian','a.jam','r.id as poli_id','r.namaruangan','r.statusenabled as poli_active','d.id as dokter_id','d.namalengkap','d.statusenabled as dokter_active','p.id as penjamin_id','p.kelompokpasien','p.statusenabled as penjamin_active']);
  if(count($rows)>100)throw new \UnexpectedValueException('Too many reservations');
  // Jhon | 2026-11-20 12:06 WITA | TAMBAH: diagnosis tahap VISITS.
  $this->readStage='VISITS';
  $registrations=$this->visits($patient,$date)->limit(101)->get(['norec','antrianpasienregistrasifk','objectruanganlastfk','objectpegawaifk','objectkelompokpasienlastfk']);if(count($registrations)>100)throw new \UnexpectedValueException('Too many visits');
  $registered=$registrations->pluck('antrianpasienregistrasifk')->filter()->map(function($id){return (string)$id;})->all();
  foreach($rows as $row){$enabled=$this->boolean($row->statusenabled);$checked=in_array((string)$row->norec,$registered,true)||($row->noantrian!==null&&trim((string)$row->noantrian)!=='');
   // Pembatalan eksplisit sumber: statusenabled=false. Sinyal pembatalan + check-in bersamaan adalah konflik, bukan tiket dapat diproses.
   $state=!$enabled?($checked?'CONFLICT':'CANCELLED'):($checked?'CHECKED_IN':'ACTIVE');
   // Jhon | 2026-11-19 21:29 WITA | UBAH: kode invalid diteruskan ke validator agar bukan diam-diam dianggap tanpa reservasi.
   $out['reservations'][]=['id'=>(string)$row->norec,'booking_code'=>(string)$row->noreservasi,'patient_id'=>$patient,'service_date'=>substr($row->tanggalreservasi,0,10),'state'=>$state,'time'=>$row->jam?substr($row->jam,0,5):null,
    'poli'=>$this->component($row->poli_id,$row->namaruangan,$row->poli_active),'doctor'=>$this->component($row->dokter_id,$row->namalengkap,$row->dokter_active),'payer'=>$this->component($row->penjamin_id,$row->kelompokpasien,$row->penjamin_active)];
  }
  foreach($registrations as $visit){$out['checked_in_visits'][]=['id'=>(string)$visit->norec,'patient_id'=>$patient,'service_date'=>$date];}
  // Jadwal/kuota hanya dibutuhkan ketika tidak ada reservasi/kunjungan relevan; kegagalan layanan master lain tidak membatalkan reservasi yang sudah tervalidasi.
  // Jhon | 2026-11-19 21:29 WITA | TAMBAH: hanya metadata keberadaan booking aktif bertanggal lampau; tidak mengekspos riwayat pasien atau menganggap jam tertentu kedaluwarsa.
  $out['expired_reservations_present']=false;
  if(!$out['reservations']&&!$out['checked_in_visits']){
  // Jhon | 2026-11-20 12:06 WITA | TAMBAH: diagnosis tahap EXPIRED_BOOKINGS.
  $this->readStage='EXPIRED_BOOKINGS';
   $out['expired_reservations_present']=$this->table('antrianpasienregistrasi_t')->where('kdprofile',$this->profile)->where('nocmfk',$patient)->where('statusenabled',true)->whereDate('tanggalreservasi','<',$date)->whereNotNull('noreservasi')->where('noreservasi','!=','-')->where('noreservasi','!=','')->exists();
   $out['offerings']=$this->offerings($date);$out['eligibility_checked']=true;
  }return $out;
 }
 // Jhon | 2026-11-18 20:53 WITA | TAMBAH: Pilihan tanpa reservasi hanya jadwal sedang buka dengan sisa kuota; tidak memesan slot, menulis APR atau membuat antrean.
 private function offerings($date):array {
  $now=Carbon::now('Asia/Makassar');if($date!==$now->toDateString()||$now->isSunday())return [];
  // Jhon | 2026-11-20 12:06 WITA | TAMBAH: diagnosis tahap HOLIDAYS.
  $this->readStage='HOLIDAYS';
  if($this->table('slottinglibur_m')->where('kdprofile',$this->profile)->where('statusenabled',true)->whereDate('tgllibur',$date)->exists())return [];
  // Jhon | 2026-11-20 12:06 WITA | TAMBAH: diagnosis tahap PAYER_MASTER.
  $this->readStage='PAYER_MASTER';
  $payers=$this->table('kelompokpasien_m')->where('kdprofile',$this->profile)->where('statusenabled',true)->orderBy('id')->get(['id','kelompokpasien'])->map(function($p){return ['id'=>(string)$p->id,'label'=>$p->kelompokpasien];})->all();if(!$payers)return [];
  // Jhon | 2026-11-20 12:06 WITA | TAMBAH: diagnosis tahap SCHEDULE_MASTER.
  $this->readStage='SCHEDULE_MASTER';
  $schedules=$this->table('jadwaldokter_m as s')->join('ruangan_m as r',function($j){$j->on('r.id','=','s.objectruanganfk')->on('r.kdprofile','=','s.kdprofile');})->join('pegawai_m as d',function($j){$j->on('d.id','=','s.objectpegawaifk')->on('d.kdprofile','=','s.kdprofile');})
   ->where('s.kdprofile',$this->profile)->where('s.statusenabled',true)->where('r.statusenabled',true)->where('d.statusenabled',true)->where('r.objectdepartemenfk',18)->limit(501)->get(['s.id','s.tanggal','s.hari','s.jammulai','s.jamakhir','s.quota','r.id as poli_id','r.namaruangan','d.id as dokter_id','d.namalengkap']);
  if(count($schedules)>500)throw new \UnexpectedValueException('Schedule overflow');$days=['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];$grouped=[];
  // Jhon | 2026-11-20 12:06 WITA | TAMBAH: diagnosis tahap SCHEDULE_PARSE.
  $this->readStage='SCHEDULE_PARSE';
  foreach($schedules as $s){if($s->tanggal){if(Carbon::parse($s->tanggal)->toDateString()!==$date)continue;}else if(!in_array(strtolower($days[$now->dayOfWeek]),array_map('trim',explode(',',strtolower((string)$s->hari))),true))continue;
   $start=Carbon::parse($s->jammulai)->format('H:i');$end=Carbon::parse($s->jamakhir)->format('H:i');if($start>$now->format('H:i')||$end<=$now->format('H:i')||(int)$s->quota<=0)continue;
   $key=$s->poli_id.':'.$s->dokter_id;if(!isset($grouped[$key]))$grouped[$key]=['schedule'=>$s,'quota'=>0];// Jhon | 2026-11-19 22:06 WITA | TAMBAH: jam praktik berasal dari jadwal existing, bukan batas registrasi atau kuota UMUM rekaan.
   $grouped[$key]['windows'][]=['start'=>$start,'end'=>$end];$grouped[$key]['quota']+=(int)$s->quota;
  }
  // Jhon | 2026-11-20 12:06 WITA | TAMBAH: diagnosis tahap QUOTA_COUNTS.
  $this->readStage='QUOTA_COUNTS';
  $offered=[];foreach($grouped as $g){$s=$g['schedule'];$booked=$this->table('antrianpasienregistrasi_t')->where('kdprofile',$this->profile)->where('statusenabled',true)->where('objectruanganfk',$s->poli_id)->where('objectpegawaifk',$s->dokter_id)->whereDate('tanggalreservasi',$date)->count();
   $direct=$this->table('pasiendaftar_t as pd')->where('pd.kdprofile',$this->profile)->where('pd.statusenabled',true)->where('pd.objectruanganlastfk',$s->poli_id)->where('pd.objectpegawaifk',$s->dokter_id)->whereDate('pd.tglregistrasi',$date)->where(function($q){$q->whereNull('pd.antrianpasienregistrasifk')->orWhereNotExists(function($sub){$sub->selectRaw('1')->from('antrianpasienregistrasi_t as a')->whereColumn('a.norec','pd.antrianpasienregistrasifk')->whereColumn('a.kdprofile','pd.kdprofile')->where('a.statusenabled',true);});})->count();
   $remaining=max(0,$g['quota']-$booked-$direct);if(!$remaining)continue;
   $offered[]=['id'=>$s->poli_id.':'.$s->dokter_id,'service_date'=>$date,'poli'=>['id'=>(string)$s->poli_id,'label'=>$s->namaruangan,'active'=>true],'doctor'=>['id'=>(string)$s->dokter_id,'label'=>$s->namalengkap,'active'=>true],'payers'=>$payers,'remaining'=>$remaining,'practice_windows'=>$g['windows']];
  }
  if(count($offered)>100)throw new \UnexpectedValueException('Offering overflow');return $offered;
 }
}
