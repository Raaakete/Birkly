<?php
/**
 * URL redirect rules for public site delivery (P66-M3).
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';

function birkly_redirects_settings_path(): string
{
    return birkly_project_root() . '/settings/redirects.json';
}

function birkly_load_redirects(): array
{
    $file = birkly_redirects_settings_path();
    if (!is_readable($file)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($file), true);
    return is_array($decoded) ? array_values($decoded) : [];
}

function birkly_normalize_redirect_path(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }

    return preg_replace('#/+#', '/', $path) ?: $path;
}

/**
 * @return array{matched: bool, to_path: string, type: int}|null
 */
function birkly_hosting_match_redirect(string $uri): ?array
{
    $path = birkly_normalize_redirect_path($uri);
    if ($path === '') {
        return null;
    }

    foreach (birkly_load_redirects() as $rule) {
        if (empty($rule['enabled'])) {
            continue;
        }
        $from = birkly_normalize_redirect_path((string) ($rule['from_path'] ?? ''));
        if ($from === '' || $from !== $path) {
            continue;
        }
        $to = trim((string) ($rule['to_path'] ?? ''));
        if ($to === '') {
            continue;
        }
        $type = (int) ($rule['type'] ?? 301);
        if (!in_array($type, [301, 302], true)) {
            $type = 301;
        }

        return [
            'matched' => true,
            'to_path' => $to,
            'type' => $type,
        ];
    }

    return null;
}

/**
 * Apply redirect rule when matched. Returns true when handled.
 */
function birkly_hosting_apply_redirect(string $uri): bool
{
    $match = birkly_hosting_match_redirect($uri);
    if ($match === null) {
        return false;
    }

    $target = (string) $match['to_path'];
    if (!preg_match('#^https?://#i', $target)) {
        require_once __DIR__ . '/hosting_urls.php';
        $role = birkly_request_host_role();
        if ($role === 'public' || $role === 'local') {
            $base = birkly_hosting_public_base_url();
            if ($base !== '') {
                $target = rtrim($base, '/') . ($target[0] === '/' ? $target : '/' . $target);
            }
        } elseif ($target[0] === '/') {
            $target = rtrim(birkly_cms_base_url(), '/') . birkly_url($target);
        }
    }

    if (!headers_sent()) {
        header('Location: ' . $target, true, (int) $match['type']);
    }
    exit;
}
