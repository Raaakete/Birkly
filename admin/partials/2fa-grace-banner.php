<?php
/**
 * Banner shown while the current user is within the org 2FA grace period.
 */
require_once __DIR__ . '/../../core/security_policy.php';

if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
    return;
}

$user = $_SESSION['user'];
if (!birkly_user_in_2fa_grace($user)) {
    return;
}

$daysLeft = birkly_user_2fa_grace_days_left($user);
$until = birkly_user_2fa_grace_until($user);
$untilLabel = $until ? date('M j, Y', strtotime($until) ?: time()) : '';
$accountUrl = birkly_admin_url('account') . '#2fa';
?>
<div class="callout callout-warning callout--banner" id="admin-2fa-grace-banner" role="status">
    <div class="callout-body">
        <p class="callout-title">Two-factor authentication required</p>
        <p>
            <?php if ($daysLeft <= 1): ?>
                Your grace period ends<?= $untilLabel !== '' ? ' on ' . htmlspecialchars($untilLabel) : ' soon' ?>.
            <?php else: ?>
                <?= (int) $daysLeft ?> days left to enable 2FA<?= $untilLabel !== '' ? ' (by ' . htmlspecialchars($untilLabel) . ')' : '' ?>.
            <?php endif; ?>
            After that, admin access is blocked until 2FA is enabled.
        </p>
    </div>
    <div class="callout-actions">
        <a class="btn btn-secondary btn-sm" href="<?= htmlspecialchars($accountUrl) ?>">Enable 2FA</a>
    </div>
</div>
