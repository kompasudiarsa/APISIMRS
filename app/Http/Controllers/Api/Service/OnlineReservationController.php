<?php
namespace App\Http\Controllers\Api\Service;
use App\Http\Controllers\Controller;
use App\Services\OnlineReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
// Jhon | 2026-11-18 15:18 WITA | TAMBAH: Kontrak JSON e-reservasi terproteksi api.signature, tanpa model/controller dari aplikasi lama.
class OnlineReservationController extends Controller {
 private function run($callback){try{return response()->json(['data'=>$callback()])->header('Cache-Control','private, no-store');}catch(ValidationException $e){return response()->json(['message'=>'Periksa data reservasi.','errors'=>$e->errors()],422);}catch(\Throwable $e){\Illuminate\Support\Facades\Log::warning('Online reservation failed',['type'=>get_class($e)]);return response()->json(['message'=>'Layanan reservasi belum dapat diproses. Hubungi petugas bila berulang.'],503);}}
 private function valid(Request $r,$rules){$v=Validator::make($r->all(),$rules);if($v->fails())throw new ValidationException($v);return $v->validated();}
 public function master(OnlineReservationService $s){return $this->run(function()use($s){return $s->master();});}
 // Jhon | 2026-11-25 08:23 WITA | UBAH: Lookup dokter mewajibkan tanggal pelayanan yang dipilih pasien.
 public function doctors(Request $r,OnlineReservationService $s){return $this->run(function()use($r,$s){$v=$this->valid($r,['poli_id'=>'required|integer|min:1','tanggal'=>'required|date_format:Y-m-d']);return $s->doctors($v['poli_id'],$v['tanggal']);});}
 public function slots(Request $r,OnlineReservationService $s){return $this->run(function()use($r,$s){$v=$this->valid($r,['poli_id'=>'required|integer|min:1','dokter_id'=>'required|integer|min:1','tanggal'=>'required|date_format:Y-m-d']);return $s->slots($v['poli_id'],$v['dokter_id'],$v['tanggal']);});}
 public function save(Request $r,OnlineReservationService $s){return $this->run(function()use($r,$s){$v=$this->valid($r,[
 'patient_id'=>'required|string|max:64','nocm'=>'required|string|max:30','request_id'=>'required|uuid','jenis_pasien'=>'required|in:lama,baru',
 'poli_id'=>'required|integer|min:1','dokter_id'=>'required|integer|min:1','tanggal'=>'required|date_format:Y-m-d','jam'=>'required|date_format:H:i','penjamin_id'=>'required|integer|min:1','asuransi_id'=>'nullable|integer|min:1','no_hp'=>'required|regex:/^[0-9+ .()\\-]{8,25}$/','catatan'=>'nullable|string|max:500',
 'nama'=>'required_if:jenis_pasien,baru|nullable|string|max:120','nik'=>'required_if:jenis_pasien,baru|nullable|digits:16','tanggal_lahir'=>'required_if:jenis_pasien,baru|nullable|date_format:Y-m-d|before_or_equal:today','tempat_lahir'=>'required_if:jenis_pasien,baru|nullable|string|max:100','alamat'=>'required_if:jenis_pasien,baru|nullable|string|max:500','jenis_kelamin_id'=>'required_if:jenis_pasien,baru|nullable|integer|min:1','agama_id'=>'required_if:jenis_pasien,baru|nullable|integer|min:1','kebangsaan_id'=>'required_if:jenis_pasien,baru|nullable|integer|min:1'
 ]);return $s->save($v);});}
}
