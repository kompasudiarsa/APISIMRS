<?php
namespace Tests\Feature;
use App\Http\Controllers\Api\Service\SirsRlReportController;
use App\Services\SirsRlReportService;
use Illuminate\Http\Request;
use Tests\TestCase;
// Jhon | 2026-11-25 19:42 WITA | TAMBAH: Kontrak adapter tanpa data pasien/database.
class SirsRlReportTest extends TestCase
{
    public function test_unknown_report_is_rejected_before_query()
    {
        $service = $this->mock(SirsRlReportService::class);
        $service->shouldNotReceive('run');
        $response = (new SirsRlReportController)->show(new Request(), 'invalid', $service);
        $this->assertSame(404, $response->getStatusCode());
    }
    public function test_invalid_period_is_rejected_before_query()
    {
        $service = $this->mock(SirsRlReportService::class);
        $service->shouldNotReceive('run');
        $response = (new SirsRlReportController)->show(new Request(['bulan'=>'1 OR 1=1','tahun'=>2026]), 'rl3-1', $service);
        $this->assertSame(422, $response->getStatusCode());
    }
    public function test_month_bounds_and_source_envelope_are_preserved()
    {
        $fixture = ['metaData'=>['code'=>200,'message'=>'Success'],'response'=>['data'=>[]]];
        $service = $this->mock(SirsRlReportService::class);
        $service->shouldReceive('run')->once()->withArgs(function ($method, $request) {
            return $method === 'getDataRL3_1' && $request->tglAwal === '2024-02-01 00:00:00'
                && $request->tglAkhir === '2024-02-29 23:59:59' && !$request->has('sql');
        })->andReturn($fixture);
        $response = (new SirsRlReportController)->show(new Request(['bulan'=>2,'tahun'=>2024,'sql'=>'ignored']), 'rl3-1', $service);
        $this->assertSame($fixture, $response->getData(true));
    }
}
