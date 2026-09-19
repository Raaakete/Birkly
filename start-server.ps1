#Requires -Version 5.1
param(
    [int]$Port = 8080,
    [switch]$Kill
)

$ErrorActionPreference = 'Stop'
$Global:BirklyWindowsScriptRoot = (Resolve-Path -LiteralPath $PSScriptRoot).Path
. (Join-Path $PSScriptRoot 'scripts\windows-php-common.ps1')

$Root = Get-BirklyRoot
Set-Location -LiteralPath $Root

Write-Host '[Birkly] Local server - keep this window open while using Birkly.'

$phpExe = Get-BirklyPhpExe -Root $Root
if (-not $phpExe) {
    Write-Host ''
    Write-Host '[Birkly] PHP not found. Run:'
    Write-Host '  powershell -ExecutionPolicy Bypass -File .\install-php.ps1'
    exit 1
}

if (-not (Test-Path -LiteralPath (Join-Path $Root 'index.php'))) {
    Write-BirklyWrongFolderHint -Root $Root
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
    $existing = @(Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue)
    foreach ($c in $existing) {
        Stop-Process -Id $c.OwningProcess -Force -ErrorAction SilentlyContinue
    }
}

Write-Host ''
Write-Host "[Birkly] Starting server on http://localhost:$Port"
Write-Host "[Birkly] Admin panel: http://localhost:$Port/admin/"
Write-Host '[Birkly] Press Ctrl+C to stop.'
Write-Host ''

$router = Join-Path $Root 'server-router.php'
& $phpExe -S "localhost:$Port" -t $Root $router
exit $LASTEXITCODE
