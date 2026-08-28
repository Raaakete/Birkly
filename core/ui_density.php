<?php
/**
 * Admin UI density preferences (P69).
 */

declare(strict_types=1);

/** @return list<string> */
function birkly_ui_density_options(): array
{
    return ['compact', 'default', 'large'];
}

function birkly_ui_density_normalize(?string $density): string
{
    $density = strtolower(trim((string) $density));
    return in_array($density, birkly_ui_density_options(), true) ? $density : 'default';
}

/**
 * @param array<string, mixed> $user
 */
function birkly_user_ui_density(array $user): string
{
    $prefs = $user['ui_preferences'] ?? [];
    if (!is_array($prefs)) {
        return 'default';
    }
    return birkly_ui_density_normalize($prefs['density'] ?? 'default');
}
