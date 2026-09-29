<?php
// invite.php - Public invitation acceptance page

require_once __DIR__ . '/core/birkly_paths.php';
require_once __DIR__ . '/api/storage.php';
require_once __DIR__ . '/api/log.php';
require_once __DIR__ . '/core/csp.php';

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
    birkly_set_admin_csp_headers();
}

$token = $_GET['token'] ?? '';
$error = '';
$success = '';
$invitation = null;
$showExpiredHelp = false;

// Validate token
if (empty($token)) {
    $error = 'Invalid invitation link';
    $showExpiredHelp = true;
} else {
    $invitationsStorage = new Storage(__DIR__ . '/data/invitations');
    $invitation = $invitationsStorage->read($token . '.json');

    if (!$invitation) {
        $error = 'Invitation not found or has expired';
        $showExpiredHelp = true;
    } elseif ($invitation['status'] !== 'pending') {
        $error = 'This invitation is no longer valid';
        $showExpiredHelp = true;
    } elseif (strtotime($invitation['expires_at']) < time()) {
        $error = 'This invitation has expired';
        $showExpiredHelp = true;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $invitation && !$error) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($password) || empty($confirmPassword)) {
        $error = 'Please fill in all fields';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } else {
        $usersStorage = new Storage(__DIR__ . '/users');
        $userFile = md5($invitation['email']) . '.json';

        if ($usersStorage->read($userFile)) {
            $error = 'User account already exists';
        } else {
            $userData = [
                'id' => md5($invitation['email']),
                'email' => $invitation['email'],
                'role' => $invitation['role'],
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'created_at' => date('c'),
                'created_by' => $invitation['created_by'],
                'invitation_accepted_at' => date('c'),
                'invitation_token' => $token,
            ];

            $result = $usersStorage->write($userFile, $userData);

            if ($result) {
                $invitation['status'] = 'accepted';
                $invitation['accepted_at'] = date('c');
                $invitationsStorage->write($token . '.json', $invitation);

                log_security_event('invitation_accepted', [
                    'email' => $invitation['email'],
                    'role' => $invitation['role'],
                ]);

                $GLOBALS['__HARU_INTERNAL_CALL'] = true;
                require_once __DIR__ . '/api/onboarding.php';
                birkly_init_invited_user_onboarding($invitation['email']);

                $success = 'Account created successfully. You can now sign in to the admin panel.';
                $invitation = null;
            } else {
                $error = 'Failed to create account. Please try again.';
            }
        }
    }
}

if ($invitation && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $invitation['attempts'] = ($invitation['attempts'] ?? 0) + 1;
    $invitationsStorage->write($token . '.json', $invitation);
}

$storage = new Storage(__DIR__ . '/settings');
$adminSettings = $storage->read('admin.json') ?? [];
$brandingSettings = $storage->read('branding.json') ?? [];
$projectName = $adminSettings['project_name'] ?? 'Birkly';
$logoPath = $brandingSettings['logoPath'] ?? '';
$defaultLogo = birkly_url('/admin/assets/birkly-icon.svg');
$logoUrl = !empty($logoPath) ? $logoPath : $defaultLogo;
$loginUrl = birkly_url('/admin/login.php');
$__birklyInviteNonceAttr = birkly_csp_nonce_attr();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accept invitation - <?= htmlspecialchars($projectName) ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(birkly_url('/admin/css/style.css')) ?>">
    <link rel="shortcut icon" href="<?= htmlspecialchars($logoUrl) ?>">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="card auth-card">
            <div class="card-body u-stack-6">
                <div class="u-center-col u-stack-2">
                    <img class="auth-logo" src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars($projectName) ?> logo">
                    <h1 class="auth-title">Accept invitation</h1>
                    <p class="auth-subtitle">Create your password to join <?= htmlspecialchars($projectName) ?></p>
                </div>

                <?php if ($error): ?>
                    <div class="callout callout-danger" role="alert">
                        <div class="callout-body u-stack-2">
                            <p><?= htmlspecialchars($error) ?></p>
                            <?php if ($showExpiredHelp): ?>
                                <p class="u-text-sm u-fg-secondary u-m-0">This invitation link is no longer valid. Contact your administrator for a new invitation.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="callout callout-success" role="status">
                        <div class="callout-body"><p><?= htmlspecialchars($success) ?></p></div>
                    </div>

                    <div class="auth-footer u-stack-3">
                        <a class="button is-main btn-full" href="<?= htmlspecialchars($loginUrl) ?>">Go to sign in</a>
                    </div>

                <?php elseif ($invitation): ?>
                    <div class="callout callout-info auth-invite-meta" role="status">
                        <div class="callout-body u-stack-2">
                            <p class="callout-title">You're invited</p>
                            <dl class="auth-invite-details">
                                <div class="auth-invite-detail">
                                    <dt>Email</dt>
                                    <dd><?= htmlspecialchars($invitation['email']) ?></dd>
                                </div>
                                <div class="auth-invite-detail">
                                    <dt>Role</dt>
                                    <dd><?= htmlspecialchars(ucfirst($invitation['role'])) ?></dd>
                                </div>
                                <div class="auth-invite-detail">
                                    <dt>Expires</dt>
                                    <dd><?= htmlspecialchars(date('M j, Y g:i A', strtotime($invitation['expires_at']))) ?></dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <form class="auth-form u-stack-4" method="POST">
                        <div class="field">
                            <label class="field-label" for="password">Create password</label>
                            <input
                                class="input"
                                type="password"
                                id="password"
                                name="password"
                                required
                                minlength="6"
                                autocomplete="new-password"
                                placeholder="Enter a secure password"
                            >
                        </div>

                        <div class="field">
                            <label class="field-label" for="confirm_password">Confirm password</label>
                            <input
                                class="input"
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                required
                                minlength="6"
                                autocomplete="new-password"
                                placeholder="Confirm your password"
                            >
                        </div>

                        <button type="submit" class="button is-main btn-full u-mt-2">
                            Create account
                        </button>
                    </form>

                    <div class="auth-footer">
                        <p class="u-text-sm u-fg-secondary u-m-0">Already have an account? <a href="<?= htmlspecialchars($loginUrl) ?>">Sign in</a></p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <script<?= $__birklyInviteNonceAttr ?>>
        document.addEventListener('DOMContentLoaded', function () {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');

            if (!password || !confirmPassword) {
                return;
            }

            function validatePasswords() {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }

            password.addEventListener('input', validatePasswords);
            confirmPassword.addEventListener('input', validatePasswords);
        });
    </script>
</body>
</html>
