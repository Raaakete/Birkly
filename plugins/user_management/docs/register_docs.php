<?php
if (!function_exists('register_plugin_docs') || (function_exists('get_plugin_docs') && get_plugin_docs('user_management') !== null)) {
    return;
}
if (function_exists('birkly_is_plugin_active') && !birkly_is_plugin_active('user_management')) {
    return;
}
register_plugin_docs('user_management', [
    'title' => 'User Management',
    'summary' => 'Site users: registration, auth, tiers, groups, profile collections, User/Password fieldtypes, collection permissions, template functions (user_current, user_profile, user_can).',
    'badge' => 'Extension',
    'docs' => [
        'ai' => __DIR__ . '/ai-context.md',
        'help' => __DIR__ . '/help-overview.md',
        'help_topics' => [
            ['id' => 'user-fieldtypes', 'title' => 'User fieldtypes', 'file' => __DIR__ . '/help-fieldtypes.md', 'keywords' => ['fieldtype', 'user', 'password']],
            ['id' => 'user-permissions', 'title' => 'Permissions & access', 'file' => __DIR__ . '/help-permissions.md', 'keywords' => ['permission', 'tier', 'registration']],
        ],
        'public_path' => '06-extensibility/user-plugin.html',
    ],
    'mcp_topic_slug' => 'user_management',
    'keywords' => ['user', 'registration', 'login', 'tier', 'profile', 'permissions'],
]);
