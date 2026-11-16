<?php
namespace Tests\Unit;
use Tests\TestCase;
use App\Services\SuratKeteranganDoctorService;
use Illuminate\Support\Facades\DB;
use Mockery;
// Jhon | 2026-11-13 19:43 WITA | TAMBAH: fixture pencocokan dokter unik, ambigu, dan perlindungan snapshot surat.
class SuratKeteranganDoctorTest extends TestCase
{
    private function staffRows(array $rows): void
    {
        $connection=Mockery::mock();$query=Mockery::mock();
        DB::shouldReceive('connection')->with('pgsql')->once()->andReturn($connection);
        $connection->shouldReceive('table')->with('pegawai_m')->once()->andReturn($query);
        $query->shouldReceive('where')->with('kdprofile',1)->once()->andReturnSelf();
        $query->shouldReceive('where')->with('namalengkap','Dokter Dummy')->once()->andReturnSelf();
        $query->shouldReceive('limit')->with(2)->once()->andReturnSelf();
        $query->shouldReceive('get')->once()->andReturn(collect($rows));
    }
    public function test_unique_doctor_adds_only_whitelisted_metadata(): void
    {
        $this->staffRows([(object)['nip'=>'DUMMY-1','nosip'=>'DUMMY-2','email'=>'not-for-response@example.test']]);
        $result=(new SuratKeteranganDoctorService)->enrich(['DDDokter'=>'Dokter Dummy'],1);
        $this->assertSame('DUMMY-1',$result['nip']['nip']);
        $this->assertArrayNotHasKey('email',$result);
    }
    public function test_ambiguous_name_does_not_choose_first_doctor(): void
    {
        $this->staffRows([(object)['nip'=>'A'],(object)['nip'=>'B']]);
        $result=(new SuratKeteranganDoctorService)->enrich(['DDDokter'=>'Dokter Dummy'],1);
        $this->assertArrayNotHasKey('nip',$result);$this->assertNotEmpty($result['_report_warnings']);
    }
    public function test_existing_snapshot_is_preserved(): void
    {
        $this->staffRows([(object)['nip'=>'NEW']]);
        $result=(new SuratKeteranganDoctorService)->enrich(['DDDokter'=>'Dokter Dummy','nip'=>'SNAPSHOT'],1);
        $this->assertSame('SNAPSHOT',$result['nip']['nip']);
    }
    public function test_missing_doctor_does_not_query_staff_database(): void
    {
        DB::shouldNotReceive('connection');
        $result=(new SuratKeteranganDoctorService)->enrich([],1);
        $this->assertNotEmpty($result['_report_warnings']);
    }
}
