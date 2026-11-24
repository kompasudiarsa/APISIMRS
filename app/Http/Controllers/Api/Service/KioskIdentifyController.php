<?php
// Jhon | 2026-11-19 13:40 WITA | TAMBAH: endpoint baca-saja bertanda tangan; identifier dalam body agar tidak masuk URL/access log.
namespace App\Http\Controllers\Api\Service;
use App\Http\Controllers\Controller;
use App\Services\KioskIdentificationFactsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
class KioskIdentifyController extends Controller
{
 public function __invoke(Request $request,KioskIdentificationFactsService $service)
 {
  if(strlen($request->getContent())>4096)return response()->json(['message'=>'Input terlalu panjang.'],413)->header('Cache-Control','private, no-store');
  $v=Validator::make($request->all(),['identifier'=>['required','string','min:3','max:64','regex:/\A[A-Za-z0-9.-]+\z/D'],'nonce'=>['required','string','regex:/\A[a-f0-9]{32}\z/D']]);
  if($v->fails()||$request->query->count()>0||array_diff(array_keys($request->all()),['identifier','nonce']))return response()->json(['message'=>'Input tidak valid.'],422)->header('Cache-Control','private, no-store');
  try{
   $result=$service->fetch($request->input('identifier'));$result['nonce']=$request->input('nonce');
   return response()->json(['data'=>$result])->header('Cache-Control','private, no-store');
  }catch(\Throwable $e){
   Log::warning('kiosk_identification_unavailable',['category'=>'upstream_read']);
   return response()->json(['message'=>'Pencarian SIMRS belum dapat diselesaikan.'],503)->header('Cache-Control','private, no-store');
  }
 }
}
