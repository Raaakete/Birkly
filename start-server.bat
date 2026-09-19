@echo off
setlocal EnableExtensions
cd /d "%~dp0"

set "PORT=8080"

:parse_args
if "%~1"=="" goto after_args
if /i "%~1"=="-p" (
    set "PORT=%~2"
    shift
    shift
    goto parse_args
)
if /i "%~1"=="--port" (
    set "PORT=%~2"
    shift
    shift
    goto parse_args
)
if /i "%~1"=="-h" goto usage
if /i "%~1"=="--help" goto usage
shift
goto parse_args

:after_args
where php >nul 2>&1
if errorlevel 1 (
    echo.
    echo [Birkly] PHP is not installed or not on your PATH.
    echo Install PHP 8.1+ from https://windows.php.net/download/
    echo Or use XAMPP / Laragon and run this file from their terminal.
    echo.
    pause
    exit /b 1
)

if not exist "index.php" (
    echo [Birkly] index.php not found. Run this from your Birkly folder.
    pause
    exit /b 1
)

for %%D in (content media users settings logs temp cache) do (
    if not exist "%%D" mkdir "%%D"
)
if not exist "content\collections" mkdir "content\collections"

echo.
echo [Birkly] Starting server on http://localhost:%PORT%
echo [Birkly] Admin panel: http://localhost:%PORT%/admin/
echo [Birkly] Keep this window open. Press Ctrl+C to stop.
echo.

php -S localhost:%PORT% -t . server-router.php
set "EXITCODE=%ERRORLEVEL%"
if not "%EXITCODE%"=="0" (
    echo.
    echo [Birkly] Server exited with code %EXITCODE%.
    pause
)
exit /b %EXITCODE%

:usage
echo Usage: start-server.bat [-p PORT]
echo   Default port: 8080
echo.
echo On Windows, double-click this file or run it from Command Prompt
echo in your Birkly folder — do not use start-server.sh unless you use Git Bash.
exit /b 0
