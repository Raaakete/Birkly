<?php
// api/invitations.php - Handle user invitations

// Start output buffering to prevent any output before JSON
ob_start();

require_once __DIR__ . '/session.php';
haru_secure_session_start();

require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/../core/mail/birkly_send_mail.php';
require_once __DIR__ . '/../core/connections/registry.php';
require_once __DIR__ . '/../core/birkly_paths.php';

// Clear any output that might have been generated
ob_clean();

header('Content-Type: application/json');

// Ensure we're authenticated for invitation management
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit;
}

// Check if user has permission to invite users (admin only for now)
if ($_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Only administrators can invite users']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // For testing, check if we have mock data
    if (isset($GLOBALS['mock_input_data'])) {
        $rawInput = $GLOBALS['mock_input_data'];
    } else {
        $rawInput = file_get_contents('php://input');
    }
    $input = json_decode($rawInput, true);
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'create_invitation':
            createInvitation($input);
            break;
        case 'resend_invitation':
            resendInvitation($input);
            break;
        case 'cancel_invitation':
            cancelInvitation($input);
            break;
        case 'mail_status':
            echo json_encode([
                'status' => 'ok',
                'data' => birkly_invitation_mail_status(),
            ]);
            break;
        case 'list_invitations':
            echo json_encode([
                'status' => 'ok',
                'data' => [
                    'invitations' => birkly_invitations_for_admin(),
                ],
            ]);
            break;
        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid action: ' . $action]);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}

function createInvitation($data) {
    $email = trim($data['email'] ?? '');
    $role = $data['role'] ?? 'viewer';
    $expiresDays = $data['expires_days'] ?? 7;
    $sendEmail = !array_key_exists('send_email', $data) || !empty($data['send_email']);
    
    // Validate input
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Valid email address required']);
        return;
    }
    
    // Check if user already exists
    $usersStorage = new Storage(__DIR__ . '/../users');
    $userFile = md5($email) . '.json';
    if ($usersStorage->read($userFile)) {
        echo json_encode(['status' => 'error', 'message' => 'User already exists']);
        return;
    }
    
    // Generate invitation token
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('c', strtotime("+$expiresDays days"));
    
    // Create invitation data
    $invitation = [
        'email' => $email,
        'role' => $role,
        'token' => $token,
        'created_by' => $_SESSION['user']['id'],
        'created_at' => date('c'),
        'expires_at' => $expiresAt,
        'status' => 'pending',
        'attempts' => 0
    ];
    
    // Store invitation
    $invitationsStorage = new Storage(__DIR__ . '/../data/invitations');
    $result = $invitationsStorage->write($token . '.json', $invitation);
    
    if (!$result) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create invitation']);
        return;
    }
    
    $inviteUrl = getInviteUrl($token);
    $responseData = [
        'token' => $token,
        'expires_at' => $expiresAt,
        'invite_url' => $inviteUrl,
    ];

    if (!$sendEmail) {
        birkly_invitation_update($token, [
            'delivery' => 'link_only',
            'email_sent' => false,
        ]);

        log_security_event('invitation_created', [
            'invited_email' => $email,
            'role' => $role,
            'created_by' => $_SESSION['user']['email'],
            'delivery' => 'link_only',
        ]);

        echo json_encode([
            'status' => 'ok',
            'message' => 'Invitation link created. Copy the link and share it with the user.',
            'data' => array_merge($responseData, [
                'delivery' => 'link_only',
            ]),
        ]);
        return;
    }

    $mailStatus = birkly_invitation_mail_status();
    if (!$mailStatus['has_connection'] && !birkly_invitation_is_local_dev()) {
        echo json_encode([
            'status' => 'error',
            'message' => 'No email connection is configured. Set one up under Settings → Connections, or create an invite link instead.',
            'data' => array_merge($responseData, [
                'invitation_saved' => true,
                'requires_email_connection' => true,
            ]),
        ]);
        return;
    }

    $emailResult = sendInvitationEmail($email, $inviteUrl, $role, $expiresDays);
    
    if ($emailResult['ok']) {
        birkly_invitation_update($token, [
            'delivery' => 'email',
            'email_sent' => true,
            'email_error' => null,
            'email_sent_at' => date('c'),
        ]);

        log_security_event('invitation_created', [
            'invited_email' => $email,
            'role' => $role,
            'created_by' => $_SESSION['user']['email'],
            'delivery' => 'email',
        ]);
        
        $response = [
            'status' => 'ok',
            'message' => 'Invitation sent successfully',
            'data' => array_merge($responseData, [
                'delivery' => 'email',
            ]),
        ];
        
        if (birkly_invitation_is_local_dev()) {
            $response['message'] = 'Invitation created! (Localhost: email logged, not delivered.)';
            $response['data']['localhost_note'] = 'Share this link to test: ' . $inviteUrl;
        }
        
        echo json_encode($response);
        return;
    }

    birkly_invitation_update($token, [
        'delivery' => 'email_failed',
        'email_sent' => false,
        'email_error' => $emailResult['send_error'] ?? $emailResult['message'],
        'email_attempt_at' => date('c'),
    ]);

    echo json_encode([
        'status' => 'error',
        'message' => $emailResult['message'],
        'data' => array_merge($responseData, [
            'invitation_saved' => true,
            'send_status' => $emailResult['send_status'] ?? null,
            'send_error' => $emailResult['send_error'] ?? null,
        ]),
    ]);
}

