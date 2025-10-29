<?php
// plugins/community_users/plugin.php
// Bootstrap for Community Users plugin

// Only register functionality if plugin is active
if (!get_plugin_state('community_users')) {
    return;
}

// Register the community_users collection as hidden (not shown in main content UI)
if (function_exists('register_collection')) {
    register_collection('community_users', '/plugins/community_users/collections/community_users', ['hidden' => true]);
}

// Register API endpoint for community user auth
if (function_exists('register_api_endpoint')) {
    register_api_endpoint('community_auth', __DIR__ . '/api/community_auth.php');
}

// Register admin page for community users (web path)
if (function_exists('register_admin_page')) {
    register_admin_page('community_users', '/plugins/community_users/admin/community_users.html');
}

// Register admin CRUD API endpoint for community users
if (function_exists('register_api_endpoint')) {
    register_api_endpoint('community_users_admin', __DIR__ . '/api/community_users_admin.php');
}



// Register dashboard stats callback
$GLOBALS['haru_plugins']['community_users']['dashboard_stats_callback'] = function() {
    error_log('Community Users dashboard stats callback called');
    
    $community_users_path = __DIR__ . '/collections/community_users/entries/';
    $user_count = 0;
    
    if (is_dir($community_users_path)) {
        $files = glob($community_users_path . '*.json');
        $user_count = count($files);
    }
    
    error_log('Community Users stats calculated: ' . $user_count . ' users');
    
    return [
        [
            'title' => 'Community Users',
            'value' => $user_count,
            'icon' => 'users',
            'color' => 'blue'
        ]
    ];
};

// Hook to handle user registration based on settings
register_hook('before_user_create', function($user_data) {
    $enable_registration = get_plugin_setting('community_users', 'enable_registration', true);
    $require_email_verification = get_plugin_setting('community_users', 'require_email_verification', false);
    $auto_approve_users = get_plugin_setting('community_users', 'auto_approve_users', true);
    $default_role = get_plugin_setting('community_users', 'default_user_role', 'user');
    $max_users = get_plugin_setting('community_users', 'max_users', 1000);
    
    // Check if registration is enabled
    if (!$enable_registration) {
        throw new Exception('User registration is disabled');
    }
    
    // Check user limit
    if ($max_users > 0) {
        $community_users_path = __DIR__ . '/collections/community_users/entries/';
        $current_count = 0;
        
        if (is_dir($community_users_path)) {
            $files = glob($community_users_path . '*.json');
            $current_count = count($files);
        }
        
        if ($current_count >= $max_users) {
            throw new Exception('Maximum number of users reached');
        }
    }
    
    // Set default role if not specified
    if (!isset($user_data['role'])) {
        $user_data['role'] = $default_role;
    }
    
    // Handle email verification requirement
    if ($require_email_verification) {
        $user_data['verified'] = false;
        $user_data['verification_token'] = bin2hex(random_bytes(32));
    } else {
        $user_data['verified'] = true;
    }
    
    // Handle auto-approval
    if ($auto_approve_users) {
        $user_data['approved'] = true;
    } else {
        $user_data['approved'] = false;
    }
    
    return $user_data;
});

// TODO: Add hooks for admin UI, user profile editing, etc.
// TODO: Add plugin config and documentation 