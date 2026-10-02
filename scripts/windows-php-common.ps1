# Shared helpers for Birkly Windows PowerShell scripts (dot-source from repo root .ps1 files).
$ErrorActionPreference = 'Stop'

$BirklyPhpRequiredExtensions = @('json', 'mbstring', 'curl', 'openssl', 'zip', 'fileinfo', 'gd')

function Test-BirklyPathExists {
    param([string]$Path)
    if (-not $Path) { return $false }
    if (Test-Path -LiteralPath $Path) { return $true }
    try {
        $null = Get-Item -LiteralPath $Path -ErrorAction Stop
        return $true
    } catch {
        return $false
    }
}

function Test-BirklyCmsRootCandidate {
    param([string]$Dir)
    if (-not $Dir) { return $false }
    $index = Join-Path $Dir 'index.php'
    if (-not (Test-BirklyPathExists -Path $index)) { return $false }
    $admin = Join-Path $Dir 'admin'
    $router = Join-Path $Dir 'server-router.php'
    return ((Test-BirklyPathExists -Path $admin) -or (Test-BirklyPathExists -Path $router))
}

function Resolve-BirklyCmsRoot {
    param([string]$StartDir)
    if (-not $StartDir) { return $null }
    $dir = $StartDir
    while ($dir) {
        if (Test-BirklyCmsRootCandidate -Dir $dir) {
            return (Resolve-Path -LiteralPath $dir).Path
        }
        $parent = Split-Path -Parent $dir
        if (-not $parent -or $parent -eq $dir) { break }
        $dir = $parent
    }
    if (Test-BirklyPathExists -Path $StartDir) {
        $children = @(Get-ChildItem -LiteralPath $StartDir -Directory -ErrorAction SilentlyContinue)
        foreach ($child in $children) {
            if (Test-BirklyCmsRootCandidate -Dir $child.FullName) {
                return (Resolve-Path -LiteralPath $child.FullName).Path
            }
        }
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

function Get-BirklyPhpSearchDirs {
    $dirs = @()
    $seen = @{}
    foreach ($candidate in @((Get-BirklyRoot), $Global:BirklyWindowsScriptRoot, (Get-Location).Path)) {
        if (-not $candidate) { continue }
        $dir = $candidate
        for ($i = 0; $i -lt 4; $i++) {
            if (-not $dir) { break }
            $key = $dir.ToLowerInvariant()
            if (-not $seen.ContainsKey($key)) {
                $seen[$key] = $true
                $dirs += $dir
            }
            $dir = Split-Path -Parent $dir
        }
    }
    return $dirs
}

function Get-BirklyPhpExe {
    param([string]$Root = (Get-BirklyRoot))
    foreach ($dir in (Get-BirklyPhpSearchDirs)) {
        foreach ($rel in @('runtime\php\php.exe', 'scripts\runtime\php\php.exe')) {
            $local = Join-Path $dir $rel
            if (Test-BirklyPathExists -Path $local) {
                return (Resolve-Path -LiteralPath $local).Path
            }
        }
    }
    $cmd = Get-Command php -ErrorAction SilentlyContinue
    if ($cmd -and $cmd.Source) {
        return $cmd.Source
    }
    return $null
}

function Test-BirklyPhpExtensions {
    param([string]$PhpExe)
    foreach ($ext in $BirklyPhpRequiredExtensions) {
        & $PhpExe -r "exit(extension_loaded('$ext') ? 0 : 1);" 2>$null
        if ($LASTEXITCODE -ne 0) {
            Write-Host "[Birkly] Missing PHP extension: $ext"
            if ($ext -eq 'zip') {
                Write-Host '[Birkly] Hub installs and Marsk starters need zip. Re-run install-php.ps1'
            }
            if ($ext -eq 'gd') {
                Write-Host '[Birkly] Image uploads/thumbnails need gd. Re-run install-php.ps1'
            }
            return $false
        }
    }
    return $true
}

function Ensure-BirklyCaBundle {
    param([string]$RuntimePhpDir)
    $sslDir = Join-Path $RuntimePhpDir 'extras\ssl'
    if (-not (Test-BirklyPathExists -Path $sslDir)) {
        New-Item -ItemType Directory -Path $sslDir -Force | Out-Null
    }
    $caPath = Join-Path $sslDir 'cacert.pem'
    if (Test-BirklyPathExists -Path $caPath) {
        return $caPath
    }
    $url = 'https://curl.se/ca/cacert.pem'
    try {
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        Invoke-WebRequest -Uri $url -OutFile $caPath -UseBasicParsing
    } catch {
        Write-Host "[Birkly] Warning: could not download CA bundle for HTTPS ($url)."
        Write-Host $_.Exception.Message
        return $null
    }
    return $caPath
}

function Get-BirklyPhpIniExtensionBlock {
    return @"

; --- Birkly local server ---
extension_dir = "ext"
allow_url_fopen = On
extension=curl
extension=mbstring
extension=openssl
extension=fileinfo
extension=zip
extension=gd
openssl.cafile = "extras/ssl/cacert.pem"
curl.cainfo = "extras/ssl/cacert.pem"
upload_max_filesize = 128M
post_max_size = 128M
memory_limit = 256M
realpath_cache_size = 4096K
realpath_cache_ttl = 600
; Built-in server uses CLI SAPI — leave OPcache off (enable_cli caused slow stat-on-every-request on Windows).
opcache.enable = 0
opcache.enable_cli = 0
"@
}

function Initialize-BirklyPhpIni {
    param([string]$RuntimePhpDir)
    Ensure-BirklyCaBundle -RuntimePhpDir $RuntimePhpDir | Out-Null
    $iniDev = Join-Path $RuntimePhpDir 'php.ini-development'
    $ini = Join-Path $RuntimePhpDir 'php.ini'
    if (-not (Test-BirklyPathExists -Path $iniDev)) {
        throw "php.ini-development not found in $RuntimePhpDir"
    }
    Copy-Item -LiteralPath $iniDev -Destination $ini -Force
    Add-Content -LiteralPath $ini -Value (Get-BirklyPhpIniExtensionBlock) -Encoding UTF8
}

function Repair-BirklyPhpIni {
    param([string]$RuntimePhpDir)
    Ensure-BirklyCaBundle -RuntimePhpDir $RuntimePhpDir | Out-Null
    $ini = Join-Path $RuntimePhpDir 'php.ini'
    if (-not (Test-BirklyPathExists -Path $ini)) {
        Initialize-BirklyPhpIni -RuntimePhpDir $RuntimePhpDir
        return
    }
    $content = Get-Content -LiteralPath $ini -Raw
    if ($content -notmatch '(?m)^\s*;\s*---\s*Birkly local server\s*---') {
        Add-Content -LiteralPath $ini -Value (Get-BirklyPhpIniExtensionBlock) -Encoding UTF8
        return
    }
    $toAdd = @()
    foreach ($ext in @('fileinfo', 'zip', 'gd')) {
        if ($content -notmatch "(?m)^\s*extension\s*=\s*$ext\s*$") {
            $toAdd += "extension=$ext"
        }
    }
    if ($content -notmatch '(?m)^\s*allow_url_fopen\s*=\s*On') {
        $toAdd += 'allow_url_fopen = On'
    }
    if ($content -notmatch '(?m)^\s*openssl\.cafile\s*=') {
        $toAdd += 'openssl.cafile = "extras/ssl/cacert.pem"'
    }
    if ($content -notmatch '(?m)^\s*curl\.cainfo\s*=') {
        $toAdd += 'curl.cainfo = "extras/ssl/cacert.pem"'
    }
    # Append even when php.ini-development already set a smaller value.
    # The last directive in the file wins.
    $performanceLines = @(
        'upload_max_filesize = 128M',
        'post_max_size = 128M',
        'realpath_cache_size = 4096K',
        'realpath_cache_ttl = 600',
        'opcache.enable = 0',
        'opcache.enable_cli = 0'
    )
    foreach ($line in $performanceLines) {
        $escaped = [regex]::Escape($line)
        if ($content -notmatch "(?m)^\s*$escaped\s*$") {
            $toAdd += $line
        }
    }
    if ($toAdd.Count -gt 0) {
        Add-Content -LiteralPath $ini -Value ("`r`n" + ($toAdd -join "`r`n")) -Encoding UTF8
    }
}

function Write-BirklyWrongFolderHint {
    param([string]$Root)
    Write-Host '[Birkly] index.php not found. Open the Birkly folder that contains index.php, admin\, and 0_START-HERE.html.'
    Write-Host "[Birkly] Scripts folder: $($Global:BirklyWindowsScriptRoot)"
    Write-Host "[Birkly] Working folder: $Root"
    if (Test-BirklyPathExists -Path $Root) {
        $names = @(Get-ChildItem -LiteralPath $Root -ErrorAction SilentlyContinue | Select-Object -First 12 -ExpandProperty Name)
        if ($names.Count -gt 0) {
            Write-Host ('[Birkly] Files here: ' + ($names -join ', '))
        }
    }
    $parent = Split-Path -Parent $Root
    if ($parent -and (Test-BirklyCmsRootCandidate -Dir $parent)) {
        Write-Host '[Birkly] The CMS root is the parent folder. Try:'
        Write-Host "  cd `"$parent`""
        Write-Host '  powershell -ExecutionPolicy Bypass -File .\start-server.ps1'
        return
    }
    if (Test-BirklyPathExists -Path $Root) {
        $children = @(Get-ChildItem -LiteralPath $Root -Directory -ErrorAction SilentlyContinue)
        foreach ($child in $children) {
            if (Test-BirklyCmsRootCandidate -Dir $child.FullName) {
                Write-Host '[Birkly] Birkly looks like it is inside a subfolder. Try:'
                Write-Host "  cd `"$($child.FullName)`""
                Write-Host '  powershell -ExecutionPolicy Bypass -File .\start-server.ps1'
                return
            }
        }
    }
    Write-Host '[Birkly] In File Explorer, confirm you see index.php next to 0_START-HERE.html. If not, extract the full zip again (not only some files).'
}
