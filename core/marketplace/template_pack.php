<?php
/**
 * P53 Wave 1 — Shared site-pack install engine (Hub + manual ZIP).
 *
 * Overwrite modes (P53-D8):
 * - replace: backup (if dirty) then remove pack-targeted collections + project overlay, then extract
 * - merge: backup (if dirty) then overlay pack files onto existing site content
 * - fresh-only: refuse when collections or project content already exist
 *
 * Demo entries (P53-D5): schema + project always; entry JSON only when include_demo_entries
 * Required plugins (P53-D4): missing required_plugins block until confirm; recommended warn-only
 */

declare(strict_types=1);

/**
 * @return list<string>
 */
function birkly_marketplace_template_pack_overwrite_modes(): array
{
    return ['replace', 'merge', 'fresh-only'];
}

/**
 * Normalize pack install options from API / UI input (P53-M4/M5/M6).
 *
 * @param array<string,mixed> $input
 * @return array<string,mixed>
 */
function birkly_marketplace_api_pack_options(array $input): array
{
    $overwrite = birkly_marketplace_normalize_overwrite_mode((string) ($input['overwrite_mode'] ?? 'merge'));

    $includeDemo = false;
    if (array_key_exists('include_demo_entries', $input)) {
        $raw = $input['include_demo_entries'];
        $includeDemo = $raw === true || $raw === 1 || $raw === '1' || $raw === 'true' || $raw === 'on';
    }

    return [
        'overwrite_mode' => $overwrite,
        'include_demo_entries' => $includeDemo,
        'confirm_required_plugins' => !empty($input['confirm_required_plugins']) || !empty($input['confirmRequiredPlugins']),
        'install_missing_plugins' => !array_key_exists('install_missing_plugins', $input)
            || !empty($input['install_missing_plugins']),
        'consent_accepted' => !empty($input['consent_accepted']) || !empty($input['consentAccepted']),
    ];
}

function birkly_marketplace_normalize_overwrite_mode(string $mode, string $default = 'merge'): string
{
    $mode = strtolower(trim($mode));
    if (in_array($mode, birkly_marketplace_template_pack_overwrite_modes(), true)) {
        return $mode;
    }
    return in_array($default, birkly_marketplace_template_pack_overwrite_modes(), true) ? $default : 'merge';
}

/**
 * Optional single wrapper directory containing manifest.json (path-traversal safe).
 */
function birkly_marketplace_template_pack_zip_prefix(ZipArchive $zip): string
{
    $manifestDirs = [];
    $topLevels = [];
    $hasRootManifest = false;

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (!is_string($name) || $name === '' || (function_exists('birkly_marketplace_should_skip_zip_entry') && birkly_marketplace_should_skip_zip_entry($name))) {
            continue;
        }
        $trimmed = rtrim(str_replace('\\', '/', $name), '/');
        if ($trimmed === '' || str_contains($trimmed, '..')) {
            continue;
        }
        if ($trimmed === 'manifest.json') {
            $hasRootManifest = true;
            continue;
        }
        if (!str_contains($trimmed, '/')) {
            continue;
        }
        $parts = explode('/', $trimmed);
        $top = $parts[0] ?? '';
        if ($top !== '') {
            $topLevels[$top] = true;
        }
        if (basename($trimmed) === 'manifest.json') {
            array_pop($parts);
            $dir = implode('/', $parts);
            if ($dir !== '') {
                $manifestDirs[$dir] = true;
            }
        }
    }

    if ($hasRootManifest) {
        return '';
    }
    if (count($manifestDirs) === 1) {
        $dir = array_key_first($manifestDirs);
        return is_string($dir) && $dir !== '' ? $dir . '/' : '';
    }
    if (count($topLevels) === 1 && count($manifestDirs) === 0) {
        // No manifest found yet — leave empty; validation will fail clearly.
        return '';
    }

    return '';
}

/**
 * @return array{ok:bool,manifest?:array<string,mixed>,prefix?:string,errors:list<string>}
 */
