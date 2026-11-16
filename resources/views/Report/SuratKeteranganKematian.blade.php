{{-- Jhon | 2026-11-13 20:13 WITA | TAMBAH: adapter PDF API dari view Nadi; tanpa kontrol browser. --}}
{{-- Jhon | 2026-11-13 19:42 WITA | UBAH: adapter format SIMRS ke laporan pasien Nadi berbahasa Indonesia. --}}
@extends('template.layout-emr2')
@section('title')
    @if ($cekWargaNegaraWNA)
        Print Death Certificate
    @else
        Cetak Surat Keterangan Kematian
    @endif
@endsection
@section('page-style')
    <style>
        body,
        table,
        td {
            font-family: 'Open Sans', sans-serif !important;
        }

        .bold {
            font-weight: bold;
        }

        .checkbox {
            vertical-align: top;
            display: inline-block
        }

        .center {
            vertical-align: middle;
            text-align: center
        }

        input[type=checkbox]:before {
            font-family: DejaVu Sans !important;
        }

        table {
            border-collapse: collapse !important;
            width: 100% !important;
        }

        .border {
            border: 1px solid black;
        }

        .font {
            font-size: 8pt !important;
        }

        table {
            page-break-inside: auto
        }

        tr {
            page-break-inside: auto;
            page-break-after: avoid
        }

        .pd {
            padding: 3px;
        }

        .pd1 {
            padding: 1.5px;
        }


        .pd2 {
            padding: 3px;
        }

        .row {
            display: flex;
            margin-bottom: 8px
        }

        .col-3 {
            width: 25%
        }

        .col-4 {
            width: 33.33%
        }

        .col-5 {
            width: 41.66%
        }

        .col-8 {
            width: 66.66%
        }

        .offset-1 {
            margin-left: 8.33%
        }

        .input {
            width: 100%;
            padding: 6px 8px;
            margin-bottom: 6px;
            box-sizing: border-box
        }

        .indent {
            margin-left: 16px
        }

        .note {
            margin: 8px 0 12px 16px
        }

        .section-title {
            margin: 16px 0 8px 16px
        }

        .footer {
            width: 240px;
            margin-left: auto;
            text-align: center
        }

        .page-break {
            page-break-before: always;
            break-before: page;
        }
    </style>
@endsection

@php
    function convertToRegularDate($isoDateString)
    {
        $date = new DateTime($isoDateString);
        return $date->format('d-m-Y');
    }

    function convertToMakassarDate($isoDateString)
    {
        $date = new DateTime($isoDateString, new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone('Asia/Makassar'));
        return $date->format('d-m-Y H:i:s');
    }

    function convertToRegularTime($isoDateString)
    {
        $date = new DateTime($isoDateString);
        $date->setTimezone(new DateTimeZone('Asia/Makassar'));
        return $date->format('H:i');
    }
@endphp

@php
    use App\Services\MyMemoryTranslateService;
    $translate = fn ($teks) => app(MyMemoryTranslateService::class)->translateV2($teks, 'en', 'id');
@endphp

