<?php
namespace Tests\Feature;
use Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Services\OnlineReservationService;
// Jhon | 2026-11-25 08:26 WITA | TAMBAH: Jadwal bertanggal/mingguan dan scope diuji dengan SQLite memory dummy.
class OnlineDoctorDateTest extends TestCase {
 protected function setUp():void{parent::setUp();config(['database.connections.pgsql'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'']]);DB::purge('pgsql');Carbon::setTestNow(Carbon::parse('2026-11-25 08:00:00','Asia/Makassar'));
 foreach(['settingdatafixed_m'=>['kdprofile','statusenabled','namafield','nilaifield'],'slottinglibur_m'=>['kdprofile','statusenabled','tgllibur'],'pegawai_m'=>['id','kdprofile','statusenabled','objectjenispegawaifk','namalengkap'],'mappegawaitoruangan_m'=>['kdprofile','statusenabled','objectruanganfk','objectpegawaifk'],'jadwaldokter_m'=>['id','kdprofile','statusenabled','objectruanganfk','objectpegawaifk','tanggal','hari','jammulai','jamakhir','quota'],'antrianpasienregistrasi_t'=>['kdprofile','statusenabled','objectruanganfk','tanggalreservasi','noreservasi','jam']]as$t=>$cols)Schema::connection('pgsql')->create($t,function(Blueprint $b)use($cols){foreach($cols as$c)$b->string($c)->nullable();});
 $this->t('settingdatafixed_m')->insert(['kdprofile'=>1,'statusenabled'=>1,'namafield'=>'kdPsikologDokter','nilaifield'=>'1']);
 for($i=1;$i<=4;$i++){$this->t('pegawai_m')->insert(['id'=>$i,'kdprofile'=>1,'statusenabled'=>1,'objectjenispegawaifk'=>1,'namalengkap'=>'Dokter Dummy '.$i]);$this->t('mappegawaitoruangan_m')->insert(['kdprofile'=>1,'statusenabled'=>1,'objectruanganfk'=>10,'objectpegawaifk'=>$i]);}
 $this->schedule(1,['hari'=>'Senin']);$this->schedule(2,['tanggal'=>'2026-11-30','hari'=>'Selasa']);$this->schedule(3,['tanggal'=>'2026-12-07','hari'=>'Senin']);$this->schedule(4,['hari'=>'Selasa']);
 }
 protected function tearDown():void{Carbon::setTestNow();DB::purge('pgsql');parent::tearDown();}
 private function t($t){return DB::connection('pgsql')->table($t);}
 private function schedule($id,$v){$this->t('jadwaldokter_m')->insert(array_merge(['id'=>$id,'kdprofile'=>1,'statusenabled'=>1,'objectruanganfk'=>10,'objectpegawaifk'=>$id,'jammulai'=>'08:00','jamakhir'=>'10:00','quota'=>4],$v));}
 private function service(){return new class extends OnlineReservationService {public function rooms(){return collect([(object)['id'=>10]]);}};}
 public function test_only_doctors_on_selected_date_and_weekday(){DB::connection('pgsql')->enableQueryLog();$this->assertSame([1,2],$this->service()->doctors(10,'2026-11-30')->pluck('id')->map(function($v){return (int)$v;})->all());$this->assertSame([4],$this->service()->doctors(10,'2026-12-01')->pluck('id')->map(function($v){return (int)$v;})->all());foreach(DB::connection('pgsql')->getQueryLog()as$q)$this->assertStringStartsWith('select',strtolower(ltrim($q['query'])));}
 public function test_inactive_other_profile_room_and_zero_quota_excluded(){foreach([['statusenabled'=>0],['kdprofile'=>2],['objectruanganfk'=>11],['quota'=>0],['jamakhir'=>'07:00']]as$change){$this->t('jadwaldokter_m')->where('id',1)->update($change);$this->assertFalse($this->service()->doctors(10,'2026-11-30')->contains('id',1));$this->t('jadwaldokter_m')->where('id',1)->update(['statusenabled'=>1,'kdprofile'=>1,'objectruanganfk'=>10,'quota'=>4,'jamakhir'=>'10:00']);}}
 public function test_no_schedule_is_empty_and_holiday_rejected(){ $this->assertCount(0,$this->service()->doctors(10,'2026-12-02'));$this->t('slottinglibur_m')->insert(['kdprofile'=>1,'statusenabled'=>1,'tgllibur'=>'2026-11-30']);$this->expectException(\Illuminate\Validation\ValidationException::class);$this->service()->doctors(10,'2026-11-30');}
 public function test_slot_rejects_doctor_on_wrong_date(){$this->expectException(\Illuminate\Validation\ValidationException::class);$this->service()->slots(10,1,'2026-12-01');}
}
