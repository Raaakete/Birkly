@echo off
setlocal EnableExtensions
cd /d "%~dp0"

echo.
echo ============================================================
echo   Birkly - PHP check
echo ============================================================
echo   Safe local helper from your Birkly folder only.
echo   If Windows blocked you: right-click this file -^> Run,
echo   or on SmartScreen choose More info, then Run anyway.
echo ============================================================
echo.

where php >nul 2>&1
if errorlevel 1 goto :no_php

for /f "delims=" %%V in ('php -r "echo PHP_VERSION;" 2^>nul') do set "PHP_VER=%%V"
if not defined PHP_VER goto :bad_php

echo [OK] Found PHP %PHP_VER%
php -r "if (version_compare(PHP_VERSION, '8.0.0', '<')) { fwrite(STDERR, 'PHP 8.0+ required'.PHP_EOL); exit(1); }"
if errorlevel 1 goto :old_php

for %%E in (json mbstring curl openssl) do (
    php -r "exit(extension_loaded('%%E')?0:1);" 2>nul
    if errorlevel 1 goto :missing_ext
)

if not exist "index.php" (
    echo [FAIL] Open this from your extracted Birkly folder ^(next to admin\^).
    echo        Do not run files from inside the zip file.
    goto :pause_fail
)

echo.
echo ============================================================
echo   SUCCESS - PHP is ready for Birkly
echo ============================================================
echo.
echo NEXT: Double-click start-server.bat in this same folder.
echo       Keep that black window open while you use Birkly.
echo.
pause
exit /b 0

:no_php
echo [FAIL] PHP is not installed ^(or not on PATH^).
echo.
echo WHAT TO DO NEXT:
echo   1. Double-click install-php-windows.bat in this folder.
echo   2. Choose option 1 ^(Laragon^) unless you are comfortable with manual PHP.
echo   3. After installing, close ALL black windows and double-click check-php.bat again.
echo.
set /p RUN="Open install-php-windows.bat now? (Y/N): "
if /i "%RUN%"=="Y" call "%~dp0install-php-windows.bat"
goto :pause_fail

:old_php
echo [FAIL] PHP is too old. Birkly needs PHP 8.0 or newer.
echo Double-click install-php-windows.bat for an updated PHP.
goto :pause_fail

:missing_ext
echo [FAIL] A required PHP extension is missing.
echo Run: php --ini
echo Enable json, mbstring, curl, openssl in php.ini, then run this check again.
echo Or install Laragon via install-php-windows.bat ^(includes extensions^).
goto :pause_fail

:bad_php
echo [FAIL] Could not run PHP. Try install-php-windows.bat
goto :pause_fail

:pause_fail
echo.
pause
exit /b 1
