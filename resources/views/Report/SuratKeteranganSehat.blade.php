{{-- Jhon | 2026-11-13 20:13 WITA | TAMBAH: adapter PDF API dari view Nadi; tanpa kontrol browser. --}}
@extends('template.layout-emr-kop-surat')
@section('title')
    @if ($cekWargaNegaraWNA)
        Print Health Certificate
    @else
        Cetak Surat Keterangan Sehat
    @endif
@endsection
@section('kode', 'RM.1C/SK/00')
@section('page-style')
    <style>
        body,
        table,
        td {
            font-family: 'Open Sans', sans-serif !important;
        }

        td {
            font-size: 10pt;
        }
    </style>
@endsection

@php
    function getIndonesianMonth($monthNumber)
    {
        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];
        return $months[$monthNumber] ?? '';
    }
    function formatDateIndonesian($date)
    {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return '-';
        }
        $day = date('d', $timestamp);
        $month = getIndonesianMonth(date('n', $timestamp));
        $year = date('Y', $timestamp);
        return "$day $month $year";
    }
    function formatDateEnglish($date)
    {
        $months = [
            '01' => 'January',
            '02' => 'February',
            '03' => 'March',
            '04' => 'April',
            '05' => 'May',
            '06' => 'June',
            '07' => 'July',
            '08' => 'August',
            '09' => 'September',
            '10' => 'October',
            '11' => 'November',
            '12' => 'December',
        ];
        $formattedDate =
            date('d', strtotime($date)) .
            ' ' .
            $months[date('m', strtotime($date))] .
            ' ' .
            date('Y', strtotime($date));
        return $formattedDate;
    }
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
    function translateGender($gender)
    {
        if ($gender === 'Laki-laki') {
            return 'Male';
        } elseif ($gender === 'Perempuan') {
            return 'Female';
        } else {
            return '-';
        }
    }
@endphp

