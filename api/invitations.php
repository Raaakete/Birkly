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
    
    // Send invitation email
    $inviteUrl = getInviteUrl($token);
    $emailSent = sendInvitationEmail($email, $inviteUrl, $role, $expiresDays);
    
    if ($emailSent) {
        log_security_event('invitation_created', [
            'invited_email' => $email,
            'role' => $role,
            'created_by' => $_SESSION['user']['email']
        ]);
        
        $response = [
            'status' => 'ok',
            'message' => 'Invitation sent successfully',
            'data' => [
                'token' => $token,
                'expires_at' => $expiresAt,
                'invite_url' => $inviteUrl
            ]
        ];
        
        // On localhost, include the invitation URL in the message
        if ($_SERVER['HTTP_HOST'] === 'localhost:8001' || strpos($_SERVER['HTTP_HOST'], 'localhost') === 0) {
            $response['message'] = 'Invitation created! (Localhost: Email not sent, but invitation link is ready)';
            $response['data']['localhost_note'] = 'Click this link to test: ' . $inviteUrl;
        }
        
        echo json_encode($response);
    } else {
        // Remove invitation if email failed
        $invitationsStorage->delete($token . '.json');
        echo json_encode([
            'status' => 'error', 
            'message' => 'Failed to send invitation email. Please check your server mail configuration.',
            'debug' => [
                'email' => $email,
                'invite_url' => $inviteUrl
            ]
        ]);
    }
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
    $emailSent = sendInvitationEmail($invitation['email'], $inviteUrl, $invitation['role'], $expiresDays);
    
    if ($emailSent) {
        log_security_event('invitation_resent', [
            'invited_email' => $invitation['email'],
            'created_by' => $_SESSION['user']['email']
        ]);
        
        echo json_encode(['status' => 'ok', 'message' => 'Invitation resent successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to resend invitation email']);
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
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . ($_SESSION['user']['email'] ?? 'noreply@localhost'),
        'Reply-To: ' . ($_SESSION['user']['email'] ?? 'noreply@localhost'),
        'X-Mailer: Birkly'
    ];
    
    // Try to send the email
    $result = @mail($email, $subject, $message, implode("\r\n", $headers));
    
    // Log the attempt
    log_security_event('invitation_email_attempt', [
        'email' => $email,
        'success' => $result,
        'sent_by' => $_SESSION['user']['email'] ?? 'unknown',
        'invite_url' => $inviteUrl
    ]);
    
    // For localhost, always return true but log the URL
    if ($_SERVER['HTTP_HOST'] === 'localhost:8001' || strpos($_SERVER['HTTP_HOST'], 'localhost') === 0) {
        error_log("LOCALHOST: Email would be sent to $email with URL: $inviteUrl");
        return true; // Always return true on localhost for testing
    }
    
    return $result;
}

// Helper function to get invitation data for display
function getInvitations() {
    $invitationsStorage = new Storage(__DIR__ . '/../data/invitations');
    $invitations = [];
    
    $files = glob($invitationsStorage->getPath() . '/*.json');
    foreach ($files as $file) {
        $invitation = json_decode(file_get_contents($file), true);
        if ($invitation) {
            $invitations[] = $invitation;
        }
    }
    
    // Sort by creation date (newest first)
    usort($invitations, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    return $invitations;
}

// Ensure clean JSON output - don't clear output, just end buffering
ob_end_flush();
?>
