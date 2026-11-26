<?php
namespace App\Services;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
// Jhon | 2026-11-18 15:18 WITA | TAMBAH: Adaptasi ReservasiMobileCtrl + ProfilePasienCtrl::saveRiwayatKontrolWeb, bukan proxy ke backend lama.
class OnlineReservationService {
 const PROFILE=1;
 private function db(){return DB::connection('pgsql');}
 private function table($name){return $this->db()->table($name);}
 private function fail($message){throw ValidationException::withMessages(['reservasi'=>$message]);}
 private function setting($name,$fallback=null){$value=$this->table('settingdatafixed_m')->where('kdprofile',self::PROFILE)->where('statusenabled',true)->where('namafield',$name)->value('nilaifield');return $value===null?$fallback:$value;}
 private function active($table){return $this->table($table)->where('kdprofile',self::PROFILE)->where('statusenabled',true);}
 public function rooms(){return $this->active('ruangan_m')->where('objectdepartemenfk',18)->whereNotIn('id',[235,280,762,763,764,765,766,767,768,769,770])->where('namaruangan','not ilike','%VIP%')->orderBy('namaruangan')->get(['id','namaruangan']);}
 public function master(){return [
  'ruangan'=>$this->rooms(),
  // Sama dengan pilihan aktif frontend e-reservasi: kelompok 2 (BPJS) tidak ditawarkan tanpa bridging peserta/rujukan.
  'penjamin'=>$this->active('kelompokpasien_m')->where('id','!=',2)->orderBy('kelompokpasien')->get(['id','kelompokpasien']),
  'jenis_kelamin'=>$this->table('jeniskelamin_m')->where('statusenabled',true)->get(['id','jeniskelamin']),
  'agama'=>$this->active('agama_m')->orderBy('agama')->get(['id','agama']),
  'kebangsaan'=>$this->active('kebangsaan_m')->orderBy('name')->get(['id','name']),
  'asuransi'=>$this->table('mapkelompokpasientopenjamin_m as m')->join('rekanan_m as r','r.id','=','m.kdpenjaminpasien')->where('m.objectkelompokpasienfk',3)->where('m.statusenabled',true)->where('r.statusenabled',true)->select('r.id','r.namarekanan')->distinct()->orderBy('r.namarekanan')->get(),
  'batas_hari'=>max(1,(int)$this->setting('isRentangReservasi',28)), 'batas_jam'=>$this->setting('maxJamReservasi','13:00'),
  'tanggal_min'=>Carbon::now('Asia/Makassar')->addDay()->toDateString(),
  'tanggal_max'=>Carbon::now('Asia/Makassar')->addDays(max(1,(int)$this->setting('isRentangReservasi',28)))->toDateString(),
  'libur'=>$this->active('slottinglibur_m')->whereDate('tgllibur','>=',Carbon::now('Asia/Makassar')->toDateString())->pluck('tgllibur'),
 ];}
 public function doctors($room,$date=null){
  // Jhon | 2026-11-25 08:23 WITA | UBAH: Tanggal opsional untuk pemanggil internal lama; route dokter mewajibkan tanggal.
  if($date!==null)$this->dateAllowed($date);
  if(!$this->rooms()->contains('id',(int)$room))$this->fail('Poli tidak tersedia untuk reservasi online.');
  $types=array_filter(array_map('intval',explode(',',(string)$this->setting('kdPsikologDokter',$this->setting('idJenisPegawaiDokter','')))));
  if(!$types)$this->fail('Jenis petugas dokter untuk reservasi belum dikonfigurasi.');
  $doctors=$this->table('pegawai_m as p')->join('mappegawaitoruangan_m as m','m.objectpegawaifk','=','p.id')->where('p.kdprofile',self::PROFILE)->where('p.statusenabled',true)->where('m.kdprofile',self::PROFILE)->where('m.statusenabled',true)->where('m.objectruanganfk',$room)->whereIn('p.objectjenispegawaifk',$types)->distinct()->orderBy('p.namalengkap')->get(['p.id','p.namalengkap']);
  if($date===null)return $doctors;
  $scheduled=$this->active('jadwaldokter_m')->where('objectruanganfk',$room)->get(['objectpegawaifk','tanggal','hari','jammulai','jamakhir','quota']);
  $ids=[];foreach($scheduled as $row){if($this->scheduleMatches($row,$date))$ids[(string)$row->objectpegawaifk]=true;}
  return $doctors->filter(function($doctor)use($ids){return isset($ids[(string)$doctor->id]);})->values();
 }
 // Jhon | 2026-11-25 08:23 WITA | TAMBAH: Predikat jadwal dipakai bersama daftar dokter dan slot, tanggal khusus tidak menjadi jadwal mingguan.
 private function scheduleMatches($s,$date){
  if((int)$s->quota<=0)return false;
  if($s->tanggal){if(Carbon::parse($s->tanggal)->toDateString()!==$date)return false;}
  else{$days=['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];$name=$days[Carbon::parse($date)->dayOfWeek];$list=array_map('strtolower',array_map('trim',explode(',',(string)$s->hari)));if(!in_array(strtolower($name),$list,true))return false;}
  if(!$s->jammulai||!$s->jamakhir)return false;
  return Carbon::parse($s->jamakhir)->format('H:i')>Carbon::parse($s->jammulai)->format('H:i');
 }
 private function dateAllowed($date){$day=Carbon::createFromFormat('!Y-m-d',$date,'Asia/Makassar');$now=Carbon::now('Asia/Makassar');$range=max(1,(int)$this->setting('isRentangReservasi',28));
  if($day->lte($now->copy()->startOfDay())||$day->gt($now->copy()->addDays($range)->endOfDay()))$this->fail('Tanggal di luar rentang reservasi.');
  if($day->isSunday()||$this->active('slottinglibur_m')->whereDate('tgllibur',$date)->exists())$this->fail('Tanggal layanan libur.');
  $cutoff=(string)$this->setting('maxJamReservasi','13:00');if($day->isSameDay($now->copy()->addDay())&&$now->format('H:i')>substr($cutoff,0,5))$this->fail('Pemesanan untuk besok sudah melewati batas waktu.');
 }
 // Jhon | 2026-11-18 15:18 WITA | TAMBAH: Jadwal/slot dihitung ulang server; parameter SQL terikat, tidak menyalin interpolasi request mentah.
 public function slots($room,$doctor,$date){$this->dateAllowed($date);if(!$this->doctors($room,$date)->contains('id',(int)$doctor))$this->fail('Dokter tidak tersedia di poli ini.');
  $schedules=$this->active('jadwaldokter_m')->where('objectruanganfk',$room)->where('objectpegawaifk',$doctor)->get(['id','tanggal','hari','jammulai','jamakhir','quota']);
  $days=['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];$dayName=$days[Carbon::parse($date)->dayOfWeek];$result=[];$capacity=0;
  $used=$this->active('antrianpasienregistrasi_t')->where('objectruanganfk',$room)->whereDate('tanggalreservasi',$date)->whereNotNull('noreservasi')->where('noreservasi','!=','-')->get(['jam','tanggalreservasi']);
  // Konsisten dengan sumber: waktu terpakai dibatasi per ruangan, bukan hanya dokter.
  $busy=[];foreach($used as $row){$busy[substr($row->jam?:Carbon::parse($row->tanggalreservasi)->format('H:i'),0,5)]=true;}
  foreach($schedules as $s){
   if(!$this->scheduleMatches($s,$date))continue;
   $quota=(int)$s->quota;if($quota<=0)continue;
   $begin=Carbon::parse($date.' '.Carbon::parse($s->jammulai)->format('H:i'),'Asia/Makassar');$end=Carbon::parse($date.' '.Carbon::parse($s->jamakhir)->format('H:i'),'Asia/Makassar');if($end->lte($begin))continue;
   $minutes=max(1,(int)floor($begin->diffInMinutes($end)/$quota));$capacity+=$quota;
   for($i=0;$i<$quota;$i++){$time=$begin->copy()->addMinutes($i*$minutes);if($time->gte($end))break;$value=$time->format('H:i');if(!isset($busy[$value]))$result[$value]=['jam'=>$value];}
  }
  ksort($result);return ['listjam'=>array_values($result),'kapasitas'=>$capacity,'tanggal'=>$date];
 }
 // Jhon | 2026-11-18 15:18 WITA | TAMBAH: Hanya data pasien yang cocok dengan identitas dan RM sesi NADI untuk pasien lama.
 public function patient($identity){$p=$this->active('pasien_m')->where('id',$identity['patient_id'])->where('nocm',$identity['nocm'])->first();if(!$p)$this->fail('Identitas pasien tidak sesuai. Silakan masuk kembali.');return $p;}
 private function recordId($actor,$request){$hash=hash_hmac('sha256',$actor.'|'.$request,(string)config('app.key'));return substr($hash,0,8).'-'.substr($hash,8,4).'-5'.substr($hash,13,3).'-a'.substr($hash,17,3).'-'.substr($hash,20,12);}
 private function receipt($row){$room=$this->table('ruangan_m')->where('id',$row->objectruanganfk)->value('namaruangan');$doctor=$this->table('pegawai_m')->where('id',$row->objectpegawaifk)->value('namalengkap');return ['noreservasi'=>$row->noreservasi,'tanggalreservasi'=>substr($row->tanggalreservasi,0,10),'jam'=>$row->jam,'noantrianpoli'=>$row->noantrianpoli,'poli'=>$room,'dokter'=>$doctor,'status'=>'Belum Teregistrasi'];}
 private function controlNumber(){
  $prefix=Carbon::now('Asia/Makassar')->format('ymd').'CNT-';$key=self::PROFILE.'_nosuratkontrol_'.$prefix;
  $counter=$this->db()->selectOne('UPDATE sequence_counters SET last_value=last_value+1 WHERE key=? RETURNING last_value',[$key]);
  if(!$counter){$max=$this->table('seqnumber_t')->where('seqname','nosuratkontrol')->where('kdprofile',self::PROFILE)->where('seqnumber','like',$prefix.'%')->max('seqnumber');$next=$max?(int)substr($max,strlen($prefix))+1:1;
   $counter=$this->db()->selectOne('INSERT INTO sequence_counters (key,last_value) VALUES (?,?) ON CONFLICT (key) DO UPDATE SET last_value=GREATEST(sequence_counters.last_value+1,EXCLUDED.last_value) RETURNING last_value',[$key,$next]);}
  $number=$prefix.str_pad((string)$counter->last_value,16-strlen($prefix),'0',STR_PAD_LEFT);
  $this->table('seqnumber_t')->insert(['norec'=>(string)\Illuminate\Support\Str::uuid(),'kdprofile'=>self::PROFILE,'seqnumber'=>$number,'tgljamseq'=>Carbon::now('Asia/Makassar')->toDateTimeString(),'seqname'=>'nosuratkontrol','created_at'=>Carbon::now('Asia/Makassar'),'updated_at'=>Carbon::now('Asia/Makassar')]);return $number;
 }
 public function save(array $v){
  return $this->db()->transaction(function()use($v){
   $patient=$this->patient($v);$id=$this->recordId((string)$patient->id,$v['request_id']);
   // Serialisasi request dengan ID sama dan alokasi slot/antrean API ini pada ruangan+tanggal yang sama.
   $this->db()->select('SELECT pg_advisory_xact_lock(hashtext(?))',['nadi-reservation:'.$id]);
   $existing=$this->active('antrianpasienregistrasi_t')->where('norec',$id)->first();
   if($existing){if(($existing->type==='BARU')!==($v['jenis_pasien']==='baru')||($v['jenis_pasien']==='baru'&&(string)$existing->noidentitas!==(string)$v['nik']))$this->fail('ID permintaan telah digunakan untuk identitas berbeda. Muat ulang formulir.');if((int)$existing->objectruanganfk!==(int)$v['poli_id']||(int)$existing->objectpegawaifk!==(int)$v['dokter_id']||substr($existing->tanggalreservasi,0,10)!==$v['tanggal']||substr($existing->jam,0,5)!==$v['jam']||(int)$existing->objectkelompokpasienfk!==(int)$v['penjamin_id'])$this->fail('ID permintaan telah digunakan dengan rincian berbeda.');return $this->receipt($existing);}
   $this->db()->select('SELECT pg_advisory_xact_lock(hashtext(?))',['nadi-room:'.self::PROFILE.':'.$v['poli_id'].':'.$v['tanggal']]);
   $slots=$this->slots($v['poli_id'],$v['dokter_id'],$v['tanggal']);if(!collect($slots['listjam'])->contains('jam',$v['jam']))$this->fail('Slot sudah terpakai atau tidak tersedia. Pilih slot lagi.');
   $group=$this->active('kelompokpasien_m')->where('id',$v['penjamin_id'])->where('id','!=',2)->first();if(!$group)$this->fail('Penjamin tidak tersedia untuk alur ini.');
   if((int)$v['penjamin_id']!==3)$v['asuransi_id']=null;
   if((int)$v['penjamin_id']===3){$insurance=$v['asuransi_id']??0;if(!$this->table('mapkelompokpasientopenjamin_m')->where('objectkelompokpasienfk',3)->where('kdpenjaminpasien',$insurance)->where('statusenabled',true)->exists()||!$this->active('rekanan_m')->where('id',$insurance)->exists())$this->fail('Pilih asuransi yang tersedia.');}
   $new=$v['jenis_pasien']==='baru';
   if($new){if($this->active('pasien_m')->where('noidentitas',$v['nik'])->exists())$this->fail('NIK sudah terdaftar. Gunakan akun pasien lama atau hubungi CS.');}
   $duplicate=$this->active('antrianpasienregistrasi_t')->whereDate('tanggalreservasi',$v['tanggal'])->where('objectruanganfk',$v['poli_id']);if($new)$duplicate->where('noidentitas',$v['nik']);else $duplicate->where('nocmfk',$patient->id);if($duplicate->exists())$this->fail('Sudah ada reservasi pasien pada poli dan tanggal ini.');
   if($new){foreach(['jenis_kelamin_id'=>['jeniskelamin_m','id'],'agama_id'=>['agama_m','id'],'kebangsaan_id'=>['kebangsaan_m','id']] as $field=>$ref){if(!$this->table($ref[0])->where('id',$v[$field])->where('statusenabled',true)->exists())$this->fail('Pilihan identitas pasien tidak valid.');}}
   $now=Carbon::now('Asia/Makassar');$dob=$new?$v['tanggal_lahir']:$patient->tgllahir;$age=Carbon::parse($dob)->diffInYears($now);
   $queue=(int)$this->table('antrianpasienregistrasi_t')->where('kdprofile',self::PROFILE)->where('objectruanganfk',$v['poli_id'])->whereDate('tanggalreservasi',$v['tanggal'])->max('noantrianpoli')+1;
   $booking='W'.substr(str_replace('-','',$id),0,7);if($this->table('antrianpasienregistrasi_t')->where('noreservasi',$booking)->exists())$booking='W'.substr(str_replace('-','',(string)\Illuminate\Support\Str::uuid()),0,7);
   $cancer=array_map('intval',explode(',',(string)$this->setting('idRuanganKanker','')));
   $row=['norec'=>$id,'kdprofile'=>self::PROFILE,'statusenabled'=>true,'noreservasi'=>$booking,'nocmfk'=>$new?null:$patient->id,'objectruanganfk'=>$v['poli_id'],'objectpegawaifk'=>$v['dokter_id'],'objectkelompokpasienfk'=>$v['penjamin_id'],'objectpenjaminfk'=>$v['asuransi_id']??null,'tanggalreservasi'=>$v['tanggal'].' '.$v['jam'].':00','noantrianpoli'=>$queue,'tglinput'=>$now->toDateTimeString(),'iskiosk'=>true,'loketkiosk'=>3,'isreservasi'=>true,'umursaatinput'=>$age,'jam'=>$v['jam'],'keterangan'=>$v['catatan']??null,'caradaftar'=>in_array((int)$v['poli_id'],$cancer,true)?'KANKER':'UTAMA','notelepon'=>$v['no_hp'],'type'=>$new?'BARU':'LAMA','tipepasien'=>'LAMA','jenis'=>$new?'RB':(($age<=17||$age>60)?'RA':'RL'),'objectjeniskelaminfk'=>$new?$v['jenis_kelamin_id']:$patient->objectjeniskelaminfk,'objectkebangsaanfk'=>$new?$v['kebangsaan_id']:$patient->objectkebangsaanfk,'created_at'=>$now,'updated_at'=>$now];
   if($new)$row=array_merge($row,['namapasien'=>$v['nama'],'noidentitas'=>$v['nik'],'tgllahir'=>$v['tanggal_lahir'],'tempatlahir'=>$v['tempat_lahir'],'alamatlengkap'=>$v['alamat'],'objectagamafk'=>$v['agama_id']]);else $row['objectpendidikanfk']=$patient->objectpendidikanfk?:0;
   $this->table('antrianpasienregistrasi_t')->insert($row);
   if(!$new){$this->table('riwayatkontrol_t')->insert(['norec'=>(string)\Illuminate\Support\Str::uuid(),'nocmfk'=>$patient->id,'kdprofile'=>self::PROFILE,'objectruanganfk'=>$v['poli_id'],'objectpegawaifk'=>$v['dokter_id'],'tglkontrol'=>$v['tanggal'].' '.$v['jam'].':00','tglentry'=>$now->toDateString(),'isterbitsep'=>false,'jam'=>$v['jam'],'catatan'=>$v['catatan']??null,'diagnosaakhir'=>'-','indikasikontrol'=>'-','nobukti'=>$this->controlNumber(),'antrianpasienregistrasifk'=>$id,'created_at'=>$now,'updated_at'=>$now]);}
   return $this->receipt((object)$row);
  },3);
 }
}
