<?php
/**
 * Admin user menu (account, language, logout).
 * Included from admin-topbar.php — not the sidebar footer.
 */
require_once __DIR__ . '/../../core/platform.php';

if (!function_exists('birkly_icon')) {
    require_once __DIR__ . '/../../core/icons.php';
}

$avatarDisplayUrl = '';
if (!empty($userAvatar)) {
    $avatarDisplayUrl = (str_starts_with($userAvatar, 'http://') || str_starts_with($userAvatar, 'https://') || str_starts_with($userAvatar, '//'))
        ? $userAvatar
        : birkly_url($userAvatar);
}

/* Always have initials for the panel/trigger fallback (sidebar may leave
   $avatarInitials empty when a photo is set). */
if (empty($avatarInitials)) {
    $initialSource = trim((string) ($userName ?? ''));
    if ($initialSource === '') {
        $initialSource = trim((string) ($userEmail ?? 'U'));
    }
    $nameParts = preg_split('/\s+/', $initialSource) ?: [];
    if (count($nameParts) >= 2) {
        $avatarInitials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
    } else {
        $avatarInitials = strtoupper(substr($initialSource, 0, 2));
    }
}

$termsUrl = birkly_legal_terms_url();
$privacyUrl = birkly_legal_privacy_url();
$showLegalLinks = birkly_alpha_program_enabled();
?>
<div class="dropdown user-menu-wrapper topbar-user-menu">
    <button class="dropdown-trigger user-menu-trigger" id="user-menu-trigger" aria-label="<?= htmlspecialchars(__('admin.user_menu_aria')) ?>" aria-expanded="false" aria-haspopup="true">
        <?php if (!empty($avatarDisplayUrl)): ?>
            <img src="<?= htmlspecialchars($avatarDisplayUrl) ?>" alt="<?= htmlspecialchars($userName) ?>" class="user-avatar user-menu-trigger-face">
        <?php else: ?>
            <div class="user-avatar-initials user-menu-trigger-face" aria-hidden="true"><?= htmlspecialchars($avatarInitials) ?></div>
        <?php endif; ?>
        <div class="user-menu-info">
            <div class="user-menu-name"><?= htmlspecialchars($userName) ?></div>
        </div>
        <?= birkly_icon('caret-down', ['class' => 'user-menu-chevron']) ?>
    </button>
    <div class="dropdown-menu dropdown-menu-right user-menu-dropdown" id="user-menu-dropdown">
        <div class="user-menu-header">
            <div class="user-menu-avatar">
                <?php if (!empty($avatarDisplayUrl)): ?>
                    <img src="<?= htmlspecialchars($avatarDisplayUrl) ?>" alt="" class="user-avatar user-menu-panel-face">
                <?php else: ?>
                    <div class="user-avatar-initials user-menu-panel-face" aria-hidden="true"><?= htmlspecialchars($avatarInitials) ?></div>
                <?php endif; ?>
            </div>
            <div class="user-menu-info">
                <div class="user-menu-name"><?= htmlspecialchars($userName) ?></div>
                <div class="user-menu-email"><?= htmlspecialchars($userEmail) ?></div>
                <div class="user-menu-role"><?= htmlspecialchars($userRole) ?></div>
            </div>
        </div>
        <div class="user-menu-divider"></div>
        <div class="user-menu-items">
            <a href="<?= htmlspecialchars(admin_url('account')) ?>" class="user-menu-item">
                <?= birkly_icon('user') ?>
                <span><?= htmlspecialchars(__('admin.account_settings')) ?></span>
            </a>
            <div class="user-menu-item user-menu-language">
                <?= birkly_icon('globe') ?>
                <select id="user-language-select" class="user-language-select" aria-label="<?= htmlspecialchars(__('admin.select_language_aria')) ?>">
                    <?php foreach ($availableLangs as $langCode => $langInfo): ?>
                        <option value="<?= htmlspecialchars($langCode) ?>" <?= $langCode === $currentLang ? 'selected' : '' ?>>
                            <?= htmlspecialchars($langInfo['native_name']) ?> <?= htmlspecialchars($langInfo['flag']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($showLegalLinks): ?>
            <div class="user-menu-divider"></div>
            <a href="<?= htmlspecialchars($termsUrl) ?>" class="user-menu-item" target="_blank" rel="noopener noreferrer">
                <?= birkly_icon('file-text') ?>
                <span><?= htmlspecialchars(__('admin.terms_of_service')) ?></span>
            </a>
            <a href="<?= htmlspecialchars($privacyUrl) ?>" class="user-menu-item" target="_blank" rel="noopener noreferrer">
                <?= birkly_icon('shield') ?>
                <span><?= htmlspecialchars(__('admin.privacy_policy')) ?></span>
            </a>
            <?php endif; ?>
            <div class="user-menu-divider"></div>
            <button class="user-menu-item user-menu-logout" id="logout-btn">
                <?= birkly_icon('sign-out') ?>
                <span><?= __('admin.logout') ?></span>
            </button>
        </div>
    </div>
</div>
