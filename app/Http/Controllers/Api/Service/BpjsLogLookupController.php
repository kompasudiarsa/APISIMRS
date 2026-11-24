<?php
namespace App\Http\Controllers\Api\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\BpjsLogLookupService;
// Jhon | 2026-11-24 13:55 WITA | TAMBAH: Signed POST agar identitas tidak masuk URL/access log; validasi eksplisit dan error aman.
class BpjsLogLookupController
{
    public function antrean(Request $r, BpjsLogLookupService $s) { return $this->lookup($r, $s, 'antrean', 'kodebooking'); }
    public function suratKontrol(Request $r, BpjsLogLookupService $s) { return $this->lookup($r, $s, 'surat-kontrol', 'noSEP'); }
    private function lookup(Request $r, BpjsLogLookupService $s, string $kind, string $field)
    {
        $v = Validator::make($r->all(), [$field => 'required|string|max:64|regex:/\A[a-zA-Z0-9.\-]+\z/', 'limit' => 'sometimes|integer|min:1|max:100']);
        if ($v->fails()) return response()->json(['message' => 'Parameter pencarian tidak valid.', 'errors' => $v->errors()], 422)->header('Cache-Control', 'private, no-store');
        try {
            $input = $v->validated();
            return response()->json($s->search($kind, $input[$field], (int)($input['limit'] ?? 20)))->header('Cache-Control', 'private, no-store');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('bpjs_log_lookup_failed', ['kind' => $kind]);
            return response()->json(['message' => 'Pencarian log BPJS belum tersedia.'], 503)->header('Cache-Control', 'private, no-store');
        }
    }
}
