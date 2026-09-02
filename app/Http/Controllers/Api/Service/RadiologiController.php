<?php

namespace App\Http\Controllers\Api\Service;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RadiologiController extends Controller
{
    //
    public function GetHasil(Request $request)
    {
        $data = DB::connection('sqlsrv_ris')
            ->table('ris_in')
            ->limit(10)
            ->get();
            return response()->json([
                'status' => true,
                'message' => 'Data berhasil diambil',
                'data' => $data
            ]);
       
    }
    public function GetPemeriksaanByNRM(Request $request)
    {
        $nrm = $request->query('nrm');
        $data = DB::connection('sqlsrv_ris')
            
            ->table('ris_out')
            ->leftJoin('ris_in', 'ris_out.no_rontgen', '=', 'ris_in.no_rontgen')
            ->select('ris_out.*', 'ris_in.nama_pemeriksaan','ris_in.nama_pasien')
            ->where('ris_in.no_rm', $nrm)
            ->limit(10)
            ->get();
            return response()->json([
                'status' => true,
                'message' => 'Data berhasil diambil',
                'data' => $data
            ]);
       
    }
}