@section('content')
    @if ($cekWargaNegaraWNA == true)
        <tr>
            <td align="center">
                <div style="font-size: 15pt; font-weight: bold; color: #000000;padding:8px">
                    <u>HEALTH CERTIFICATE</u><br>
                    <span style="font-size:13pt">NUMBER : {{ isset($data['nosurat']) ? $data['nosurat'] : '-' }}</span>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <div style="padding:7px">
                    <span>The undersigned hereby declares with truth that :</span>
                    <div style="margin-left: 20px;">
                        <table width="100%" style="border-spacing: 0 8px;">
                            <tr>
                                <td width="30%">Name</td>
                                <td width="70%">: {{ $data['TBNamaPasien'] ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td width="30%">Date of birth / Age</td>
                                <td width="70%">
                                    : {{ isset($data['DTanggalLahir']) ? formatDateEnglish($data['DTanggalLahir']) : '-' }}
                                    / {{ isset($data['TBSTahun']) ? $data['TBSTahun'] : '-' }} Years Old
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Gender</td>
                                <td width="70%">:
                                    {{ isset($data['TBJenisKelamin']) ? translateGender($data['TBJenisKelamin']) : '-' }}
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Address</td>
                                <td width="70%"
                                    style="word-wrap: break-word; overflow-wrap: break-word; white-space: normal;">:
                                    {{ isset($data['TAAlamat']) ? $data['TAAlamat'] : '-' }}
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Height</td>
                                <td width="70%">:
                                    {{ isset($data['TBSTinggiBadan']) ? $data['TBSTinggiBadan'] : '-' }} Cm
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Weight</td>
                                <td width="70%">:
                                    {{ isset($data['TBSBeratBadan']) ? $data['TBSBeratBadan'] : '-' }} Kg
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Blood Pressure</td>
                                <td width="70%">:
                                    {{ isset($data['TBSTekananDarah']) ? $data['TBSTekananDarah'] : '-' }} mmHg
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">BMI</td>
                                <td width="70%">: {{ isset($data['TBSbmi']) ? $data['TBSbmi'] : '-' }} Kg/m<sup>2</sup>
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Color Blindness</td>
                                <td width="70%">: {{ isset($data['TBButaWarna']) ? $data['TBButaWarna'] : '-' }}</td>
                            </tr>
                            <tr>
                                <td width="30%">Blood Type</td>
                                <td width="70%">: {{ isset($data['TBGolDarah']) ? $data['TBGolDarah'] : '-' }}</td>
                            </tr>
                        </table>
                    </div>
                    <div style="margin-top: 10px">
                        After the Health Check, the person concerned was declared <b>HEALTHY</b> for
                        <b> {{ isset($data['TAKeterangan']) ? App\Traits\Valet::english($data['TAKeterangan']) : '-' }}
                        </b>.<br>
                        Thus this Certificate is made with truth for the purpose as it should be.
                    </div>
                    <div style="margin-top: 10px;">
                        <table width="100%">
                            <tr>
                                <td width="60%"></td>
                                <td style="text-align: center" width="40%">
                                    <br>
                                    Denpasar,
                                    {{-- Jhon | 2026-11-13 19:31 WITA | UBAH: tanggal kosong tidak diganti tanggal hari ini. --}}
                                    {{ !empty($data['registrasi']['tglregistrasi']) ? (translateDateToEnglish(\Carbon\Carbon::parse($data['registrasi']['tglregistrasi'])->format('Y-m-d'))) : '-' }}
                                </td>
                            </tr>
                            <tr>
                                <td width="60%"></td>
                                <td style="text-align: center" width="40%">
                                    Examining Doctor
                                </td>
                            </tr>
                            <tr>
                                <td width="60%"></td>
                                <td style="text-align: center" width="40%">
                                    {{-- <br> --}}
                                    <img src="data:image/png;base64, {!! $tte !!}" width="120">
                                    {{-- <br><br> --}}
                                </td>
                            </tr>
                            <tr>
                                <td width="60%"></td>
                                <td style="text-align: center;font-weight: bold;font-size:8pt" width="40%">
                                    @if (is_array($data['DDDokter']) && array_key_exists('label', $data['DDDokter']))
                                        {{ $data['DDDokter']['label'] ?? '-' }}
                                    @else
                                        {{ $data['DDDokter'] ?? '-' }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td width="60%"></td>
                                <td style="text-align: center;font-weight: bold;font-size:8pt" width="40%">
                                    {{-- NIP :
                                    @if (isset($data['nip']->nip))
                                        {{ $data['nip']->nip }}
                                    @else
                                        {{ '-' }}
                                    @endif --}}
                                    @if (!empty($data['nip']->nip)) 
                                        NIP : {{ $data['nip']->nip }} 
                                        @if (isset($data['nosip']->nosip) && !empty($data['nosip']->nosip)) 
                                            <br>SIP : {{ $data['nosip']->nosip }} 
                                        @endif
                                    @elseif (isset($data['nosip']->nosip) && !empty($data['nosip']->nosip))
                                        SIP : {{ $data['nosip']->nosip }} 
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </td>
        </tr>
    @else
        <tr>
            <td align="center">
                <div style="font-size: 15pt; font-weight: bold; color: #000000;padding:8px">
                    <u>SURAT KETERANGAN SEHAT</u><br>
                    <span style="font-size:13pt">NOMOR : {{ isset($data['nosurat']) ? $data['nosurat'] : '-' }}</span>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <div style="padding:7px">
                    <span>Yang bertanda tangan dibawah ini menerangkan dengan sebenarnya bahwa :</span>
                    <div style="margin-left: 20px;">
                        <table width="100%" style="border-spacing: 0 8px;">
                            <tr>
                                <td width="30%">Nama</td>
                                <td width="70%">: {{ $data['TBNamaPasien'] ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td width="30%">Tanggal lahir / Umur</td>
                                <td width="70%">
                                    :
                                    {{ isset($data['DTanggalLahir']) ? formatDateIndonesian($data['DTanggalLahir']) : '-' }}
                                    / {{ isset($data['TBSTahun']) ? $data['TBSTahun'] : '-' }} Tahun
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Jenis Kelamin</td>
                                <td width="70%">:
                                    {{ isset($data['TBJenisKelamin']) ? $data['TBJenisKelamin'] : '-' }}
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Alamat</td>
                                <td width="70%">:
                                    {{ isset($data['TAAlamat']) ? $data['TAAlamat'] : '-' }}
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Tinggi Badan</td>
                                <td width="70%">:
                                    {{ isset($data['TBSTinggiBadan']) ? $data['TBSTinggiBadan'] : '-' }} Cm
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Berat Badan</td>
                                <td width="70%">:
                                    {{ isset($data['TBSBeratBadan']) ? $data['TBSBeratBadan'] : '-' }} Kg
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Tekanan Darah</td>
                                <td width="70%">:
                                    {{ isset($data['TBSTekananDarah']) ? $data['TBSTekananDarah'] : '-' }} mmHg
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">BMI</td>
                                <td width="70%">: {{ isset($data['TBSbmi']) ? $data['TBSbmi'] : '-' }} Kg/m<sup>2</sup>
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Buta Warna</td>
                                <td width="70%">: {{ isset($data['TBButaWarna']) ? $data['TBButaWarna'] : '-' }}</td>
                            </tr>
                            <tr>
                                <td width="30%">Golongan Darah</td>
                                <td width="70%">: {{ isset($data['TBGolDarah']) ? $data['TBGolDarah'] : '-' }}</td>
                            </tr>
                        </table>
                    </div>
                    <div style="margin-top: 10px">
                        Setelah dilakukan Pemeriksaan Kesehatan,yang bersangkutan dinyatakan <b>SEHAT</b> untuk
                        <b>{{ $data['TAKeterangan'] ?? '-' }}</b>.<br>
                        Demikian Surat Keterangan ini dibuat dengan sebenarnya untuk keperluan sebagaimana mestinya.
                    </div>
                    <div style="margin-top: 10px;">
                        <table width="100%">
                            <tr>
                                <td width="60%"></td>
                                <td style="text-align: center" width="40%">
                                    <br>
                                    Denpasar,
                                    {{-- Jhon | 2026-11-13 19:31 WITA | UBAH: tanggal kosong tidak diganti tanggal hari ini. --}}
                                    {{ !empty($data['registrasi']['tglregistrasi']) ? (formatDateIndonesian(\Carbon\Carbon::parse($data['registrasi']['tglregistrasi'])->format('Y-m-d')) ?? '-') : '-' }}
                                </td>
                            </tr>
                            <tr>
                                <td width="60%"></td>
                                <td style="text-align: center" width="40%">
                                    Dokter Pemeriksa
                                </td>
                            </tr>
                            <tr>
                                <td width="60%"></td>
                                <td style="text-align: center" width="40%">
                                    {{-- <br> --}}
                                    <img src="data:image/png;base64, {!! $tte !!}" width="120">
                                    {{-- <br><br> --}}
                                </td>
                            </tr>
                            <tr>
                                <td width="60%"></td>
                                <td style="text-align: center;font-weight: bold;font-size:8pt" width="40%">
                                    @if (is_array($data['DDDokter']) && array_key_exists('label', $data['DDDokter']))
                                        {{ $data['DDDokter']['label'] ?? '-' }}
                                    @else
                                        {{ $data['DDDokter'] ?? '-' }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td width="60%"></td>
                                <td style="text-align: center; font-weight: bold; font-size: 8pt" width="40%">
                                    @if (!empty($data['nip']->nip)) 
                                        NIP : {{ $data['nip']->nip }} 
                                        @if (isset($data['nosip']->nosip) && !empty($data['nosip']->nosip)) 
                                            <br>SIP : {{ $data['nosip']->nosip }} 
                                        @endif
                                    @elseif (isset($data['nosip']->nosip) && !empty($data['nosip']->nosip))
                                        SIP : {{ $data['nosip']->nosip }} 
                                    @endif
                                    {{-- {{dd($data['nosip'])}} --}}
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </td>
        </tr>
    @endif
@endsection
