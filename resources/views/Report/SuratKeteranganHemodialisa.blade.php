{{-- Jhon | 2026-11-13 20:13 WITA | TAMBAH: adapter PDF API dari view Nadi; tanpa kontrol browser. --}}
@extends('template.layout-emr-kop-surat')
@section('title')
    @if ($cekWargaNegaraWNA)
        Print Hemodialysis Certificate
    @else
        Cetak Surat Keterangan Hemodialisis
    @endif
@endsection
@section('kode', 'RM.1B/SK/00')
@section('page-style')
    <style>
        body{
            page-break-inside: avoid;
        }
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
                    <u>HEMODIALYSIS CERTIFICATE</u><br>
                    <span style="font-size:13pt">HD/FORM.CERTIFICATE/{{ date('m') }}/{{ date('Y') }}</span>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <div style="padding:7px">
                    <span>The undersigned :</span>
                    <div style="margin-left: 20px;">
                        <table width="100%" style="border-spacing: 0 5px;">
                            <tr>
                                <td width="30%">Name</td>
                                <td width="70%">:
                                    {{ isset($data['DDDokter']) ? $data['DDDokter'] : '-' }}</td>
                            </tr>
                            <tr>
                                <td width="30%">Title</td>
                                <td width="70%">
                                    :
                                    {{ isset($data['jabatanDokter']) ? App\Traits\Valet::english($data['jabatanDokter']) : '-' }}
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Address</td>
                                <td width="70%">:
                                    {{ isset($data['alamatDokter']) ? $data['alamatDokter'] : '-' }}
                                </td>
                            </tr>
                        </table>
                    </div>
                    <span>Explain that :</span>
                    <div style="margin-left: 20px;">
                        <table width="100%" style="border-spacing: 0 5px;">
                            <tr>
                                <td width="30%">Name</td>
                                <td width="70%">: {{ isset($data['namaPasien']) ? $data['namaPasien'] : '-' }}</td>
                            </tr>
                            <tr>
                                <td width="30%">Medical Record Number</td>
                                <td width="70%">
                                    : {{ isset($data['rmPasien']) ? $data['rmPasien'] : '-' }}
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Birth Date</td>
                                <td width="70%">:
                                    {{ isset($data['tglLahirPasien']) ? formatDateEnglish($data['tglLahirPasien']) : '-' }}
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Address</td>
                                <td width="70%">:
                                    {{ isset($data['alamatPasien']) ? $data['alamatPasien'] : '-' }}
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div style="margin-top: 10px">
                        It is true that the patient above was examined at Bali Mandara Hospital with a diagnosis of :
                        <b>{{ isset($data['diagnosis']) ? App\Traits\Valet::english($data['diagnosis']) : '-' }}.</b><br>
                        <br> The patient requires Hemodialysis therapy for 3 X in a week from up to date
                        <b>{{ isset($data['tglAwal']) ? formatDateEnglish($data['tglAwal']) : '-' }}</b> up to date
                        <b>{{ isset($data['tglAkhir']) ? formatDateEnglish($data['tglAkhir']) : '-' }}</b>
                        For indication
                        <b>{{ isset($data['indikasi']) ? App\Traits\Valet::english($data['indikasi']) : '-' }}</b>.<br><br>
                        Thus this certificate is made so that it can be used properly.
                    </div>
                    <div style="margin-top: 10px;">
                        <table width="100%">
                            <tr>
                                <td width="60%"></td>
                                <td style="text-align: center" width="40%">
                                    <br>
                                    Denpasar,
                                    {{-- Jhon | 2026-11-13 19:31 WITA | UBAH: tanggal kosong tidak diganti tanggal hari ini. --}}
                                    {{ !empty($data['registrasi']['tglregistrasi']) ? (formatDateEnglish(\Carbon\Carbon::parse($data['registrasi']['tglregistrasi'])->format('Y-m-d')) ?? '-') : '-' }}
                                </td>
                            </tr>
                            <tr>
                                <td width="60%"></td>
                                <td style="text-align: center" width="40%">
                                    Doctor
                                </td>
                            </tr>
                            <tr>
                                <td width="60%"></td>
                                <td style="text-align: center" width="40%">
                                    {{-- <br> --}}
                                    <img src="data:image/png;base64, {!! $tte !!}" width="100px" height="100px">
                                    {{-- <br><br> --}}
                                </td>
                            </tr>
                            <tr>
                                <td width="60%"></td>
                                <td style="text-align: center;font-weight: bold;font-size:8pt" width="40%">
                                    {{ isset($data['DDDokter']) ? $data['DDDokter'] : '-' }}
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
                    <u>SURAT KETERANGAN HEMODIALISIS</u><br>
                    <span style="font-size:13pt">HD/FORM.SURAT KETERANGAN/{{ date('m') }}/{{ date('Y') }}</span>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <div style="padding:7px">
                    <span>Yang bertanda tangan dibawah ini :</span>
                    <div style="margin-left: 20px;">
                        <table width="100%" style="border-spacing: 0 5px;">
                            <tr>
                                <td width="30%">Nama</td>
                                <td width="70%">:
                                    {{ isset($data['DDDokter']) ? $data['DDDokter'] : '-' }}</td>
                            </tr>
                            <tr>
                                <td width="30%">Jabatan</td>
                                <td width="70%">
                                    : {{ isset($data['jabatanDokter']) ? $data['jabatanDokter'] : '-' }}
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Alamat</td>
                                <td width="70%">:
                                    {{ isset($data['alamatDokter']) ? $data['alamatDokter'] : '-' }}
                                </td>
                            </tr>
                        </table>
                    </div>
                    <span>Menerangkan Bahwa :</span>
                    <div style="margin-left: 20px;">
                        <table width="100%" style="border-spacing: 0 5px;">
                            <tr>
                                <td width="30%">Nama</td>
                                <td width="70%">: {{ isset($data['namaPasien']) ? $data['namaPasien'] : '-' }}</td>
                            </tr>
                            <tr>
                                <td width="30%">Nomor Rekam Medis</td>
                                <td width="70%">
                                    : {{ isset($data['rmPasien']) ? $data['rmPasien'] : '-' }}
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Tanggal Lahir</td>
                                <td width="70%">:
                                    {{ isset($data['tglLahirPasien']) ? formatDateIndonesian($data['tglLahirPasien']) : '-' }}
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Alamat</td>
                                <td width="70%">:
                                    {{ isset($data['alamatPasien']) ? $data['alamatPasien'] : '-' }}
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div style="margin-top: 10px">
                        Memang benar pasien tersebut diatas melakukan pemeriksaan di RSUD Bali Mandara dengan diagnosis :
                        <b>{{ isset($data['diagnosis']) ? $data['diagnosis'] : '-' }}.</b><br>
                        <br> Pasien memerlukan terapi Hemodialisis sebanyak 3 X dalam seminggu dari sampai dengan tanggal
                        <b>{{ isset($data['tglAwal']) ? formatDateIndonesian($data['tglAwal']) : '-' }}</b> sampai dengan
                        tanggal
                        <b>{{ isset($data['tglAkhir']) ? formatDateIndonesian($data['tglAkhir']) : '-' }}</b>
                        Atas indikasi <b>{{ isset($data['indikasi']) ? $data['indikasi'] : '-' }}</b>.<br><br>
                        Demikian surat keterangan ini dibuat agar dapat dipergunakan sebagaimana mestinya.
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
                                    Dokter
                                </td>
                            </tr>
                            <tr>
                                <td width="60%"></td>
                                <td style="text-align: center" width="40%">
                                    {{-- <br> --}}
                                    <img src="data:image/png;base64, {!! $tte !!}" width="80px" height="80px">
                                    {{-- <br><br> --}}
                                </td>
                            </tr>
                            <tr>
                                <td width="60%"></td>
                                <td style="text-align: center;font-weight: bold;font-size:8pt" width="40%">
                                    {{ isset($data['DDDokter']) ? $data['DDDokter'] : '-' }}
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </td>
        </tr>
    @endif
@endsection
