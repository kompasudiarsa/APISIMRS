{{-- Jhon | 2026-11-13 20:13 WITA | TAMBAH: adapter PDF API dari view Nadi; tanpa kontrol browser. --}}
{{-- Jhon | 2026-11-13 19:42 WITA | UBAH: adapter format SIMRS ke laporan pasien Nadi berbahasa Indonesia. --}}
@extends('template.layout-emr-surat')
@section('title')
    @if ($cekWargaNegaraWNA)
        Print Certificate of Disability
    @else
        Cetak Surat Keterangan Disabilitas
    @endif
@endsection
@section('kode', 'RM.14/SK/00')
@section('page-style')
    <style>
        body,
        table,
        td,
        pre {
            font-family: 'Open Sans', sans-serif !important;
        }

        td {
            font-size: 10pt;
        }

        .page-break {
            page-break-after: always;
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
@endphp

@section('content')
    @if ($cekWargaNegaraWNA == true)
        <tr>
            <td align="center">
                <div style="font-size: 15pt; font-weight: bold; color: #000000;padding:8px">
                    <u>CERTIFICATE OF DISABILITY</u><br>
                    <span style="font-size:13pt">NOMOR : {{ isset($data['nosurat']) ? $data['nosurat'] : '-' }}</span>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <div style="padding:7px">
                    <span>Below, the examining doctor at Bali Mandara Hospital explained that :</span>
                    <div style="margin-left: 20px;margin-top: 10px;margin-bottom: 10px">
                        <table width="100%" style="border-spacing: 0px;">
                            <tr>
                                <td width="30%">Name</td>
                                <td width="70%">: {{ $data['TBNamaPasien'] ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td width="30%">Place / Date of birth</td>
                                <td width="70%">:
                                    {{ isset($data['TBTempatLahirPasien']) ? $data['TBTempatLahirPasien'] : '-' }} /
                                    {{ isset($data['DTanggalLahir']) ? formatDateIndonesian($data['DTanggalLahir']) : '-' }}
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Age</td>
                                <td width="70%">:
                                    {{ isset($data['TBSUmurPasien']) ? $data['TBSUmurPasien'] : '-' }} Years Old
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Sex</td>
                                <td width="70%">
                                    <table width="100%">
                                        <tr>
                                            <td style="width: 10%">:</td>
                                            <td style="width: 45%">
                                                <input type="checkbox"
                                                    {{ isset($data['TBJenisKelaminPasien']) && $data['TBJenisKelaminPasien'] == 'Laki-laki' ? 'checked' : '' }} />
                                                <span style="font-size: 9pt;" color="#000000">Man</span>
                                            </td>
                                            <td style="width: 45%">
                                                <input type="checkbox"
                                                    {{ isset($data['TBJenisKelaminPasien']) && $data['TBJenisKelaminPasien'] == 'Perempuan' ? 'checked' : '' }} />
                                                <span style="font-size: 9pt;" color="#000000">Woman</span>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Address</td>
                                <td width="70%">:
                                    {{ isset($data['TAAlamatPasien']) ? $data['TAAlamatPasien'] : '-' }}
                                </td>
                            </tr>
                        </table>
                    </div>

                    <span>After a medical examination and functional ability that the person concerned is truly a person
                        with a disability in the form of :</span>
                    <div style="margin-left: 20px;margin-top: 10px">
                        <p>1. Type/Diversity of Disability</p>
                        <div style="margin-left: 20px;margin-top: 10px">
                            <span>a. Physical Disability</span>
                            <div style="margin-left: 10px;margin-bottom: 10px">
                                <table width="100%" style="border-spacing: 0px;">
                                    <tr>
                                        <td style="width: 55%">1). Amputation</td>
                                        <td style="width: 15%">
                                            <input type="checkbox"
                                                {{ isset($data['CBTanganAmputasi']) && $data['CBTanganAmputasi'] == 'Tangan' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000">Hand</span>
                                        </td>
                                        <td style="width: 15%">
                                            <input type="checkbox"
                                                {{ isset($data['CBKakiAmputasi']) && $data['CBKakiAmputasi'] == 'Kaki' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000">Foot</span>
                                        </td>
                                        <td style="width: 15%">
                                            <input type="checkbox"
                                                {{ isset($data['CBAmputasi']) && $data['CBAmputasi'] == 'Amputasi' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000"></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>2). Limp or stiff paralysis</td>
                                        <td>
                                            <input type="checkbox"
                                                {{ isset($data['CBTanganLumpuhLayuh']) && $data['CBTanganLumpuhLayuh'] == 'Tangan' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000">Hand</span>
                                        </td>
                                        <td>
                                            <input type="checkbox"
                                                {{ isset($data['CBKakiLumpuhLayuh']) && $data['CBKakiLumpuhLayuh'] == 'Kaki' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000">Foot</span>
                                        </td>
                                        <td>
                                            <input type="checkbox"
                                                {{ isset($data['CBLumpuhLayuh']) && $data['CBLumpuhLayuh'] == 'Lumpuh layuh atau kaku' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000"></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="3">3). Paraplegia (lower limbs that include both legs and pelvic
                                            organs)</td>
                                        <td>
                                            <input type="checkbox"
                                                {{ isset($data['CBParaplegi']) && $data['CBParaplegi'] == 'Paraplegi' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000"></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="3">4. Cerebral Palsy (CP)</td>
                                        <td>
                                            <input type="checkbox"
                                                {{ isset($data['CBCerebalPalsy']) && $data['CBCerebalPalsy'] == 'Cerebral Palsy' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000"></span>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <span>b. Sensory Disability</span>
                            <div style="margin-left: 10px;margin-bottom: 10px">
                                <table width="100%" style="border-spacing: 0px;">
                                    <tr>
                                        <td colspan="4">1). Netra</td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" style="width: 85%">
                                            <div style="margin-left: 10px">a. Total blindness</div>
                                        </td>
                                        <td style="width: 15%">
                                            <input type="checkbox"
                                                {{ isset($data['CBButaTotalNetra']) && $data['CBButaTotalNetra'] == 'a. Buta Total' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000"></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" style="width: 85%">
                                            <div style="margin-left: 10px">b. Light Perception / Low vision</div>
                                        </td>
                                        <td style="width: 15%">
                                            <input type="checkbox"
                                                {{ isset($data['CBPersepsiCahayaNetra']) && $data['CBPersepsiCahayaNetra'] == 'b. Persepsi Cahaya Low vision' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000"></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="3">2). Deaf</td>
                                        <td>
                                            <input type="checkbox"
                                                {{ isset($data['CBRungu']) && $data['CBRungu'] == 'Rungu' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000"></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="3">3. Speech</td>
                                        <td>
                                            <input type="checkbox"
                                                {{ isset($data['CBWicara']) && $data['CBWicara'] == 'Wicara' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000"></span>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <span>c. Intellectual Disability</span>
                            <div style="margin-left: 10px;margin-bottom: 10px">
                                <table width="100%" style="border-spacing: 0px;">
                                    <tr>
                                        <td colspan="3" style="width: 85%">
                                            <div style="margin-left: 10px">1). Mental Disability</div>
                                        </td>
                                        <td style="width: 15%">
                                            <input type="checkbox"
                                                {{ isset($data['CBDisabilitasGrahitaDI']) && $data['CBDisabilitasGrahitaDI'] == 'Disabilitas Grahita' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000"></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" style="width: 85%">
                                            <div style="margin-left: 10px">2). Down Syndrome</div>
                                        </td>
                                        <td style="width: 15%">
                                            <input type="checkbox"
                                                {{ isset($data['CBDownSyndromeDI']) && $data['CBDownSyndromeDI'] == 'Down Syndrome' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000"></span>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <span>d. Mental Disability</span>
                            <div style="margin-left: 10px;margin-bottom: 10px">
                                <table width="100%" style="border-spacing: 0px;">
                                    <tr>
                                        <td style="width: 40%">
                                            <div style="margin-left: 10px;">1). Psychosocial :</div>
                                        </td>
                                        <td style="width: 17%">
                                            <input type="checkbox"
                                                {{ isset($data['CBSkizofreniaPsikososial']) && $data['CBSkizofreniaPsikososial'] == 'Skizofrenia' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000">Schizophrenia</span>
                                        </td>
                                        <td style="width: 15%">
                                            <input type="checkbox"
                                                {{ isset($data['CBBipolarPsikososial']) && $data['CBBipolarPsikososial'] == 'Bipolar' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000">Bipolar</span>
                                        </td>
                                        <td style="width: 15%">
                                            <input type="checkbox"
                                                {{ isset($data['CBDepresiPsikososial']) && $data['CBDepresiPsikososial'] == 'Depresi' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000">Depression</span>
                                        </td>
                                        <td style="width: 15%">
                                            <input type="checkbox"
                                                {{ isset($data['CBPsikososial']) && $data['CBPsikososial'] == 'Psikososial' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000"></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td></td>
                                        <td>
                                            <input type="checkbox"
                                                {{ isset($data['CBAnxietasPsikososial']) && $data['CBAnxietasPsikososial'] == 'Anxietas' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000">Anxietas</span>
                                        </td>
                                        <td colspan="2">
                                            <input type="checkbox"
                                                {{ isset($data['CBGangguanKepribadianPsikososial']) && $data['CBGangguanKepribadianPsikososial'] == 'Gangguan Kepribadian' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000">Personality Disorder</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div style="margin-left: 10px;">2). Developmental disabilities :</div>
                                        </td>
                                        <td>
                                            <input type="checkbox"
                                                {{ isset($data['CBAutisDP']) && $data['CBAutisDP'] == 'Autis' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000">Autism</span>
                                        </td>
                                        <td>
                                            <input type="checkbox"
                                                {{ isset($data['CBHiperaktifDP']) && $data['CBHiperaktifDP'] == 'Hiperaktif' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000">Hyperactive</span>
                                        </td>
                                        <td></td>
                                        <td>
                                            <input type="checkbox"
                                                {{ isset($data['CBDisabilitasPerkembangan']) && $data['CBDisabilitasPerkembangan'] == 'Disabilitas Perkembangan' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000"></span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <p>2. Degree of Disability :</p>
                        <div style="margin-left: 20px;margin-top: -10px">
                            <pre>{{ isset($data['TADerajatDisabilitas']) ? App\Traits\Valet::english($data['TADerajatDisabilitas']) : '-' }}</pre>
                        </div>

                        <div class="page-break"></div>

                        <p>3. Causes :</p>
                        <div style="margin-left: 20px;margin-top: 10px">
                            <table width="100%" style="border-spacing: 0px;">
                                <tr>
                                    <td>
                                        <input type="checkbox"
                                            {{ isset($data['CBSejakLahir']) && $data['CBSejakLahir'] == 'Sejak Lahir' ? 'checked' : '' }} />
                                        <span style="font-size: 9pt;" color="#000000">Since Birth</span>
                                    </td>
                                    <td>
                                        <input type="checkbox"
                                            {{ isset($data['CBKecelakaanDalamPekerjaan']) && $data['CBKecelakaanDalamPekerjaan'] == 'Kecelakaan dalam pekerjaan' ? 'checked' : '' }} />
                                        <span style="font-size: 9pt;" color="#000000">Accidents on the job</span>
                                    </td>
                                    <td>
                                        <input type="checkbox"
                                            {{ isset($data['CBKecelakaanLaluLintas']) && $data['CBKecelakaanLaluLintas'] == 'Kecelakaan Lalu Lintas' ? 'checked' : '' }} />
                                        <span style="font-size: 9pt;" color="#000000">Traffic Accidents</span>
                                    </td>
                                    <td>
                                        <input type="checkbox"
                                            {{ isset($data['CBPenyakit']) && $data['CBPenyakit'] == 'Penyakit' ? 'checked' : '' }} />
                                        <span style="font-size: 9pt;" color="#000000">Disease</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox"
                                            {{ isset($data['CBAkibatStroke']) && $data['CBAkibatStroke'] == 'Akibat Stroke' ? 'checked' : '' }} />
                                        <span style="font-size: 9pt;" color="#000000">Stroke Consequences</span>
                                    </td>
                                    <td>
                                        <input type="checkbox"
                                            {{ isset($data['CBAkibatKusta']) && $data['CBAkibatKusta'] == 'Akibat Kusta' ? 'checked' : '' }} />
                                        <span style="font-size: 9pt;" color="#000000">Leprosy Consequences</span>
                                    </td>
                                    <td>
                                        <input type="checkbox" {{ isset($data['TALainlain']) ? 'checked' : '' }} />
                                        <span style="font-size: 9pt;" color="#000000">Other :</span>
                                        {{ isset($data['TALainlain']) ? App\Traits\Valet::english($data['TALainlain']) : '-' }}
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <p>4. Tools used :</p>
                        <div style="margin-left: 20px;margin-top: 10px">
                            <table width="100%" style="border-spacing: 0px;">
                                <tr>
                                    <td>
                                        <input type="checkbox"
                                            {{ isset($data['CBABYD']) && $data['CBABYD'] == 'Tidak' ? 'checked' : '' }} />
                                        <span style="font-size: 9pt;" color="#000000">No</span>
                                    </td>
                                    <td>
                                        <input type="checkbox"
                                            {{ isset($data['CBABYD']) && $data['CBABYD'] == 'Ada' ? 'checked' : '' }} />
                                        <span style="font-size: 9pt;" color="#000000">There is, in the form of :</span>
                                        {{ isset($data['TBABYD']) ? App\Traits\Valet::english($data['TBABYD']) : '-' }}
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <p>
                        This certificate is for the purpose of : <span>
                            {{ isset($data['TAskiuk']) ? App\Traits\Valet::english($data['TAskiuk']) : '-' }}</span>
                    </p>

                    <hr />

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
                                    Examiner
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
                    <u>SURAT KETERANGAN DISABILITAS</u><br>
                    <span style="font-size:13pt">NOMOR : {{ isset($data['nosurat']) ? $data['nosurat'] : '-' }}</span>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <div style="padding:7px">
                    <span>Di bawah ini, Dokter pemeriksa di RSUD Bali Mandara menerangkan bahwa :</span>
                    <div style="margin-left: 20px;margin-top: 10px;margin-bottom: 10px">
                        <table width="100%" style="border-spacing: 0px;">
                            <tr>
                                <td width="30%">Nama</td>
                                <td width="70%">: {{ $data['TBNamaPasien'] ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td width="30%">Tempat / Tanggal lahir</td>
                                <td width="70%">:
                                    {{ isset($data['TBTempatLahirPasien']) ? $data['TBTempatLahirPasien'] : '-' }} /
                                    {{ isset($data['DTanggalLahir']) ? formatDateIndonesian($data['DTanggalLahir']) : '-' }}
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Umur</td>
                                <td width="70%">:
                                    {{ isset($data['TBSUmurPasien']) ? $data['TBSUmurPasien'] : '-' }} Tahun
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Jenis Kelamin</td>
                                <td width="70%">
                                    <table width="100%">
                                        <tr>
                                            <td style="width: 10%">:</td>
                                            <td style="width: 45%">
                                                <input type="checkbox"
                                                    {{ isset($data['TBJenisKelaminPasien']) && $data['TBJenisKelaminPasien'] == 'Laki-laki' ? 'checked' : '' }} />
                                                <span style="font-size: 9pt;" color="#000000">Laki-laki</span>
                                            </td>
                                            <td style="width: 45%">
                                                <input type="checkbox"
                                                    {{ isset($data['TBJenisKelaminPasien']) && $data['TBJenisKelaminPasien'] == 'Perempuan' ? 'checked' : '' }} />
                                                <span style="font-size: 9pt;" color="#000000">Perempuan</span>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Alamat</td>
                                <td width="70%">:
                                    {{ isset($data['TAAlamatPasien']) ? $data['TAAlamatPasien'] : '-' }}
                                </td>
                            </tr>
                        </table>
                    </div>

                    <span>Setelah dilakukan pemeriksaan kesehatan dan kemampuan fungsional bahwa yang bersangkutan
                        benar-benar sebagai Penyandang Disabilitas berupa:</span>
                    <div style="margin-left: 20px;margin-top: 10px">
                        <p>1. Jenis/Ragam Disabilitas</p>
                        <div style="margin-left: 20px;margin-top: 10px">
                            <span>a. Disabilitas Fisik</span>
                            <div style="margin-left: 10px;margin-bottom: 10px">
                                <table width="100%" style="border-spacing: 0px;">
                                    <tr>
                                        <td style="width: 55%">1). Amputasi</td>
                                        <td style="width: 15%">
                                            <input type="checkbox"
                                                {{ isset($data['CBTanganAmputasi']) && $data['CBTanganAmputasi'] == 'Tangan' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000">Tangan</span>
                                        </td>
                                        <td style="width: 15%">
                                            <input type="checkbox"
                                                {{ isset($data['CBKakiAmputasi']) && $data['CBKakiAmputasi'] == 'Kaki' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000">Kaki</span>
                                        </td>
                                        <td style="width: 15%">
                                            <input type="checkbox"
                                                {{ isset($data['CBAmputasi']) && $data['CBAmputasi'] == 'Amputasi' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000"></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>2). Lumpuh layuh atau kaku</td>
                                        <td>
                                            <input type="checkbox"
                                                {{ isset($data['CBTanganLumpuhLayuh']) && $data['CBTanganLumpuhLayuh'] == 'Tangan' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000">Tangan</span>
                                        </td>
                                        <td>
                                            <input type="checkbox"
                                                {{ isset($data['CBKakiLumpuhLayuh']) && $data['CBKakiLumpuhLayuh'] == 'Kaki' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000">Kaki</span>
                                        </td>
                                        <td>
                                            <input type="checkbox"
                                                {{ isset($data['CBLumpuhLayuh']) && $data['CBLumpuhLayuh'] == 'Lumpuh layuh atau kaku' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000"></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="3">3). Paraplegi (anggota tubuh bagian bawah yang meliputi kedua
                                            tungkai dan organ panggul)</td>
                                        <td>
                                            <input type="checkbox"
                                                {{ isset($data['CBParaplegi']) && $data['CBParaplegi'] == 'Paraplegi' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000"></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="3">4. Cerebral Palsy (CP)</td>
                                        <td>
                                            <input type="checkbox"
                                                {{ isset($data['CBCerebalPalsy']) && $data['CBCerebalPalsy'] == 'Cerebral Palsy' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000"></span>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <span>b. Disabilitas Sensorik</span>
                            <div style="margin-left: 10px;margin-bottom: 10px">
                                <table width="100%" style="border-spacing: 0px;">
                                    <tr>
                                        <td colspan="4">1). Netra</td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" style="width: 85%">
                                            <div style="margin-left: 10px">a. Buta total</div>
                                        </td>
                                        <td style="width: 15%">
                                            <input type="checkbox"
                                                {{ isset($data['CBButaTotalNetra']) && $data['CBButaTotalNetra'] == 'a. Buta Total' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000"></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" style="width: 85%">
                                            <div style="margin-left: 10px">b. Persepsi Cahaya/Low vision</div>
                                        </td>
                                        <td style="width: 15%">
                                            <input type="checkbox"
                                                {{ isset($data['CBPersepsiCahayaNetra']) && $data['CBPersepsiCahayaNetra'] == 'b. Persepsi Cahaya Low vision' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000"></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="3">2). Rungu</td>
                                        <td>
                                            <input type="checkbox"
                                                {{ isset($data['CBRungu']) && $data['CBRungu'] == 'Rungu' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000"></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="3">3. Wicara</td>
                                        <td>
                                            <input type="checkbox"
                                                {{ isset($data['CBWicara']) && $data['CBWicara'] == 'Wicara' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000"></span>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <span>c. Disabilitas Intelektual</span>
                            <div style="margin-left: 10px;margin-bottom: 10px">
                                <table width="100%" style="border-spacing: 0px;">
                                    <tr>
                                        <td colspan="3" style="width: 85%">
                                            <div style="margin-left: 10px">1). Disabilitas Grahita</div>
                                        </td>
                                        <td style="width: 15%">
                                            <input type="checkbox"
                                                {{ isset($data['CBDisabilitasGrahitaDI']) && $data['CBDisabilitasGrahitaDI'] == 'Disabilitas Grahita' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000"></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" style="width: 85%">
                                            <div style="margin-left: 10px">2). Down Syndrome</div>
                                        </td>
                                        <td style="width: 15%">
                                            <input type="checkbox"
                                                {{ isset($data['CBDownSyndromeDI']) && $data['CBDownSyndromeDI'] == 'Down Syndrome' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000"></span>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <span>d. Disabilitas Mental</span>
                            <div style="margin-left: 10px;margin-bottom: 10px">
                                <table width="100%" style="border-spacing: 0px;">
                                    <tr>
                                        <td style="width: 40%">
                                            <div style="margin-left: 10px;">1). Psikososial :</div>
                                        </td>
                                        <td style="width: 15%">
                                            <input type="checkbox"
                                                {{ isset($data['CBSkizofreniaPsikososial']) && $data['CBSkizofreniaPsikososial'] == 'Skizofrenia' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000">Skizofrenia</span>
                                        </td>
                                        <td style="width: 15%">
                                            <input type="checkbox"
                                                {{ isset($data['CBBipolarPsikososial']) && $data['CBBipolarPsikososial'] == 'Bipolar' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000">Bipolar</span>
                                        </td>
                                        <td style="width: 15%">
                                            <input type="checkbox"
                                                {{ isset($data['CBDepresiPsikososial']) && $data['CBDepresiPsikososial'] == 'Depresi' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000">Depresi</span>
                                        </td>
                                        <td style="width: 15%">
                                            <input type="checkbox"
                                                {{ isset($data['CBPsikososial']) && $data['CBPsikososial'] == 'Psikososial' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000"></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td></td>
                                        {{-- <td>
                                            <input type="checkbox"
                                                {{ isset($data['CBDepresiPsikososial']) && $data['CBDepresiPsikososial'] == 'Depresi' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000">Depresi</span>
                                        </td> --}}
                                        <td>
                                            <input type="checkbox"
                                                {{ isset($data['CBAnxietasPsikososial']) && $data['CBAnxietasPsikososial'] == 'Anxietas' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000">Anxietas</span>
                                        </td>
                                        <td colspan="2">
                                            <input type="checkbox"
                                                {{ isset($data['CBGangguanKepribadianPsikososial']) && $data['CBGangguanKepribadianPsikososial'] == 'Gangguan Kepribadian' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000">Gangguan Kepribadian</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div style="margin-left: 10px;">2). Disabilitas perkembangan :</div>
                                        </td>
                                        <td>
                                            <input type="checkbox"
                                                {{ isset($data['CBAutisDP']) && $data['CBAutisDP'] == 'Autis' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000">Autis</span>
                                        </td>
                                        <td>
                                            <input type="checkbox"
                                                {{ isset($data['CBHiperaktifDP']) && $data['CBHiperaktifDP'] == 'Hiperaktif' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000">Hiperaktif</span>
                                        </td>
                                        <td></td>
                                        <td>
                                            <input type="checkbox"
                                                {{ isset($data['CBDisabilitasPerkembangan']) && $data['CBDisabilitasPerkembangan'] == 'Disabilitas Perkembangan' ? 'checked' : '' }} />
                                            <span style="font-size: 9pt;" color="#000000"></span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <p>2. Derajat Disabilitas :</p>
                        <div style="margin-left: 20px;margin-top: -10px">
                            <pre>{{ isset($data['TADerajatDisabilitas']) ? $data['TADerajatDisabilitas'] : '-' }}</pre>
                        </div>

                        <div class="page-break"></div>

                        <p>3. Penyebab :</p>
                        <div style="margin-left: 20px;margin-top: 10px">
                            <table width="100%" style="border-spacing: 0px;">
                                <tr>
                                    <td>
                                        <input type="checkbox"
                                            {{ isset($data['CBSejakLahir']) && $data['CBSejakLahir'] == 'Sejak Lahir' ? 'checked' : '' }} />
                                        <span style="font-size: 9pt;" color="#000000">Sejak Lahir</span>
                                    </td>
                                    <td>
                                        <input type="checkbox"
                                            {{ isset($data['CBKecelakaanDalamPekerjaan']) && $data['CBKecelakaanDalamPekerjaan'] == 'Kecelakaan dalam pekerjaan' ? 'checked' : '' }} />
                                        <span style="font-size: 9pt;" color="#000000">Kecelakaan dalam pekerjaan</span>
                                    </td>
                                    <td>
                                        <input type="checkbox"
                                            {{ isset($data['CBKecelakaanLaluLintas']) && $data['CBKecelakaanLaluLintas'] == 'Kecelakaan Lalu Lintas' ? 'checked' : '' }} />
                                        <span style="font-size: 9pt;" color="#000000">Kecelakaan Lalu Lintas</span>
                                    </td>
                                    <td>
                                        <input type="checkbox"
                                            {{ isset($data['CBPenyakit']) && $data['CBPenyakit'] == 'Penyakit' ? 'checked' : '' }} />
                                        <span style="font-size: 9pt;" color="#000000">Penyakit</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox"
                                            {{ isset($data['CBAkibatStroke']) && $data['CBAkibatStroke'] == 'Akibat Stroke' ? 'checked' : '' }} />
                                        <span style="font-size: 9pt;" color="#000000">Akibat Stroke</span>
                                    </td>
                                    <td>
                                        <input type="checkbox"
                                            {{ isset($data['CBAkibatKusta']) && $data['CBAkibatKusta'] == 'Akibat Kusta' ? 'checked' : '' }} />
                                        <span style="font-size: 9pt;" color="#000000">Akibat Kusta</span>
                                    </td>
                                    <td>
                                        <input type="checkbox" {{ isset($data['TALainlain']) ? 'checked' : '' }} />
                                        <span style="font-size: 9pt;" color="#000000">Lain-lain :</span>
                                        {{ isset($data['TALainlain']) ? $data['TALainlain'] : '-' }}
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <p>4. Alat bantu yang digunakan :</p>
                        <div style="margin-left: 20px;margin-top: 10px">
                            <table width="100%" style="border-spacing: 0px;">
                                <tr>
                                    <td>
                                        <input type="checkbox"
                                            {{ isset($data['CBABYD']) && $data['CBABYD'] == 'Tidak' ? 'checked' : '' }} />
                                        <span style="font-size: 9pt;" color="#000000">Tidak</span>
                                    </td>
                                    <td>
                                        <input type="checkbox"
                                            {{ isset($data['CBABYD']) && $data['CBABYD'] == 'Ada' ? 'checked' : '' }} />
                                        <span style="font-size: 9pt;" color="#000000">Ada, berupa :</span>
                                        {{ isset($data['TBABYD']) ? $data['TBABYD'] : '-' }}
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <p>
                        Surat keterangan ini untuk keperluan : <span> {{ isset($data['TAskiuk']) ? $data['TAskiuk'] : '-' }} </span>
                    </p>

                    <hr />


                    <div style="margin-top: 10px;">
                        <table width="100%">
                            <tr>
                                <td width="60%"></td>
                                <td style="text-align: center" width="40%">
                                    <br>
                                    Denpasar, {{ \Carbon\Carbon::parse($data['registrasi']['tglregistrasi'])->format('d F Y') }}
                                </td>
                            </tr>
                            <tr>
                                <td width="60%"></td>
                                <td style="text-align: center" width="40%">
                                    Pemeriksa
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
                                {{-- <td style="text-align: center;font-weight: bold;font-size:8pt" width="40%">
                                    NIP :
                                    @if (isset($data['nip']->nip))
                                        {{ $data['nip']->nip }}
                                    @else
                                        {{ '-' }}
                                    @endif
                                </td> --}}
                                <td style="text-align: center;font-weight: bold;font-size:8pt" width="40%">
                                    {{-- @php
                                        $identities = [];
                                        if (isset($data['nip']->nip) && !empty($data['nip']->nip)) {
                                            $identities[] = 'NIP: ' . $data['nip']->nip;
                                        }
                                        if (isset($data['nip']->nosip) && !empty($data['nip']->nosip)) {
                                            $identities[] = 'NOSIP: ' . $data['nip']->nosip;
                                        }
                                        if (isset($data['nip']->nipppk) && !empty($data['nip']->nipppk)) {
                                            $identities[] = 'NIPPPK: ' . $data['nip']->nipppk;
                                        }
                                        if (isset($data['nip']->nosipppk) && !empty($data['nip']->nosipppk)) {
                                            $identities[] = 'NOSIPPPK: ' . $data['nip']->nosipppk;
                                        }

                                        echo $identities ? implode(' / ', $identities) : '-';
                                    @endphp --}}
                                    @if (!empty($data['nip']->nip))
                                        NIP : {{ $data['nip']->nip }}<br>
                                    @endif

                                    @if (!empty($data['nosip']->nosip))
                                        SIP : {{ $data['nosip']->nosip }}<br>
                                    @endif

                                    @if (!empty($data['noskp']->noskp))
                                        SKP : {{ $data['noskp']->noskp }}<br>
                                    @endif

                                    @if (!empty($data['nosippk']->nosippk))
                                        SIPPK : {{ $data['nosippk']->nosippk }}<br>
                                    @endif

                                    @if (empty($data['nip']->nip) && empty($data['nosip']->nosip) && empty($data['noskp']->noskp))
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