function resendInvitation($data) {
    $token = $data['token'] ?? '';
    
    if (empty($token)) {
        echo json_encode(['status' => 'error', 'message' => 'Invitation token required']);
        return;
    }
    
    $invitationsStorage = new Storage(__DIR__ . '/../data/invitations');
    $invitation = $invitationsStorage->read($token . '.json');
    
    if (!$invitation) {
        echo json_encode(['status' => 'error', 'message' => 'Invitation not found']);
        return;
    }
    
    if ($invitation['status'] !== 'pending') {
        echo json_encode(['status' => 'error', 'message' => 'Invitation is no longer valid']);
        return;
    }
    
    // Check if expired
    if (strtotime($invitation['expires_at']) < time()) {
        echo json_encode(['status' => 'error', 'message' => 'Invitation has expired']);
        return;
    }
    
    // Resend email
    $inviteUrl = getInviteUrl($token);
    $expiresDays = ceil((strtotime($invitation['expires_at']) - time()) / 86400);
    $emailResult = sendInvitationEmail($invitation['email'], $inviteUrl, $invitation['role'], $expiresDays);
    
    if ($emailResult['ok']) {
        birkly_invitation_update($token, [
            'delivery' => 'email',
            'email_sent' => true,
            'email_error' => null,
            'email_sent_at' => date('c'),
        ]);

        log_security_event('invitation_resent', [
            'invited_email' => $invitation['email'],
            'created_by' => $_SESSION['user']['email']
        ]);
        
        echo json_encode(['status' => 'ok', 'message' => 'Invitation resent successfully']);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => $emailResult['message'],
            'data' => [
                'invite_url' => $inviteUrl,
            ],
        ]);
    }
}

function cancelInvitation($data) {
    $token = $data['token'] ?? '';
    
    if (empty($token)) {
        echo json_encode(['status' => 'error', 'message' => 'Invitation token required']);
        return;
    }
    
    $invitationsStorage = new Storage(__DIR__ . '/../data/invitations');
    $invitation = $invitationsStorage->read($token . '.json');
    
    if (!$invitation) {
        echo json_encode(['status' => 'error', 'message' => 'Invitation not found']);
        return;
    }
    
    // Mark as cancelled
    $invitation['status'] = 'cancelled';
    $invitation['cancelled_at'] = date('c');
    $invitation['cancelled_by'] = $_SESSION['user']['id'];
    
    $result = $invitationsStorage->write($token . '.json', $invitation);
    
    if ($result) {
        log_security_event('invitation_cancelled', [
            'invited_email' => $invitation['email'],
            'cancelled_by' => $_SESSION['user']['email']
        ]);
        
        echo json_encode(['status' => 'ok', 'message' => 'Invitation cancelled successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to cancel invitation']);
    }
}

