<?php
/**
 * Admin user menu (account, language, logout).
 * Included from admin-topbar.php — not the sidebar footer.
 */
require_once __DIR__ . '/../../core/platform.php';

$avatarDisplayUrl = '';
if (!empty($userAvatar)) {
    $avatarDisplayUrl = (str_starts_with($userAvatar, 'http://') || str_starts_with($userAvatar, 'https://') || str_starts_with($userAvatar, '//'))
        ? $userAvatar
        : birkly_url($userAvatar);
}

$termsUrl = birkly_legal_terms_url();
$privacyUrl = birkly_legal_privacy_url();
$showLegalLinks = birkly_alpha_program_enabled();
?>
<div class="dropdown user-menu-wrapper topbar-user-menu">
    <button class="dropdown-trigger user-menu-trigger" id="user-menu-trigger" aria-label="User menu" aria-expanded="false" aria-haspopup="true">
        <?php if (!empty($avatarDisplayUrl)): ?>
            <img src="<?= htmlspecialchars($avatarDisplayUrl) ?>" alt="<?= htmlspecialchars($userName) ?>" class="user-avatar">
        <?php else: ?>
            <div class="user-avatar-initials"><?= htmlspecialchars($avatarInitials) ?></div>
        <?php endif; ?>
        <div class="user-menu-info">
            <div class="user-menu-name"><?= htmlspecialchars($userName) ?></div>
        </div>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="user-menu-chevron">
            <polyline points="6,9 12,15 18,9"></polyline>
        </svg>
    </button>
    <div class="dropdown-menu dropdown-menu-right user-menu-dropdown" id="user-menu-dropdown">
        <div class="user-menu-header">
            <div class="user-menu-avatar">
                <?php if (!empty($avatarDisplayUrl)): ?>
                    <img src="<?= htmlspecialchars($avatarDisplayUrl) ?>" alt="<?= htmlspecialchars($userName) ?>" class="user-avatar">
                <?php else: ?>
                    <div class="user-avatar-initials"><?= htmlspecialchars($avatarInitials) ?></div>
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
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span>Account Settings</span>
            </a>
            <div class="user-menu-item user-menu-language">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="2" y1="12" x2="22" y2="12"></line>
                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                </svg>
                <select id="user-language-select" class="user-language-select" aria-label="Select language">
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
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14,2 14,8 20,8"></polyline>
                </svg>
                <span>Terms of service</span>
            </a>
            <a href="<?= htmlspecialchars($privacyUrl) ?>" class="user-menu-item" target="_blank" rel="noopener noreferrer">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                </svg>
                <span>Privacy policy</span>
            </a>
            <a href="https://birkly.com/#waitlist" class="user-menu-item" target="_blank" rel="noopener noreferrer">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
                <span>Request invite</span>
            </a>
            <?php endif; ?>
            <div class="user-menu-divider"></div>
            <button class="user-menu-item user-menu-logout" id="logout-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16,17 21,12 16,7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                <span><?= __('admin.logout') ?></span>
            </button>
        </div>
    </div>
</div>
