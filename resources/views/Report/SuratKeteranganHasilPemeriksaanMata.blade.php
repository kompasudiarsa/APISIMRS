{{-- Jhon | 2026-11-13 20:13 WITA | TAMBAH: adapter PDF API dari view Nadi; tanpa kontrol browser. --}}
@extends('template.layout-emr-kop-surat')
@section('title')
    @if ($cekWargaNegaraWNA)
        Print Eye Examination Result Certificate
    @else
        Cetak Surat Keterangan Hasil Pemeriksaan Mata
    @endif
@endsection
@section('kode', 'RM.1G/SK/00')
@section('page-style')
    <style>
        body,
        table,
        td {
            font-family: 'Open Sans', sans-serif !important;
        }

        .center {
            vertical-align: middle;
            text-align: center
        }

        .border {
            border: 1px solid black;
        }

        .subJudul {
            vertical-align: middle;
            width: 25%;
        }

        .isi {
            width: 75%;
            vertical-align: middle;
        }

        .table {
            width: 100%;
            border-collapse: collapse
        }

        .list2>td {
            padding: 7px;
            border: none;
            font-size: 10pt
        }

        .font {
            font-size: 10pt
        }
    </style>
@endsection

@php
    function translateDateToEnglish($date)
    {
        $months = [
            'Januari' => 'January',
            'Februari' => 'February',
            'Maret' => 'March',
            'April' => 'April',
            'Mei' => 'May',
            'Juni' => 'June',
            'Juli' => 'July',
            'Agustus' => 'August',
            'September' => 'September',
            'Oktober' => 'October',
            'November' => 'November',
            'Desember' => 'December',
        ];
        foreach ($months as $indonesian => $english) {
            if (strpos($date, $indonesian) !== false) {
                $date = str_replace($indonesian, $english, $date);
                break;
            }
        }
        return $date;
    }
    function convertToRegularDate($isoDateString)
    {
        $date = new DateTime($isoDateString);
        return $date->format('d-m-Y');
    }

    function convertToRegularTime($isoDateString)
    {
        $date = new DateTime($isoDateString);
        return $date->format('H:i');
    }

    // dd($data);

@endphp

