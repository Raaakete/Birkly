<?php
/**
 * J-INVITE — Team invite (admin creates invitation → invite.php accept → login).
 */

require_once __DIR__ . '/_helpers.php';

return [
    'id' => 'J-INVITE',
    'name' => 'Team invite accept',
    'server' => 'dev',
    'run' => function (array $ctx): void {
        $base = $ctx['base_url'];
        $root = $ctx['repo_root'];

        $usersDir = $root . '/users';
        $invitationsDir = $root . '/data/invitations';
        $backupUsers = sys_get_temp_dir() . '/birkly-journey-invite-users-' . getmypid();
        $backupInvites = sys_get_temp_dir() . '/birkly-journey-invites-' . getmypid();
        $adminCookie = journey_temp_cookie_file('invite-admin');
        $inviteeCookie = journey_temp_cookie_file('invite-user');

        $adminEmail = 'journey-invite-admin-' . getmypid() . '@birkly.test';
        $inviteeEmail = 'journey-invitee-' . getmypid() . '@birkly.test';
        $password = 'SecurePass123!';
        $inviteePassword = 'InvitePass123!';

        try {
            birkly_test_backup_directory($usersDir, $backupUsers);
            birkly_test_clear_json_files($usersDir);
            if (is_dir($invitationsDir)) {
                birkly_test_backup_directory($invitationsDir, $backupInvites);
                birkly_test_clear_json_files($invitationsDir);
            } else {
                mkdir($invitationsDir, 0755, true);
            }

            journey_create_first_admin($base, $adminCookie, $adminEmail, $password);
            journey_onboarding_skip($base, $adminCookie);
            $csrf = journey_admin_csrf($base, $adminCookie);

            $create = journey_http($base, '/api/invitations.php', [
                'method' => 'POST',
                'headers' => ['Content-Type: application/json'],
                'body' => json_encode([
                    'action' => 'create_invitation',
                    'email' => $inviteeEmail,
                    'role' => 'editor',
                    'expires_days' => 7,
                    '_csrf' => $csrf,
                ]),
                'cookie_file' => $adminCookie,
            ]);
            $createData = birkly_test_json_decode($create['body']);
            journey_assert('create invitation HTTP 200', $create['code'] === 200, $create['body']);
            journey_assert('invitation status ok', ($createData['status'] ?? '') === 'ok', $create['body']);
            $token = (string) ($createData['data']['token'] ?? '');
            journey_assert('invitation token returned', $token !== '');

            $invitePage = journey_http($base, '/invite.php?token=' . rawurlencode($token));
            journey_assert('invite.php HTTP 200', $invitePage['code'] === 200);
            journey_assert('invite accept form present', stripos($invitePage['body'], 'password') !== false);

            $accept = journey_http($base, '/invite.php?token=' . rawurlencode($token), [
                'method' => 'POST',
                'headers' => ['Content-Type: application/x-www-form-urlencoded'],
                'body' => http_build_query([
                    'password' => $inviteePassword,
                    'confirm_password' => $inviteePassword,
                ]),
            ]);
            journey_assert('invite accept HTTP 200', $accept['code'] === 200);
            journey_assert('invite accept success message', stripos($accept['body'], 'success') !== false, substr($accept['body'], 0, 200));

            $userFile = $usersDir . '/' . md5($inviteeEmail) . '.json';
            journey_assert('invited user file exists', is_file($userFile), $userFile);

            journey_admin_login($base, $inviteeCookie, $inviteeEmail, $inviteePassword);
            $dashboard = journey_http($base, '/admin/index.php?page=dashboard', ['cookie_file' => $inviteeCookie]);
            journey_assert('invited user dashboard HTTP 200', $dashboard['code'] === 200);
        } finally {
            birkly_test_restore_directory($usersDir, $backupUsers);
            if (is_dir($backupInvites)) {
                birkly_test_restore_directory($invitationsDir, $backupInvites);
                birkly_test_remove_directory($backupInvites);
            }
            birkly_test_remove_directory($backupUsers);
            @unlink($adminCookie);
            @unlink($inviteeCookie);
        }
    },
];