function birkly_marketplace_read_template_pack_manifest(string $zipPath): array
{
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'errors' => ['ZipArchive extension not available']];
    }
    if (!is_file($zipPath)) {
        return ['ok' => false, 'errors' => ['Pack zip not found']];
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return ['ok' => false, 'errors' => ['Cannot open zip archive — invalid or corrupt ZIP']];
    }

    $prefix = birkly_marketplace_template_pack_zip_prefix($zip);
    $manifestName = $prefix . 'manifest.json';
    $raw = $zip->getFromName($manifestName);
    if ($raw === false) {
        // Some archives store with leading ./
        $raw = $zip->getFromName(ltrim($manifestName, './'));
    }
    $zip->close();

    if ($raw === false || !is_string($raw) || trim($raw) === '') {
        return ['ok' => false, 'errors' => ['Invalid site pack: manifest.json is missing']];
    }

    $manifest = json_decode($raw, true);
    if (!is_array($manifest)) {
        return ['ok' => false, 'errors' => ['Invalid site pack: manifest.json is not valid JSON']];
    }

    $id = trim((string) ($manifest['id'] ?? ''));
    $version = trim((string) ($manifest['version'] ?? ''));
    $collections = $manifest['collections'] ?? null;
    $errors = [];
    if ($id === '' || !preg_match('/^[a-z][a-z0-9-]{0,62}$/', $id)) {
        $errors[] = 'Invalid site pack: manifest.id is required (lowercase slug)';
    }
    if ($version === '') {
        $errors[] = 'Invalid site pack: manifest.version is required';
    }
    if (!is_array($collections) || $collections === []) {
        $errors[] = 'Invalid site pack: manifest.collections must be a non-empty array';
    }
    if ($errors !== []) {
        return ['ok' => false, 'errors' => $errors];
    }

    $manifest['required_plugins'] = array_values(array_filter(array_map(
        static fn($v) => is_string($v) ? trim($v) : '',
        (array) ($manifest['required_plugins'] ?? [])
    )));
    $manifest['recommended_plugins'] = array_values(array_filter(array_map(
        static fn($v) => is_string($v) ? trim($v) : '',
        (array) ($manifest['recommended_plugins'] ?? [])
    )));

    return ['ok' => true, 'manifest' => $manifest, 'prefix' => $prefix, 'errors' => []];
}

/**
 * Validate Ops starter / site-pack zip layout (manifest + collections/ + project/).
 *
 * @return array{ok:bool,manifest?:array<string,mixed>,prefix?:string,errors:list<string>,has_collections?:bool,has_project?:bool}
 */
function birkly_marketplace_validate_template_pack_zip(string $zipPath): array
{
    $manifestResult = birkly_marketplace_read_template_pack_manifest($zipPath);
    if (!$manifestResult['ok']) {
        return $manifestResult;
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return ['ok' => false, 'errors' => ['Cannot open zip archive — invalid or corrupt ZIP']];
    }

    $prefix = (string) ($manifestResult['prefix'] ?? '');
    $hasCollections = false;
    $hasProject = false;
    $errors = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (!is_string($name) || $name === '') {
            continue;
        }
        $normalized = str_replace('\\', '/', $name);
        if (str_contains($normalized, '..')) {
            $zip->close();
            return ['ok' => false, 'errors' => ['Unsafe path in zip (path traversal): ' . $normalized]];
        }
        if (function_exists('birkly_marketplace_should_skip_zip_entry') && birkly_marketplace_should_skip_zip_entry($normalized)) {
            continue;
        }
        $relative = $prefix !== '' && str_starts_with($normalized, $prefix)
            ? substr($normalized, strlen($prefix))
            : $normalized;
        $relative = ltrim($relative, '/');
        if ($relative === '' || str_ends_with($relative, '/')) {
            continue;
        }
        if (str_starts_with($relative, 'collections/')) {
            $hasCollections = true;
        }
        if (str_starts_with($relative, 'project/')) {
            $hasProject = true;
        }
    }
    $zip->close();

    if (!$hasCollections) {
        $errors[] = 'Invalid site pack: collections/ directory is required';
    }
    if (!$hasProject) {
        $errors[] = 'Invalid site pack: project/ directory is required';
    }
    if ($errors !== []) {
        return ['ok' => false, 'errors' => $errors];
    }

    return [
        'ok' => true,
        'manifest' => $manifestResult['manifest'],
        'prefix' => $prefix,
        'has_collections' => true,
        'has_project' => true,
        'errors' => [],
    ];
}

/**
 * Whether the CMS site already has collections or project content.
 *
 * @return array{dirty:bool,collection_slugs:list<string>,project_file_count:int}
 */
