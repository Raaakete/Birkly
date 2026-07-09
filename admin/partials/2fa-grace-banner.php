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
<div class="admin-2fa-grace-banner" id="admin-2fa-grace-banner" role="status">
    <div class="admin-2fa-grace-banner-inner">
        <strong>Two-factor authentication required</strong>
        <span>
            <?php if ($daysLeft <= 1): ?>
                Your grace period ends<?= $untilLabel !== '' ? ' on ' . htmlspecialchars($untilLabel) : ' soon' ?>.
            <?php else: ?>
                <?= (int) $daysLeft ?> days left to enable 2FA<?= $untilLabel !== '' ? ' (by ' . htmlspecialchars($untilLabel) . ')' : '' ?>.
            <?php endif; ?>
            After that, admin access is blocked until 2FA is enabled.
        </span>
        <a class="btn btn-secondary btn-small" href="<?= htmlspecialchars($accountUrl) ?>">Enable 2FA</a>
    </div>
</div>
<style>
.admin-2fa-grace-banner {
    margin: 0 1.5rem 1rem;
    padding: 0.75rem 1rem;
    background: var(--color-positiv, #e4f1ec);
    border: 1px solid #9ae6b4;
    border-radius: 8px;
    color: var(--text-primary, #111827);
    font-size: 0.875rem;
}
.admin-2fa-grace-banner-inner {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem 1rem;
}
.admin-2fa-grace-banner strong {
    white-space: nowrap;
}
</style>
