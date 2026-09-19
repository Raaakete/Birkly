@echo off
setlocal EnableExtensions
cd /d "%~dp0"

echo.
echo [Birkly] Checking PHP for local development...
echo.

where php >nul 2>&1
if errorlevel 1 (
    echo [Birkly] PHP is not installed or not on your PATH.
    echo.
    echo Birkly needs PHP 8.0 or newer on your PATH before you can start the server.
    echo.
    echo Install options:
    echo   1. Windows Package Manager ^(if available^):
    echo        winget install PHP.PHP.8.3
    echo   2. Official builds: https://windows.php.net/download/
    echo      ^(add the folder containing php.exe to your PATH^)
    echo   3. XAMPP, Laragon, or MAMP — then open their terminal and run this check again.
    echo.
    echo After installing, close and reopen Command Prompt, then run check-php.bat again.
    echo.
    pause
    exit /b 1
)

for /f "delims=" %%V in ('php -r "echo PHP_VERSION;" 2^>nul') do set "PHP_VER=%%V"
if not defined PHP_VER (
    echo [Birkly] Could not read PHP version. Check your PHP installation.
    pause
    exit /b 1
)

echo [Birkly] Found PHP %PHP_VER%
php -r "if (version_compare(PHP_VERSION, '8.0.0', '<')) { fwrite(STDERR, '[Birkly] PHP 8.0+ is required.'.PHP_EOL); exit(1); }"
if errorlevel 1 (
    echo.
    echo Install a newer PHP from https://windows.php.net/download/
    pause
    exit /b 1
)

for %%E in (json mbstring curl openssl) do (
    php -r "exit(extension_loaded('%%E')?0:1);" 2>nul
    if errorlevel 1 (
        echo [Birkly] Missing PHP extension: %%E
        echo Enable extensions in your php.ini ^(see php --ini^), then run this check again.
        php --ini
        pause
        exit /b 1
    )
)

if not exist "index.php" (
    echo [Birkly] index.php not found. Run this from your Birkly folder.
    pause
    exit /b 1
)

echo [Birkly] PHP is ready. Next: start-server.bat ^(keep that window open^).
echo.
pause
exit /b 0
