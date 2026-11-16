@echo off
rem Jhon | 2026-11-13 12:06 WITA | TAMBAH: jalankan hanya di terminal host API-SIMRS lokal; tanpa restart, perubahan php.ini atau deployment.
setlocal
cd /d "%~dp0.."
where php >nul 2>nul
if errorlevel 1 (
 echo GAGAL: PHP belum ada di PATH terminal ini. Gunakan terminal PHP lokal yang menjalankan API-SIMRS.
 exit /b 1
)
where composer >nul 2>nul
if errorlevel 1 (
 echo GAGAL: Composer belum ada di PATH terminal ini.
 exit /b 1
)
php tools/check-mongodb.php --preflight
if errorlevel 1 exit /b 1
php tools/check-mongodb.php --backup
if errorlevel 1 exit /b 1
rem Jhon | 2026-11-13 12:06 WITA | TAMBAH: seri 3.8 mendukung Laravel 8; Composer memeriksa ekstensi/platform, tanpa upgrade framework atau vendor publish.
call composer require "jenssegers/mongodb:3.8.*" --no-scripts --no-interaction
if errorlevel 1 exit /b 1
php artisan package:discover --ansi
if errorlevel 1 exit /b 1
php tools/check-mongodb.php
exit /b %errorlevel%
