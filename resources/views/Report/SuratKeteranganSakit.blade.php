{{-- Jhon | 2026-11-13 20:13 WITA | TAMBAH: adapter PDF API dari view Nadi; tanpa kontrol browser. --}}
@extends('template.layout-emr-kop-surat')
@section('title')
    @if ($cekWargaNegaraWNA)
        Print Sick Certificate
    @else
        Cetak Surat Keterangan Sakit
    @endif
@endsection
@section('kode', 'RM.1D/SK/01')
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

    // dd($data)

@endphp

@section('content')
    @if ($cekWargaNegaraWNA == true)
        <tr>
            <td align="center">
                <div style="font-size: 15pt; font-weight: bold; color: #000000;padding:8px">
                    <u>SICKNESS CERTIFICATE</u><br>
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
                                <td width="30%">Nama</td>
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
                                <td width="30%">Diagnosis</td>
                                <td width="70%">:
                                    {{ isset($data['TADiagnosa']) ? $data['TADiagnosa'] : '-' }}
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div style="margin-top: 10px">
                        After the medical examination, the person needs to rest for
                        <b>{{ isset($data['TBTotalHari']) ? $data['TBTotalHari'] : '-' }}</b> Day. From
                        {{ isset($data['DDariTanggal']) ? date('d-m-Y', strtotime($data['DDariTanggal'])) : '-' }} to
                        {{ isset($data['DKeTanggal']) ? date('d-m-Y', strtotime($data['DKeTanggal'])) : '-' }}
                        <br><br> Thus this Certificate is made with truth for the purpose as it should be.
                    </div>
                    <div style="margin-top: 10px;">
                        <table width="100%">
                            <tr>
                                <td width="60%"></td>
                                <td style="text-align: center" width="40%">
                                    <br>
                                    Denpasar, {{-- translateDateToEnglish($identitas['dateNow']) --}}
                                    {{-- Jhon | 2026-11-13 19:31 WITA | UBAH: tanggal surat kosong tetap kosong, bukan tanggal cetak. --}}
                                    {{ !empty($data['DTglPulang'])
                                        ? \Carbon\Carbon::parse($data['DTglPulang'])
                                            ->setTimezone('Asia/Makassar')
                                            ->locale('en')
                                            ->translatedFormat('d F Y')
                                        : '-'
                                    }}
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
                    <u>SURAT KETERANGAN SAKIT</u><br>
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
                                <td width="30%">Diagnosis</td>
                                <td width="70%">:
                                    {{ isset($data['TADiagnosa']) ? $data['TADiagnosa'] : '-' }}
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div style="margin-top: 10px">
                        Setelah dilakukan Pemeriksaan Kesehatan,yang bersangkutan perlu Beristirahat selama
                        <b>{{ isset($data['TBTotalHari']) ? $data['TBTotalHari'] : '-' }}</b> Hari. Dari tanggal
                        {{ isset($data['DDariTanggal']) ? date('d-m-Y', strtotime($data['DDariTanggal'])) : '-' }} sampai dengan tanggal
                        {{ isset($data['DKeTanggal']) ? date('d-m-Y', strtotime($data['DKeTanggal'])) : '-' }}
                        <br><br>Demikian Surat Keterangan ini dibuat dengan sebenarnya untuk keperluan sebagaimana mestinya
                    </div>
                    <div style="margin-top: 10px;">
                        <table width="100%">
                            <tr>
                                <td width="60%"></td>
                                <td style="text-align: center" width="40%">
                                    <br>
                                    Denpasar, 
                                    {{-- isset($data['DTglPulang']) ? \Carbon\Carbon::parse($data['DTglPulang'])->format('d F Y') : \Carbon\Carbon::now()->format('d F Y') --}}
                                    {{-- \Carbon\Carbon::parse($data['registrasi']['tglregistrasi'])->format('d F Y') --}}
                                    {{-- Jhon | 2026-11-13 19:31 WITA | UBAH: tanggal surat kosong tetap kosong, bukan tanggal cetak. --}}
                                    {{ !empty($data['DTglPulang'])
                                        ? \Carbon\Carbon::parse($data['DTglPulang'])
                                            ->setTimezone('Asia/Makassar')
                                            ->locale('id')
                                            ->translatedFormat('d F Y')
                                        : '-'
                                    }}
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
    @endif
@endsection
