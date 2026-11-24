<?php
namespace App\Http\Controllers\Api\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\KioskReservationFactsService;
// Jhon | 2026-11-18 20:53 WITA | TAMBAH: Kontrak lengkap baca-saja untuk Kios-K; hanya akses bertanda tangan, identitas tidak dicari lewat nama/RM/booking terakhir.
class KioskReservationCheckController {
 public function __invoke(Request $r,KioskReservationFactsService $service){$v=Validator::make($r->all(),['patient_id'=>'required|string|max:64|regex:/^[a-zA-Z0-9.\-]+$/','service_date'=>'required|date_format:Y-m-d']);if($v->fails())return response()->json(['message'=>'Parameter pemeriksaan tidak valid.','errors'=>$v->errors()],422);
  try{$data=$service->fetch($v->validated()['patient_id'],$v->validated()['service_date']);return response()->json(['data'=>$data])->header('Cache-Control','private, no-store');}
  // Jhon | 2026-11-20 12:06 WITA | TAMBAH: log aman: tahap, kelas error dan SQLSTATE saja; tidak query/bindings/pesan mentah/identitas.
  catch(\Throwable $e){$sqlstate=null;if($e instanceof \Illuminate\Database\QueryException){$info=$e->errorInfo??[];if(is_string($info[0]??null)&&preg_match('/\A[A-Z0-9]{5}\z/D',$info[0]))$sqlstate=$info[0];}
   $ref=(string)\Illuminate\Support\Str::uuid();\Illuminate\Support\Facades\Log::warning('kiosk_reservation_check_failed',['category'=>'upstream_read','diagnostic_reference'=>$ref,'read_stage'=>$service->readStage(),'error_type'=>$e instanceof \Illuminate\Database\QueryException?'DATABASE_READ':($e instanceof \UnexpectedValueException?'DATA_CONTRACT':'INTERNAL'),'sqlstate'=>$sqlstate]);return response()->json(['message'=>'Pemeriksaan reservasi belum tersedia.','diagnostic_reference'=>$ref],503);}
 }
}
