{{-- Jhon | 2026-11-13 20:13 WITA | TAMBAH: adapter PDF API dari view Nadi; tanpa kontrol browser. --}}
@extends('template.layout-emr-kop-surat')
@section('title')
    @if ($cekWargaNegaraWNA)
        Certificate of Diagnosis
    @else
        Surat Keterangan Diagnosa
    @endif
@endsection
@section('kode', 'RM.1E/SK/00')
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
    function convertDayToIndonesian($dateString)
    {
        $dayMap = [
            '01' => 'satu',
            '02' => 'dua',
            '03' => 'tiga',
            '04' => 'empat',
            '05' => 'lima',
            '06' => 'enam',
            '07' => 'tujuh',
            '08' => 'delapan',
            '09' => 'sembilan',
            '10' => 'sepuluh',
            '11' => 'sebelas',
            '12' => 'dua belas',
            '13' => 'tiga belas',
            '14' => 'empat belas',
            '15' => 'lima belas',
            '16' => 'enam belas',
            '17' => 'tujuh belas',
            '18' => 'delapan belas',
            '19' => 'sembilan belas',
            '20' => 'dua puluh',
            '21' => 'dua puluh satu',
            '22' => 'dua puluh dua',
            '23' => 'dua puluh tiga',
            '24' => 'dua puluh empat',
            '25' => 'dua puluh lima',
            '26' => 'dua puluh enam',
            '27' => 'dua puluh tujuh',
            '28' => 'dua puluh delapan',
            '29' => 'dua puluh sembilan',
            '30' => 'tiga puluh',
            '31' => 'tiga puluh satu',
        ];
        $day = date('d', strtotime($dateString));
        return $dayMap[$day];
    }
    function convertDayToEnglish($dateString)
    {
        $dayMap = [
            '01' => 'one',
            '02' => 'two',
            '03' => 'three',
            '04' => 'four',
            '05' => 'five',
            '06' => 'six',
            '07' => 'seven',
            '08' => 'eight',
            '09' => 'nine',
            '10' => 'ten',
            '11' => 'eleven',
            '12' => 'twelve',
            '13' => 'thirteen',
            '14' => 'fourteen',
            '15' => 'fifteen',
            '16' => 'sixteen',
            '17' => 'seventeen',
            '18' => 'eighteen',
            '19' => 'nineteen',
            '20' => 'twenty',
            '21' => 'twenty-one',
            '22' => 'twenty-two',
            '23' => 'twenty-three',
            '24' => 'twenty-four',
            '25' => 'twenty-five',
            '26' => 'twenty-six',
            '27' => 'twenty-seven',
            '28' => 'twenty-eight',
            '29' => 'twenty-nine',
            '30' => 'thirty',
            '31' => 'thirty-one',
        ];
        $day = date('d', strtotime($dateString));
        return $dayMap[$day];
    }
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
    function convertMonthToEnglish($dateString)
    {
        $monthMap = [
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
        $month = date('m', strtotime($dateString));
        return $monthMap[$month];
    }
    function convertMonthToIndonesian($dateString)
    {
        $monthMap = [
            '01' => 'Januari',
            '02' => 'Februari',
            '03' => 'Maret',
            '04' => 'April',
            '05' => 'Mei',
            '06' => 'Juni',
            '07' => 'Juli',
            '08' => 'Agustus',
            '09' => 'September',
            '10' => 'Oktober',
            '11' => 'November',
            '12' => 'Desember',
        ];
        $month = date('m', strtotime($dateString));
        return $monthMap[$month] ?? '-';
    }
    function convertYearToIndonesian($year)
    {
        $yearConvert = preg_replace('/\s+/', ' ', trim($year));
        $parts = explode(' ', $yearConvert);
        if (count($parts) === 3) {
            $year = $parts[2];
        }

        $units = [
            '0' => 'nol',
            '1' => 'satu',
            '2' => 'dua',
            '3' => 'tiga',
            '4' => 'empat',
            '5' => 'lima',
            '6' => 'enam',
            '7' => 'tujuh',
            '8' => 'delapan',
            '9' => 'sembilan',
        ];
        $yearString = str_pad((string) $year, 4, '0', STR_PAD_LEFT);
        $words = [];
        if ($yearString[0] != '0') {
            $words[] = $units[$yearString[0]] . ' ribu';
        }
        if ($yearString[1] != '0') {
            $words[] = $units[$yearString[1]] . ' ratus';
        }
        if ($yearString[2] != '0') {
            $words[] = $units[$yearString[2]] . ' puluh';
        }
        if ($yearString[3] != '0') {
            $words[] = $units[$yearString[3]];
        }
        return implode(' ', $words);
    }
    function convertYearToEnglish($dateString)
    {
        $year = date('Y', strtotime($dateString));
        $yearMap = [
            '2020' => 'Two Thousand Twenty',
            '2021' => 'Two Thousand Twenty-One',
            '2022' => 'Two Thousand Twenty-Two',
            '2023' => 'Two Thousand Twenty-Three',
            '2024' => 'Two Thousand Twenty-Four',
            '2025' => 'Two Thousand Twenty-Five',
            '2026' => 'Two Thousand Twenty-Six',
            // Add more years as needed
        ];
        return $yearMap[$year] ?? 'Unknown Year';
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
@endphp

@section('content')
    @if ($cekWargaNegaraWNA == true)
        <tr>
            <td align="center">
                <div style="font-size: 15pt; font-weight: bold; color: #000000;padding:8px">
                    <u> CERTIFICATE OF DIAGNOSIS </u><br>
                    <span style="font-size:13pt">NUMBER : {{ isset($data['nosurat']) ? $data['nosurat'] : '-' }}</span>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <div style="padding:7px">
                    <span>The undersigned hereby declares with truth that :</span>
                    <div style="margin-left: 20px;margin-top: 10px">
                        <table width="100%" style="border-spacing: 0 8px;">
                            <tr>
                                <td width="30%">Name</td>
                                <td width="70%">: {{ $data['TBNamaPasien'] ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td width="30%">Age</td>
                                <td width="70%">
                                    : {{ isset($data['TBSTahun']) ? $data['TBSTahun'] : '-' }} Years Old
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
                                <td width="30%">Treated from the date of</td>
                                <td width="70%">:
                                    {{ isset($data['DDirawatDariTanggal']) ? formatDateEnglish($data['DDirawatDariTanggal']) : '-' }}
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Diagnose</td>
                                <td width="70%">: 
                                    {{ isset($data['TADiagnosis']) ? $data['TADiagnosis'] : '-' }}
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div style="margin-top: 10px">
                        Thus this Certificate is made with truth for the purpose as it should be.
                    </div>
                    <div style="margin-top: 10px;">
                        <table width="100%">
                            <tr>
                                <td width="60%"></td>
                                <td style="text-align: center" width="40%">
                                    <br>
                                    Denpasar, {{ translateDateToEnglish($identitas['dateNow']) }}
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
                                    NIP :
                                    @if (isset($data['nip']->nip))
                                        {{ $data['nip']->nip }}
                                    @else
                                        {{ '-' }}
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
                    <u>SURAT KETERANGAN DIAGNOSA</u><br>
                    <span style="font-size:13pt">NOMOR : {{ isset($data['nosurat']) ? $data['nosurat'] : '-' }}</span>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <div style="padding:7px">
                    <span>Yang bertanda tangan dibawah ini menerangkan dengan sebenarnya bahwa :</span>
                    <div style="margin-left: 20px;margin-top: 10px">
                        <table width="100%" style="border-spacing: 0 8px;">
                            <tr>
                                <td width="30%">Nama</td>
                                <td width="70%">: {{ $data['TBNamaPasien'] ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td width="30%">Umur</td>
                                <td width="70%">
                                    : {{ isset($data['TBSTahun']) ? $data['TBSTahun'] : '-' }} Tahun
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
                                <td width="30%">Dirawat dari tanggal</td>
                                <td width="70%">:
                                    {{ isset($data['DDirawatDariTanggal']) ? formatDateIndonesian($data['DDirawatDariTanggal']) : '-' }}
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Diagnosis</td>
                                <td width="70%">: 
                                    {{ isset($data['TADiagnosis']) ? $data['TADiagnosis'] : '-' }}
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div style="margin-top: 10px">
                        Demikian surat keterangan ini dibuat dengan sebenarnya untuk keperluan sebagaimana mestinya.
                    </div>
                    <div style="margin-top: 10px;">
                        <table width="100%">
                            <tr>
                                <td width="60%"></td>
                                <td style="text-align: center" width="40%">
                                    <br>
                                    Denpasar, {{ $identitas['dateNow'] ?? '-' }}
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
                                    NIP :
                                    @if (isset($data['nip']->nip))
                                        {{ $data['nip']->nip }}
                                    @else
                                        {{ '-' }}
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td style="text-align: left">
            </td>
        </tr>
    @endif
@endsection
