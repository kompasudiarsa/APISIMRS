<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Carbon\Carbon;
use App\Services\KioskReservationFactsService;
// Jhon | 2026-11-18 21:19 WITA | TAMBAH: Database SQLite memory khusus fixture, menggantikan koneksi pgsql dalam tes; tidak menyentuh SIMRS nyata.
class KioskReservationFactsTest extends TestCase {
 protected function setUp():void{parent::setUp();config(['database.connections.pgsql'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>false]]);DB::purge('pgsql');Carbon::setTestNow(Carbon::parse('2026-11-18 10:00:00','Asia/Makassar'));
  foreach(['pasien_m'=>['id','kdprofile','statusenabled'],'ruangan_m'=>['id','kdprofile','statusenabled','objectdepartemenfk','namaruangan'],'pegawai_m'=>['id','kdprofile','statusenabled','namalengkap'],'kelompokpasien_m'=>['id','kdprofile','statusenabled','kelompokpasien'],'antrianpasienregistrasi_t'=>['norec','kdprofile','nocmfk','statusenabled','noreservasi','tanggalreservasi','noantrian','jam','objectruanganfk','objectpegawaifk','objectkelompokpasienfk'],'pasiendaftar_t'=>['norec','kdprofile','nocmfk','statusenabled','tglregistrasi','antrianpasienregistrasifk','objectruanganlastfk','objectpegawaifk','objectkelompokpasienlastfk'],'slottinglibur_m'=>['id','kdprofile','statusenabled','tgllibur'],'jadwaldokter_m'=>['id','kdprofile','statusenabled','tanggal','hari','jammulai','jamakhir','quota','objectruanganfk','objectpegawaifk']]as$table=>$columns)Schema::connection('pgsql')->create($table,function(Blueprint $b)use($columns){foreach($columns as$c)$b->string($c)->nullable();});
  $this->t('pasien_m')->insert(['id'=>'pat-A','kdprofile'=>1,'statusenabled'=>1]);$this->t('ruangan_m')->insert(['id'=>1,'kdprofile'=>1,'statusenabled'=>1,'objectdepartemenfk'=>18,'namaruangan'=>'Poli Dummy']);$this->t('pegawai_m')->insert(['id'=>2,'kdprofile'=>1,'statusenabled'=>1,'namalengkap'=>'Dokter Dummy']);$this->t('kelompokpasien_m')->insert(['id'=>3,'kdprofile'=>1,'statusenabled'=>1,'kelompokpasien'=>'Penjamin Dummy']);$this->t('jadwaldokter_m')->insert(['id'=>4,'kdprofile'=>1,'statusenabled'=>1,'hari'=>'Rabu','jammulai'=>'08:00','jamakhir'=>'16:00','quota'=>3,'objectruanganfk'=>1,'objectpegawaifk'=>2]);
 }
 protected function tearDown():void{Carbon::setTestNow();DB::purge('pgsql');parent::tearDown();}
 private function t($table){return DB::connection('pgsql')->table($table);}
 private function apr($id,$status=1,$date='2026-11-18',$patient='pat-A',$profile=1){$this->t('antrianpasienregistrasi_t')->insert(['norec'=>$id,'kdprofile'=>$profile,'nocmfk'=>$patient,'statusenabled'=>$status,'noreservasi'=>'W'.$id,'tanggalreservasi'=>$date.' 09:00:00','noantrian'=>null,'jam'=>'09:00','objectruanganfk'=>1,'objectpegawaifk'=>2,'objectkelompokpasienfk'=>3]);}
 public function test_lookup_is_exact_patient_date_and_profile_and_includes_cancellation(){
  $this->apr('active');$this->apr('cancelled',0);$this->apr('future',1,'2026-11-19');$this->apr('other-patient',1,'2026-11-18','pat-B');$this->apr('other-profile',1,'2026-11-18','pat-A',2);
  DB::connection('pgsql')->enableQueryLog();$before=$this->t('antrianpasienregistrasi_t')->count();$result=app(KioskReservationFactsService::class)->fetch('pat-A','2026-11-18');$this->assertSame(['ACTIVE','CANCELLED'],array_column($result['reservations'],'state'));$this->assertSame($before,$this->t('antrianpasienregistrasi_t')->count());foreach(DB::connection('pgsql')->getQueryLog()as$q)$this->assertSame(0,strpos(strtolower(ltrim($q['query'])),'select'),'Pemeriksaan tidak boleh menulis database');
 }
 public function test_checked_in_reservation_and_direct_visit_are_returned(){ $this->apr('checked');$this->t('pasiendaftar_t')->insert(['norec'=>'visit','kdprofile'=>1,'nocmfk'=>'pat-A','statusenabled'=>1,'tglregistrasi'=>'2026-11-18 09:00:00','antrianpasienregistrasifk'=>'checked','objectruanganlastfk'=>1,'objectpegawaifk'=>2]);$r=app(KioskReservationFactsService::class)->fetch('pat-A','2026-11-18');$this->assertSame('CHECKED_IN',$r['reservations'][0]['state']);$this->assertCount(1,$r['checked_in_visits']);}
 public function test_missing_patient_is_successful_absence_not_error(){$r=app(KioskReservationFactsService::class)->fetch('unknown','2026-11-18');$this->assertFalse($r['patient_exists']);$this->assertTrue($r['complete']);$this->assertEmpty($r['reservations']);}
 public function test_schedule_quota_closed_and_holiday_rules(){ $s=app(KioskReservationFactsService::class);$r=$s->fetch('pat-A','2026-11-18');$this->assertTrue($r['eligibility_checked']);// Jhon | 2026-11-19 22:10 WITA | TAMBAH: jam praktik dibuktikan berasal dari jadwal fixture, bukan jam/cutoff rekaan.
 $this->assertSame(3,$r['offerings'][0]['remaining']);$this->assertSame([['start'=>'08:00','end'=>'16:00']],$r['offerings'][0]['practice_windows']);$this->apr('quota1');$this->apr('quota2');$this->apr('quota3');$this->assertEmpty($s->fetch('pat-A','2026-11-18')['offerings']);$this->t('antrianpasienregistrasi_t')->delete();$this->t('slottinglibur_m')->insert(['id'=>1,'kdprofile'=>1,'statusenabled'=>1,'tgllibur'=>'2026-11-18']);$this->assertEmpty($s->fetch('pat-A','2026-11-18')['offerings']);}
 public function test_checkin_and_cancellation_conflict_is_not_processable(){$this->apr('conflict',0);$this->t('antrianpasienregistrasi_t')->where('norec','conflict')->update(['noantrian'=>'1']);$this->assertSame('CONFLICT',app(KioskReservationFactsService::class)->fetch('pat-A','2026-11-18')['reservations'][0]['state']);}

 // Jhon | 2026-11-19 21:31 WITA | TAMBAH: metadata kedaluwarsa tetap scoped pasien/profil dan kode dikirim dari APR existing; SELECT saja.
 public function test_expired_metadata_is_scoped_and_active_code_is_in_facts(){
  $this->apr('other-past',1,'2026-11-17','other-patient');$this->apr('tenant-past',1,'2026-11-17','pat-A',2);
  $s=app(KioskReservationFactsService::class);$this->assertFalse($s->fetch('pat-A','2026-11-18')['expired_reservations_present']);
  $this->apr('past',1,'2026-11-17');DB::connection('pgsql')->enableQueryLog();
  $r=$s->fetch('pat-A','2026-11-18');$this->assertTrue($r['expired_reservations_present']);$this->assertEmpty($r['reservations']);
  foreach(DB::connection('pgsql')->getQueryLog()as$q)$this->assertSame(0,strpos(strtolower(ltrim($q['query'])),'select'));
  $this->apr('today');$r=$s->fetch('pat-A','2026-11-18');$this->assertSame('Wtoday',$r['reservations'][0]['booking_code']);$this->assertSame('ACTIVE',$r['reservations'][0]['state']);
 }
 public function test_reservation_endpoint_requires_signature_and_returns_existing_contract(){
  config(['api_service.client_token'=>'fixture-token','api_service.client_secret'=>'fixture-secret','cache.default'=>'array']);
  $this->getJson('/api/service/kiosk/reservation-check?patient_id=pat-A&service_date=2026-11-18')->assertStatus(401);
  $this->apr('signed');$uri='/api/service/kiosk/reservation-check?patient_id=pat-A&service_date=2026-11-18&nonce=fixture-phase2';$time=(string)time();
  $sig=hash_hmac('sha256',implode("\n",['GET',$uri,$time,hash('sha256','')]),'fixture-secret');
  // Jhon | 2026-11-19 21:33 WITA | UBAH: GET bertanda tangan memakai body kosong persis adapter, bukan getJson yang menyisipkan JSON array kosong.
  $this->call('GET',$uri,[],[],[],['HTTP_ACCEPT'=>'application/json','HTTP_X_TOKEN'=>'fixture-token','HTTP_X_TIMESTAMP'=>$time,'HTTP_X_SIGNATURE'=>$sig],'')->assertOk()->assertJsonPath('data.patient_id','pat-A')->assertJsonPath('data.reservations.0.booking_code','Wsigned');
 }
}
