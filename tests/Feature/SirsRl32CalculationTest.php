<?php
namespace Tests\Feature;
use App\Services\SirsRl32V1Service;
use Tests\TestCase;
// Jhon | 2026-11-25 21:30 WITA | TAMBAH: fixture hari per kelas dan batas kematian aktual 48 jam tanpa database/pasien nyata.
class SirsRl32CalculationTest extends TestCase
{
    private function invoke($service, $name, array &$row, $item): void
    {
        $method = new \ReflectionMethod($service, $name);
        $method->setAccessible(true);
        $args = [&$row, $item];
        $method->invokeArgs($service, $args);
    }
    public function test_class_days_equal_total_days_including_unknown_class()
    {
        $service = new SirsRl32V1Service;
        $row = array_fill_keys(['rincianvvip','rincianvip','rinciankel1','rinciankel2','rinciankel3','rinciankelaskhusus'],0);
        foreach ([[5,2],[4,3],[3,4],[2,5],[1,6],[null,7]] as $pair) {
            $this->invoke($service,'accumulateKelas',$row,(object)['objectkelasfk'=>$pair[0],'lamadirawat'=>$pair[1]]);
        }
        $this->assertEquals(27,array_sum(array_intersect_key($row,array_flip(['rincianvvip','rincianvip','rinciankel1','rinciankel2','rinciankel3','rinciankelaskhusus']))));
        $this->assertEquals(1,$row['_kelas_belum_terpetakan']);
    }
    public function test_death_48_hour_boundary_and_duplicate_episode()
    {
        $service = new SirsRl32V1Service;
        foreach (['periodStart'=>strtotime('2026-11-01'),'periodEnd'=>strtotime('2026-11-30 23:59:59')] as $name=>$value) {
            $property=new \ReflectionProperty($service,$name);$property->setAccessible(true);$property->setValue($service,$value);
        }
        $row=array_fill_keys(['pasienawalbulan','pasienakhirbulan','pasienmasuk','pasienpindahan','pasiendipindahkan','pasienkeluarhidup','matikurang48jaml','matilebih48jaml','matikurang48jamp','matilebih48jamp'],0);
        $base=['tglregistrasi'=>'2026-11-01','tanggal_masuk_asli'=>'2026-11-01 00:00:00','objectstatuskeluarfk'=>5,'objectjeniskelaminfk'=>1,'lamadirawat'=>2,'objectdepartemenfk'=>16,'deptasal'=>16,'objectruanganasalfk'=>1,'objectruanganlastfk'=>1,'statuskeluar'=>'Meninggal','tglpulang'=>null];
        $first=(object)array_merge($base,['registration_id'=>'dummy1','tglmeninggal'=>'2026-11-02 23:59:59']);
        $second=(object)array_merge($base,['registration_id'=>'dummy2','tglmeninggal'=>'2026-11-03 00:00:00']);
        $this->invoke($service,'accumulatePasienStatus',$row,$first);
        $this->invoke($service,'accumulatePasienStatus',$row,$first);
        $this->invoke($service,'accumulatePasienStatus',$row,$second);
        $this->assertEquals(1,$row['matikurang48jaml']);
        $this->assertEquals(1,$row['matilebih48jaml']);
        $this->assertEquals(0,$row['pasienkeluarhidup']);
    }
    // Jhon | 2026-11-26 13:02 WITA | TAMBAH: kasus nyata ICCU319 dan konflik yang tidak boleh ditebak.
    public function test_special_service_resolution_requires_one_exact_room_token()
    {
        $service = new SirsRl32V1Service;
        $method = new \ReflectionMethod($service, 'resolveServiceFromRoomName');
        $method->setAccessible(true);
        $this->assertSame('ICCU/ICVCU', $method->invoke($service, 'RAWAT INAP ICCU', ['ICCU/ICVCU','RICU']));
        $this->assertSame('RICU', $method->invoke($service, 'RAWAT INAP RICU', ['ICU','RICU']));
        $this->assertSame('PICU', $method->invoke($service, 'RAWAT INAP PICU', ['ICU','PICU']));
        $this->assertNull($method->invoke($service, 'RAWAT INAP UMUM', ['ICCU/ICVCU','RICU']));
        $this->assertNull($method->invoke($service, 'ICCU / RICU', ['ICCU/ICVCU','RICU']));
    }
    // Jhon | 2026-11-26 22:05 WITA | TAMBAH: tujuh saldo layanan dari lampiran Oktober 2026.
    public function test_october_reference_closing_balances()
    {
        $method = new \ReflectionMethod(SirsRl32V1Service::class, 'calculateClosingBalance');
        $method->setAccessible(true);
        $keys = ['pasienawalbulan','pasienmasuk','pasienpindahan','pasiendipindahkan','pasienkeluarhidup','matikurang48jaml','matilebih48jaml','matikurang48jamp','matilebih48jamp'];
        foreach ([[44,1077,772,217,823,3,9,1,6,834],[2,29,29,19,11,2,2,1,2,23],[1,46,46,30,26,0,1,0,1,35],[0,20,19,0,17,1,0,0,0,21],[2,4,2,2,3,0,0,0,1,2],[0,32,28,11,19,0,2,0,0,28],[0,34,0,0,34,0,0,0,0,0]] as $fixture) {
            $expected = array_pop($fixture);
            $this->assertSame($expected, $method->invoke(new SirsRl32V1Service, array_combine($keys, $fixture)));
        }
    }
}
