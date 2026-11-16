{{-- Jhon | 2026-11-13 20:13 WITA | TAMBAH: adapter PDF API dari view Nadi; tanpa kontrol browser. --}}
@extends('template.layout-emr-kop-surat')
@section('title')
    @if ($cekWargaNegaraWNA)
        Lung Physical Examination Letter
    @else
        Cetak Surat Keterangan Sehat Paru
    @endif
@endsection
@section('kode', 'RM.1B/SK/00')
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
                    <u>LUNG PHYSICAL EXAMINATION LETTER</u><br>
                    <span style="font-size:13pt">NUMBER : {{ isset($data['nosurat']) ? $data['nosurat'] : '-' }}</span>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <div style="padding:7px">
                    <span>The undersigned hereby certifies that :</span>
                    <div style="margin-left: 20px;">
                        <table width="100%" style="border-spacing: 0 5px;">
                            <tr>
                                <td width="30%">Name</td>
                                <td width="70%">: {{ $data['namaPasien'] ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td width="30%">Age</td>
                                <td width="70%">
                                    : {{ isset($data['umurPasien']) ? $data['umurPasien'] : '-' }} Year
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Sex</td>
                                <td width="70%">:
                                    {{ isset($data['jenisKelaminPasien']) ? App\Traits\Valet::english($data['jenisKelaminPasien']) : '-' }}
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Address</td>
                                <td width="70%">:
                                    {{ isset($data['alamatPasien']) ? $data['alamatPasien'] : '-' }}
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Job</td>
                                <td width="70%">:
                                    {{ isset($data['pekerjaanPasien']) ? App\Traits\Valet::english($data['pekerjaanPasien']) : '-' }}
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div style="margin-top: 10px">
                        After carrying out a Lung Health Examination, with conclusions :<br>
                        <b>{{ isset($data['kondisiParu']) ? App\Traits\Valet::english($data['kondisiParu']) : '-' }}
                            @if (isset($data['kondisiParu']) && $data['kondisiParu'] == 'Tidak Sehat/Abnormal')
                                {{ isset($data['KP_Abnormal']) ? ', ' . App\Traits\Valet::english($data['KP_Abnormal']) : '-' }}
                            @endif.
                        </b><br>
                        Suggestion : <b>{{ isset($data['saran']) ? App\Traits\Valet::english($data['saran']) : '-' }}</b>
                        <br><br>
                        Thus, this Statement Letter was made in truth for the proper purposes.
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
                                    Doctor In Charge
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
                                    @if (!empty($data['nip']->nip))
                                        NIP : {{ $data['nip']->nip }}<br>
                                    @endif
                                    @if (!empty($data['nosip']->nosip))
                                        SIP : {{ $data['nosip']->nosip }}<br>
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
                    <u>SURAT KETERANGAN SEHAT PARU</u><br>
                    <span style="font-size:13pt">NOMOR : {{ isset($data['nosurat']) ? $data['nosurat'] : '-' }}</span>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <div style="padding:7px">
                    <span>Yang bertanda tangan dibawah ini menerangkan dengan sebenarnya bahwa :</span>
                    <div style="margin-left: 20px;">
                        <table width="100%" style="border-spacing: 0 5px;">
                            <tr>
                                <td width="30%">Nama</td>
                                <td width="70%">: {{ $data['namaPasien'] ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td width="30%">Umur</td>
                                <td width="70%">
                                    : {{ isset($data['umurPasien']) ? $data['umurPasien'] : '-' }} Tahun
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Jenis Kelamin</td>
                                <td width="70%">:
                                    {{ isset($data['jenisKelaminPasien']) ? $data['jenisKelaminPasien'] : '-' }}
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Alamat</td>
                                <td width="70%">:
                                    {{ isset($data['alamatPasien']) ? $data['alamatPasien'] : '-' }}
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Pekerjaan</td>
                                <td width="70%">:
                                    {{ isset($data['pekerjaanPasien']) ? $data['pekerjaanPasien'] : '-' }}
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div style="margin-top: 10px">
                        Setelah dilakukan Pemeriksaan Kesehatan Paru, dengan kesimpulan :<br>
                        <b>{{ isset($data['kondisiParu']) ? $data['kondisiParu'] : '-' }}
                            @if (isset($data['kondisiParu']) && $data['kondisiParu'] == 'Tidak Sehat/Abnormal')
                                {{ isset($data['KP_Abnormal']) ? ', ' . $data['KP_Abnormal'] : '-' }}
                            @endif.
                        </b><br>
                        Saran : <b>{{ isset($data['saran']) ? $data['saran'] : '-' }}</b>
                        <br><br>
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
                                <td style="text-align: center;font-weight: bold;font-size:8pt" width="40%">
                                    @if (!empty($data['nip']->nip))
                                        NIP : {{ $data['nip']->nip }}<br>
                                    @endif
                                    @if (!empty($data['nosip']->nosip))
                                        SIP : {{ $data['nosip']->nosip }}<br>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </td>
        </tr>
    @endif
@endsection
