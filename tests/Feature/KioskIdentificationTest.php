<?php
// Jhon | 2026-11-19 13:44 WITA | TAMBAH: uji signed HTTP + query SQLite fixture; tidak memakai pasien/database produksi.
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Support\Facades\{DB,Schema,Cache};
use Illuminate\Database\Schema\Blueprint;
class KioskIdentificationTest extends TestCase
{
 protected function setUp():void
 {
  parent::setUp();config(['database.connections.pgsql'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>false],
   'api_service.client_token'=>'fixture-token','api_service.client_secret'=>'fixture-secret','cache.default'=>'array']);
  DB::purge('pgsql');Cache::flush();
  Schema::connection('pgsql')->create('pasien_m',function(Blueprint $b){foreach(['id','kdprofile','statusenabled','nocm','nobpjs','noidentitas','namapasien']as$c)$b->string($c)->nullable();});
  Schema::connection('pgsql')->create('antrianpasienregistrasi_t',function(Blueprint $b){foreach(['nocmfk','kdprofile','statusenabled','noreservasi']as$c)$b->string($c)->nullable();});
  $this->patient();
 }
 protected function tearDown():void{DB::purge('pgsql');parent::tearDown();}
 private function t($table){return DB::connection('pgsql')->table($table);}
 private function patient(array $overrides=[]){$this->t('pasien_m')->insert(array_merge(['id'=>'123','kdprofile'=>1,'statusenabled'=>1,'nocm'=>'00.01.23','nobpjs'=>'0000000000123','noidentitas'=>'0000000000000123','namapasien'=>'Nama Fixture'],$overrides));}
 private function booking($code='B-123',$patient='123',$profile=1,$active=1){$this->t('antrianpasienregistrasi_t')->insert(['nocmfk'=>$patient,'kdprofile'=>$profile,'statusenabled'=>$active,'noreservasi'=>$code]);}
 private function signed(array $data,$signature=null){$body=json_encode($data);$time=(string)time();$uri='/api/service/kiosk/identify';$sig=$signature===null?hash_hmac('sha256',implode("\n",['POST',$uri,$time,hash('sha256',$body)]),'fixture-secret'):$signature;
  return $this->call('POST',$uri,[],[],[],['CONTENT_TYPE'=>'application/json','HTTP_ACCEPT'=>'application/json','HTTP_X_TOKEN'=>'fixture-token','HTTP_X_TIMESTAMP'=>$time,'HTTP_X_SIGNATURE'=>$sig],$body);
 }
 private function lookup($id){return $this->signed(['identifier'=>$id,'nonce'=>bin2hex(random_bytes(16))]);}
 public function test_rm_bpjs_nik_and_booking_are_exact_and_preserve_zeroes(){
  $this->booking();foreach(['00.01.23','0000000000123','0000000000000123','B-123']as$id){$r=$this->lookup($id)->assertOk()->assertJsonPath('data.status','PATIENT_FOUND')->assertJsonPath('data.patient.id','123')->assertJsonPath('data.patient.rm','00.01.23');$this->assertSame(['id','rm','name','source'],array_keys($r->json('data.patient')));}
  $this->lookup('01.23')->assertOk()->assertJsonPath('data.status','PATIENT_NOT_FOUND')->assertJsonPath('data.patient',null);
 }
 public function test_disabled_and_other_profile_are_not_matched(){
  $this->patient(['id'=>'2','nocm'=>'DISABLED','statusenabled'=>0]);$this->patient(['id'=>'3','nocm'=>'OTHER','kdprofile'=>2]);$this->booking('OTHER-BOOK','123',2);
  foreach(['DISABLED','OTHER','OTHER-BOOK']as$id)$this->lookup($id)->assertOk()->assertJsonPath('data.status','PATIENT_NOT_FOUND');
 }
 public function test_multiple_patient_matches_and_shared_booking_are_not_selected(){
  $this->patient(['id'=>'2','nocm'=>'00.01.23']);$this->lookup('00.01.23')->assertJsonPath('data.status','MULTIPLE_MATCHES')->assertJsonPath('data.patient',null);
  $this->booking();$this->booking();$this->booking('B-123','2');$this->lookup('B-123')->assertJsonPath('data.status','MULTIPLE_MATCHES');
 }
 public function test_booking_unlinked_disabled_and_cross_profile_patient_require_verification(){
  $this->patient(['id'=>'2','statusenabled'=>0]);$this->patient(['id'=>'3','kdprofile'=>2]);
  foreach(['missing','2','3']as$id){$this->booking('B-'.$id,$id);$this->lookup('B-'.$id)->assertOk()->assertJsonPath('data.status','VERIFICATION_REQUIRED')->assertJsonPath('data.patient',null);}
 }
 public function test_cancelled_booking_can_identify_but_does_not_claim_booking_valid(){
  $this->booking('B-CANCEL','123',1,0);$this->lookup('B-CANCEL')->assertOk()->assertJsonPath('data.status','PATIENT_FOUND')->assertJsonMissing(['reservation_valid'=>true]);
 }
 public function test_lookup_only_selects_and_echoes_nonce(){
  DB::connection('pgsql')->enableQueryLog();$nonce=bin2hex(random_bytes(16));$this->signed(['identifier'=>'00.01.23','nonce'=>$nonce])->assertOk()->assertJsonPath('data.nonce',$nonce);
  $queries=DB::connection('pgsql')->getQueryLog();$this->assertCount(2,$queries);foreach($queries as$q)$this->assertSame(0,strpos(strtolower(ltrim($q['query'])),'select'));
 }
 public function test_missing_booking_table_is_service_unavailable_even_if_patient_found(){
  Schema::connection('pgsql')->drop('antrianpasienregistrasi_t');$this->lookup('00.01.23')->assertStatus(503)->assertJsonMissing(['status'=>'PATIENT_NOT_FOUND'])->assertDontSee('SQLSTATE');
 }
 public function test_signature_missing_wrong_and_replay_are_rejected(){
  $this->postJson('/api/service/kiosk/identify',['identifier'=>'00.01.23'])->assertStatus(401);
  $body=['identifier'=>'00.01.23','nonce'=>str_repeat('a',32)];$this->signed($body,str_repeat('0',64))->assertStatus(401);$this->signed($body)->assertOk();$this->signed($body)->assertStatus(409);
 }
 public function test_invalid_input_and_extra_fields_are_not_queries(){
  DB::connection('pgsql')->enableQueryLog();foreach([
   ['identifier'=>['00.01.23'],'nonce'=>str_repeat('b',32)],['identifier'=>'!!','nonce'=>str_repeat('c',32)],['identifier'=>'00.01.23','nonce'=>'bad'],['identifier'=>'00.01.23','nonce'=>str_repeat('d',32),'profile'=>2]
  ]as$body)$this->signed($body)->assertStatus(422);$this->assertEmpty(DB::connection('pgsql')->getQueryLog());
 }
}
