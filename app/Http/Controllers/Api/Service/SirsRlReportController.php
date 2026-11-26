<?php
namespace App\Http\Controllers\Api\Service;
use App\Http\Controllers\Controller;
use App\Services\SirsRlReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// Jhon | 2026-11-25 19:41 WITA | TAMBAH: Whitelist laporan RL dan periode tervalidasi sebelum query sumber.
class SirsRlReportController extends Controller
{
    const REPORTS = [
        'rl3-1' => 'getDataRL3_1',
        'rl3-2' => 'getDataRL3_2',
        'rl3-3' => 'getDataRL3_3',
        'rl3-4' => 'getDataRL3_4',
        'rl3-5' => 'getDataRL3_5',
        'rl3-6' => 'getDataRL3_6',
        'rl3-7' => 'getDataRL3_7',
        'rl3-8' => 'getDataRL3_8',
        'rl3-9' => 'getDataRL3_9',
        'rl3-10' => 'getDataRL3_10',
        'rl3-11' => 'getDataRL3_11',
        'rl3-11-detail' => 'getDataRL3_11_detail',
        'rl3-12' => 'getDataRL3_12',
        'rl3-13' => 'getDataRL3_13',
        'rl3-14' => 'getDataRL3_14',
        'rl3-15' => 'getDataRL3_15',
        'rl3-16' => 'getDataRL3_16',
        'rl3-17' => 'getDataRL3_17',
        'rl3-18' => 'getDataRL3_18',
        'rl3-19' => 'getDataRL3_19',
        'rl4-1' => 'getDataRL4_1',
        'rl4-2' => 'getDataRL4_2',
        'rl4-3' => 'getDataRL4_3',
        'rl5-1' => 'getDataRL5_1',
        'rl5-2' => 'getDataRL5_2',
        'rl5-3' => 'getDataRL5_3',
    ];
    public function index()
    {
        return response()->json(['data' => array_keys(self::REPORTS)]);
    }
    public function show(Request $request, string $report, SirsRlReportService $service)
    {
        if (!isset(self::REPORTS[$report])) return response()->json(['message' => 'Laporan tidak ditemukan.'], 404);
        $validator = Validator::make($request->only(['bulan','tahun','tglAwal','tglAkhir','idkonten']), [
            'bulan' => 'required|integer|between:1,12',
            'tahun' => 'required|integer|between:1900,2100',
            'tglAwal' => 'nullable|date_format:Y-m-d',
            'tglAkhir' => 'nullable|date_format:Y-m-d|after_or_equal:tglAwal',
            'idkonten' => ($report === 'rl3-11-detail' ? 'required' : 'nullable').'|integer|min:1',
        ]);
        if ($validator->fails()) return response()->json(['message' => 'Periode tidak valid.', 'errors' => $validator->errors()], 422);
        $v = $validator->validated();
        $month = sprintf('%04d-%02d-01', $v['tahun'], $v['bulan']);
        $start = $v['tglAwal'] ?? $month;
        $end = $v['tglAkhir'] ?? date('Y-m-t', strtotime($month));
        if ($report !== 'rl3-2' && (substr($start,0,7) !== substr($month,0,7) || substr($end,0,7) !== substr($month,0,7))) {
            return response()->json(['message' => 'Tanggal harus berada dalam bulan dan tahun laporan.'], 422);
        }
        $v['tglAwal'] = $start.' 00:00:00';
        $v['tglAkhir'] = $end.' 23:59:59';
        try {
            return response()->json($service->run(self::REPORTS[$report], new Request($v)));
        // Jhon | 2026-11-25 21:04 WITA | TAMBAH: diagnosis terstruktur tanpa SQL, binding, data pasien atau kredensial.
        } catch (\DomainException $e) {
            $setting = preg_match('/^[A-Za-z0-9_]{1,100}$/', $e->getMessage()) ? $e->getMessage() : null;
            return response()->json(['error_code'=>'RL_SETTING_MISSING', 'setting'=>$setting,
                'message'=>'Pengaturan laporan RL untuk profil 1 belum tersedia.'], 503);
        } catch (\Illuminate\Database\QueryException $e) {
            // Jhon | 2026-11-25 21:06 WITA | UBAH: baca SQLSTATE dari PDO sebelumnya, hanya kode 5 karakter yang boleh keluar.
            $previous = $e->getPrevious();
            $state = (string) ($e->errorInfo[0] ?? $e->getCode());
            if (!preg_match('/^[A-Z0-9]{5}$/', $state) && $previous
                && preg_match('/SQLSTATE\[([A-Z0-9]{5})\]/', $previous->getMessage(), $match)) $state = $match[1];
            $connectionError = $previous && preg_match('/connection refused|could not connect|connection timed out|could not translate host name|no connection to the server/i', $previous->getMessage());
            $safeState = preg_match('/^[A-Z0-9]{5}$/', $state) ? $state : null;
            // Jhon | 2026-11-25 21:07 WITA | TAMBAH: klasifikasi exception non-PDO dan penanda revisi diagnosis tanpa exception mentah.
            $reason = 'unclassified';
            $reasonPatterns = ['binding-array'=>'/Array to string conversion/i', 'binding-object'=>'/could not be converted to string/i',
                'driver-missing'=>'/could not find driver/i', 'db-password'=>'/password authentication failed|no password supplied/i',
                'db-access'=>'/no pg_hba.conf entry|permission denied/i', 'db-connection'=>'/connection refused|could not connect|could not translate host name|timeout expired/i'];
            foreach ($reasonPatterns as $name=>$pattern) { if ($previous && preg_match($pattern,$previous->getMessage())) { $reason=$name; break; } }
            $exceptionType = $previous ? class_basename($previous) : class_basename($e);
            $codes = ['42P01'=>'RL_TABLE_MISSING','42703'=>'RL_COLUMN_MISSING','22P02'=>'RL_VALUE_INVALID',
                '42883'=>'RL_QUERY_TYPE','42804'=>'RL_QUERY_TYPE','42702'=>'RL_AMBIGUOUS_COLUMN','42803'=>'RL_GROUPING_ERROR','42501'=>'RL_DB_PERMISSION','3D000'=>'RL_DATABASE_MISSING','08006'=>'RL_DB_CONNECTION','08001'=>'RL_DB_CONNECTION','28P01'=>'RL_DB_AUTH'];
            return response()->json(['error_code'=>$reason === 'driver-missing' ? 'RL_DB_DRIVER' : ($connectionError ? 'RL_DB_CONNECTION' : ($codes[$state] ?? 'RL_QUERY_FAILED')), 'sqlstate'=>$safeState, 'diagnostic_version'=>'RL-D3', 'reason'=>$reason, 'exception_type'=>$exceptionType,
                'message'=>'Query laporan tidak dapat dijalankan pada database API-SIMRS.'], 503);
        } catch (\Throwable $e) {
            $code = strpos($e->getMessage(), 'could not find driver') !== false ? 'RL_DB_DRIVER' : 'RL_CALCULATION_FAILED';
            // Jhon | 2026-11-26 12:58 WITA | UBAH: semua error runtime membawa revisi; tanpa teks exception bebas atau stack/data pasien.
            if ($e->getMessage() === 'Mapping layanan khusus ambigu.') $code = 'RL_MAPPING_AMBIGUOUS';
            $origin = null;
            if (basename($e->getFile()) === 'SirsRl32V1Service.php') $origin = 'RL32:'.$e->getLine();
            foreach ($e->getTrace() as $frame) {
                if (isset($frame['class']) && $frame['class'] === \App\Services\SirsRl32V1Service::class) {
                    $origin = 'RL32:'.($frame['function'] ?? 'run');
                    break;
                }
            }
            return response()->json(['error_code'=>$code, 'diagnostic_version'=>'RL-D4',
                'exception_type'=>class_basename($e), 'origin'=>$origin,
                // Jhon | 2026-11-26 13:00 WITA | TAMBAH: identitas master ruangan untuk pembenahan mapping yang tepat.
                'mapping_conflict'=>$e instanceof \App\Exceptions\RlMappingConflictException ? $e->room : null,
                'message'=>'Laporan belum dapat dihitung pada server API-SIMRS.'], 503);
        }
    }
}
