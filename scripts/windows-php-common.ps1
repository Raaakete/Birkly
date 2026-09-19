# Shared helpers for Birkly Windows PowerShell scripts (dot-source from repo root .ps1 files).
$ErrorActionPreference = 'Stop'

function Get-BirklyRoot {
    if ($PSScriptRoot) {
        return (Resolve-Path $PSScriptRoot).Path
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
