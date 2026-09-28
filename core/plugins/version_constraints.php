<?php
/**
 * Semver-style version constraints for plugin compatibility (Birkly core + PHP).
 */

function birkly_resolve_cms_version_string(): string
{
    if (function_exists('get_birkly_version')) {
        return get_birkly_version();
    }

    require_once dirname(__DIR__) . '/version.php';

    return BirklyVersion::getCurrentVersion();
}

/**
 * CMS version used when evaluating plugin requires_birkly / requires.
 *
 * Alpha and beta installs may ship a low distribution label (e.g. 0.0.1) while
 * still running the 1.x plugin API. Pre-release channels floor sub-1.0 labels
 * to 1.0.0 for compatibility checks only (display version is unchanged).
 */
function birkly_cms_version_for_plugin_compat(): string
{
    $version = birkly_resolve_cms_version_string();

    if (!function_exists('birkly_ops_release_channel')) {
        $opsPath = dirname(__DIR__) . '/platform_ops.php';
        if (is_file($opsPath)) {
            require_once $opsPath;
        }
    }

    if (function_exists('birkly_ops_release_channel')) {
        $channel = birkly_ops_release_channel();
        if (in_array($channel, ['alpha', 'beta'], true) && version_compare($version, '1.0.0', '<')) {
            return '1.0.0';
        }
    }

    return $version;
}

/**
 * Resolve the Birkly constraint from plugin.json metadata.
 */
function birkly_plugin_birkly_constraint_from_metadata(array $metadata): ?string
{
    $requiresBirkly = trim((string) ($metadata['requires_birkly'] ?? ''));
    if ($requiresBirkly !== '') {
        return $requiresBirkly;
    }

    $legacy = trim((string) ($metadata['requires'] ?? ''));
    if ($legacy === '') {
        return null;
    }

    return birkly_minimum_version_constraint($legacy);
}

/**
 * A bare version in plugin.json ("1.0.0") means "at least this version";
 * explicit operators (^, ~, >=, =, …) are kept as written.
 */
function birkly_minimum_version_constraint(string $constraint): string
{
    $constraint = trim($constraint);
    if ($constraint === '' || $constraint === '*' || preg_match('/^[<>=!~^]/', $constraint) === 1) {
        return $constraint;
    }

    return '>=' . $constraint;
}

/**
 * Whether $current satisfies a Composer-style constraint string.
 */
function birkly_version_satisfies_constraint(string $current, string $constraint): bool
{
    $current = trim($current);
    $constraint = trim($constraint);
    if ($constraint === '' || $constraint === '*') {
        return true;
    }
    if ($current === '') {
        return false;
    }

    foreach (preg_split('/\s*,\s*/', $constraint) ?: [] as $part) {
        $part = trim((string) $part);
        if ($part === '') {
            continue;
        }
        if (!birkly_version_satisfies_single_constraint($current, $part)) {
            return false;
        }
    }

    return true;
}

/**
 * @internal
 */
function birkly_version_satisfies_single_constraint(string $current, string $constraint): bool
{
    if ($constraint === '*' || $constraint === '') {
        return true;
    }

    if ($constraint[0] === '^') {
        $base = ltrim(substr($constraint, 1));
        if ($base === '') {
            return false;
        }
        $parts = explode('.', $base);
        $major = (int) ($parts[0] ?? 0);
        $upper = ($major + 1) . '.0.0';
        return version_compare($current, $base, '>=') && version_compare($current, $upper, '<');
    }

    if ($constraint[0] === '~') {
        $base = ltrim(substr($constraint, 1));
        if ($base === '') {
            return false;
        }
        $parts = explode('.', $base);
        if (count($parts) >= 2) {
            $minor = (int) ($parts[1] ?? 0);
            $major = (int) ($parts[0] ?? 0);
            $upper = $major . '.' . ($minor + 1) . '.0';
        } else {
            $major = (int) ($parts[0] ?? 0);
            $upper = ($major + 1) . '.0.0';
        }
        return version_compare($current, $base, '>=') && version_compare($current, $upper, '<');
    }

    if (str_starts_with($constraint, '>=')) {
        return version_compare($current, ltrim(substr($constraint, 2)), '>=');
    }
    if (str_starts_with($constraint, '<=')) {
        return version_compare($current, ltrim(substr($constraint, 2)), '<=');
    }
    if (str_starts_with($constraint, '!=')) {
        return version_compare($current, ltrim(substr($constraint, 2)), '!=');
    }
    if (str_starts_with($constraint, '<>')) {
        return version_compare($current, ltrim(substr($constraint, 2)), '!=');
    }
    if ($constraint[0] === '>') {
        return version_compare($current, ltrim(substr($constraint, 1)), '>');
    }
    if ($constraint[0] === '<') {
        return version_compare($current, ltrim(substr($constraint, 1)), '<');
    }
    if ($constraint[0] === '=') {
        return version_compare($current, ltrim(substr($constraint, 1)), '==');
    }

    return version_compare($current, $constraint, '==');
}

/**
 * Environment compatibility errors from plugin.json metadata (PHP + Birkly).
 *
 * @return list<string>
 */
function birkly_plugin_compat_errors_from_metadata(array $metadata): array
{
    $errors = [];

    $required_php = $metadata['requires_php'] ?? null;
    if ($required_php && !birkly_version_satisfies_constraint(PHP_VERSION, (string) $required_php)) {
        $errors[] = 'PHP version mismatch. Required: ' . $required_php . ', Found: ' . PHP_VERSION;
    }

    $required_birkly = birkly_plugin_birkly_constraint_from_metadata($metadata);
    if ($required_birkly !== null) {
        $haru_version = birkly_cms_version_for_plugin_compat();
        if (!birkly_version_satisfies_constraint($haru_version, $required_birkly)) {
            $display_version = birkly_resolve_cms_version_string();
            $errors[] = 'Birkly version mismatch. Required: ' . $required_birkly . ', Found: ' . $display_version;
        }
    }

    return $errors;
}
