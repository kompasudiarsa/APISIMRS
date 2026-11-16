{{-- Jhon | 2026-11-13 20:13 WITA | TAMBAH: layout PDF API dari layout lokal Nadi. --}}
{{-- Jhon | 2026-11-13 19:30 WITA | UBAH: kop dari template Pak Pasek; URL aset absolut agar benar pada route dokumen bertingkat. --}}
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    {{-- Jhon | 2026-11-13 19:30 WITA | TAMBAH: navigasi dan status pratinjau, tanpa script/aset eksternal. --}}
    <style>body{margin:0;padding:20px;font-family:Arial,sans-serif}.report-status{padding:10px;border:1px solid #dce5ee;margin:10px 0;font-size:12px}.report-toolbar{margin-bottom:12px}table{max-width:100%}img{max-width:100%}img[src="data:image/png;base64, "]{display:none}@media print{body{padding:0}.report-toolbar{display:none}}</style>
    <title>@yield('title')</title>
    @yield('page-style')
</head>

<body>
    {{-- Jhon | 2026-11-13 19:53 WITA | UBAH: kontrol cetak PDF bersama untuk seluruh format surat. --}}

    
    @if(!empty($reportMissingFields))
        <div class="report-status" role="status">Data belum lengkap: {{ implode(', ', $reportMissingFields) }}.</div>
    @endif
    {{-- Jhon | 2026-11-13 19:42 WITA | TAMBAH: tampilkan kegagalan pencocokan master dokter tanpa data pegawai lain. --}}
    @foreach(($reportWarnings??[]) as $warning)<div class="report-status">{{ $warning }}</div>@endforeach
    <table width="100%" cellspacing="0" cellpadding="0"
        style="border: 0px solid black;border-collapse: collapse">
        <thead>
            
            <tr>
                <td colspan="2" style="border: 0px solid black">
                    <img src="{{ public_path('img/kop-surat.jpg') }}" width="100%" alt="Kop surat rumah sakit" style="display:block!important;height:auto;">
                </td>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="2">
                    <table width="100%" cellspacing="0" cellpadding="0"
                        style="border-collapse: collapse;">
                        @yield('content')
                    </table>
                </td>
            </tr>
        </tbody>
    </table>
</body>

</html>
