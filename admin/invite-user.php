<?php
// admin/invite-user.php - User invitation system for admin panel
session_start();

// Include authentication middleware
require_once __DIR__ . '/auth_middleware.php';

// Require admin permission
requireAdminPermission('create_user');

// Include CSRF helper
require_once __DIR__ . '/../core/csrf.php';

$error = '';
$success = '';

// Handle invitation form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'viewer';
    $csrf = $_POST['_csrf'] ?? '';
    
    // Validate CSRF token
    if (!validate_csrf_token($csrf)) {
        $error = 'Invalid security token. Please try again.';
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!in_array($role, ['viewer', 'editor', 'admin'])) {
        $error = 'Invalid role selected.';
    } else {
        // Check if user already exists
        $userFile = md5($email) . '.json';
        $usersDir = __DIR__ . '/../users';
        
        if (file_exists($usersDir . '/' . $userFile)) {
            $error = 'A user with this email already exists.';
        } else {
            // Create invitation
            $invitationId = uniqid('inv_', true);
            $invitation = [
                'id' => $invitationId,
                'email' => $email,
                'role' => $role,
                'invited_by' => $_SESSION['user']['id'],
                'invited_at' => date('c'),
                'expires_at' => date('c', strtotime('+7 days')),
                'status' => 'pending'
            ];
            
            // Save invitation
            $invitationsDir = __DIR__ . '/../settings/invitations';
            if (!file_exists($invitationsDir)) {
                mkdir($invitationsDir, 0755, true);
            }
            
            if (file_put_contents($invitationsDir . '/' . $invitationId . '.json', json_encode($invitation, JSON_PRETTY_PRINT))) {
                $success = "Invitation sent to {$email}. They have 7 days to accept.";
                
                // Log the invitation
                require_once __DIR__ . '/../api/log.php';
                log_security_event('user_invited', ['email' => $email, 'role' => $role]);
            } else {
                $error = 'Failed to create invitation. Please try again.';
            }
        }
    }
}

// Load existing invitations
$invitationsDir = __DIR__ . '/../settings/invitations';
$invitations = [];
if (file_exists($invitationsDir)) {
    $files = glob($invitationsDir . '/*.json');
    foreach ($files as $file) {
        $invitation = json_decode(file_get_contents($file), true);
        if ($invitation) {
            $invitations[] = $invitation;
        }
    }
}

