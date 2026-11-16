{{-- Jhon | 2026-11-13 20:13 WITA | TAMBAH: adapter PDF API dari view Nadi; tanpa kontrol browser. --}}
{{-- Jhon | 2026-11-13 19:42 WITA | UBAH: adapter format SIMRS ke laporan pasien Nadi berbahasa Indonesia. --}}
<!DOCTYPE html>
<html>

<head>
    <title>@yield('title')</title>
    @yield('page-style')
    <style>
        .checkbox-wrapper {
            white-space: nowrap
        }

        @page{
          /* margin: 5mm; */
          size: 210mm 297mm;
        }

        .checkbox {
            vertical-align: top;
            display: inline-block
        }

        .checkbox-label {
            white-space: normal display:inline-block
        }
    </style>
</head>

<body>

    @php
        use Carbon\Carbon;
    @endphp
    <table width="100%" cellspacing="0" cellpadding="0"
        style="border: 1px solid black; border-bottom: none;border-collapse: collapse">
        <tr colspan="3">
            <td style="background-color: lightblue; padding:5px; font-weight:bold; width: 50%;">
                RSUD BALI MANDARA
            </td>
            <td colspan="2"
                style="background-color: lightblue; padding:5px; font-weight:bold; width: 50%; text-align: right;">
                RM 4/SK/01
            </td>
        </tr>
    </table>
    <table width="100%" cellspacing="0" cellpadding="0"
        style="border: 1px solid black; border-bottom: none;border-collapse: collapse">
        <tr>
            <td style="border: 1px solid black; width: 10%;">
                <img src="{{ public_path('img/logo-provinsi-bali.svg') }}" width="100px" height="100px" style="display: block;">
            </td>

            <td style="border: 1px solid black; font-weight: bold; text-align: center; width: 60%;">
                <h3>PEMERINTAH PROVINSI BALI <br>RSUD BALI MANDARA <br>Jalan By Pass Ngurah Rai No. 548, Denpasar - Bali
                </h3>
            </td>
            <td style="border: 1px solid black; width: 10%;">
                <img src="{{ public_path('img/logo-rs.png') }}" width="100px" height="100px" style="display: block;">
            </td>
        </tr>
    </table>
    <table width="100%" cellspacing="0" cellpadding="0" style="border: 1px solid black; border-collapse: collapse">
        <tr>
            <td align="center">
                <div style="font-size: 15pt; font-weight: bold; color: #000000;padding:8px">
                    <u>SURAT KETERANGAN HAMIL</u><br>
                    <span style="font-size:13pt">NOMOR : {{ isset($data['nosurat']) ? $data['nosurat'] : '-' }}</span>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <div style="padding:7px;padding-top: 0px;padding-bottom: 0px">
                    <span>Saya yang bertanda tangan dibawah ini :</span>
                    <div style="margin-left: 20px;margin-top: 10px;margin-bottom: 10px">
                        <table width="100%" style="border-spacing: 0px;">
                            <tr>
                                <td width="30%">Nama</td>
                                <td width="70%">:
                                    @if (isset($data['petugasAddmision']['label']))
                                        {{ $data['petugasAddmision']['label'] }}
                                    @elseif (isset($data['petugasAddmision']))
                                        {{ $data['petugasAddmision'] }}
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Spesialis</td>
                                <td width="70%">: {{ isset($data['spesialis']) ? $data['spesialis'] : '-' }}
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Jabatan</td>
                                <td width="70%">: {{ isset($data['jabatan']) ? $data['jabatan'] : '-' }}
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <div style="padding:7px;padding-top: 0px;padding-bottom: 0px">
                    <span>Menerangkan dengan sebenarnya bahwa :</span>
                    <div style="margin-left: 20px;margin-top: 10px;margin-bottom: 10px">
                        <table width="100%" style="border-spacing: 0px;">
                            <tr>
                                <td width="40%">No.Rekam Medis</td>
                                <td width="60%">: {{ isset($data['norm']) ? $data['norm'] : '-' }}</td>
                            </tr>
                            <tr>
                                <td width="30%">Nama</td>
                                <td width="70%" colspan="4">:
                                    {{ isset($data['namaPasien']) ? $data['namaPasien'] : '-' }}</td>
                            </tr>
                            <tr>
                                <td width="50%">Tanggal lahir / Umur </td>
                                <td width="50%">:
                                    {{ isset($data['pasien']['tgllahir']) ? $data['pasien']['tgllahir'] : '-' }} /
                                    {{ isset($data['pasien']['umur']) ? $data['pasien']['umur'] : '-' }}
                                </td>
                                <td width="40%" style="text-align: right;">Jenis Kelamin</td>
                                <td width="60%">
                                    <table width="100%">
                                        <tr>
                                            <td style="width: 10%">:</td>
                                            <td style="width: 45%">
                                                <input type="checkbox"
                                                    {{ isset($data['jeniskelamin']) && $data['jeniskelamin'] == 'Laki-laki' ? 'checked' : '' }} />
                                                <span style="font-size: 9pt;" color="#000000">Lk</span>
                                            </td>
                                            <td style="width: 45%">
                                                <input type="checkbox"
                                                    {{ isset($data['jeniskelamin']) && $data['jeniskelamin'] == 'Perempuan' ? 'checked' : '' }} />
                                                <span style="font-size: 9pt;" color="#000000">Pr</span>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Alamat</td>
                                <td width="70%" colspan="4">:
                                    {{ isset($data['alamat']) ? $data['alamat'] : '-' }}
                                </td>
                            </tr>
                            <tr>
                                <td width="30%">Diagnosa</td>
                                <td width="70%" colspan ="4">:
                                    {{ isset($data['diagnosa']) ? $data['diagnosa'] : '-' }}
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <div style="padding:7px">
                    <span>Pada Pemeriksaan Medis yang Dilakukan Didapatkan :</span>
                    <div style="margin-left: 20px;margin-top: 10px;margin-bottom: 10px">
                        <table width="100%" style="border-spacing: 0px;">
                            <tr>
                                <td width="100%">
                                    <div style="margin-bottom: 10px;">
                                        a. Yang bersangkutan dan keadaan <b>HAMIL</b> :
                                        {{ isset($data['hamil']) ? $data['hamil'] : '-' }} Dengan Umur
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td width="100%">
                                    <div style="margin-bottom: 10px;">
                                        Kehamilan : {{ isset($data['umurKehamilan']) ? $data['umurKehamilan'] : '-' }}
                                        Minggu : {{ isset($data['minggu']) ? $data['minggu'] : '-' }}
                                        Hari : {{ isset($data['hari']) ? $data['hari'] : '-' }}
                                        dan akan diperkirakan Melahirkan Pada
                                    </div>
                                </td>

                            </tr>
                            <tr>
                                <td width="100%">
                                    <div style="margin-bottom: 10px;">
                                        Tanggal
                                        {{ isset($data['tanggalMelahirkan']) ? Carbon::parse($data['tanggalMelahirkan'])->translatedFormat('d F Y') : '-' }}
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td width="100%">
                                    <div style="margin-bottom: 10px;">
                                        b. Yang bersangkutan dalam keadaan melahirkan/nifas hari ke
                                        {{ isset($data['melahirkanNifas']) ? $data['melahirkanNifas'] : '-' }}
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <div style="padding:7px;padding-top: 0px;">
                    <span>Pada Pemeriksaan Medis yang Dilakukan Didapatkan :</span>
                    <div style="margin-left: 20px;margin-top: 10px;margin-bottom: 0px">
                        <table width="100%" style="border-spacing: 0px;">
                            <tr>
                                <td width="100%">
                                    <div style="margin-bottom: 10px;">
                                        a. Istirahat Kerja Selama :
                                        {{ isset($data['istirahat']) ? $data['istirahat'] : '-' }} Hari,
                                        Mulai dari Tanggal
                                        {{ isset($data['tanggalIstirahat']) ? Carbon::parse($data['tanggalIstirahat'])->translatedFormat('d F Y') : '-' }}
                                        s/d
                                        {{ isset($data['tanggalIstirahat2']) ? Carbon::parse($data['tanggalIstirahat2'])->translatedFormat('d F Y') : '-' }}
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td width="100%">
                                    <div style="margin-bottom: 10px;">
                                        b. Tidak ikut kegiatan Olahraga/Kerja berat dan atau Sejenisnya
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td width="100%">
                                    <div style="margin-bottom: 10px;">
                                        c. Mendapatkan <b>CUTI HAMIL</b> Selama
                                        {{ isset($data['cutiHamil']) ? $data['cutiHamil'] : '-' }} Hari,
                                        Mulai dari Tanggal
                                        {{ isset($data['tanggalCuti']) ? Carbon::parse($data['tanggalCuti'])->translatedFormat('d F Y') : '-' }}
                                        s/d
                                        {{ isset($data['tanggalCuti2']) ? Carbon::parse($data['tanggalCuti2'])->translatedFormat('d F Y') : '-' }}
                                    </div>
                                </td>
                            </tr>
                            @if (!empty($data['keteranganLainnya']))
                                <tr>
                                    <td>
                                        Keterangan Lainnya : {{ $data['keteranganLainnya'] }}
                                    </td>
                                </tr>
                            @endif
                        </table>
                    </div>
                    <span>Demikian surat ini kami sampaikan untuk dapat dipergunakan sebagaimana mestinya</span>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <table width="100%" cellspacing="0" cellpadding="0">
                    <tr>
                        <td width="60%"></td>
                        <td class="tc vt" width="50%"
                            style="{{ empty($data['DDDokter']) ? 'padding-bottom: 2em;' : '' }};text-align:center;">
                            <div>Denpasar, {{ Carbon::parse($data['tanggal'])->translatedFormat('d F Y') }}</div>
                            {{-- <br> --}}
                            <!-- Qrcode ceritanya -->
                            @if (isset($data['DDDokter']['label']))
                                <img src="data:image/png;base64, {!! $qrcode !!}"
                                    style="width: 100px;height: 100px">
                            @endif
                            <br>
                            <span>{{ isset($data['DDDokter']['label']) ? $data['DDDokter']['label'] : ' ' }}</span>
                            <div>
                                NIP :
                                @if (isset($data['nip']->nip))
                                    {{ $data['nip']->nip }}
                                @else
                                    {{ '-' }}
                                @endif
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
