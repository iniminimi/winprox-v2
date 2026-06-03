@echo off
echo === WinProx Windows-services installeren (admin vereist) ===
echo Voer dit script uit als Administrator.

set MYSQL_BIN=C:\Program Files\MySQL\MySQL Server 8.4\bin
set APACHE_BIN=C:\Users\domin\AppData\Local\Microsoft\WinGet\Packages\ApacheLounge.httpd_Microsoft.Winget.Source_8wekyb3d8bbwe\Apache24\bin
set MY_INI=C:\winprox\my.ini

"%MYSQL_BIN%\mysqld.exe" --install MySQL84 --defaults-file="%MY_INI%"
net start MySQL84

cd /d "%APACHE_BIN%"
httpd.exe -k install -n "Apache24"
net start Apache24

echo.
echo Services geinstalleerd. Gebruik daarna start-stack.bat of Services.msc.
pause
