<?php

namespace App\Http\Controllers\Api\Service;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LaboratoriumController extends Controller
{
    //
    public function GetHasil(Request $request)
    {
        $request->validate([
            'noorder' => 'required|string',
        ]);

        $noorder = $request->input('noorder');

        $dataBrid = DB::connection('sqlsrv_lis')
            ->table('reshd as rh')
            ->join(
                'resdt as rd',
                'rd.ONO',
                '=',
                'rh.ONO'
            )
            ->where('rh.ONO', $noorder)
            ->where('rd.RESULT_VALUE', '!=', '!')
            ->where('rd.RESULT_FT', '!=', '!')
            ->orderBy('rd.DISP_SEQ')
            ->orderBy('rd.TEST_NM', 'desc')
            ->select(
                'rd.*',
                'rh.CLINICIAN_NM',
                'rh.COMMENT',
                'rh.clinician_info'
            )
            ->get();

        $nobilling = DB::connection('sqlsrv_lis')
            ->table('reshd as rh')
            ->where('rh.ONO', $noorder)
            ->select('rh.LNO')
            ->first();

        $groupedData = [];

        foreach ($dataBrid as $item) {

            $validateBy = !empty($item->VALIDATE_BY)
                ? explode('^', $item->VALIDATE_BY)
                : [];

            $releaseBy = !empty($item->RELEASE_BY)
                ? explode('^', $item->RELEASE_BY)
                : [];

            $diotorisasi = $validateBy[1]
                ?? $validateBy[0]
                ?? '-';

            $dokterdiperiksa = $releaseBy[1]
                ?? $releaseBy[0]
                ?? '-';

            $formattedItem = [
                'namaproduk' =>
                $item->ORDER_TESTNM ?? '-',

                'detailpemeriksaan' =>
                $item->TEST_NM ?? '-',

                'hasil' =>
                $item->RESULT_VALUE ?? null,

                'result_ft' =>
                $item->RESULT_FT ?? null,

                'test_group' =>
                $item->TEST_GROUP ?? null,

                'flag' =>
                $item->FLAG ?? null,

                'nilaitext' =>
                $item->REF_RANGE ?? null,

                'satuanstandar' =>
                $item->UNIT ?? null,

                'tglhasil' =>
                $this->formatTimestamp(
                    $item->VALIDATE_ON ?? null
                ),

                'noorder' =>
                $item->ONO ?? null,

                'metode' =>
                $item->METHOD ?? null,

                'comment' =>
                $item->TEST_COMMENT ?? null,
                'commentheader' =>
                $item->COMMENT ?? null,
            ];

            $specialDetail = in_array(
                $item->TEST_NM ?? '',
                [
                    'Rhesus',
                    'Golongan Darah',
                ]
            );

            $groupKey = $specialDetail
                ? ($item->ORDER_TESTNM ?? 'LAINNYA')
                : ($item->TEST_GROUP ?? 'LAINNYA');

            if (!isset($groupedData[$groupKey])) {

                $groupedData[$groupKey] = [
                    'group' => $groupKey,
                    'items' => [],
                    'diotorisasi' =>
                    $diotorisasi,
                    'dokterdiperiksa' =>
                    $dokterdiperiksa,
                ];
            }

            $groupedData[$groupKey]['items'][]
                = $formattedItem;
        }

        return response()->json([
            'status' => true,
            'message' => 'Data hasil laboratorium berhasil diambil',

            'data' => [
                'noorder' => $noorder,

                'nobilling' => $nobilling
                    ? trim($nobilling->LNO)
                    : null,

                'hasil' => array_values($groupedData),
                
            ],
        ], 200);
    }
    private function formatTimestamp($value)
    {
        if (empty($value)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)
                ->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return $value;
        }
    }
}
