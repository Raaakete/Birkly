#Requires -Version 5.1
param(
    [int]$Port = 8080,
    [switch]$Kill
)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'scripts\windows-php-common.ps1')

$Root = Get-BirklyRoot
Set-Location -LiteralPath $Root

Write-Host "[Birkly] Local server — keep this window open while using Birkly."

$phpExe = Get-BirklyPhpExe -Root $Root
if (-not $phpExe) {
    Write-Host ""
    Write-Host "[Birkly] PHP not found. Run:"
    Write-Host "  powershell -ExecutionPolicy Bypass -File .\install-php.ps1"
    exit 1
}

if (-not (Test-Path -LiteralPath (Join-Path $Root 'index.php'))) {
    Write-Host "[Birkly] index.php not found. Run this from your Birkly folder."
    exit 1
}

foreach ($dir in @('content', 'media', 'users', 'settings', 'logs', 'temp', 'cache')) {
    $p = Join-Path $Root $dir
    if (-not (Test-Path -LiteralPath $p)) {
        New-Item -ItemType Directory -Path $p -Force | Out-Null
    }
}
$collections = Join-Path $Root 'content\collections'
if (-not (Test-Path -LiteralPath $collections)) {
    New-Item -ItemType Directory -Path $collections -Force | Out-Null
}

if ($Kill) {
    try {
        $conn = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue
        if ($conn) {
            $conn | ForEach-Object { Stop-Process -Id $_.OwningProcess -Force -ErrorAction SilentlyContinue }
        }
    } catch {
        Write-Host "[Birkly] Could not free port $Port — try another port with -Port 8001"
    }
}

Write-Host ""
Write-Host "[Birkly] Starting server on http://localhost:$Port"
Write-Host "[Birkly] Admin panel: http://localhost:$Port/admin/"
Write-Host "[Birkly] Press Ctrl+C to stop."
Write-Host ""

& $phpExe -S "localhost:$Port" -t $Root (Join-Path $Root 'server-router.php')
exit $LASTEXITCODE
