{{-- Jhon | 2026-11-13 20:13 WITA | TAMBAH: adapter PDF API dari view Nadi; tanpa kontrol browser. --}}
{{-- Jhon | 2026-11-13 19:42 WITA | UBAH: adapter format SIMRS ke laporan pasien Nadi berbahasa Indonesia. --}}
<!DOCTYPE html>
<html>

<head>
    @php
        $isWna = false; // Nadi menyediakan pratinjau Indonesia, bukan terjemahan otomatis.
    @endphp
    <title>
        @if ($isWna)
            Emergency Medical Certificate
        @else
            Surat Keterangan Gawat Darurat
        @endif
    </title>
    @yield('page-style')
</head>

<body>

    @php
        function removeSymbol($text)
        {
            $pattern = '/[\x{200B}-\x{200D}\x{FEFF}\x{2060}]/u';
            return preg_replace($pattern, '', $text);
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
    @endphp
    <table width="100%" cellspacing="0" cellpadding="0"
        style="border: 1px solid black; border-bottom: none;border-collapse: collapse">
        <tr colspan="3">
            <td style="background-color: lightblue; padding:5px; font-weight:bold; width: 50%;">
                RSUD BALI MANDARA
            </td>
            <td colspan="2"
                style="background-color: lightblue; padding:5px; font-weight:bold; width: 50%; text-align: right;">
                RM.5/SK/00
            </td>
        </tr>
    </table>
    <table width="100%" cellspacing="0" cellpadding="0"
        style="border: 1px solid black; border-bottom: none;border-collapse: collapse">
        <tr>
            <td style="border: 1px solid black; width: 10%;">
                <img src="{{ public_path('img/logo-rs.png') }}" width="100px" height="100px" style="display: block;">
            </td>

            <td style="border: 1px solid black; font-weight: bold; text-align: center; width: 60%;">
                <h3>PEMERINTAH PROVINSI BALI <br>RSUD BALI MANDARA <br>Jalan By Pass Ngurah Rai No. 548, Denpasar - Bali
                </h3>
            </td>
        </tr>
    </table>
    <table width="100%" cellspacing="0" cellpadding="0" style="border: 1px solid black; border-collapse: collapse">
        <tr>
            <td style=" font-weight: bold; text-align: center; width: 60%;">
                @if ($isWna)
                    <span style="text-transform: uppercase">
                        Emergency Medical Certificate
                    </span>
                @else
                    SURAT KETERANGAN GAWAT DARURAT
                @endif

            </td>
        </tr>

        <tr>
            <td style="padding-left: 15px; padding-top: 10px;">
                @if ($isWna)
                    The undersigned below:
                @else
                    Yang bertanda tangan dibawah ini:
                @endif
            </td>
        </tr>
        <tr>
            <td style="padding-left: 35px;">
                @php
                    $dokter = '-';
                    if (isset($data['DDDokter']['label'])) {
                        $dokter = $data['DDDokter']['label'];
                    } elseif (isset($data['DDDokter'])) {
                        $dokter = $data['DDDokter'];
                    }

                    $label = $isWna ? 'Name' : 'Nama';
                @endphp
                <p>{{ $label }} : {{ $dokter }}</p>
                @if ($isWna)
                    <p>Position : {{ isset($data['jabatanBertandaTangan']) ? $data['jabatanBertandaTangan'] : '-' }}</p>
                @else
                    <p>Jabatan : {{ isset($data['jabatanBertandaTangan']) ? $data['jabatanBertandaTangan'] : '-' }}</p>
                @endif
            </td>
        </tr>

        <tr>
            <td style="padding-left: 15px; padding-top: 10px;">
                @if ($isWna)
                    The undersigned below:
                @else
                    Yang bertanda tangan dibawah ini:
                @endif
            </td>
        </tr>
        <tr>
            <td style="padding-left: 35px;">
                @if ($isWna)
                    <p>Name : {{ isset($data['namaPasien']) ? $data['namaPasien'] : '-' }}</p>
                @else
                    <p>Nama : {{ isset($data['namaPasien']) ? $data['namaPasien'] : '-' }}</p>
                @endif

                @if ($isWna)
                    <p>Date Of Birth/Age
                        <?php
                        function hitungUmur($tanggalLahir, $tanggalReferensi = null)
                        {
                            if (!$tanggalLahir) {
                                return ' ';
                            }
                        
                            $tglLahir = new DateTime($tanggalLahir);
                            $tglReferensi = $tanggalReferensi ? new DateTime($tanggalReferensi) : new DateTime();
                            $umur = $tglReferensi->diff($tglLahir);
                            return $umur->y . 'thn ' . $umur->m . 'bln ' . $umur->d . 'hr';
                        }
                        
                        $tglLahir = isset($data['tgllahir']) ? $data['tgllahir']->tgllahir : '';
                        $tglRawatInap = isset($data['tanggal']) ? $data['tanggal'] : null;
                        
                        $umur = isset($data['tgllahir']) ? hitungUmur($data['tgllahir']->tgllahir, $tglRawatInap) : '';
                        
                        echo ': ' . $tglLahir . ' / ' . $umur;
                        ?>
                    </p>
                @else
                    <p>Tanggal Lahir/Umur
                        <?php
                        function hitungUmur($tanggalLahir, $tanggalReferensi = null)
                        {
                            if (!$tanggalLahir) {
                                return ' ';
                            }
                        
                            $tglLahir = new DateTime($tanggalLahir);
                            $tglReferensi = $tanggalReferensi ? new DateTime($tanggalReferensi) : new DateTime();
                            $umur = $tglReferensi->diff($tglLahir);
                            return $umur->y . 'thn ' . $umur->m . 'bln ' . $umur->d . 'hr';
                        }
                        
                        $tglLahir = isset($data['tgllahir']) ? $data['tgllahir']->tgllahir : '';
                        $tglRawatInap = isset($data['tanggal']) ? $data['tanggal'] : null;
                        
                        $umur = isset($data['tgllahir']) ? hitungUmur($data['tgllahir']->tgllahir, $tglRawatInap) : '';
                        
                        echo ': ' . $tglLahir . ' / ' . $umur;
                        ?>
                    </p>
                @endif

                @php
                    $gender = '-';

                    if (isset($data['jeniskelamin'])) {
                        $jk = strtolower(trim($data['jeniskelamin']));

                        if ($isWna) {
                            // Versi Inggris
                            if ($jk == 'perempuan') {
                                $gender = 'Female';
                            } elseif ($jk == 'laki-laki' || $jk == 'lakilaki' || $jk == 'laki laki') {
                                $gender = 'Male';
                            } else {
                                $gender = $data['jeniskelamin']; // fallback kalau nilai aneh
                            }
                        } else {
                            // Versi Indonesia
                            if ($jk == 'female') {
                                $gender = 'Perempuan';
                            } elseif ($jk == 'male') {
                                $gender = 'Laki-Laki';
                            } else {
                                $gender = $data['jeniskelamin'];
                            }
                        }
                    }

                    $label = $isWna ? 'Gender' : 'Jenis Kelamin';
                @endphp
                <p>{{ $label }} : {{ $gender }}</p>

                @if ($isWna)
                    <p>Medical Record Number : {{ isset($data['norm']) ? $data['norm'] : '-' }}</p>
                @else
                    <p>No Rekam Medis : {{ isset($data['norm']) ? $data['norm'] : '-' }}</p>
                @endif

                @if ($isWna)
                    <p>Diagnose : {{ isset($data['diagnosa']) ? removeSymbol($data['diagnosa']) : '-' }}</p>
                @else
                    <p>Diagnosa : {{ isset($data['diagnosa']) ? removeSymbol($data['diagnosa']) : '-' }}</p>
                @endif
            </td>
        </tr>
        @php
            // Tindakan
            $tindakan = isset($data['tindakan']) ? $data['tindakan'] : '-';

            // Untuk (Rawat Inap / dll)
            $untuk = isset($data['untuk']) && $data['untuk'] != '' ? $data['untuk'] : 'Rawat Inap';

            // Translate Perlu / Tidak Perlu
            $requireText = '-';
            if (strtolower(trim($tindakan)) == 'tidak perlu') {
                $requireText = $isWna ? 'did not require' : 'Tidak Perlu';
            } elseif (strtolower(trim($tindakan)) == 'perlu') {
                $requireText = $isWna ? 'require' : 'Perlu';
            } else {
                $requireText = $tindakan; // fallback kalau isinya aneh
            }
        @endphp

        <tr>
            <p style="padding-left: 15px;">
                @if ($isWna)
                    It is hereby certified that the patient has received <span
                        style="font-weight: bold">EMERGENCY</span> medical treatment and
                    <span style="font-weight: bold">{{ $requireText }}</span>
                    {{ strtolower($untuk) == 'rawat inap' ? 'hospitalization' : $untuk }}.
                    This certificate is issued for all due purposes.
                @else
                    Memang benar telah mendapat penanganan
                    <span style="font-weight: bold">GAWAT DARURAT (EMERGENCY)</span> dan
                    <span style="font-weight: bold">{{ $requireText }}</span>
                    untuk {{ $untuk }}.
                    Demikian Surat Keterangan ini dibuat untuk dapat dipergunakan sebagaimana mestinya.
                @endif
            </p>
        </tr>

        @php
            use Carbon\Carbon;
            $tanggalText = '-';

            if (isset($data['tanggal'])) {
                $dateFormatted = Carbon::parse($data['tanggal'])
                    ->setTimezone('Asia/Makassar')
                    ->translatedFormat('d F Y');

                if ($isWna) {
                    $tanggalText = translateDateToEnglish($dateFormatted);
                } else {
                    $tanggalText = $dateFormatted;
                }
            }
        @endphp
        <tr>
            <td style="text-align: center;">
                <p>{{ $tanggalText }}</p>
                <img src="data:image/png;base64, {!! $tte !!}">
                @if (!isset($data['DDDokter']['label']))
                    <p>{{ isset($data['DDDokter']) ? removeSymbol($data['DDDokter']) : '-' }}</p>
                @else
                    <p>{{ isset($data['DDDokter']['label']) ? removeSymbol($data['DDDokter']['label']) : '-' }}</p>
                @endif

                @if (isset($data['nip']->nip))
                    NIP : {{ $data['nip']->nip }}
                @endif
            </td>
        </tr>
    </table>

</body>

</html>
