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
            <td style=" font-weight: bold; text-align: center; width: 60%;">
                SURAT KETERANGAN MASIH DALAM PERAWATAN (SURAT KONTROL)
            </td>
        </tr>
        <tr>
            <td style=" font-weight: bold; text-align: center; width: 60%;">
                NOMOR : {{ isset($data['nosurat']) ? $data['nosurat'] : '' }}
            </td>
        </tr>

        <tr>
            <td style="padding-left: 15px; padding-top: 10px;">
                Yang bertanda tangan dibawah ini:
            </td>
        </tr>
        <tr>
            <td style="padding-left: 35px;">
                <p>Nama : {{ isset($data['DDDokter']) && isset($data['DDDokter']['label']) ? $data['DDDokter']['label'] : (isset($data['DDDokter']) ? $data['DDDokter'] : '') }}</p>
            </td>
        </tr>

        <tr>
            <td style="padding-left: 15px;">
                Menerangkan dengan sebenarnya bahwa :
            </td>
        </tr>
        <tr>
            <td style="padding-left: 35px;">
                <p>Nama : {{ isset($data['namaPasien']) ? $data['namaPasien'] : '-' }}</p>
                <p>Tanggal Lahir/Umur <?php
                function hitungUmur($tanggalLahir)
                {
                    $tglLahir = new DateTime($tanggalLahir);
                    $sekarang = new DateTime();
                    $umur = $sekarang->diff($tglLahir);
                    return $umur->y . ' tahun, ' . $umur->m . ' bulan';
                }
                echo ':' . $data['pasien']['tgllahir'] . ' / ' . hitungUmur($data['pasien']['tgllahir']);
                ?></p>
                <p>Jenis Kelamin: {{ isset($data['jeniskelamin']) ? $data['jeniskelamin'] : '-' }}</p>
                <p>No Rekam Medis : {{ isset($data['norm']) ? $data['norm'] : '-' }}</p>
                <p>Diagnosa : {{ isset($data['diagnosa']) ? $data['diagnosa'] : '-' }}</p>
                <p>Terapi: {{ isset($data['terapi']) ? $data['terapi'] : '-' }}</p>
            </td>
        </tr>
        <tr>
            <div>
                <div style="padding-left: 15px;">Pasien tersebut di atas <span style="font-weight: bold">Masih Dalam
                        Pengawasan dengan tindak lanjut</span> yang disarankan :</div>
            </div>
            <div style="margin-left: 35px;">
                @if (isset($data['rawatJalan']) && $data['rawatJalan'] == 'Perawatan RAWAT JALAN' ? 'checked' : '')
                    <div class="text-left checkbox-wrapper">
                        <input type="checkbox" id="terms" class="checkbox" checked>
                        <label class="checkbox-label" for="terms">{{ $data['rawatJalan'] }}, Poliklinik :
                            {{ $data['poli']['label'] }}</label>
                    </div>
                @endif
            </div>
        </tr>
        <tr>
            <div style="margin-left: 35px;">
                @if (isset($data['perawatanHemodialisa']) &&  $data['perawatanHemodialisa'] == 'Perawatan HEMODIALISA' ? 'checked' : '')
                    <div class="text-left checkbox-wrapper">
                        <input type="checkbox" id="terms" class="checkbox" checked>
                        <label class="checkbox-label" for="terms">{{ $data['perawatanHemodialisa'] }}</label>
                    </div>
                @endif
            </div>
        </tr>
        <tr>
            <div style="margin-left: 35px;">
                @if (isset($data['perawatanChemoTeraphy']) && $data['perawatanChemoTeraphy'] == 'Perawatan CHEMOTERAPHY' ? 'checked' : '')
                    <div class="text-left checkbox-wrapper">
                        <input type="checkbox" id="terms" class="checkbox" checked>
                        <label class="checkbox-label" for="terms">{{ $data['perawatanChemoTeraphy'] }}</label>
                    </div>
                @endif
            </div>
        </tr>

        <tr>
            <div style="padding-left: 15px;">
                Demikian Surat Keterangan ini dibuat untuk dapat dipergunakan sebagaimana mestinya.
            </div>
        </tr>
        @php
            use Carbon\Carbon;
        @endphp
        <tr>
            <div style="text-align: center; margin-top: 20px;">
                <div>{{ isset($data['tanggal']) ? Carbon::parse($data['tanggal'])->translatedFormat('d F Y') : '-' }}
                </div>
                <img src="data:image/png;base64, {!! $tte !!}">
                <div>{{ isset($data['DDDokter']) && isset($data['DDDokter']['label']) ? $data['DDDokter']['label'] : (isset($data['DDDokter']) ? $data['DDDokter'] : '') }}</div>
                <div>
                    NIP :
                    @if (isset($data['nip']->nip))
                        {{ $data['nip']->nip }}
                    @else
                        {{ '-' }}
                    @endif
                </div>
            </div>
        </tr>

    </table>

</body>

</html>
