<?php
// Jhon | 2026-11-19 18:51 WITA | TAMBAH: signed read-only summary, profile/patient/date scope dan kegagalan DB.
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Support\Facades\{DB,Schema,Cache};
use Illuminate\Database\Schema\Blueprint;
class KioskPatientSummaryTest extends TestCase
{
 protected function setUp():void{
  parent::setUp();config(['database.connections.pgsql'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>false],'api_service.client_token'=>'fixture-token','api_service.client_secret'=>'fixture-secret','cache.default'=>'array']);
  DB::purge('pgsql');Cache::flush();
  Schema::connection('pgsql')->create('pasien_m',function(Blueprint $b){foreach(['id','kdprofile','statusenabled','namapasien','nocm','alamatlengkap','tempatlahir','tgllahir']as$c)$b->string($c)->nullable();});
  Schema::connection('pgsql')->create('antrianpasienregistrasi_t',function(Blueprint $b){foreach(['nocmfk','kdprofile','statusenabled','noreservasi','tanggalreservasi']as$c)$b->string($c)->nullable();});
  DB::connection('pgsql')->table('pasien_m')->insert(['id'=>'123','kdprofile'=>1,'statusenabled'=>1]);
 }
 protected function tearDown():void{DB::purge('pgsql');parent::tearDown();}
 private function booking($code,$date,$patient='123',$profile=1,$active=1){DB::connection('pgsql')->table('antrianpasienregistrasi_t')->insert(['noreservasi'=>$code,'tanggalreservasi'=>$date,'nocmfk'=>$patient,'kdprofile'=>$profile,'statusenabled'=>$active]);}
 private function signed(array $overrides=[],$sig=null){$data=array_merge(['patient_id'=>'123','identifier'=>'000123','nonce'=>bin2hex(random_bytes(16))],$overrides);$body=json_encode($data);$time=(string)time();$uri='/api/service/kiosk/patient-summary';return $this->call('POST',$uri,[],[],[],['CONTENT_TYPE'=>'application/json','HTTP_ACCEPT'=>'application/json','HTTP_X_TOKEN'=>'fixture-token','HTTP_X_TIMESTAMP'=>$time,'HTTP_X_SIGNATURE'=>$sig===null?hash_hmac('sha256',implode("\n",['POST',$uri,$time,hash('sha256',$body)]),'fixture-secret'):$sig],$body);}
 public function test_patient_profile_date_and_exact_searched_booking_scope(){
  // Jhon | 2026-11-20 06:27 WITA | UBAH: tanggal fixture harus mengikuti hari pelayanan WITA, bukan timezone CLI UTC.
  $today=\Carbon\Carbon::now('Asia/Makassar')->toDateString();$this->booking('TODAY',$today);$this->booking('FUTURE',\Carbon\Carbon::now('Asia/Makassar')->addDay()->toDateString());$this->booking('PAST','2000-01-01');$this->booking('OTHER-PATIENT',$today,'456');$this->booking('OTHER-PROFILE',$today,'123',2);$this->booking('CANCEL',$today,'123',1,0);
  $r=$this->signed()->assertOk()->assertJsonPath('data.patient_id','123');$codes=array_column($r->json('data.bookings'),'code');$this->assertEqualsCanonicalizing(['TODAY','FUTURE','CANCEL'],$codes);
  $this->signed(['identifier'=>'PAST'])->assertOk()->assertJsonFragment(['code'=>'PAST','service_date'=>'2000-01-01','state'=>'ACTIVE']);
 }
 public function test_empty_is_success_and_read_only(){DB::connection('pgsql')->enableQueryLog();$this->signed()->assertOk()->assertJsonPath('data.bookings',[]);foreach(DB::connection('pgsql')->getQueryLog()as$q)$this->assertSame(0,strpos(strtolower(ltrim($q['query'])),'select'));}
 public function test_auth_replay_and_input_are_rejected(){
  $this->signed([],str_repeat('0',64))->assertStatus(401);$this->signed(['patient_id'=>['123']])->assertStatus(422);
  $n=str_repeat('a',32);$this->signed(['nonce'=>$n])->assertOk();$this->signed(['nonce'=>$n])->assertStatus(409);
 }
 public function test_disabled_patient_and_database_failure_do_not_claim_no_booking(){
  DB::connection('pgsql')->table('pasien_m')->update(['statusenabled'=>0]);$this->signed()->assertStatus(404);
  DB::connection('pgsql')->table('pasien_m')->update(['statusenabled'=>1]);Schema::connection('pgsql')->drop('antrianpasienregistrasi_t');$this->signed()->assertStatus(503)->assertDontSee('SQLSTATE');
 }
 public function test_detail_projection_is_opt_in_and_does_not_include_identity_numbers(){
  DB::connection('pgsql')->table('pasien_m')->update(['namapasien'=>'Fixture Name','nocm'=>'000123','alamatlengkap'=>'Fixture Address','tempatlahir'=>'Fixture Place','tgllahir'=>'1990-01-02 00:00:00']);
  $this->signed()->assertOk()->assertJsonPath('data.patient',null);
  $r=$this->signed(['include_details'=>true])->assertOk()->assertJsonPath('data.patient.address','Fixture Address')->assertJsonPath('data.patient.birth_date','1990-01-02');
  $this->assertSame(['name','record_number','address','birth_place','birth_date'],array_keys($r->json('data.patient')));
 }
 // Jhon | 2026-11-20 06:27 WITA | UBAH: uji overflow memakai tanggal layanan WITA yang sama dengan controller.
 public function test_overflow_does_not_silently_truncate(){for($i=0;$i<51;$i++)$this->booking('B-'.$i,\Carbon\Carbon::now('Asia/Makassar')->toDateString());$this->signed()->assertStatus(503);}
 // Jhon | 2026-11-20 12:27 WITA | TAMBAH: rincian kunjungan tersedia tanpa identitas lengkap, tenant/pasien tetap dibatasi.
 public function test_visit_fields_return_clinic_doctor_payer_without_private_identity(){
  foreach(['ruangan_m'=>['id','kdprofile','namaruangan'],'pegawai_m'=>['id','kdprofile','namalengkap'],'kelompokpasien_m'=>['id','kdprofile','kelompokpasien']]as$t=>$cols)Schema::connection('pgsql')->create($t,function(Blueprint $b)use($cols){foreach($cols as$c)$b->string($c)->nullable();});
  Schema::connection('pgsql')->table('antrianpasienregistrasi_t',function(Blueprint $b){foreach(['objectruanganfk','objectpegawaifk','objectkelompokpasienfk','jam']as$c)$b->string($c)->nullable();});
  $db=DB::connection('pgsql');$db->table('ruangan_m')->insert(['id'=>'p','kdprofile'=>1,'namaruangan'=>'Poli Uji']);$db->table('pegawai_m')->insert(['id'=>'d','kdprofile'=>1,'namalengkap'=>'Dokter Uji']);$db->table('kelompokpasien_m')->insert(['id'=>'b','kdprofile'=>1,'kelompokpasien'=>'BPJS']);
  $date=\Carbon\Carbon::now('Asia/Makassar')->toDateString();$this->booking('BOOK-TEST',$date);$db->table('antrianpasienregistrasi_t')->update(['objectruanganfk'=>'p','objectpegawaifk'=>'d','objectkelompokpasienfk'=>'b','jam'=>'09:00']);
  $this->signed(['include_visit_fields'=>true])->assertOk()->assertJsonPath('data.bookings.0.poli','Poli Uji')->assertJsonPath('data.bookings.0.doctor','Dokter Uji')->assertJsonPath('data.bookings.0.payer','BPJS')->assertJsonPath('data.bookings.0.time','09:00')->assertJsonPath('data.patient',null);
 }
}