function getInviteUrl($token) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    return "$protocol://$host/invite.php?token=$token";
}

function birkly_invitation_is_local_dev(): bool
{
    return function_exists('birkly_is_local_dev_request') && birkly_is_local_dev_request();
}

function birkly_invitation_mail_status(): array
{
    $connection = birkly_connection_get_default('email', false);
    if ($connection === null) {
        return [
            'has_connection' => false,
            'can_send_email' => birkly_invitation_is_local_dev(),
            'provider' => null,
            'connection_name' => null,
            'from_email' => null,
            'settings_url' => birkly_admin_url('settings', ['tab' => 'connections', 'section' => 'email']),
        ];
    }

    return [
        'has_connection' => true,
        'can_send_email' => true,
        'provider' => $connection['provider'] ?? null,
        'connection_name' => $connection['name'] ?? ($connection['alias'] ?? null),
        'from_email' => $connection['from_email'] ?? null,
        'settings_url' => birkly_admin_url('settings', ['tab' => 'connections', 'section' => 'email']),
    ];
}

function sendInvitationEmail($email, $inviteUrl, $role, $expiresDays) {
    $siteName = 'Birkly'; // Could be loaded from settings
    
    $subject = "Invitation to join $siteName";
    
    $message = "
    <html>
    <head>
        <title>Invitation to join $siteName</title>
    </head>
    <body>
        <h2>You're invited to join $siteName</h2>
        <p>You have been invited to join our content management system.</p>
        <p><strong>Role:</strong> " . ucfirst($role) . "</p>
        <p><strong>Expires in:</strong> $expiresDays days</p>
        <p>Click the link below to accept the invitation and set your password:</p>
        <p><a href=\"$inviteUrl\" style=\"background: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;\">Accept Invitation</a></p>
        <p>Or copy this link: <a href=\"$inviteUrl\">$inviteUrl</a></p>
        <p>If you didn't expect this invitation, you can safely ignore this email.</p>
        <hr>
        <p><small>This invitation will expire in $expiresDays days.</small></p>
    </body>
    </html>
    ";
    
    $inviterEmail = trim((string) ($_SESSION['user']['email'] ?? ''));
    $inviterName = trim((string) ($_SESSION['user']['name'] ?? ''));

    // Use the configured email connection From address — not the inviter's mailbox.
    // Many SMTP/ESP providers reject messages when From does not match the verified sender.
    $sendResult = birkly_send_mail([
        'to' => $email,
        'subject' => $subject,
        'html' => $message,
        'from_name' => $inviterName !== '' ? ($inviterName . ' via Birkly') : birkly_mail_default_from_name(),
        'reply_to' => $inviterEmail !== '' ? $inviterEmail : null,
    ]);

    $ok = in_array($sendResult['status'] ?? '', ['queued', 'accepted', 'logged'], true);
    $error = trim((string) ($sendResult['error'] ?? ''));

    // Log the attempt
    log_security_event('invitation_email_attempt', [
        'email' => $email,
        'success' => $ok,
        'send_status' => $sendResult['status'] ?? 'unknown',
        'send_error' => $error !== '' ? $error : null,
        'sent_by' => $inviterEmail !== '' ? $inviterEmail : 'unknown',
        'invite_url' => $inviteUrl
    ]);

    if (birkly_invitation_is_local_dev()) {
        error_log("LOCALHOST: Invitation email logged for $email with URL: $inviteUrl");
        return [
            'ok' => true,
            'message' => 'Invitation email logged on localhost.',
            'send_status' => $sendResult['status'] ?? 'logged',
            'send_error' => null,
        ];
    }

    if ($ok) {
        return [
            'ok' => true,
            'message' => 'Invitation email sent.',
            'send_status' => $sendResult['status'] ?? 'accepted',
            'send_error' => null,
        ];
    }

    $message = $error !== ''
        ? 'Failed to send invitation email: ' . $error
        : 'Failed to send invitation email. Check Settings → Connections and send a test email from your email connection.';

    return [
        'ok' => false,
        'message' => $message,
        'send_status' => $sendResult['status'] ?? 'failed',
        'send_error' => $error !== '' ? $error : null,
    ];
}

