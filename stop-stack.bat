@echo off
echo === WinProx lokale stack stoppen ===

taskkill /F /IM httpd.exe >nul 2>&1
if %errorlevel%==0 (echo Apache gestopt.) else (echo Apache was niet actief.)

sc query MySQL84 >nul 2>&1
if %errorlevel%==0 (
    net stop MySQL84 >nul 2>&1
    echo MySQL-service gestopt.
) else (
    taskkill /F /IM mysqld.exe >nul 2>&1
    if %errorlevel%==0 (echo MySQL gestopt.) else (echo MySQL was niet actief.)
)

pause
