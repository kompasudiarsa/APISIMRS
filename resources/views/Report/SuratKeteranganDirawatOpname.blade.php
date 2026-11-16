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
        function removeSymbol($text) {
            $pattern = '/[\x{200B}-\x{200D}\x{FEFF}\x{2060}]/u';
            return preg_replace($pattern, '', $text);
        }
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
                    <u>SURAT KETERANGAN DIRAWAT/ OPNAME</u><br>
                    <span style="font-size:13pt">NOMOR : {{ isset($data['nosurat']) ? $data['nosurat'] : '-' }}</span>
                </div>
            </td>
        </tr>
   
        <tr>
            <td>
                <div style="padding:7px">
                    <span>Saya yang bertanda tangan dibawah ini :</span>
                        <div style="margin-left: 20px;margin-top: 10px;margin-bottom: 10px">
                            <table width="100%" style="border-spacing: 0px;">
                                <tr>
                                    <td width="30%">Nama</td>
                                    <td width="70%">:  {{ isset($data['NamaPetugas']['label']) ? $data['NamaPetugas']['label'] : '-' }}</td>
                                </tr>
                                <tr>
                                    <td width="30%">Jabatan</td>
                                    <td width="70%">: {{ isset($data['jabatan']) ? $data['jabatan'] : '-' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td width="30%">NIP</td>
                                    <td width="70%">: {{ isset($data['NIP']) ? $data['NIP'] : '-' }}
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
                    <span>Menerangkan dengan sebenarnya bahwa :</span>
                        <div style="margin-left: 20px;margin-top: 10px;margin-bottom: 10px">
                            <table width="100%" style="border-spacing: 0px;">
                                <tr>
                                    <td width="40%">Nama</td>
                                    <td width="60%">: {{ isset($data['namaPasien']) ? $data['namaPasien'] : '-' }}</td>
                                </tr>
                                <tr>
                                    <td width="45%">Tanggal lahir / Umur </td>
                                    <td width="55%">:
                                    {{ isset($data['pasien']['tgllahir']) ? $data['pasien']['tgllahir'] : '-' }} /
                                    {{ isset($data['pasien']['umur']) ? $data['pasien']['umur'] : '-' }}
                                    </td>
                                    <td width="30%" style="text-align: right;">Jenis Kelamin</td>
                                    <td width="60%">
                                        <table width="100%">
                                            <tr>
                                                <td style="width: 10%">:</td>
                                                <td style="width: 45%;">
                                                    <input type="checkbox" style="vertical-align: middle"
                                                        {{ isset($data['jeniskelamin']) && $data['jeniskelamin'] == 'Laki-laki' ? 'checked' : '' }} />
                                                    <span style="font-size: 9pt;vertical-align:middle;" color="#000000">Lk</span>
                                                </td>
                                                <td style="width: 45%;">
                                                    <input type="checkbox" style="vertical-align: middle"
                                                        {{ isset($data['jeniskelamin']) && $data['jeniskelamin'] == 'Perempuan' ? 'checked' : '' }} />
                                                    <span style="font-size: 9pt;vertical-align:middle;" color="#000000">Pr</span>
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
                                <tr>
                                    <td width="30%">Dirawat dari Tanggal</td>
                                    <td width="70%" colspan ="4">:
                                        {{-- Carbon::parse($data['tanggalAwalRawat'])->translatedFormat('d F Y') }} s/d {{ Carbon::parse($data['tanggalAkhirRawat'])->translatedFormat('d F Y') --}}
                                        {{ \App\Support\SuratReportFormatter::date($data['tanggalAwalRawat']) }} s/d {{ \App\Support\SuratReportFormatter::date($data['tanggalAkhirRawat']) }}
                                        ({{ isset($data['hari']) ? $data['hari'] : '-' }} Hari)
                                    </td>
                                </tr>
                                <tr>
                                    <td width="30%">Ruang Perawatan</td>
                                    <td width="70%" colspan ="4">: <b>Ruang</b>
                                        @if(isset($data['ruanganPerawatan']) && isset($data['ruanganPerawatan']['label']))
                                            {{ $data['ruanganPerawatan']['label'] }}
                                        @elseif(isset($data['ruanganPerawatan']) && !isset($data['ruanganPerawatan']['label']))
                                            {{ $data['ruanganPerawatan'] }}
                                        @else
                                            -
                                        @endif
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
                    <span>Demikian Surat Keterangan ini dibuat dengan sebenarnya untuk keperluan sebagaimana semestinya.</span>
                </div>       
            </td> 
        </tr> 
        
        <table width="100%" cellspacing="0" cellpadding="0">
            
            <table width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td width="60%"></td>
                <td class="tc vt" width="50%" style="{{ empty($data['DDDokter']) ? 'padding-bottom: 2em;' : '' }};text-align:center;">
                    <div>Denpasar, {{ Carbon::parse($data['tanggal'])->translatedFormat('d F Y') }}</div>
                    <br>
                    <!-- Qrcode ceritanya -->
                    @if (isset($data['DDDokter']['label']))
                        <img src="data:image/png;base64, {!! $qrcode !!}">
                    @endif
                    <br>
                    <span>{{ isset($data['DDDokter']['label']) ? $data['DDDokter']['label'] : ' ' }}</span>
                    <div>
                        NIP :
                        {{ isset($data['NIP']) ? $data['NIP'] : '-' }}
                    </div>
                </td>
            </tr>
            </table>
        </table>
    </table>
</body>

</html>
