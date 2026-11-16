{{-- Jhon | 2026-11-13 20:13 WITA | TAMBAH: layout PDF API dari layout lokal Nadi. --}}
{{-- Jhon | 2026-11-13 19:42 WITA | TAMBAH: layout dari simrsbm-tm/backend, aset disesuaikan untuk Nadi. --}}
<!DOCTYPE html>
<html>

<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title')</title>
    @yield('page-style')
</head>

<body>

    <table width="100%" cellspacing="0" cellpadding="0" style="border: 1px solid black;border-collapse: collapse;">
        <tr>
            <td style="background-color: lightblue; padding:5px; font-weight:bold; width: 50%;">
                RSUD BALI MANDARA
            </td>
            <td style="background-color: lightblue; padding:5px; font-weight:bold; width: 50%; text-align: right;">
                @yield('kode')
            </td>
        </tr>
        <tr>
            <td colspan="2" style="border: 1px solid black">
                <table width="100%" style="border-collapse: collapse">
                    <tr style="border: ">
                        <td width="15%" style="text-align: center;padding: 10px">
                            <img src="{{ public_path('img/logo-provinsi-bali.svg') }}" width="80px" height="80px"
                                style="display: block;">
                        </td>
                        <td width="70%" style="text-align: center;">
                            <span>
                                <b>PEMERINTAH PROVINSI BALI</b><br>
                                <b>RUMAH SAKIT UMUM DAERAH BALI MANDARA</b><br>
                                Jalan ByPass Ngurah Rai No.548 Sanur,Denpasar-Bali<br>
                                No.Telp : (0361) 4490566, E-mail : rsud.balimandara@gmail.com
                            </span>
                        </td>
                        <td width="15%" style="text-align: center;padding: 10px">
                            <img src="{{ public_path('img/logo-rs.png') }}" width="80px" height="80px" style="display: block;">
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse: collapse;">
                    @yield('content')
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
