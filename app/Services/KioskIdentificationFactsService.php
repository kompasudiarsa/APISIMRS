<?php
// Jhon | 2026-11-19 13:40 WITA | TAMBAH: pencarian exact read-only, profile tetap 1, tanpa memilih hasil ganda otomatis.
namespace App\Services;
use Illuminate\Support\Facades\DB;
class KioskIdentificationFactsService
{
 public function fetch(string $identifier): array
 {
  $db=DB::connection('pgsql');
  $patients=$db->table('pasien_m')->where('kdprofile',1)->where('statusenabled',true)
   ->where(function($q)use($identifier){$q->where('nocm',$identifier)->orWhere('nobpjs',$identifier)->orWhere('noidentitas',$identifier);})
   ->select('id','nocm','namapasien')->distinct()->limit(2)->get();
  // Booking dibaca termasuk yang dibatalkan; validitas kunjungan diperiksa pada reservation-check, bukan lookup identitas.
  $bookings=$db->table('antrianpasienregistrasi_t as apr')->leftJoin('pasien_m as pm',function($join){
    $join->on('pm.id','=','apr.nocmfk')->where('pm.kdprofile',1)->where('pm.statusenabled',true);
   })->where('apr.kdprofile',1)->where('apr.noreservasi',$identifier)
   ->select('pm.id','pm.nocm','pm.namapasien')->distinct()->limit(3)->get();
  $matches=[];$unlinked=false;
  foreach($patients as $row)$this->add($matches,$row,'patient');
  foreach($bookings as $row){if($row->id===null)$unlinked=true;else $this->add($matches,$row,'reservation');}
  if(count($matches)>1)return ['status'=>'MULTIPLE_MATCHES','patient'=>null];
  if($unlinked)return ['status'=>'VERIFICATION_REQUIRED','patient'=>null];
  if(!$matches)return ['status'=>'PATIENT_NOT_FOUND','patient'=>null];
  return ['status'=>'PATIENT_FOUND','patient'=>array_values($matches)[0]];
 }
 private function add(array &$matches,$row,string $source):void
 {
  $id=(string)$row->id;
  if(!preg_match('/\A[A-Za-z0-9.-]{1,64}\z/D',$id)||$id==='0')throw new \UnexpectedValueException('Invalid candidate');
  $candidate=['id'=>$id,'rm'=>$row->nocm,'name'=>$row->namapasien,'source'=>$source];
  if(isset($matches[$id])&&$matches[$id]['rm']!==$candidate['rm'])throw new \UnexpectedValueException('Conflicting candidate');
  if(!isset($matches[$id]))$matches[$id]=$candidate;
 }
}
