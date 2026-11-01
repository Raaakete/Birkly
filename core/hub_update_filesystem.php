<?php
/**
 * Hub CMS update — filesystem checks and Docker vs zip hosting guidance.
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';

/**
 * True when this instance is the official Docker image layout (Dokploy, etc.).
 */
function birkly_is_docker_deployment(?string $projectRoot = null): bool
{
    $root = rtrim($projectRoot ?? birkly_project_root(), '/\\');

    if (getenv('BIRKLY_DATA') !== false && trim((string) getenv('BIRKLY_DATA')) !== '') {
        return true;
    }

    if (is_file($root . '/BUILD_REVISION')) {
        return true;
    }

    return is_file($root . '/.birkly-docker');
}

/**
 * Program directories Hub must write during in-app install.
 *
 * @return list<string>
 */
function birkly_hub_update_program_dir_names(): array
{
    return ['core', 'admin', 'api', 'fieldtypes'];
}

/**
 * @return array<string, array{exists: bool, writable: bool}>
 */
function birkly_hub_update_program_dir_status(?string $projectRoot = null): array
{
    $root = rtrim($projectRoot ?? birkly_project_root(), '/\\');
    $out = [];

    foreach (birkly_hub_update_program_dir_names() as $dir) {
        $path = $root . '/' . $dir;
        $out[$dir] = [
            'exists' => is_dir($path),
            'writable' => is_dir($path) && birkly_path_is_writable($path),
        ];
    }

    return $out;
}

function birkly_hub_update_program_dirs_writable(?string $projectRoot = null): bool
{
    foreach (birkly_hub_update_program_dir_status($projectRoot) as $row) {
        if (!empty($row['exists']) && empty($row['writable'])) {
            return false;
        }
    }

    return true;
}

/**
 * Preferred update path for this install.
 *
 * @return 'in_app'|'docker_redeploy'
 */
function birkly_hub_update_install_mode(?string $projectRoot = null): string
{
    if (birkly_is_docker_deployment($projectRoot) && !birkly_hub_update_program_dirs_writable($projectRoot)) {
        return 'docker_redeploy';
    }

    return 'in_app';
}

function birkly_hub_update_docker_redeploy_message(): string
{
    return 'This Birkly site runs in Docker. Core files are updated when you redeploy the container from git '
        . '(for example Dokploy autodeploy on Raaakete/Birkly main), not with Hub Install. '
        . 'Redeploy the CMS app, wait for the build to finish, then reload the admin. '
        . 'Your content and media on the data volume are not replaced by a redeploy. '
        . 'Hub Install remains for traditional PHP/zip hosting where the web server owns the whole CMS folder.';
}

function birkly_hub_update_host_permission_message(string $dir): string
{
    return 'Cannot update Birkly: the server cannot write to ' . $dir . '/. '
        . 'Hub updates need PHP to update program folders (core/, admin/, api/), not only content/ and media/. '
        . 'Ask your hosting provider to allow the web server user to replace files under those folders.';
}

/**
 * @return null|string User-facing error, or null when in-app install may proceed.
 */
function birkly_hub_update_preflight_error(?string $projectRoot = null): ?string
{
    $root = rtrim($projectRoot ?? birkly_project_root(), '/\\');

    if (birkly_hub_update_program_dirs_writable($root)) {
        require_once __DIR__ . '/update_staging.php';
        $stagingRoot = birkly_update_offload_staging_root($root);
        if (!is_dir($stagingRoot) && !@mkdir($stagingRoot, 0755, true)) {
            return 'Cannot update Birkly: the server cannot write to data/cms_update_staging/. '
                . 'Ask your hosting provider to allow PHP to write under data/.';
        }

        return null;
    }

    if (birkly_is_docker_deployment($root)) {
        return birkly_hub_update_docker_redeploy_message();
    }

    foreach (birkly_hub_update_program_dir_status($root) as $dir => $row) {
        if (!empty($row['exists']) && empty($row['writable'])) {
            return birkly_hub_update_host_permission_message($dir);
        }
    }

    return birkly_hub_update_host_permission_message('core');
}
