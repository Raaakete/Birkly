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
 * Redirect rules that send /project visitors to a CMS alias host break same-host delivery.
 *
 * @return list<array<string, mixed>>
 */
function birkly_hosting_harmful_project_redirects(): array
{
    require_once __DIR__ . '/hosting_urls.php';
    $config = birkly_hosting_load();
    $cmsAliases = birkly_hosting_cms_alias_hosts($config);
    if ($cmsAliases === []) {
        return [];
    }

    $bad = [];
    foreach (birkly_load_redirects() as $rule) {
        if (empty($rule['enabled'])) {
            continue;
        }
        $from = birkly_normalize_redirect_path((string) ($rule['from_path'] ?? ''));
        if ($from !== '/project' && $from !== '/project/') {
            continue;
        }
        $to = trim((string) ($rule['to_path'] ?? ''));
        if (!preg_match('#^https?://#i', $to)) {
            continue;
        }
        $host = birkly_normalize_host((string) parse_url($to, PHP_URL_HOST));
        if ($host !== '' && in_array($host, $cmsAliases, true)) {
            $bad[] = $rule;
        }
    }

    return $bad;
}

/**
 * Disable redirects that send /project to a CMS-only alias (e.g. www → admin host).
 *
 * @return array{changed: bool, rules: list<string>}
 */
function birkly_hosting_repair_harmful_redirects(bool $persist = true): array
{
    $harmful = birkly_hosting_harmful_project_redirects();
    if ($harmful === []) {
        return ['changed' => false, 'rules' => []];
    }

    $disabled = [];
    $redirects = birkly_load_redirects();
    foreach ($redirects as $index => $rule) {
        foreach ($harmful as $badRule) {
            if (($rule['id'] ?? null) !== ($badRule['id'] ?? null)) {
                continue;
            }
            if ($persist) {
                $redirects[$index]['enabled'] = false;
            }
            $disabled[] = birkly_normalize_redirect_path((string) ($rule['from_path'] ?? ''))
                . ' → ' . trim((string) ($rule['to_path'] ?? ''));
            break;
        }
    }

    if ($persist && $disabled !== []) {
        $path = birkly_redirects_settings_path();
        file_put_contents(
            $path,
            json_encode(array_values($redirects), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    return ['changed' => $disabled !== [], 'rules' => $disabled];
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
