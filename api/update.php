<?php
// HaruCMS Safe Updater v1.0
header('Content-Type: application/json');

// --- CONFIG ---
$backupDir = __DIR__ . '/../backups';
$updateDir = __DIR__ . '/../update_tmp';
$rootDir   = realpath(__DIR__ . '/..');
$exclude = [
    'content/',
    'media/',
    'users/',
    'settings/',
    'plugins/',
    'templates/', // Optionally allow updating templates if not customized
    'backups/',
    'update_tmp/'
];

// --- HELPERS ---
function rrmdir($dir) {
    if (!is_dir($dir)) return;
    $objects = scandir($dir);
    foreach ($objects as $object) {
        if ($object == "." || $object == "..") continue;
        $path = "$dir/$object";
        if (is_dir($path)) rrmdir($path);
        else unlink($path);
    }
    rmdir($dir);
}

function startsWith($haystack, $needle) {
    return substr($haystack, 0, strlen($needle)) === $needle;
}

// --- MAIN ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

$url = $_POST['url'] ?? '';
if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['error' => 'Invalid or missing update URL']);
    exit;
}

// Create backup dir if needed
if (!is_dir($backupDir)) mkdir($backupDir, 0775, true);
if (!is_dir($updateDir)) mkdir($updateDir, 0775, true);

$timestamp = date('Ymd_His');
$backupFile = "$backupDir/backup_coreadmin_$timestamp.zip";
$tmpZip = "$updateDir/update.zip";

// 1. Backup core/admin files
$zip = new ZipArchive();
if ($zip->open($backupFile, ZipArchive::CREATE) !== TRUE) {
    echo json_encode(['error' => 'Could not create backup']);
    exit;
}
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootDir));
foreach ($rii as $file) {
    if ($file->isDir()) continue;
    $relPath = str_replace($rootDir . '/', '', $file->getPathname());
    $skip = false;
    foreach ($exclude as $ex) {
        if (startsWith($relPath, $ex)) {
            $skip = true;
            break;
        }
    }
    if (!$skip) $zip->addFile($file->getPathname(), $relPath);
}
$zip->close();

// 2. Download update ZIP
$zipData = @file_get_contents($url);
if (!$zipData) {
    echo json_encode(['error' => 'Failed to download update package']);
    exit;
}
file_put_contents($tmpZip, $zipData);

// 3. Extract update ZIP to temp dir
$extractDir = "$updateDir/extracted";
rrmdir($extractDir);
mkdir($extractDir, 0775, true);
$zip = new ZipArchive();
if ($zip->open($tmpZip) !== TRUE) {
    echo json_encode(['error' => 'Failed to open update ZIP']);
    exit;
}
$zip->extractTo($extractDir);
$zip->close();

// 4. Overwrite allowed files only
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractDir));
$updated = [];
foreach ($rii as $file) {
    if ($file->isDir()) continue;
    $relPath = str_replace($extractDir . '/', '', $file->getPathname());
    $skip = false;
    foreach ($exclude as $ex) {
        if (startsWith($relPath, $ex)) {
            $skip = true;
            break;
        }
    }
    if ($skip) continue;
    $targetPath = $rootDir . '/' . $relPath;
    if (!is_dir(dirname($targetPath))) mkdir(dirname($targetPath), 0775, true);
    if (copy($file->getPathname(), $targetPath)) {
        $updated[] = $relPath;
    }
}

// 5. Cleanup
rrmdir($extractDir);
@unlink($tmpZip);

// 6. Done
$response = [
    'success' => true,
    'updated_files' => $updated,
    'backup' => basename($backupFile)
];
echo json_encode($response); 