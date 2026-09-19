# Shared helpers for Birkly Windows PowerShell scripts (dot-source from repo root .ps1 files).
$ErrorActionPreference = 'Stop'

function Resolve-BirklyCmsRoot {
    param([string]$StartDir)
    if (-not $StartDir) { return $null }
    $dir = $StartDir
    while ($dir) {
        $index = Join-Path $dir 'index.php'
        $admin = Join-Path $dir 'admin'
        if ((Test-Path -LiteralPath $index) -and (Test-Path -LiteralPath $admin)) {
            return (Resolve-Path -LiteralPath $dir).Path
        }
        $parent = Split-Path -Parent $dir
        if (-not $parent -or $parent -eq $dir) { break }
        $dir = $parent
    }
    return $null
}

function Get-BirklyRoot {
    if ($Global:BirklyWindowsScriptRoot) {
        $fromEntry = Resolve-BirklyCmsRoot -StartDir $Global:BirklyWindowsScriptRoot
        if ($fromEntry) { return $fromEntry }
    }
    $fromCwd = Resolve-BirklyCmsRoot -StartDir (Get-Location).Path
    if ($fromCwd) { return $fromCwd }
    if ($Global:BirklyWindowsScriptRoot) {
        return $Global:BirklyWindowsScriptRoot
    }
    return (Get-Location).Path
}

function Get-BirklyPhpExe {
    param([string]$Root = (Get-BirklyRoot))
    $local = Join-Path $Root 'runtime\php\php.exe'
    if (Test-Path -LiteralPath $local) {
        return $local
    }
    $cmd = Get-Command php -ErrorAction SilentlyContinue
    if ($cmd -and $cmd.Source) {
        return $cmd.Source
    }
    return $null
}

function Test-BirklyPhpExtensions {
    param([string]$PhpExe)
    $required = @('json', 'mbstring', 'curl', 'openssl')
    foreach ($ext in $required) {
        & $PhpExe -r "exit(extension_loaded('$ext') ? 0 : 1);" 2>$null
        if ($LASTEXITCODE -ne 0) {
            Write-Host "[Birkly] Missing PHP extension: $ext"
            return $false
        }
    }
    return $true
}

function Initialize-BirklyPhpIni {
    param([string]$RuntimePhpDir)
    $iniDev = Join-Path $RuntimePhpDir 'php.ini-development'
    $ini = Join-Path $RuntimePhpDir 'php.ini'
    if (-not (Test-Path -LiteralPath $iniDev)) {
        throw "php.ini-development not found in $RuntimePhpDir"
    }
    Copy-Item -LiteralPath $iniDev -Destination $ini -Force
    $block = @"

; --- Birkly local server ---
extension_dir = "ext"
extension=curl
extension=mbstring
extension=openssl
extension=fileinfo
"@
    Add-Content -LiteralPath $ini -Value $block -Encoding UTF8
}

function Write-BirklyWrongFolderHint {
    param([string]$Root)
    Write-Host '[Birkly] index.php not found. Open the Birkly folder that contains index.php, admin\, and 0_START-HERE.html.'
    Write-Host "[Birkly] Current folder: $Root"
    $parent = Split-Path -Parent $Root
    if ($parent -and (Test-Path -LiteralPath (Join-Path $parent 'index.php'))) {
        Write-Host "[Birkly] index.php is in the parent folder. Try:"
        Write-Host "  cd `"$parent`""
        Write-Host '  powershell -ExecutionPolicy Bypass -File .\start-server.ps1'
    }
}
