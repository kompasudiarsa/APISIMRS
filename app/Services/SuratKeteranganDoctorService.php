<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
// Jhon | 2026-11-13 19:42 WITA | TAMBAH: adaptasi legalityCheck/externalDataPegawai SIMRS, tanpa memilih dokter ambigu atau menyalin seluruh data pegawai.
class SuratKeteranganDoctorService
{
    public function enrich(array $document, int $profile): array
    {
        $doctor=$document['DDDokter']??$document['dokterPemeriksa']??$document['CBDokter']??data_get($document,'registrasi.dokter');
        $id=is_array($doctor)?($doctor['value']??$doctor['id']??null):null;
        $name=is_array($doctor)?($doctor['label']??null):(is_scalar($doctor)?$doctor:null);
        $document['_report_warnings']=[];
        if (!$id && !$name) { $document['_report_warnings'][]='Identitas dokter belum tersedia pada dokumen.'; return $document; }
        $query=DB::connection('pgsql')->table('pegawai_m')->where('kdprofile',$profile);
        if ($id!==null && $id!=='') $query->where('id',$id);
        else $query->where('namalengkap',(string)$name);
        $rows=$query->limit(2)->get();
        if ($rows->count()!==1) {
            $document['_report_warnings'][]='Identitas dokter belum cocok secara unik dengan master pegawai.';
            return $document;
        }
        $pegawai=$rows->first();
        // Jhon | 2026-11-13 19:42 WITA | TAMBAH: whitelist field legalitas; pertahankan snapshot yang sudah tersimpan pada surat.
        foreach (['nip','nosip','noskp','nosippk','nipppk'] as $field) {
            // Jhon | 2026-11-13 19:43 WITA | UBAH: snapshot scalar tidak ditimpa data master yang lebih baru.
            if (isset($document[$field]) && is_scalar($document[$field]) && (string)$document[$field] !== '') {
                $document[$field]=[$field=>$document[$field]];
            }
            if (empty(data_get($document,$field.'.'.$field))) {
                $value=$pegawai->$field??null;
                if ($value!==null && $value!=='') $document[$field]=[$field=>$value];
            }
        }
        return $document;
    }
}
