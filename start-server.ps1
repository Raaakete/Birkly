#Requires -Version 5.1
param(
    [int]$Port = 8080,
    [switch]$Kill
)

$ErrorActionPreference = 'Stop'
$entryScript = if ($PSCommandPath) { $PSCommandPath } elseif ($MyInvocation.MyCommand.Path) { $MyInvocation.MyCommand.Path } else { Join-Path $PSScriptRoot 'start-server.ps1' }
$Global:BirklyWindowsScriptRoot = (Split-Path -Parent (Resolve-Path -LiteralPath $entryScript).Path)
. (Join-Path $Global:BirklyWindowsScriptRoot 'scripts\windows-php-common.ps1')

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

if (-not (Test-BirklyCmsRootCandidate -Dir $Root)) {
    Write-BirklyWrongFolderHint -Root $Root
    exit 1
}
if ($Root -ne $Global:BirklyWindowsScriptRoot) {
    Write-Host "[Birkly] Using CMS folder: $Root"
}

foreach ($dir in @('content', 'media', 'users', 'settings', 'logs', 'temp', 'cache', 'plugins')) {
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
Write-Host "[Birkly] Birkly is running at http://localhost:$Port"
Write-Host "[Birkly] Admin login: http://localhost:$Port/admin/"
Write-Host '[Birkly] Leave this window open while you use Birkly.'
Write-Host '[Birkly] To start again later, run this same command from the Birkly folder, then open the admin login above.'
Write-Host '[Birkly] Request log: logs\server.log'
Write-Host '[Birkly] Press Ctrl+C to stop.'
Write-Host ''

$router = Join-Path $Root 'server-router.php'
$log = Join-Path $Root 'logs\server.log'
& $phpExe -S "localhost:$Port" -t $Root $router *>> $log
exit $LASTEXITCODE
