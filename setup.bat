@echo off
REM ABMM Sampark one-click setup (XAMPP). Double-click from this folder. Needs XAMPP (MySQL running) and Composer installed.
cd /d "%~dp0"
set PATH=C:\xampp\php;C:\xampp\mysql\bin;%PATH%
if not exist .env copy .env.example .env
echo [1/6] Installing packages (Laravel 13 + mpdf)...
call composer update --no-interaction
if errorlevel 1 goto fail
echo [2/6] App key...
php artisan key:generate --force
echo [3/6] Creating database abmm_sampark...
mysql -u root -e "CREATE DATABASE IF NOT EXISTS abmm_sampark CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
if errorlevel 1 goto fail
echo [4/6] Migrating + seeding...
php artisan migrate --seed --force
if errorlevel 1 goto fail
echo [5/6] Storage link...
php artisan storage:link
echo [6/6] Done. Open http://localhost:8000/login  (admin@abmm.test / ChangeMe@12345)
php artisan serve
goto end
:fail
echo.
echo SETUP FAILED - upar ka error text copy karke bhejein.
pause
:end
