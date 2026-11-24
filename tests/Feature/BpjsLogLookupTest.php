<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\BpjsLogLookupService;
use Illuminate\Support\Facades\DB;
// Jhon | 2026-11-24 13:58 WITA | TAMBAH: Query PostgreSQL dikompilasi dan dieksekusi pada mock; tanpa koneksi database pasien.
class BpjsLogLookupTest extends TestCase
{
    public function test_queries_have_fixed_url_method_exact_json_binding_and_limit()
    {
        foreach (['antrean'=>'000abc','surat-kontrol'=>'000SEP'] as $kind=>$value) {
            $connection = \Mockery::mock(\Illuminate\Database\ConnectionInterface::class);
            $builder = new \Illuminate\Database\Query\Builder($connection, new \Illuminate\Database\Query\Grammars\PostgresGrammar, new \Illuminate\Database\Query\Processors\PostgresProcessor);
            $connection->shouldReceive('getQueryGrammar')->andReturn(new \Illuminate\Database\Query\Grammars\PostgresGrammar);
            $connection->shouldReceive('select')->once()->withArgs(function($sql,$bindings)use($kind,$value){
                $this->assertStringStartsWith('select "url", "method", "data", "response" from "log_bpjs_t"', $sql);
                $this->assertStringContainsString('limit 3', $sql);
                $this->assertSame(BpjsLogLookupService::URLS[$kind],$bindings[0]);$this->assertSame('POST',$bindings[1]);
                $this->assertStringContainsString('"'.$value.'"',$bindings[2]);
                $this->assertCount($kind==='antrean'?3:4,$bindings);
                if($kind!=='antrean')$this->assertStringContainsString('null',$bindings[3]);
                return true;
            })->andReturn([(object)['data'=>'dummy1'],(object)['data'=>'dummy2'],(object)['data'=>'dummy3']]);
            $wrapper = \Mockery::mock();$wrapper->shouldReceive('table')->with('log_bpjs_t')->andReturn($builder->from('log_bpjs_t'));
            DB::swap(\Mockery::mock(\Illuminate\Database\DatabaseManager::class));
            DB::shouldReceive('connection')->with('pgsql')->andReturn($wrapper);
            $out=(new BpjsLogLookupService)->search($kind,$value,2);$this->assertCount(2,$out['data']);$this->assertTrue($out['meta']['truncated']);
        }
    }
    private function signed($path,array $payload)
    {
        config(['api_service.client_token'=>'dummy-token','api_service.client_secret'=>'dummy-secret','cache.default'=>'array']);
        $uri='/api/service/bpjs-log/'.$path.'?nonce='.bin2hex(random_bytes(12));$body=json_encode($payload);$time=(string)time();
        $sig=hash_hmac('sha256',implode("\n",['POST',$uri,$time,hash('sha256',$body)]),'dummy-secret');
        return $this->call('POST',$uri,[],[],[],['CONTENT_TYPE'=>'application/json','HTTP_ACCEPT'=>'application/json','HTTP_X_TOKEN'=>'dummy-token','HTTP_X_TIMESTAMP'=>$time,'HTTP_X_SIGNATURE'=>$sig],$body);
    }
    public function test_guest_denied(){$this->postJson('/api/service/bpjs-log/antrean',['kodebooking'=>'dummy'])->assertStatus(401);}
    public function test_signed_valid_requests_and_empty_results()
    {
        $s=\Mockery::mock(BpjsLogLookupService::class);$this->app->instance(BpjsLogLookupService::class,$s);
        foreach(['antrean'=>['kodebooking'=>'000abc'],'surat-kontrol'=>['noSEP'=>'000SEP']]as$path=>$payload){$s->shouldReceive('search')->once()->with($path,reset($payload),20)->andReturn(['data'=>[],'meta'=>['count'=>0,'limit'=>20,'truncated'=>false]]);$this->signed($path,$payload)->assertOk()->assertJsonPath('data',[])->assertHeader('Cache-Control','no-store, private');}
    }
    public function test_invalid_inputs_rejected(){foreach([[],['kodebooking'=>'%'],['kodebooking'=>123],['kodebooking'=>'abc','limit'=>101]]as$p)$this->signed('antrean',$p)->assertStatus(422);}
    public function test_read_failure_is_not_empty_success(){\Illuminate\Support\Facades\Log::shouldReceive('warning')->once()->with('bpjs_log_lookup_failed',['kind'=>'antrean']);$s=\Mockery::mock(BpjsLogLookupService::class);$s->shouldReceive('search')->andThrow(new \RuntimeException('secret database failure'));$this->app->instance(BpjsLogLookupService::class,$s);$this->signed('antrean',['kodebooking'=>'dummy'])->assertStatus(503)->assertDontSee('secret');}
}