@section('content')
    @if ($cekWargaNegaraWNA == true)
        <tr>
            <td align="center">
                <div style="font-size: 15pt; font-weight: bold; color: #000000;padding:8px">
                    EYE EXAMINATION RESULT CERTIFICATE<br>
                    <span style="font-size:13pt">NUMBER : {{ isset($data['nosurat']) ? $data['nosurat'] : '-' }}</span>
                </div>
            </td>
        </tr>
        <tr>
            <td class="font">
                <div style="padding:7px">
                    The undersigned :
                    @if (is_array($data['DDDokter']) && array_key_exists('label', $data['DDDokter']))
                        <u>{{ $data['DDDokter']['label'] ?? '-' }}</u>,
                    @else
                        <u>{{ $data['DDDokter'] ?? '-' }}</u>,
                    @endif
                    Government Doctor at UPTD. RSUD Bali Mandara, Under Oath of Office, Explains in fact that :
                    <table class="table" style="margin-top: 5px">
                        <tr>
                            <td class="subJudul">Name</td>
                            <td class="isi">:
                                {{ isset($data['TBNamaPasien']) ? $data['TBNamaPasien'] : '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="subJudul">Date of Birth / Age</td>
                            <td class="isi">:
                                {{ isset($data['DTanggalLahir']) ? $data['DTanggalLahir'] : '-' }} /
                                {{ isset($data['TBSUmur']) ? $data['TBSUmur'] : '-' }} Years Old
                            </td>
                        </tr>
                        <tr>
                            <td class="subJudul">Gender</td>
                            <td class="isi">:
                                @if (isset($data['TBJenisKelamin']))
                                    @if ($data['TBJenisKelamin'] == 'Perempuan')
                                        <s>Man</s> / Woman*
                                    @elseif ($data['TBJenisKelamin'] == 'Laki-laki')
                                        Man / <s>Woman</s>*
                                    @endif
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="subJudul">Address</td>
                            <td class="isi">:
                                {{ isset($data['TAAlamat']) ? $data['TAAlamat'] : '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="subJudul">Jobs</td>
                            <td class="isi">:
                                {{ isset($data['TBPekerjaan']) ? App\Traits\Valet::english($data['TBPekerjaan']) : '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <p style="margin-bottom: 5px">
                                    After an eye health examination, with the following examination results:
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td class="subJudul">V.O.D</td>
                            <td class="isi">:
                                {{ isset($data['TBvod']) ? $data['TBvod'] : '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="subJudul">V.O.S</td>
                            <td class="isi">:
                                {{ isset($data['TBvos']) ? $data['TBvos'] : '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="subJudul">Segmen Anterior OD</td>
                            <td class="isi">:
                                {{ isset($data['TBSAod']) ? $data['TBSAod'] : '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="subJudul">Segmen Anterior OS</td>
                            <td class="isi">:
                                {{ isset($data['TBSAos']) ? $data['TBSAos'] : '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="subJudul">Segmen Posterior OD</td>
                            <td class="isi">:
                                {{ isset($data['TBSPod']) ? $data['TBSPod'] : '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="subJudul">Segmen Posterior OS</td>
                            <td class="isi">:
                                {{ isset($data['TBSPos']) ? $data['TBSPos'] : '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="subJudul">Colorblindness</td>
                            <td class="isi">:
                                {{ isset($data['TBButaWarna']) ? App\Traits\Valet::english($data['TBButaWarna']) : '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td style="width: 40%">Conclusion of Eye Examination Results</td>
                            <td style="width: 60%">:
                                {{ isset($data['TAKesimpulan']) ? App\Traits\Valet::english($data['TAKesimpulan']) : '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <p style="margin:0px; margin-top: 5px">
                                    Thus this Certificate is made and valid for 3 three months from the date of issue.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <table class="table" style="margin-top: 20px">
                                    <tr>
                                        <td style="width: 67%;vertical-align: bottom" colspan="2">
                                            Note: * Circle the appropriate one
                                        </td>
                                        <td style="width: 33%;text-align: center">
                                            Denpasar, {{ translateDateToEnglish($identitas['dateNow']) }}
                                            <br><b>Examining Doctor</b>
                                            {{-- <br><br> --}}
                                            <br>
                                            <img src="data:image/png;base64, {!! $tte !!}" width="120">
                                            <br>
                                            {{-- <br><br> --}}
                                            @if (is_array($data['DDDokter']) && array_key_exists('label', $data['DDDokter']))
                                                {{ $data['DDDokter']['label'] ?? '-' }}
                                            @else
                                                {{ $data['DDDokter'] ?? '-' }}
                                            @endif
                                            <br>
                                            <b>
                                                {{-- NIP :
                                                @if (isset($data['nip']->nip))
                                                    {{ $data['nip']->nip }}
                                                @else
                                                    -
                                                @endif --}}
                                                @if (!empty($data['nip']->nip))
                                                    NIP : {{ $data['nip']->nip }}
                                                @else
                                                    SIP : {{ isset($data['nosip']->nosip) ? $data['nosip']->nosip : '-' }}
                                                @endif
                                            </b>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    @else
        <tr>
            <td align="center">
                <div style="font-size: 15pt; font-weight: bold; color: #000000;padding:8px">
                    SURAT KETERANGAN HASIL PEMERIKSAAN MATA<br>
                    <span style="font-size:13pt">NOMOR : {{ isset($data['nosurat']) ? $data['nosurat'] : '-' }}</span>
                </div>
            </td>
        </tr>
        <tr>
            <td class="font">
                <div style="padding:7px">
                    Yang bertanda tangan dibawah ini :
                    @if (is_array($data['DDDokter']) && array_key_exists('label', $data['DDDokter']))
                        <u>{{ $data['DDDokter']['label'] ?? '-' }}</u>,
                    @else
                        <u>{{ $data['DDDokter'] ?? '-' }}</u>,
                    @endif
                    Dokter Pemerintah pada UPTD. RSUD Bali Mandara, Atas Sumpah Jabatan, Menerangkan dengan sebenarnya bahwa
                    :
                    <table class="table" style="margin-top: 5px">
                        <tr>
                            <td class="subJudul">Nama</td>
                            <td class="isi">:
                                {{ isset($data['TBNamaPasien']) ? $data['TBNamaPasien'] : '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="subJudul">Tanggal Lahir / Umur</td>
                            <td class="isi">:
                                {{ isset($data['DTanggalLahir']) ? $data['DTanggalLahir'] : '-' }} /
                                {{ isset($data['TBSUmur']) ? $data['TBSUmur'] : '-' }} Tahun
                            </td>
                        </tr>
                        <tr>
                            <td class="subJudul">Jenis Kelamin</td>
                            <td class="isi">:
                                {{-- @if (isset($data['TBJenisKelamin']))
                                    @if ($data['TBJenisKelamin'] == 'Perempuan')
                                        <s>Laki-Laki</s> / Perempuan*
                                    @elseif ($data['TBJenisKelamin'] == 'Laki-laki')
                                        Laki-Laki / <s>Perempuan</s>*
                                    @endif
                                @else
                                    -
                                @endif --}}
                                {{ isset($data['TBJenisKelamin']) ? $data['TBJenisKelamin'] : '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="subJudul">Alamat</td>
                            <td class="isi">:
                                {{ isset($data['TAAlamat']) ? $data['TAAlamat'] : '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="subJudul">Pekerjaan</td>
                            <td class="isi">:
                                {{ isset($data['TBPekerjaan']) ? $data['TBPekerjaan'] : '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <p style="margin-bottom: 5px">Setelah dilakukan Pemeriksaan Kesehatan Mata, dengan Hasil
                                    Pemeriksaan sebagai berikut :</p>
                            </td>
                        </tr>
                        <tr>
                            <td class="subJudul">V.O.D</td>
                            <td class="isi">:
                                {{ isset($data['TBvod']) ? $data['TBvod'] : '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="subJudul">V.O.S</td>
                            <td class="isi">:
                                {{ isset($data['TBvos']) ? $data['TBvos'] : '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="subJudul">Segmen Anterior OD</td>
                            <td class="isi">:
                                {{ isset($data['TBSAod']) ? $data['TBSAod'] : '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="subJudul">Segmen Anterior OS</td>
                            <td class="isi">:
                                {{ isset($data['TBSAos']) ? $data['TBSAos'] : '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="subJudul">Segmen Posterior OD</td>
                            <td class="isi">:
                                {{ isset($data['TBSPod']) ? $data['TBSPod'] : '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="subJudul">Segmen Posterior OS</td>
                            <td class="isi">:
                                {{ isset($data['TBSPos']) ? $data['TBSPos'] : '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="subJudul">Buta Warna</td>
                            <td class="isi">:
                                {{ isset($data['TBButaWarna']) ? $data['TBButaWarna'] : '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td style="width: 40%">Kesimpulan Hasil Pemeriksaan Mata</td>
                            <td style="width: 60%">:
                                {{ isset($data['TAKesimpulan']) ? $data['TAKesimpulan'] : '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <p style="margin:0px; margin-top: 5px">Demikian Surat Keterangan ini dibuat dan berlaku
                                    selama 3 tiga bulan sejak tanggal dikeluarkan.</p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <table class="table" style="margin-top: 20px">
                                    <tr>
                                        <td style="width: 67%;vertical-align: bottom" colspan="2">
                                            Note : * Lingkari salah satu yang sesuai
                                        </td>
                                        <td style="width: 33%;text-align: center">
                                            Denpasar, {{ $identitas['dateNow'] ?? '-' }}
                                            <br><b>Dokter Pemeriksa</b>
                                            {{-- <br><br> --}}
                                            <br>
                                            <img src="data:image/png;base64, {!! $tte !!}" width="120">
                                            {{-- <br><br> --}}
                                            <br>
                                            @if (is_array($data['DDDokter']) && array_key_exists('label', $data['DDDokter']))
                                                {{ $data['DDDokter']['label'] ?? '-' }}
                                            @else
                                                {{ $data['DDDokter'] ?? '-' }}
                                            @endif
                                            <br>
                                            <b>
                                                {{-- NIP :
                                                @if (isset($data['nip']->nip))
                                                    {{ $data['nip']->nip }}
                                                @else
                                                    -
                                                @endif --}}
                                                @if (!empty($data['nip']->nip))
                                                    NIP : {{ $data['nip']->nip }}
                                                @else
                                                    SIP : {{ isset($data['nosip']->nosip) ? $data['nosip']->nosip : '-' }}
                                                @endif
                                            </b>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    @endif
@endsection