function birkly_marketplace_template_pack_site_state(string $projectRoot): array
{
    if (!function_exists('birkly_content_collections_dir')) {
        require_once dirname(__DIR__) . '/ops_starters_client.php';
    }
    if (!function_exists('birkly_public_site_dir')) {
        require_once dirname(__DIR__) . '/project_site.php';
    }

    $collectionsDir = birkly_content_collections_dir($projectRoot);
    $projectDir = rtrim($projectRoot, '/\\') . '/' . birkly_public_site_dir();
    $slugs = [];

    if (is_dir($collectionsDir)) {
        foreach (scandir($collectionsDir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (is_dir($collectionsDir . '/' . $item)) {
                $slugs[] = $item;
            }
        }
    }

    $projectFileCount = 0;
    if (is_dir($projectDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($projectDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $base = $file->getFilename();
            if ($base === '.gitkeep' || $base === '.DS_Store') {
                continue;
            }
            $projectFileCount++;
        }
    }

    return [
        'dirty' => $slugs !== [] || $projectFileCount > 0,
        'collection_slugs' => $slugs,
        'project_file_count' => $projectFileCount,
    ];
}

/**
 * Backup collections + project before destructive Replace/Merge.
 *
 * @return array{ok:bool,path?:string,error?:string}
 */
function birkly_marketplace_backup_template_pack_targets(string $projectRoot): array
{
    if (!function_exists('birkly_content_collections_dir')) {
        require_once dirname(__DIR__) . '/ops_starters_client.php';
    }
    if (!function_exists('birkly_public_site_dir')) {
        require_once dirname(__DIR__) . '/project_site.php';
    }

    $backupRoot = rtrim($projectRoot, '/\\') . '/backup/pack-install-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
    if (!is_dir(dirname($backupRoot)) && !@mkdir(dirname($backupRoot), 0755, true) && !is_dir(dirname($backupRoot))) {
        return ['ok' => false, 'error' => 'Cannot create backup directory'];
    }
    if (!@mkdir($backupRoot, 0755, true) && !is_dir($backupRoot)) {
        return ['ok' => false, 'error' => 'Cannot create pack install backup directory'];
    }

    $collectionsDir = birkly_content_collections_dir($projectRoot);
    $projectDir = rtrim($projectRoot, '/\\') . '/' . birkly_public_site_dir();

    if (is_dir($collectionsDir)) {
        $dest = $backupRoot . '/collections';
        $cmd = 'cp -a ' . escapeshellarg($collectionsDir) . ' ' . escapeshellarg($dest);
        exec($cmd, $out, $code);
        if ($code !== 0 || !is_dir($dest)) {
            return ['ok' => false, 'error' => 'Failed to backup collections'];
        }
    }
    if (is_dir($projectDir)) {
        $dest = $backupRoot . '/project';
        $cmd = 'cp -a ' . escapeshellarg($projectDir) . ' ' . escapeshellarg($dest);
        exec($cmd, $out, $code);
        if ($code !== 0 || !is_dir($dest)) {
            return ['ok' => false, 'error' => 'Failed to backup project site'];
        }
    }

    return ['ok' => true, 'path' => $backupRoot];
}

/**
 * Apply overwrite mode before extract. Onboarding uses replace semantics separately.
 *
 * @param list<string> $packCollections
 * @return array{ok:bool,backup_path?:string|null,error?:string,code?:string}
 */
function birkly_marketplace_prepare_template_pack_overwrite(
    string $projectRoot,
    string $overwriteMode,
    array $packCollections,
    bool $forceNoBackup = false
): array {
    $overwriteMode = birkly_marketplace_normalize_overwrite_mode($overwriteMode);
    $state = birkly_marketplace_template_pack_site_state($projectRoot);

    if ($overwriteMode === 'fresh-only' && $state['dirty']) {
        return [
            'ok' => false,
            'code' => 'site_not_fresh',
            'error' => 'Fresh-only install refused: site already has collections or project files. Choose Merge or Replace, or clear the site first.',
        ];
    }

    $backupPath = null;
    $needsBackup = $state['dirty'] && in_array($overwriteMode, ['replace', 'merge'], true) && !$forceNoBackup;
    if ($needsBackup) {
        $backup = birkly_marketplace_backup_template_pack_targets($projectRoot);
        if (!$backup['ok']) {
            return ['ok' => false, 'error' => $backup['error'] ?? 'Backup failed', 'code' => 'backup_failed'];
        }
        $backupPath = $backup['path'] ?? null;
    }

    if ($overwriteMode === 'replace') {
        if (!function_exists('birkly_content_collections_dir')) {
            require_once dirname(__DIR__) . '/ops_starters_client.php';
        }
        if (!function_exists('birkly_public_site_dir')) {
            require_once dirname(__DIR__) . '/project_site.php';
        }
        if (!function_exists('birkly_demo_remove_tree')) {
            require_once dirname(__DIR__) . '/demo_starter_templates.php';
        }

        $collectionsDir = birkly_content_collections_dir($projectRoot);
        foreach ($packCollections as $slug) {
            $slug = preg_replace('/[^a-z0-9_-]/i', '', (string) $slug) ?? '';
            if ($slug === '') {
                continue;
            }
            $path = $collectionsDir . '/' . $slug;
            if (is_dir($path)) {
                birkly_demo_remove_tree($path);
            }
        }

        $projectDir = rtrim($projectRoot, '/\\') . '/' . birkly_public_site_dir();
        if (is_dir($projectDir) && function_exists('birkly_demo_remove_dir_contents')) {
            birkly_demo_remove_dir_contents($projectDir);
        }
    }

    // merge: no pre-delete — extract overlays files
    return ['ok' => true, 'backup_path' => $backupPath];
}

/**
 * Resolve whether a required/recommended plugin id or marketplace slug is installed.
 *
 * @return array{installed:bool,plugin_id:?string,marketplace_slug:?string}
 */
function birkly_marketplace_resolve_pack_plugin_requirement(string $ref): array
{
    $ref = trim($ref);
    if ($ref === '') {
        return ['installed' => false, 'plugin_id' => null, 'marketplace_slug' => null];
    }

    birkly_marketplace_ensure_plugins_core();
    $plugins = get_all_plugins();
    $refLower = strtolower($ref);

    if (isset($plugins[$ref])) {
        return ['installed' => true, 'plugin_id' => $ref, 'marketplace_slug' => birkly_marketplace_plugin_offer_slug($ref, $plugins[$ref]) ?: null];
    }

    foreach ($plugins as $id => $plugin) {
        $slug = strtolower(birkly_marketplace_plugin_offer_slug((string) $id, is_array($plugin) ? $plugin : []));
        if ($slug !== '' && $slug === $refLower) {
            return ['installed' => true, 'plugin_id' => (string) $id, 'marketplace_slug' => $slug];
        }
        if (strtolower((string) $id) === $refLower) {
            return ['installed' => true, 'plugin_id' => (string) $id, 'marketplace_slug' => $slug !== '' ? $slug : null];
        }
    }

    $officialId = function_exists('birkly_marketplace_plugin_id_from_slug')
        ? birkly_marketplace_plugin_id_from_slug($ref)
        : null;
    $marketplaceSlug = $ref;
    if ($officialId === null && isset(birkly_official_marketplace_slugs()[$ref])) {
        $officialId = $ref;
        $marketplaceSlug = birkly_official_marketplace_slugs()[$ref];
    }

    return [
        'installed' => false,
        'plugin_id' => $officialId,
        'marketplace_slug' => $marketplaceSlug,
    ];
}

/**
 * @param list<string> $refs
 * @return array{missing:list<array<string,mixed>>,present:list<array<string,mixed>>}
 */
function birkly_marketplace_partition_pack_plugins(array $refs): array
{
    $missing = [];
    $present = [];
    foreach ($refs as $ref) {
        if (!is_string($ref) || trim($ref) === '') {
            continue;
        }
        $resolved = birkly_marketplace_resolve_pack_plugin_requirement($ref);
        $row = [
            'ref' => trim($ref),
            'plugin_id' => $resolved['plugin_id'],
            'marketplace_slug' => $resolved['marketplace_slug'],
        ];
        if ($resolved['installed']) {
            $present[] = $row;
        } else {
            $missing[] = $row;
        }
    }
    return ['missing' => $missing, 'present' => $present];
}

/**
 * Preflight a pack zip: layout, overwrite, required/recommended plugins.
 *
 * @param array<string,mixed> $options
 * @return array<string,mixed>
 */
function birkly_marketplace_preflight_template_pack(string $zipPath, array $options = []): array
{
    $validation = birkly_marketplace_validate_template_pack_zip($zipPath);
    if (!$validation['ok']) {
        return [
            'ok' => false,
            'errors' => $validation['errors'] ?? ['Invalid site pack'],
            'code' => 'invalid_pack',
        ];
    }

    $manifest = $validation['manifest'] ?? [];
    $projectRoot = (string) ($options['project_root'] ?? (function_exists('birkly_project_root') ? birkly_project_root() : dirname(__DIR__, 2)));
    $overwriteMode = birkly_marketplace_normalize_overwrite_mode((string) ($options['overwrite_mode'] ?? 'merge'));
    $includeDemo = array_key_exists('include_demo_entries', $options)
        ? (bool) $options['include_demo_entries']
        : false;
    $siteState = birkly_marketplace_template_pack_site_state($projectRoot);

    $required = birkly_marketplace_partition_pack_plugins((array) ($manifest['required_plugins'] ?? []));
    $recommended = birkly_marketplace_partition_pack_plugins((array) ($manifest['recommended_plugins'] ?? []));

    $warnings = [];
    foreach ($recommended['missing'] as $row) {
        $warnings[] = 'Recommended plugin not installed: ' . ($row['ref'] ?? '');
    }

    $freshBlocked = $overwriteMode === 'fresh-only' && $siteState['dirty'];

    return [
        'ok' => !$freshBlocked,
        'code' => $freshBlocked ? 'site_not_fresh' : null,
        'errors' => $freshBlocked
            ? ['Fresh-only install refused: site already has collections or project files.']
            : [],
        'manifest' => [
            'id' => $manifest['id'] ?? '',
            'version' => $manifest['version'] ?? '',
            'label' => $manifest['label'] ?? ($manifest['id'] ?? ''),
            'collections' => array_values((array) ($manifest['collections'] ?? [])),
            'required_plugins' => array_values((array) ($manifest['required_plugins'] ?? [])),
            'recommended_plugins' => array_values((array) ($manifest['recommended_plugins'] ?? [])),
        ],
        'overwrite_mode' => $overwriteMode,
        'include_demo_entries' => $includeDemo,
        'site' => $siteState,
        'missing_required_plugins' => $required['missing'],
        'present_required_plugins' => $required['present'],
        'missing_recommended_plugins' => $recommended['missing'],
        'warnings' => $warnings,
        'requires_plugin_confirmation' => $required['missing'] !== [],
    ];
}

/**
 * Install missing required plugins after user confirmation (never silent — P53-D4).
 *
 * @param list<array<string,mixed>> $missing
 * @return array{ok:bool,installed:list<string>,errors:list<string>,code?:string,consent?:array<string,mixed>}
 */
function birkly_marketplace_install_missing_pack_plugins(array $missing, bool $consentAccepted = false): array
{
    $installed = [];
    $errors = [];

    foreach ($missing as $row) {
        $slug = trim((string) ($row['marketplace_slug'] ?? $row['ref'] ?? ''));
        if ($slug === '') {
            $errors[] = 'Cannot resolve marketplace slug for required plugin ' . ($row['ref'] ?? '');
            continue;
        }

        // Prefer free claim path via empty license inside install_plugin? Use install_offer plugin path.
        $detail = function_exists('birkly_marketplace_offer_detail') ? birkly_marketplace_offer_detail($slug) : ['ok' => false];
        $version = '';
        if (!empty($detail['ok']) && is_array($detail['releases'] ?? null)) {
            foreach ($detail['releases'] as $release) {
                if (!is_array($release)) {
                    continue;
                }
                $ver = (string) ($release['version'] ?? '');
                if ($ver !== '' && ($version === '' || version_compare($ver, $version, '>'))) {
                    $version = $ver;
                }
            }
        }
        if ($version === '') {
            $version = '0.0.0';
        }

        $consentCheck = birkly_marketplace_require_install_consent($slug, $version, $consentAccepted);
        if (!$consentCheck['ok']) {
            $permPreview = birkly_marketplace_install_permissions($slug, $version);
            return [
                'ok' => false,
                'installed' => $installed,
                'errors' => [$consentCheck['error'] ?? 'Permission consent required for required plugin'],
                'code' => $consentCheck['code'] ?? 'consent_required',
                'consent' => [
                    'slug' => $slug,
                    'version' => $permPreview['version'] ?? $version,
                    'permissions_manifest' => $permPreview['permissions_manifest'] ?? [],
                ],
            ];
        }

        $result = birkly_marketplace_install_plugin($slug, $version, '', false, $consentAccepted);
        if (empty($result['ok'])) {
            $errors[] = 'Failed to install required plugin ' . $slug . ': ' . implode('; ', $result['errors'] ?? ['unknown error']);
            continue;
        }
        $installed[] = (string) ($result['plugin_id'] ?? $slug);
    }

    if ($errors !== []) {
        return ['ok' => false, 'installed' => $installed, 'errors' => $errors, 'code' => 'required_plugin_install_failed'];
    }

    return ['ok' => true, 'installed' => $installed, 'errors' => []];
}

/**
 * Shared install from a local zip path (Hub download or manual upload).
 *
 * Options:
 * - overwrite_mode: replace|merge|fresh-only (default merge)
 * - include_demo_entries: bool (default false for Hub/ZIP)
 * - confirm_required_plugins: bool (required when missing deps)
 * - install_missing_plugins: bool (default true when confirmed)
 * - consent_accepted: bool (for required plugin installs)
 * - source: marketplace|upload|onboarding
 * - slug / version: registry keys
 * - project_root: override
 * - skip_backup: bool (tests)
 *
 * @param array<string,mixed> $options
 * @return array<string,mixed>
 */
function birkly_marketplace_install_template_pack_from_zip(string $zipPath, array $options = []): array
{
    if (!function_exists('birkly_ops_extract_starter_zip')) {
        require_once dirname(__DIR__) . '/ops_starters_client.php';
    }

    $projectRoot = (string) ($options['project_root'] ?? (function_exists('birkly_project_root') ? birkly_project_root() : dirname(__DIR__, 2)));
    $overwriteMode = birkly_marketplace_normalize_overwrite_mode((string) ($options['overwrite_mode'] ?? 'merge'));
    $includeDemo = array_key_exists('include_demo_entries', $options)
        ? (bool) $options['include_demo_entries']
        : false;
    $confirmRequired = !empty($options['confirm_required_plugins']);
    $installMissing = array_key_exists('install_missing_plugins', $options)
        ? (bool) $options['install_missing_plugins']
        : true;
    $consentAccepted = !empty($options['consent_accepted']);
    $source = (string) ($options['source'] ?? 'upload');
    $slug = trim((string) ($options['slug'] ?? ''));
    $version = trim((string) ($options['version'] ?? ''));

    $preflight = birkly_marketplace_preflight_template_pack($zipPath, [
        'project_root' => $projectRoot,
        'overwrite_mode' => $overwriteMode,
        'include_demo_entries' => $includeDemo,
    ]);
    if (empty($preflight['ok']) && ($preflight['code'] ?? '') === 'site_not_fresh') {
        return [
            'ok' => false,
            'errors' => $preflight['errors'] ?? ['Fresh-only refused'],
            'code' => 'site_not_fresh',
            'preflight' => $preflight,
        ];
    }
    if (empty($preflight['ok']) && ($preflight['code'] ?? '') === 'invalid_pack') {
        return [
            'ok' => false,
            'errors' => $preflight['errors'] ?? ['Invalid pack'],
            'code' => 'invalid_pack',
            'preflight' => $preflight,
        ];
    }
    // Layout errors surface as invalid_pack above; other preflight ok=false only fresh-only today.

    $validation = birkly_marketplace_validate_template_pack_zip($zipPath);
    if (!$validation['ok']) {
        return ['ok' => false, 'errors' => $validation['errors'] ?? ['Invalid pack'], 'code' => 'invalid_pack'];
    }
    $manifest = $validation['manifest'] ?? [];
    if ($slug === '') {
        $slug = (string) ($manifest['id'] ?? 'site-pack');
    }
    if ($version === '') {
        $version = (string) ($manifest['version'] ?? '0.0.0');
    }

    $missingRequired = $preflight['missing_required_plugins'] ?? [];
    if ($missingRequired !== [] && !$confirmRequired) {
        return [
            'ok' => false,
            'errors' => ['This site pack requires plugins that are not installed. Confirm to install missing required plugins, then retry.'],
            'code' => 'required_plugins_confirmation_required',
            'preflight' => $preflight,
            'missing_required_plugins' => $missingRequired,
            'warnings' => $preflight['warnings'] ?? [],
        ];
    }

    $pluginsInstalled = [];
    if ($missingRequired !== [] && $confirmRequired && $installMissing) {
        $pluginInstall = birkly_marketplace_install_missing_pack_plugins($missingRequired, $consentAccepted);
        if (!$pluginInstall['ok']) {
            return [
                'ok' => false,
                'errors' => $pluginInstall['errors'] ?? ['Required plugin install failed'],
                'code' => $pluginInstall['code'] ?? 'required_plugin_install_failed',
                'consent' => $pluginInstall['consent'] ?? null,
                'preflight' => $preflight,
                // Cancel semantics: do not write pack when deps fail
            ];
        }
        $pluginsInstalled = $pluginInstall['installed'] ?? [];
    } elseif ($missingRequired !== [] && $confirmRequired && !$installMissing) {
        // Confirmed awareness but skip auto-install — still refuse if still missing
        return [
            'ok' => false,
            'errors' => ['Required plugins are still missing; install them before the site pack.'],
            'code' => 'required_plugins_missing',
            'missing_required_plugins' => $missingRequired,
            'preflight' => $preflight,
        ];
    }

    // Re-check after plugin installs
    $recheck = birkly_marketplace_partition_pack_plugins((array) ($manifest['required_plugins'] ?? []));
    if ($recheck['missing'] !== []) {
        return [
            'ok' => false,
            'errors' => ['Required plugins still missing after confirmation: ' . implode(', ', array_map(
                static fn($r) => (string) ($r['ref'] ?? ''),
                $recheck['missing']
            ))],
            'code' => 'required_plugins_missing',
            'missing_required_plugins' => $recheck['missing'],
            'preflight' => $preflight,
        ];
    }

    $prepare = birkly_marketplace_prepare_template_pack_overwrite(
        $projectRoot,
        $overwriteMode,
        array_values((array) ($manifest['collections'] ?? [])),
        !empty($options['skip_backup'])
    );
    if (!$prepare['ok']) {
        return [
            'ok' => false,
            'errors' => [$prepare['error'] ?? 'Overwrite preparation failed'],
            'code' => $prepare['code'] ?? 'overwrite_failed',
            'preflight' => $preflight,
        ];
    }

    $extract = birkly_ops_extract_starter_zip($zipPath, $projectRoot, [
        'include_demo_entries' => $includeDemo,
        'zip_prefix' => (string) ($validation['prefix'] ?? ''),
    ]);

    if (empty($extract['success'])) {
        $errors = $extract['errors'] ?? [];
        if ($errors === [] && !empty($extract['message'])) {
            $errors[] = (string) $extract['message'];
        }
        if ($errors === []) {
            $errors[] = 'Site pack extract failed';
        }
        return [
            'ok' => false,
            'errors' => $errors,
            'code' => 'extract_failed',
            'backup_path' => $prepare['backup_path'] ?? null,
            'preflight' => $preflight,
        ];
    }

    $installedCollections = array_values($extract['installed_collections'] ?? []);
    $projectSite = !empty($extract['project_site']);

    birkly_marketplace_save_template_pack_source(
        $slug,
        $version,
        $installedCollections,
        $projectSite,
        $projectRoot,
        [
            'source' => $source,
            'overwrite_mode' => $overwriteMode,
            'include_demo_entries' => $includeDemo,
            'pack_id' => (string) ($manifest['id'] ?? $slug),
            'backup_path' => $prepare['backup_path'] ?? null,
            'required_plugins_installed' => $pluginsInstalled,
        ]
    );

    return [
        'ok' => true,
        'source' => $source,
        'offer_type' => 'template_pack',
        'version' => $version,
        'pack_id' => (string) ($manifest['id'] ?? $slug),
        'installed_collections' => $installedCollections,
        'project_site' => $projectSite,
        'include_demo_entries' => $includeDemo,
        'overwrite_mode' => $overwriteMode,
        'backup_path' => $prepare['backup_path'] ?? null,
        'required_plugins_installed' => $pluginsInstalled,
        'warnings' => $preflight['warnings'] ?? [],
        'path' => rtrim($projectRoot, '/\\') . '/' . (function_exists('birkly_public_site_dir') ? birkly_public_site_dir() : 'project'),
        'errors' => [],
        'preflight' => $preflight,
    ];
}
