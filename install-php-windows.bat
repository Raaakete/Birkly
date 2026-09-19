@echo off
setlocal EnableExtensions
cd /d "%~dp0"

echo.
echo ============================================================
echo   Birkly - Install PHP on Windows (beginner-friendly)
echo ============================================================
echo.
echo Birkly needs PHP 8+ before the server can start.
echo.
echo RECOMMENDED (easiest):
echo   Install Laragon - it includes PHP and is one installer.
echo   1. We can open the Laragon download page in your browser.
echo   2. Run the installer, then open Laragon.
echo   3. In Laragon, use "www" or point the site folder to this Birkly folder.
echo      OR after Laragon installs PHP, come back here and double-click check-php.bat
echo.
echo ALTERNATIVE:
echo   Download PHP from windows.php.net (more technical - PATH setup).
echo.
echo ============================================================
echo.
set /p CHOICE="Press 1 for Laragon download, 2 for PHP.net download, or Enter to close: "
if "%CHOICE%"=="1" start "" "https://laragon.org/download/"
if "%CHOICE%"=="2" start "" "https://windows.php.net/download/"
echo.
echo When PHP is installed, double-click check-php.bat in your Birkly folder again.
echo Then double-click start-server.bat.
echo.
pause
exit /b 0
