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
<div class="grace-banner" data-birch>
    <div class="signal" id="admin-2fa-grace-banner" role="status">
        <?= birkly_icon('shield') ?>
        <div class="signal-body">
            <p class="signal-title"><?= htmlspecialchars(__('admin.auth_2fa_grace_title')) ?></p>
            <p class="signal-text">
                <?php if ($daysLeft <= 1): ?>
                    <?= htmlspecialchars($untilLabel !== '' ? __('admin.auth_2fa_grace_ends_on', ['date' => $untilLabel]) : __('admin.auth_2fa_grace_ends_soon')) ?>
                <?php else: ?>
                    <?= htmlspecialchars($untilLabel !== '' ? __('admin.auth_2fa_grace_days_by', ['days' => (int) $daysLeft, 'date' => $untilLabel]) : __('admin.auth_2fa_grace_days', ['days' => (int) $daysLeft])) ?>
                <?php endif; ?>
                <?= htmlspecialchars(__('admin.auth_2fa_grace_after')) ?>
            </p>
        </div>
        <a class="btn btn-secondary btn-sm" href="<?= htmlspecialchars($accountUrl) ?>"><?= htmlspecialchars(__('admin.auth_2fa_grace_enable')) ?></a>
    </div>
</div>
