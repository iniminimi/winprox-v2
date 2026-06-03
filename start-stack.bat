@echo off
setlocal EnableDelayedExpansion

set WINPROX_ROOT=C:\winprox
set MYSQL_BIN=C:\Program Files\MySQL\MySQL Server 8.4\bin
set APACHE_BIN=C:\Users\domin\AppData\Local\Microsoft\WinGet\Packages\ApacheLounge.httpd_Microsoft.Winget.Source_8wekyb3d8bbwe\Apache24\bin
set MY_INI=%WINPROX_ROOT%\my.ini

echo === WinProx lokale stack starten ===

sc query MySQL84 >nul 2>&1
if %errorlevel%==0 (
    net start MySQL84
) else (
    tasklist /FI "IMAGENAME eq mysqld.exe" 2>nul | find /I "mysqld.exe" >nul
    if errorlevel 1 (
        echo MySQL starten...
        start "" /B "%MYSQL_BIN%\mysqld.exe" --defaults-file="%MY_INI%"
        timeout /t 3 /nobreak >nul
    ) else (
        echo MySQL draait al.
    )
)

tasklist /FI "IMAGENAME eq httpd.exe" 2>nul | find /I "httpd.exe" >nul
if errorlevel 1 (
    echo Apache starten...
    start "" /B "%APACHE_BIN%\httpd.exe"
    timeout /t 3 /nobreak >nul
) else (
    echo Apache draait al.
)

for /f "delims=" %%i in ('powershell -NoProfile -ExecutionPolicy Bypass -File "%WINPROX_ROOT%\scripts\update-app-url.ps1"') do set LAN_IP=%%i

if defined LAN_IP (
    echo APP_URL bijgewerkt naar http://!LAN_IP!
)

echo.
echo Klaar — open in je browser:
echo   Lokaal:      http://localhost
echo   Lokaal:      http://localhost/login
if defined LAN_IP (
    echo.
    echo   Vanaf gsm:   http://!LAN_IP!
    echo   Vanaf gsm:   http://!LAN_IP!/login
    echo   Login:       admin@winprox.test / password
) else (
    echo.
    echo   LAN-IP niet gevonden — gebruik ipconfig om je IP op te zoeken.
)
echo.
echo   phpMyAdmin:  http://localhost/phpmyadmin
echo.
pause
