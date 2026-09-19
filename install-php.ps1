# Downloads portable PHP into runtime\php (no Git Bash, no manual zip steps).
# Run from your Birkly folder in PowerShell:
#   powershell -ExecutionPolicy Bypass -File .\install-php.ps1
#Requires -Version 5.1

$ErrorActionPreference = 'Stop'
$entryScript = if ($PSCommandPath) { $PSCommandPath } elseif ($MyInvocation.MyCommand.Path) { $MyInvocation.MyCommand.Path } else { Join-Path $PSScriptRoot 'install-php.ps1' }
$Global:BirklyWindowsScriptRoot = (Split-Path -Parent (Resolve-Path -LiteralPath $entryScript).Path)
. (Join-Path $Global:BirklyWindowsScriptRoot 'scripts\windows-php-common.ps1')

$Root = Get-BirklyRoot
$RuntimeDir = Join-Path $Root 'runtime\php'
$phpExe = Join-Path $RuntimeDir 'php.exe'

if (Test-Path -LiteralPath $phpExe) {
    Repair-BirklyPhpIni -RuntimePhpDir $RuntimeDir
    Write-Host "[Birkly] PHP is already installed in runtime\php (php.ini checked for Birkly extensions)."
    exit 0
}

$ReleaseBase = 'https://downloads.php.net/~windows/releases/'
$ZipName = $null
$Version = $null

[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

try {
    $manifest = Invoke-RestMethod -Uri ($ReleaseBase + 'releases.json') -UseBasicParsing
    $branch = $manifest.'8.3'
    if ($branch -and $branch.'ts-vs16-x64' -and $branch.'ts-vs16-x64'.zip.path) {
        $ZipName = $branch.'ts-vs16-x64'.zip.path
        $Version = $branch.version
    }
} catch {
    Write-Host "[Birkly] Could not read PHP release list; using fallback build."
}

if (-not $ZipName) {
    $Version = '8.3.33'
    $ZipName = "php-$Version-Win32-vs16-x64.zip"
}

$Url = $ReleaseBase + $ZipName
$ZipPath = Join-Path $env:TEMP $ZipName

Write-Host "[Birkly] Downloading PHP $Version (portable, ~30 MB)..."
Write-Host "[Birkly] This may take a few minutes."

try {
    Invoke-WebRequest -Uri $Url -OutFile $ZipPath -UseBasicParsing
} catch {
    Write-Host "[Birkly] Download failed. Check your internet connection or try again later."
    Write-Host $_.Exception.Message
    Write-Host "[Birkly] URL: $Url"
    exit 1
}

if (Test-Path -LiteralPath $RuntimeDir) {
    Remove-Item -LiteralPath $RuntimeDir -Recurse -Force
}
New-Item -ItemType Directory -Path $RuntimeDir -Force | Out-Null

Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::ExtractToDirectory($ZipPath, $RuntimeDir)

Initialize-BirklyPhpIni -RuntimePhpDir $RuntimeDir

Remove-Item -LiteralPath $ZipPath -Force -ErrorAction SilentlyContinue

if (-not (Test-Path -LiteralPath $phpExe)) {
    Write-Host "[Birkly] Install failed: php.exe not found after extract."
    exit 1
}

$ver = & $phpExe -r "echo PHP_VERSION;"
Write-Host "[Birkly] Installed PHP $ver in runtime\php"
Write-Host "[Birkly] Next: powershell -ExecutionPolicy Bypass -File .\check-php.ps1"
exit 0
