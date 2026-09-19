@echo off
setlocal EnableExtensions EnableDelayedExpansion
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
    echo.
    echo WHAT TO DO NEXT:
    echo   1. Double-click install-php-windows.bat in this folder.
    echo   2. When check-php.bat shows SUCCESS, run start-server.bat again.
    echo.
    set /p RUN="Open install-php-windows.bat now? (Y/N): "
    if /i "!RUN!"=="Y" call "%~dp0install-php-windows.bat"
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