// Helper function to get invitation data for display
function birkly_invitations_storage(): Storage
{
    return new Storage(__DIR__ . '/../data/invitations');
}

function birkly_invitation_update(string $token, array $patch): bool
{
    if ($token === '') {
        return false;
    }
    $storage = birkly_invitations_storage();
    $file = $token . '.json';
    $existing = $storage->read($file);
    if (!is_array($existing)) {
        return false;
    }
    return (bool) $storage->write($file, array_merge($existing, $patch));
}

function birkly_invitation_effective_status(array $invitation): string
{
    $status = (string) ($invitation['status'] ?? 'pending');
    if ($status === 'pending') {
        $expiresAt = $invitation['expires_at'] ?? null;
        if ($expiresAt && strtotime((string) $expiresAt) < time()) {
            return 'expired';
        }
    }
    return $status;
}

function birkly_invitation_delivery_label(array $invitation): string
{
    $delivery = (string) ($invitation['delivery'] ?? '');
    if ($delivery === '') {
        return !empty($invitation['email_sent']) ? 'Email sent' : 'Link only';
    }
    return match ($delivery) {
        'email' => 'Email sent',
        'link_only' => 'Link only',
        'email_failed' => 'Email failed',
        default => 'Unknown',
    };
}

function getInvitations() {
    $invitationsStorage = birkly_invitations_storage();
    $invitations = [];

    $files = $invitationsStorage->list('');
    foreach ($files as $file) {
        if (!str_ends_with($file, '.json')) {
            continue;
        }
        $invitation = $invitationsStorage->read($file);
        if (!is_array($invitation)) {
            continue;
        }
        if (empty($invitation['token'])) {
            $invitation['token'] = pathinfo($file, PATHINFO_FILENAME);
        }
        $invitations[] = $invitation;
    }

    usort($invitations, function ($a, $b) {
        return strtotime($b['created_at'] ?? '') <=> strtotime($a['created_at'] ?? '');
    });

    return $invitations;
}

function birkly_invitations_for_admin(): array
{
    $rows = [];
    foreach (getInvitations() as $invitation) {
        $token = (string) ($invitation['token'] ?? '');
        $status = birkly_invitation_effective_status($invitation);
        $rows[] = [
            'token' => $token,
            'email' => $invitation['email'] ?? '',
            'role' => $invitation['role'] ?? 'viewer',
            'status' => $status,
            'raw_status' => $invitation['status'] ?? 'pending',
            'delivery' => $invitation['delivery'] ?? null,
            'delivery_label' => birkly_invitation_delivery_label($invitation),
            'email_sent' => !empty($invitation['email_sent']),
            'email_error' => $invitation['email_error'] ?? null,
            'created_at' => $invitation['created_at'] ?? null,
            'expires_at' => $invitation['expires_at'] ?? null,
            'accepted_at' => $invitation['accepted_at'] ?? null,
            'cancelled_at' => $invitation['cancelled_at'] ?? null,
            'invite_url' => $token !== '' ? getInviteUrl($token) : null,
            'can_resend' => $status === 'pending',
            'can_copy_link' => $status === 'pending',
            'can_cancel' => in_array($status, ['pending', 'expired'], true),
        ];
    }
    return $rows;
}

// Ensure clean JSON output - don't clear output, just end buffering
ob_end_flush();
?>
