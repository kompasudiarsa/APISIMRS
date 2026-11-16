@echo off
rem Jhon | 2026-11-13 20:13 WITA | TAMBAH: pasang dependency PDF/QR pada host API lokal, tanpa restart/deployment atau menyalin vendor backend.
setlocal
cd /d "%~dp0.."
where php >nul 2>nul
if errorlevel 1 exit /b 1
where composer >nul 2>nul
if errorlevel 1 exit /b 1
php tools/check-mongodb.php --backup
if errorlevel 1 exit /b 1
rem Jhon | 2026-11-13 21:50 WITA | UBAH: wildcard seri mayor menghindari caret ganda akibat lapisan cmd/Composer Windows.
call composer require "dompdf/dompdf:3.*" "endroid/qr-code:4.*" --no-scripts --no-interaction
if errorlevel 1 exit /b 1
php artisan package:discover --ansi
exit /b %errorlevel%
