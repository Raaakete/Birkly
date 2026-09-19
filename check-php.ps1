#Requires -Version 5.1
$ErrorActionPreference = 'Stop'
$entryScript = if ($PSCommandPath) { $PSCommandPath } elseif ($MyInvocation.MyCommand.Path) { $MyInvocation.MyCommand.Path } else { Join-Path $PSScriptRoot 'check-php.ps1' }
$Global:BirklyWindowsScriptRoot = (Split-Path -Parent (Resolve-Path -LiteralPath $entryScript).Path)
. (Join-Path $Global:BirklyWindowsScriptRoot 'scripts\windows-php-common.ps1')

$Root = Get-BirklyRoot
Set-Location -LiteralPath $Root

Write-Host ""
Write-Host "[Birkly] Checking PHP for local development..."
Write-Host ""

$phpExe = Get-BirklyPhpExe -Root $Root
if (-not $phpExe) {
    Write-Host "[Birkly] PHP is not installed yet."
    Write-Host ""
    Write-Host "Run this in the same folder (downloads PHP into runtime\php):"
    Write-Host "  powershell -ExecutionPolicy Bypass -File .\install-php.ps1"
    Write-Host ""
    exit 1
}

$phpVersion = & $phpExe -r "echo PHP_VERSION;" 2>$null
if (-not $phpVersion) {
    Write-Host "[Birkly] Could not read PHP version."
    exit 1
}

Write-Host "[Birkly] Found PHP $phpVersion ($phpExe)"

& $phpExe -r "if (version_compare(PHP_VERSION, '8.0.0', '<')) { fwrite(STDERR, '[Birkly] PHP 8.0+ is required.'.PHP_EOL); exit(1); }"
if ($LASTEXITCODE -ne 0) { exit 1 }

if (-not (Test-BirklyPhpExtensions -PhpExe $phpExe)) {
    Write-Host "[Birkly] Re-run: powershell -ExecutionPolicy Bypass -File .\install-php.ps1"
    Write-Host "[Birkly] Required: json, mbstring, curl, openssl, zip, fileinfo, gd (+ HTTPS CA bundle for Hub)."
    exit 1
}

if (-not (Test-BirklyCmsRootCandidate -Dir $Root)) {
    Write-BirklyWrongFolderHint -Root $Root
    exit 1
}

Write-Host "[Birkly] PHP is ready. Next: powershell -ExecutionPolicy Bypass -File .\start-server.ps1"
Write-Host ""
exit 0