// Sort by creation date (newest first)
usort($invitations, function($a, $b) {
    return strtotime($b['invited_at']) - strtotime($a['invited_at']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo get_csrf_token(); ?>">
    <title>Invite Users - HaruCMS Admin</title>
    <link rel="stylesheet" href="/admin/css/style.css">
    <link rel="shortcut icon" href="assets/haru_favicon.svg">
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/partials/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="invite-users-page">
                <div class="page-header">
                    <h1>Invite Users</h1>
                    <div class="breadcrumbs">
                        <a href="/admin/index.php?page=settings">Settings</a>
                        <span class="separator">›</span>
                        <a href="/admin/index.php?page=users">Users</a>
                        <span class="separator">›</span>
                        <span class="current">Invite Users</span>
                    </div>
                </div>
                
                <?php if ($error): ?>
                    <div class="error-message">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="success-message">
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>
                
                <div class="invite-form-card">
                    <h2>Send Invitation</h2>
                    <form method="POST" class="invite-form">
                        <input type="hidden" name="_csrf" value="<?= get_csrf_token() ?>">
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" required class="input" 
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                   placeholder="user@example.com">
                        </div>
                        
                        <div class="form-group">
                            <label for="role">Role</label>
                            <select id="role" name="role" class="input">
                                <option value="viewer" <?= ($_POST['role'] ?? '') === 'viewer' ? 'selected' : '' ?>>Viewer</option>
                                <option value="editor" <?= ($_POST['role'] ?? '') === 'editor' ? 'selected' : '' ?>>Editor</option>
                                <option value="admin" <?= ($_POST['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                <polyline points="22,6 12,13 2,6"></polyline>
                            </svg>
                            Send Invitation
                        </button>
                    </form>
                </div>
                
                <div class="invitations-list-card">
                    <h2>Pending Invitations</h2>
                    
                    <?php if (empty($invitations)): ?>
                        <div class="empty-state">
                            <p>No pending invitations.</p>
                        </div>
                    <?php else: ?>
                        <div class="invitations-table">
                            <div class="table-header">
                                <div class="table-row">
                                    <div class="table-cell">Email</div>
                                    <div class="table-cell">Role</div>
                                    <div class="table-cell">Invited</div>
                                    <div class="table-cell">Expires</div>
                                    <div class="table-cell">Status</div>
                                    <div class="table-cell">Actions</div>
                                </div>
                            </div>
                            <div class="table-body">
                                <?php foreach ($invitations as $invitation): ?>
                                    <?php
                                    $isExpired = strtotime($invitation['expires_at']) < time();
                                    $statusClass = $isExpired ? 'expired' : 'pending';
                                    $statusText = $isExpired ? 'Expired' : 'Pending';
                                    ?>
                                    <div class="table-row">
                                        <div class="table-cell"><?= htmlspecialchars($invitation['email']) ?></div>
                                        <div class="table-cell">
                                            <span class="role-badge <?= $invitation['role'] ?>"><?= $invitation['role'] ?></span>
                                        </div>
                                        <div class="table-cell"><?= date('M j, Y', strtotime($invitation['invited_at'])) ?></div>
                                        <div class="table-cell"><?= date('M j, Y', strtotime($invitation['expires_at'])) ?></div>
                                        <div class="table-cell">
                                            <span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span>
                                        </div>
                                        <div class="table-cell">
                                            <div class="user-actions">
                                                <button class="btn-icon" onclick="resendInvitation('<?= $invitation['id'] ?>')" title="Resend Invitation">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <polyline points="23,4 23,10 17,10"></polyline>
                                                        <polyline points="1,20 1,14 7,14"></polyline>
                                                        <path d="M20.49,9A9,9,0,0,0,5.64,5.64L1,10m22,4L18.36,18.36A9,9,0,0,1,3.51,15"></path>
                                                    </svg>
                                                </button>
                                                <button class="btn-icon danger" onclick="cancelInvitation('<?= $invitation['id'] ?>')" title="Cancel Invitation">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <line x1="18" y1="6" x2="6" y2="18"></line>
                                                        <line x1="6" y1="6" x2="18" y2="18"></line>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <style>
        .invite-users-page {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .invite-form-card, .invitations-list-card {
            background: var(--bg-elevated);
            border-radius: var(--radius-lg);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .invite-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            max-width: 400px;
        }
        
        .invitations-table {
            background: var(--bg-primary);
            border-radius: var(--radius-md);
            overflow: hidden;
        }
        
        .table-header {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
        }
        
        .table-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr;
            gap: 1rem;
            padding: 1rem 1.5rem;
            align-items: center;
        }
        
        .table-cell {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
        }
        
        .table-body .table-row {
            border-bottom: 1px solid var(--border-light);
            transition: background 0.2s;
        }
        
        .table-body .table-row:hover {
            background: var(--bg-hover);
        }
        
        .table-body .table-row:last-child {
            border-bottom: none;
        }
        
        .table-body .table-cell {
            font-weight: normal;
            color: var(--text-secondary);
        }
        
        .status-badge {
            display: inline-flex;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-sm);
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: capitalize;
        }
        
        .status-badge.pending {
            background: var(--primary-100);
            color: var(--primary-700);
        }
        
        .status-badge.expired {
            background: var(--error-100);
            color: var(--error-700);
        }
        
        .user-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-icon {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: var(--radius-sm);
            transition: all 0.2s;
        }
        
        .btn-icon:hover {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }
        
        .btn-icon.danger:hover {
            background: var(--error-100);
            color: var(--error-700);
        }
    </style>
    
    <script>
        function resendInvitation(invitationId) {
            if (confirm('Resend this invitation?')) {
                // TODO: Implement resend functionality
                alert('Resend functionality will be implemented');
            }
        }
        
        function cancelInvitation(invitationId) {
            if (confirm('Cancel this invitation? This action cannot be undone.')) {
                // TODO: Implement cancel functionality
                alert('Cancel functionality will be implemented');
            }
        }
    </script>
</body>
</html>
