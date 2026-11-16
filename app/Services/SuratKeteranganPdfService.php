<?php
namespace App\Services;
// Jhon | 2026-11-13 20:13 WITA | TAMBAH: adaptasi terbatas cetakEMR backend untuk surat yang sudah lolos filter pemilik API.
class SuratKeteranganPdfService
{
    private const VIEWS=['SuratKeteranganSehat','SuratKeteranganSakit','SuratKeteranganDiagnosa','SuratKeteranganSehatJiwa','SuratKeteranganNarkoba','SuratKeteranganSehatParu','SuratKeteranganHasilPemeriksaanMata','SuratKeteranganHemodialisa','SuratKeteranganRekomendasiPsikologi','SuratKeteranganDalamMasaPerawatan','SuratKeteranganDirawatOpname','SuratKeteranganDisabilitas','SuratKeteranganGawatDarurat','SuratKeteranganHamil','SuratKeteranganKematian'];
    public function render(string $collection, array $data)
    {
        if (!in_array($collection,self::VIEWS,true)) return response()->json(['code'=>'PDF_UNSUPPORTED','message'=>'Format PDF surat belum didukung.'],422);
        if (!class_exists(\Dompdf\Dompdf::class) || !class_exists(\Endroid\QrCode\QrCode::class)) {
            return response()->json(['code'=>'PDF_DEPENDENCY_MISSING','message'=>'Generator PDF/QR belum terpasang. Jalankan pemasang PDF pada host API-SIMRS.'],503);
        }
        // Jhon | 2026-11-13 20:15 WITA | TAMBAH: PNG QR memerlukan GD; validasi prasyarat sebelum render.
        foreach (['gd','dom','mbstring'] as $extension) {
            if (!extension_loaded($extension)) return response()->json(['code'=>'PDF_EXTENSION_MISSING','extension'=>$extension,'message'=>'Ekstensi PHP untuk PDF/QR belum lengkap.'],503);
        }
        $verification=(string)config('surat_keterangan.verification_url');
        // Jhon | 2026-11-13 21:59 WITA | TAMBAH: fallback URL hasil resolusi route backend, tanpa membaca .env atau menyalin kunci backend.
        if ($verification==='') {
            $urlFile=storage_path('app/simrs-verification-url.json');
            if (is_file($urlFile)) {
                $saved=json_decode(file_get_contents($urlFile),true);
                $verification=is_array($saved)&&is_string($saved['verification_url']??null)?$saved['verification_url']:'';
            }
        }

        if (!filter_var($verification,FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($verification,PHP_URL_SCHEME)),['http','https'],true)
            || parse_url($verification,PHP_URL_USER) || parse_url($verification,PHP_URL_PASS) || parse_url($verification,PHP_URL_QUERY) || parse_url($verification,PHP_URL_FRAGMENT)) {
            return response()->json(['code'=>'PDF_VERIFICATION_URL_MISSING','message'=>'Alamat route dokumen.signature SIMRS belum dikonfigurasi dengan benar.'],503);
        }
        $mongoId=$data['_id']??null;
        if (is_array($mongoId)) $mongoId=$mongoId['$oid']??null;
        if (!is_scalar($mongoId) || (string)$mongoId==='') return response()->json(['code'=>'PDF_DOCUMENT_ID_MISSING','message'=>'ID MongoDB dokumen untuk QR belum tersedia.'],422);
        // Jhon | 2026-11-13 20:13 WITA | TAMBAH: payload/URL QR mengikuti cetakEMR, bukan QR nama dokter atau tanda tangan palsu.
        $key=base64_encode($collection.';'.(string)$mongoId);
        $qrUrl=$verification.'?'.http_build_query(['key'=>$key,'a'=>'0'],'','&',PHP_QUERY_RFC3986);
        // Jhon | 2026-11-13 21:55 WITA | TAMBAH: kode kegagalan aman, tidak mengembalikan exception generator.
        try {
        $qr=new \Endroid\QrCode\QrCode($qrUrl);
        $qr->setSize(125);$qr->setMargin(5);
        $qr->setErrorCorrectionLevel(new \Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow());
        $image=(new \Endroid\QrCode\Writer\PngWriter())->write($qr);
        $base64=base64_encode($image->getString());
        } catch (\Throwable $exception) {
            return response()->json(['code'=>'PDF_QR_FAILED','message'=>'QR verifikasi belum dapat dibuat.'],503);
        }
        foreach (['nip','nosip','noskp','nosippk','nipppk'] as $field) {
            if (isset($data[$field]) && is_scalar($data[$field])) $data[$field]=(object)[$field=>$data[$field]];
            elseif (isset($data[$field]) && is_array($data[$field])) $data[$field]=(object)$data[$field];
        }
        $data['DDDokter']=$data['DDDokter']??'';$data['registrasi']=$data['registrasi']??[];
        // Jhon | 2026-11-13 21:55 WITA | TAMBAH: bedakan template tidak sesuai dari kegagalan koneksi.
        try {
        $html=view('Report.'.$collection,[
            'data'=>$data,'cekWargaNegaraWNA'=>false,'tte'=>$base64,'qrcode'=>$base64,
            'reportWarnings'=>$data['_report_warnings']??[],'reportMissingFields'=>[],
            'reportTitle'=>$collection,
        ])->render();
        } catch (\Throwable $exception) {
            return response()->json(['code'=>'PDF_TEMPLATE_FAILED','message'=>'Template surat belum dapat dirender.'],503);
        }
        // Jhon | 2026-11-13 20:13 WITA | TAMBAH: PDF tidak mengunduh aset jarak jauh; gambar kop/logo lokal dibatasi chroot public.
        $options=new \Dompdf\Options();$options->set('isRemoteEnabled',false);$options->set('isPhpEnabled',false);$options->set('isJavascriptEnabled',false);
        $options->set('chroot',public_path());$options->set('defaultFont','DejaVu Sans');
        try {
        $pdf=new \Dompdf\Dompdf($options);$pdf->setPaper('A4','portrait');$pdf->loadHtml($html,'UTF-8');$pdf->render();
        $binary=$pdf->output();
        } catch (\Throwable $exception) {
            return response()->json(['code'=>'PDF_RENDER_FAILED','message'=>'Generator PDF belum dapat menyelesaikan surat.'],503);
        }
        return response($binary,200,[
            'Content-Type'=>'application/pdf','Content-Disposition'=>'inline; filename="'.$collection.'.pdf"',
            'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff',
        ]);
    }
}
