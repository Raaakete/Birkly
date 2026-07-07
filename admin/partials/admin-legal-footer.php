<?php
/**
 * Closed-alpha legal links in admin footer (P15-M4).
 */
require_once __DIR__ . '/../../core/platform.php';

if (!birkly_alpha_program_enabled()) {
    return;
}

$termsUrl = birkly_legal_terms_url();
$privacyUrl = birkly_legal_privacy_url();
?>
<footer class="admin-legal-footer" aria-label="Legal">
    <p class="admin-legal-footer-text">
        Closed alpha ·
        <a href="<?= htmlspecialchars($termsUrl) ?>" target="_blank" rel="noopener noreferrer">Terms of service</a>
        ·
        <a href="<?= htmlspecialchars($privacyUrl) ?>" target="_blank" rel="noopener noreferrer">Privacy policy</a>
    </p>
</footer>
