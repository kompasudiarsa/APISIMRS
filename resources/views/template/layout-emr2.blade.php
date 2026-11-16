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

    <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse: collapse;">
        <thead>
            <tr>
                <td colspan="2">
                    <table width="100%" style="border-collapse: collapse;" class="border">
                        <tr>
                            <td style="text-align: center; padding: 15px;">
                                <img src="{{ public_path('img/logo-provinsi-bali.svg') }}" width="100px" height="100px"
                                    style="display: block;">
                            </td>

                            <td style="text-align: center;">
                                <span style="font-weight: bold;">PEMERINTAH PROVINSI BALI </span><br>
                                <span style="font-weight: bold;">DINAS KESEHATAN</span> <br>
                                <span style="font-weight: bold;">RUMAH SAKIT UMUM DAERAH BALI MANDARA</span> <br>
                                <span>Jalan ByPass Ngurah Rai No.548 Sanur,Denpasar-Bali</span>
                                <span>No.Telp : (0361) 4490566, E-mail : rsud.balimandara@gmail.com</span>
                            </td>
                            <td style="text-align: center; padding: 15px;">
                                <img src="{{ public_path('img/logo-rs.png') }}" width="100px" height="100px"
                                    style="display: block;">
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="2">
                    <table class="border" width="100%" cellspacing="0" cellpadding="0"
                        style="border-collapse: collapse;border-top: none;">
                        @yield('content')
                    </table>
                </td>
            </tr>
        </tbody>
    </table>
</body>

</html>
