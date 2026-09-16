<?php
/**
 * Admin Guide API (P87).
 */

declare(strict_types=1);

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../core/birkly_paths.php';
haru_secure_session_start();

if (!headers_sent()) {
    header('Content-Type: application/json');
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../core/birkly_users.php';
require_once __DIR__ . '/../core/i18n.php';
require_once __DIR__ . '/../core/admin_guide.php';

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode((string) file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? 'progress');

$user = birkly_load_user_account_for_session($_SESSION['user']);
if ($user === null) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit;
}

$page = (string) ($_GET['page'] ?? $input['page'] ?? '');
$tab = (string) ($_GET['tab'] ?? $input['tab'] ?? '');
$area = birkly_guide_area_from_request($page, $tab);

if ($action === 'progress') {
    $prefs = birkly_guide_prefs_from_user($user);
    echo json_encode([
        'status' => 'ok',
        'data' => birkly_guide_build_progress_payload($prefs, $area),
    ]);
    exit;
}

if ($action === 'update') {
    $csrf = $_POST['_csrf'] ?? $input['_csrf'] ?? '';
    if (!validate_csrf_token((string) $csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }

    $prefs = birkly_guide_prefs_from_user($user);
    $patch = [];

    if (!empty($input['ack_first_visit'])) {
        $patch['first_admin_visit_ack_at'] = date('c');
    }
    if (isset($input['disable_attention'])) {
        $patch['disable_attention'] = (bool) $input['disable_attention'];
    }
    if (!empty($input['mark_tip_seen']) && is_string($input['mark_tip_seen'])) {
        $seen = is_array($prefs['tips_seen'] ?? null) ? $prefs['tips_seen'] : [];
        $id = $input['mark_tip_seen'];
        if (!in_array($id, $seen, true)) {
            $seen[] = $id;
        }
        $patch['tips_seen'] = $seen;
    }
    if (!empty($input['dismiss_attention']) && is_string($input['dismiss_attention'])) {
        $dismissed = is_array($prefs['attention_dismissed'] ?? null) ? $prefs['attention_dismissed'] : [];
        $id = $input['dismiss_attention'];
        if (!in_array($id, $dismissed, true)) {
            $dismissed[] = $id;
        }
        $patch['attention_dismissed'] = $dismissed;
    }
    if (!empty($input['mark_journey_complete']) && is_string($input['mark_journey_complete'])) {
        $journeys = is_array($prefs['journeys_completed'] ?? null) ? $prefs['journeys_completed'] : [];
        $jid = $input['mark_journey_complete'];
        if (!in_array($jid, $journeys, true)) {
            $journeys[] = $jid;
        }
        $patch['journeys_completed'] = $journeys;
    }
    if (!empty($input['reset_progress'])) {
        $ui = is_array($user['ui_preferences'] ?? null) ? $user['ui_preferences'] : [];
        $ui['guide'] = array_merge(birkly_guide_default_prefs(), ['reset_at' => date('c')]);
        $user['ui_preferences'] = $ui;
        if (!birkly_save_user_account($user)) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to reset guide progress']);
            exit;
        }
        $_SESSION['user']['ui_preferences'] = $user['ui_preferences'];
        $newPrefs = birkly_guide_prefs_from_user($user);
        echo json_encode([
            'status' => 'ok',
            'data' => birkly_guide_build_progress_payload($newPrefs, $area),
        ]);
        exit;
    }

    $updated = birkly_guide_merge_prefs($user, $patch);
    if ($updated === null) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save guide preferences']);
        exit;
    }

    $_SESSION['user']['ui_preferences'] = $updated['ui_preferences'] ?? [];
    $newPrefs = birkly_guide_prefs_from_user($updated);
    echo json_encode([
        'status' => 'ok',
        'data' => birkly_guide_build_progress_payload($newPrefs, $area),
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