@section('content')
    @if ($cekWargaNegaraWNA)
        <tr>
            <td align="center">
                <div style="font-size: 15pt; font-weight: bold;padding:3px">
                    <u>DEATH CERTIFICATE</u><br>
                    <span style="font-size:13pt">NUMBER: {{ isset($data['nomor']) ? $data['nomor'] : '-' }}</span>
                </div>
            </td>
        </tr>
        <tr>
            <td class="font">
                <table>
                    <tr>
                        <td style="width: 33%" class="pd center">
                            <span>Hospital Code: {{ isset($data['kodeRS']) ? $data['kodeRS'] : '-' }}</span>
                        </td>
                        <td style="width: 33%" class="pd center">
                            <span>Registration Number:
                                {{ isset($data['noUrutPencatatan']) ? $data['noUrutPencatatan'] : '-' }}</span>
                        </td>
                        <td style="width: 33%" class="pd center">
                            <span>Medical Record Number: {{ isset($data['pasien']['nocm']) ? $data['pasien']['nocm'] : '-' }}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td class="font pd" style="font-weight: bold;padding-left:5px">I. Deceased's Identity</td>
        </tr>
        <tr>
            <td class="font pd">
                <table style="margin-left: 10px">
                    <tr>
                        <td class="pd1">
                            1. Name: {{ isset($data['namaLengkap']) ? $data['namaLengkap'] : '-' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="pd1">
                            2. ID Number/Passport Number: {{ isset($data['NIK']) ? $data['NIK'] : '-' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="pd1">
                            3. Family Card Number: {{ isset($data['NKK']) ? $data['NKK'] : '-' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="pd1">
                            4. Gender: {{ isset($data['jenisKlm']) ? $translate($data['jenisKlm']) : '-' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="pd1">
                            5. Place/Date of Birth: {{ isset($data['tmptLhr']) ? $data['tmptLhr'] : '-' }} /
                            {{ isset($data['pasien']['tgllahir']) ? convertToRegularDate($data['pasien']['tgllahir']) : '-' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="pd1">
                            6. Religion: {{ isset($data['agama']) ? $translate($data['agama']) : '-' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="pd1">
                            7. Address: {{ isset($data['alamatTempTingl']) ? $data['alamatTempTingl'] : '-' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="pd1">
                            8. Nationality: {{ isset($data['kewarnegaraan']) ? $data['kewarnegaraan'] : '-' }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td class="font pd" style="font-weight: bold;padding-left:5px">II. Special Death Information</td>
        </tr>
        <tr>
            <td class="font pd">
                <table style="margin-left: 10px">
                    <tr>
                        <td class="pd1">
                            1. Place of Death:
                            {{ isset($data['tempatMeninggal']) ? $data['tempatMeninggal'] . ', ' : '-' }}
                            @if (isset($data['tempatMeninggal']))
                                @if ($data['tempatMeninggal'] == 'Ruang Rawat')
                                    Length of Stay:
                                    {{ isset($data['lamaDirawatHari_RR']) ? $data['lamaDirawatHari_RR'] : '-' }}
                                    Days,
                                    Hours: {{ isset($data['lamaDirawatJam_RR']) ? $data['lamaDirawatJam_RR'] : '-' }} Hours
                                @elseif ($data['tempatMeninggal'] == 'Instalasi Gawat Darurat')
                                    Length of Stay:
                                    {{ isset($data['lamaDirawatHari_IGD']) ? $data['lamaDirawatHari_IGD'] : '-' }}
                                    Days,
                                    Hours: {{ isset($data['lamaDirawatJam_IGD']) ? $data['lamaDirawatJam_IGD'] : '-' }}
                                    Hours
                                @elseif ($data['tempatMeninggal'] == 'Ruang Bersalin')
                                    {{ isset($data['bayiLahirMeninggal']) ? $data['bayiLahirMeninggal'] : '' }}
                                    {{ isset($data['ibuMelahirkanMeninggal']) ? $data['ibuMelahirkanMeninggal'] : '' }}
                                @elseif ($data['tempatMeninggal'] == 'Diterima di RS dalam keadaan meninggal')
                                    Arrived at the hospital dead
                                @endif
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="pd1">
                            2. Time of Death:
                            {{ isset($data['waktuMeninggal']) ? convertToMakassarDate($data['waktuMeninggal']) : '-' }}
                            WITA
                        </td>
                    </tr>
                    <tr>
                        <td class="pd1">
                            3. Estimated Time of Death:
                            {{ isset($data['perkiraanWaktuMeninggal']) ? convertToMakassarDate($data['perkiraanWaktuMeninggal']) : '-' }}
                            WITA
                        </td>
                    </tr>
                    <tr>
                        <td class="pd1">
                            4. Examination Time:
                            {{ isset($data['waktuPemeriksaan']) ? convertToMakassarDate($data['waktuPemeriksaan']) : '-' }}
                            WITA
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td class="font pd" style="font-weight: bold;padding-left:5px">III. Cause of Death</td>
        </tr>
        <tr>
            <td class="font pd">
                <table style="margin-left: 10px">
                    <tr>
                        <td class="pd1" colspan="4">
                            Basis for Diagnosis (May include more than one)
                        </td>
                    </tr>
                    <tr>
                        <td class="pd1" style="width: 25%">
                            <input type="checkbox" {{ isset($data['catatanMedis']) ? 'checked' : '' }} class="checkbox" />
                            <span>Medical Notes</span>
                        </td>
                        <td class="pd1" style="width: 25%">
                            <input type="checkbox" {{ isset($data['pemeriksaanLuarJenasah']) ? 'checked' : '' }}
                                class="checkbox" />
                            <span>External Body Examination</span>
                        </td>
                        <td class="pd1" style="width: 25%">
                            <input type="checkbox" {{ isset($data['otopsiForesik']) ? 'checked' : '' }} class="checkbox" />
                            <span>Forensic Autopsy</span>
                        </td>
                        <td class="pd1" style="width: 25%">
                            <input type="checkbox" {{ isset($data['otopsiKlinik']) ? 'checked' : '' }} class="checkbox" />
                            <span>Clinical Autopsy</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td class="font pd" style="font-weight: bold;padding-left:5px">IV. Cause of Death Category</td>
        </tr>
        <tr>
            <td class="font pd">
                <table style="margin-left: 10px">
                    <tr>
                        <td class="pd1" style="width: 33%">
                            <input type="checkbox" {{ ($data['alamiah'] ?? '') === 'Alamiah' ? 'checked' : '' }}
                                class="checkbox" />
                            <span>Natural</span>
                        </td>
                        <td class="pd1" style="width: 33%">
                            <input type="checkbox" {{ ($data['tidakAlamiah'] ?? '') === 'Tidak Alamiah' ? 'checked' : '' }}
                                class="checkbox" />
                            <span>Non-Natural</span>
                        </td>
                        <td class="pd1" style="width: 33%">
                            <input type="checkbox"
                                {{ ($data['tidakDD_KPK'] ?? '') === 'Tidak Dapat Ditentukan' ? 'checked' : '' }}
                                class="checkbox" />
                            <span>Indeterminate</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td class="font pd">
                <table>
                    <tr>
                        <td style="width: 70%"></td>
                        <td style="width: 30%;text-align:center">
                            <br><br>
                            Denpasar, {{ isset($data['tanggal']) ? convertToMakassarDate($data['tanggal']) : '-' }}<br>
                            <span>Examiners Doctor</span><br />
                            @if (isset($data['dokterPemeriksa']['label']))
                                <img src="data:image/png;base64, {{ $qrcode }}" width="100" alt="QR verifikasi dokumen SIMRS"><br>
                            @endif
                            {{ isset($data['dokterPemeriksa']) ? $data['dokterPemeriksa']['label'] : '-' }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        @if (
            ($data['alamiah'] ?? '') === 'Alamiah' ||
                ($data['tidakAlamiah'] ?? '') === 'Tidak Alamiah' ||
                ($data['tidakDD_KPK'] ?? '') === 'Tidak Dapat Ditentukan')
            <tr class="page-break" style="border-top:1px solid black">
                <td class="font pd">

                    <table style="width:100%; margin-left:10px; border-collapse:collapse">
                        <tr>
                            <td colspan="2" class="pd1" style="width:66%">
                                <strong>Cause of death based on ICD-10</strong><br>
                                <strong style="margin-left:15px">
                                    Death aged 7 days
                                </strong>
                            </td>
                            <td class="pd1" style="width:34%; vertical-align:bottom">
                                <strong>ICD Code</strong>
                            </td>
                        </tr>

                        @foreach (['A', 'B', 'C', 'D'] as $index => $item)
                            <tr>
                                @if ($index === 0)
                                    <td class="pd1" style="width:25%; vertical-align:top" rowspan="4">
                                        <strong>a. Direct Cause</strong><br>
                                        <span style="margin-left:15px">Undirect Cause</span>
                                    </td>
                                @endif

                                <td class="pd1" style="width:41%; vertical-align:top">
                                    {{ strtolower($item) }}.
                                    {{ $data['isiDirectCause' . $item] ?? '.............................................' }}
                                </td>

                                <td class="pd1" style="width:34%; vertical-align:top">
                                    {{ isset($data['kodeICD' . $item]['label'])
                                        ? $data['kodeICD' . $item]['label']
                                        : $data['kodeICD' . $item] ?? '.............................................' }}
                                </td>
                            </tr>
                        @endforeach

                        <tr>
                            <td class="pd1">
                                <strong>b. Other cause</strong>
                            </td>

                            <td class="pd1">
                                {{ $data['isiOtherCause'] ?? '.................................................' }}<br>
                            </td>

                            <td class="pd1">
                                {{ $data['kodeICDOtherCause']['label'] ?? '.............................................' }}<br>
                            </td>
                        </tr>

                        <tr>
                            <td colspan="3" class="pd1" style="padding-left:15px">
                                <strong>
                                    (Contributing but not related to 1a-d)
                                </strong>
                            </td>
                        </tr>

                        <tr>
                            <td colspan="3" class="pd1" style="padding-left:15px">
                                <strong>
                                    Perinatal death (0-6) days
                                </strong>
                            </td>
                        </tr>

                        @php
                            $perinatal = [
                                'a' => [
                                    'label' => 'Main causes of infant mortality',
                                    'key' => 'Penyebabutamakematianbayi',
                                ],
                                'b' => [
                                    'label' => 'Other causes of infant mortality',
                                    'key' => 'Penyebablainkematianbayi',
                                ],
                                'c' => [
                                    'label' => 'Main causes of maternal mortality',
                                    'key' => 'Penyebabutamakematianibu',
                                ],
                                'd' => [
                                    'label' => 'Other causes of maternal mortality',
                                    'key' => 'Penyebablainkematianibu',
                                ],
                            ];
                        @endphp

                        @foreach ($perinatal as $key => $object)
                            <tr>
                                <td class="pd1" style="padding-left:15px; width:33%">
                                    <strong>{{ $key }}. {{ $object['label'] }}</strong>
                                </td>

                                <td class="pd1" style="width:34%">
                                    {{ $data['isi' . $object['key']] ?? '.............................................' }}<br>
                                </td>

                                <td class="pd1" style="width:33%">
                                    {{ isset($data['kodeICD' . $object['key']]['label'])
                                        ? $data['kodeICD' . $object['key']]['label']
                                        : $data['kodeICD' . $object['key']] ?? '.............................................' }}
                            </tr>
                        @endforeach

                        <tr>
                            <td colspan="3" class="pd1" style="text-align:right; padding-top:10px">
                                <div style="width:240px; text-align:center; margin-left:auto">
                                    <br><br>
                                    Denpasar,
                                    {{ isset($data['tanggalDokterDiagnosis']) ? convertToMakassarDate($data['tanggalDokterDiagnosis']) : '-' }}<br>
                                    <span>Doctor who diagnose</span><br />
                                    @if (isset($data['dokterDiagnosis']['label']))
                                        <img src="data:image/png;base64, {{ $qrcode }}" width="100" alt="QR verifikasi dokumen SIMRS"><br>
                                    @endif
                                    {{ isset($data['dokterDiagnosis']) ? $data['dokterDiagnosis']['label'] : '-' }}
                                </div>
                            </td>
                        </tr>

                    </table>
                </td>
            </tr>
        @endif
    @else
        <tr>
            <td align="center">
                <div style="font-size: 15pt; font-weight: bold;padding:3px">
                    <u>SURAT KETERANGAN KEMATIAN {{ !empty($data['pasienBayi']) ? 'BAYI' : '' }}</u><br>
                    <span style="font-size:13pt">NOMOR : {{ isset($data['nomor']) ? $data['nomor'] : '-' }}</span>
                </div>
            </td>
        </tr>
        <tr>
            <td class="font">
                <table>
                    <tr>
                        <td style="width: 33%" class="pd center">
                            <span>Kode RS : {{ isset($data['kodeRS']) ? $data['kodeRS'] : '-' }}</span>
                        </td>
                        <td style="width: 33%" class="pd center">
                            <span>Nomor Urut Pencatatan :
                                {{ isset($data['noUrutPencatatan']) ? $data['noUrutPencatatan'] : '-' }}</span>
                        </td>
                        <td style="width: 33%" class="pd center">
                            <span>Nomor Rekam Medis : {{ isset($data['pasien']['nocm']) ? $data['pasien']['nocm'] : '-' }}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td class="font pd" style="font-weight: bold;padding-left:5px">I. Identitas Jenazah</td>
        </tr>
        <tr>
            <td class="font pd">
                <table style="margin-left: 10px">
                    <tr>
                        <td class="pd1">
                            1. Nama : {{ isset($data['namaLengkap']) ? $data['namaLengkap'] : '-' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="pd1">
                            2. NIK/No Paspor : {{ isset($data['NIK']) ? $data['NIK'] : '-' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="pd1">
                            3. Nomor Kartu Keluarga : {{ isset($data['NKK']) ? $data['NKK'] : '-' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="pd1">
                            4. Jenis Kelamin : {{ isset($data['jenisKlm']) ? $data['jenisKlm'] : '-' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="pd1">
                            5. Tempat/Tgl Lahir : {{ isset($data['tmptLhr']) ? $data['tmptLhr'] : '-' }} /
                            {{ isset($data['pasien']['tgllahir']) ? convertToRegularDate($data['pasien']['tgllahir']) : '-' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="pd1">
                            6. Agama : {{ isset($data['agama']) ? $data['agama'] : '-' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="pd1">
                            7. Alamat : {{ isset($data['alamatTempTingl']) ? $data['alamatTempTingl'] : '-' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="pd1">
                            8. Kewarganegaraan : {{ isset($data['kewarnegaraan']) ? $data['kewarnegaraan'] : '-' }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td class="font pd" style="font-weight: bold;padding-left:5px">II. Keterangan Khusus Kematian</td>
        </tr>
        <tr>
            <td class="font pd">
                <table style="margin-left: 10px">
                    <tr>
                        <td class="pd1">
                            1. Tempat Meninggal :
                            {{ isset($data['tempatMeninggal']) ? $data['tempatMeninggal'] . ', ' : '-' }}
                            @if (isset($data['tempatMeninggal']))
                                @if ($data['tempatMeninggal'] == 'Ruang Rawat')
                                    Lama Dirawat :
                                    {{ isset($data['lamaDirawatHari_RR']) ? $data['lamaDirawatHari_RR'] : '-' }}
                                    Hari,
                                    Jam : {{ isset($data['lamaDirawatJam_RR']) ? $data['lamaDirawatJam_RR'] : '-' }} Jam
                                @elseif ($data['tempatMeninggal'] == 'Instalasi Gawat Darurat')
                                    Lama Dirawat :
                                    {{ isset($data['lamaDirawatHari_IGD']) ? $data['lamaDirawatHari_IGD'] : '-' }}
                                    Hari,
                                    Jam : {{ isset($data['lamaDirawatJam_IGD']) ? $data['lamaDirawatJam_IGD'] : '-' }} Jam
                                @elseif ($data['tempatMeninggal'] == 'Ruang Bersalin')
                                    {{ isset($data['bayiLahirMeninggal']) ? $data['bayiLahirMeninggal'] : '' }}
                                    {{ isset($data['ibuMelahirkanMeninggal']) ? $data['ibuMelahirkanMeninggal'] : '' }}
                                @elseif ($data['tempatMeninggal'] == 'Diterima di RS dalam keadaan meninggal')
                                    Diterima di RS dalam keadaan meninggal
                                @endif
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="pd1">
                            2. Waktu Meninggal :
                            {{ isset($data['waktuMeninggal']) ? convertToMakassarDate($data['waktuMeninggal']) : '-' }}
                            WITA
                        </td>
                    </tr>
                    <tr>
                        <td class="pd1">
                            3. Perkiraan Waktu Meninggal :
                            {{ isset($data['perkiraanWaktuMeninggal']) ? convertToMakassarDate($data['perkiraanWaktuMeninggal']) : '-' }}
                            WITA
                        </td>
                    </tr>
                    <tr>
                        <td class="pd1">
                            4. Waktu Pemeriksaan :
                            {{ isset($data['waktuPemeriksaan']) ? convertToMakassarDate($data['waktuPemeriksaan']) : '-' }}
                            WITA
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td class="font pd" style="font-weight: bold;padding-left:5px">III. Penyebab Kematian</td>
        </tr>
        <tr>
            <td class="font pd">
                <table style="margin-left: 10px">
                    <tr>
                        <td class="pd1" colspan="4">
                            Dasar Diagnosis (Dapat lebih dari satu)
                        </td>
                    </tr>
                    <tr>
                        <td class="pd1" style="width: 25%">
                            <input type="checkbox" {{ isset($data['catatanMedis']) ? 'checked' : '' }}
                                class="checkbox" />
                            <span>Catatan Medis</span>
                        </td>
                        <td class="pd1" style="width: 25%">
                            <input type="checkbox" {{ isset($data['pemeriksaanLuarJenasah']) ? 'checked' : '' }}
                                class="checkbox" />
                            <span>Pemeriksaan Luar Jenasah</span>
                        </td>
                        <td class="pd1" style="width: 25%">
                            <input type="checkbox" {{ isset($data['otopsiForesik']) ? 'checked' : '' }}
                                class="checkbox" />
                            <span>Otopsi Foresik</span>
                        </td>
                        <td class="pd1" style="width: 25%">
                            <input type="checkbox" {{ isset($data['otopsiKlinik']) ? 'checked' : '' }}
                                class="checkbox" />
                            <span>Otopsi Klinik</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td class="font pd" style="font-weight: bold;padding-left:5px">IV. Kelompok Penyebab Kematian</td>
        </tr>
        <tr>
            <td class="font pd">
                <table style="margin-left: 10px">
                    <tr>
                        <td class="pd1" style="width: 33%">
                            <input type="checkbox" {{ ($data['alamiah'] ?? '') === 'Alamiah' ? 'checked' : '' }}
                                class="checkbox" />
                            <span>Alamiah</span>
                        </td>
                        <td class="pd1" style="width: 33%">
                            <input type="checkbox"
                                {{ ($data['tidakAlamiah'] ?? '') === 'Tidak Alamiah' ? 'checked' : '' }}
                                class="checkbox" />
                            <span>Tidak Alamiah</span>
                        </td>
                        <td class="pd1" style="width: 33%">
                            <input type="checkbox"
                                {{ ($data['tidakDD_KPK'] ?? '') === 'Tidak Dapat Ditentukan' ? 'checked' : '' }}
                                class="checkbox" />
                            <span>Tidak Dapat Ditentukan</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td class="font pd">
                <table>
                    <tr>
                        <td style="width: 70%"></td>
                        <td style="width: 30%;text-align:center">
                            <br><br>
                            Denpasar, {{ isset($data['tanggal']) ? convertToMakassarDate($data['tanggal']) : '-' }}<br>
                            <span>Dokter pemeriksaan</span><br />
                            @if (isset($data['dokterPemeriksa']['label']))
                                <img src="data:image/png;base64, {{ $qrcode }}" width="100" alt="QR verifikasi dokumen SIMRS"><br>
                            @endif
                            {{ isset($data['dokterPemeriksa']) ? $data['dokterPemeriksa']['label'] : '-' }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        @if (
            ($data['alamiah'] ?? '') === 'Alamiah' ||
                ($data['tidakAlamiah'] ?? '') === 'Tidak Alamiah' ||
                ($data['tidakDD_KPK'] ?? '') === 'Tidak Dapat Ditentukan')
            <tr class="page-break" style="border-top:1px solid black">
                <td class="font pd">

                    <table style="width:100%; margin-left:10px; border-collapse:collapse">
                        <tr>
                            <td colspan="2" class="pd1" style="width:66%">
                                <strong>Penyebab kematian berdasarkan ICD-10</strong><br>
                                <strong style="margin-left:15px">
                                    Kematian umur 7 (tujuh) hari keatas
                                </strong>
                            </td>
                            {{-- <td class="pd1" style="width:34%; vertical-align:bottom">
                                <strong>Kode ICD</strong>
                            </td> --}}
                        </tr>

                        @foreach (['A', 'B', 'C'] as $index => $item)
                            <tr>
                                @if ($index === 0)
                                    <td class="pd1" style="width:25%; vertical-align:top" rowspan="4">
                                        <strong>a. Penyebab langsung</strong><br>
                                        <span style="margin-left:15px">
                                            Penyebab antara
                                        </span>
                                    </td>
                                @endif

                                <td class="pd1" style="width:41%; vertical-align:top; line-height: 1.4">
                                    {{ strtolower($item) }}.
                                    {!! nl2br(e($data['isiDirectCause' . $item] ?? '.................................................')) !!} <br>
                                </td>

                                {{-- <td class="pd1" style="width:34%; vertical-align:top">
                                    {{ isset($data['kodeICD' . $item]['label'])
                                        ? $data['kodeICD' . $item]['label']
                                        : $data['kodeICD' . $item] ?? '.............................................' }}
                                </td> --}}
                            </tr>
                        @endforeach

                        @php
                            $isiOther = $data['isiOtherCause'] ?? null;

                            if ($isiOther) {
                                $isiOther = preg_replace('/\s+/', "\n", trim($isiOther));
                            }
                        @endphp
                        <tr>
                            <td class="pd1">
                                <div style="margin-left:-80%">
                                    <strong>b. Penyebab lain</strong>
                                </div>
                            </td>

                            <td class="pd1">
                                <div style="margin-left:-160%; line-height: 1.4">
                                    {!! nl2br(e($data['isiOtherCause'] ?? '.................................................')) !!}
                                </div>
                                <br>
                            </td>

                            {{-- <td class="pd1">
                                {{ $data['kodeICDOtherCause']['label'] ?? '.............................................' }}<br>
                            </td> --}}
                        </tr>

                        <tr>
                            <td colspan="3" class="pd1" style="padding-left:15px">
                                <strong>
                                    (berkontribusi tapi tidak terkait dengan 1a–d)
                                </strong>
                            </td>
                        </tr>

                        <tr>
                            <td colspan="3" class="pd1" style="padding-left:15px">
                                <strong>
                                    Kematian umur 0–6 (enam) hari termasuk lahir mati
                                </strong>
                            </td>
                        </tr>

                        @php
                            $perinatal = [
                                'a' => [
                                    'label' => 'Penyebab utama kematian bayi',
                                    'key' => 'Penyebabutamakematianbayi',
                                ],
                                'b' => [
                                    'label' => 'Penyebab lain kematian bayi',
                                    'key' => 'Penyebablainkematianbayi',
                                ],
                                'c' => [
                                    'label' => 'Penyebab utama kematian ibu',
                                    'key' => 'Penyebabutamakematianibu',
                                ],
                                'd' => [
                                    'label' => 'Penyebab lain kematian ibu',
                                    'key' => 'Penyebablainkematianibu',
                                ],
                            ];
                        @endphp

                        @foreach ($perinatal as $key => $object)
                            <tr>
                                <td class="pd1" style="padding-left:15px; width:33%">
                                    <strong>{{ $key }}. {{ $object['label'] }}</strong>
                                </td>

                                <td class="pd1" style="width:34%">
                                    {{ $data['isi' . $object['key']] ?? '.............................................' }}<br>
                                </td>

                                {{-- <td class="pd1" style="width:33%">
                                    {{ isset($data['kodeICD' . $object['key']]['label'])
                                        ? $data['kodeICD' . $object['key']]['label']
                                        : $data['kodeICD' . $object['key']] ?? '.............................................' }} --}}
                            </tr>
                        @endforeach

                        <tr>
                            <td colspan="3" class="pd1" style="text-align:right; padding-top:10px">
                                <div style="width:240px; text-align:center; margin-left:auto">
                                    <br><br>
                                    Denpasar,
                                    {{ isset($data['tanggalDokterDiagnosis']) ? convertToMakassarDate($data['tanggalDokterDiagnosis']) : '-' }}<br>
                                    <span>Dokter yang mendiagnosis</span><br />
                                    @if (isset($data['dokterDiagnosis']['label']))
                                        <img src="data:image/png;base64, {{ $qrcode }}" width="100" alt="QR verifikasi dokumen SIMRS"><br>
                                    @endif
                                    {{ isset($data['dokterDiagnosis']) ? $data['dokterDiagnosis']['label'] : '-' }}
                                </div>
                            </td>
                        </tr>

                    </table>
                </td>
            </tr>
        @endif
    @endif
@endsection
