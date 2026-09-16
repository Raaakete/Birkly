<?php
require_once __DIR__ . '/../../core/platform.php';
$privacyUrl = birkly_legal_privacy_url();
$termsUrl = birkly_legal_terms_url();
$privacyLink = '<a href="' . htmlspecialchars($privacyUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">'
    . htmlspecialchars(__('admin.feedback_privacy_policy'), ENT_QUOTES, 'UTF-8') . '</a>';
$termsLink = '<a href="' . htmlspecialchars($termsUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">'
    . htmlspecialchars(__('admin.feedback_alpha_terms'), ENT_QUOTES, 'UTF-8') . '</a>';
$privacyNotice = __('admin.feedback_privacy_notice', [
    'privacy' => $privacyLink,
    'terms' => $termsLink,
]);
$feedbackSubmitterEmail = trim((string) ($_SESSION['user']['email'] ?? ''));
?>
<!-- Alpha feedback floating button + modal (P3-M10, P15-M4) -->
<div class="feedback-floating-wrap" id="feedback-floating-wrap">
    <button type="button" id="feedback-floating-btn" class="feedback-floating-btn" title="<?= htmlspecialchars(__('admin.feedback_send_title'), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars(__('admin.feedback_send_aria'), ENT_QUOTES, 'UTF-8') ?>">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
        </svg>
    </button>
</div>

<div class="modal feedback-modal" id="feedback-submit-modal">
    <div class="modal-backdrop" data-close-feedback-submit></div>
    <div class="modal-content modal--md">
        <div class="modal-header">
            <h2><?= htmlspecialchars(__('admin.feedback_send_title'), ENT_QUOTES, 'UTF-8') ?></h2>
            <button type="button" class="modal-close" data-close-feedback-submit aria-label="<?= htmlspecialchars(__('admin.close'), ENT_QUOTES, 'UTF-8') ?>">×</button>
        </div>
        <form id="feedback-submit-form" class="modal-body u-stack-4" enctype="multipart/form-data">
            <p class="feedback-privacy-notice u-text-sm u-fg-secondary">
                <?= $privacyNotice ?>
            </p>
            <div class="field">
                <label class="field-label" for="feedback-type"><?= htmlspecialchars(__('admin.feedback_type_label'), ENT_QUOTES, 'UTF-8') ?></label>
                <select id="feedback-type" name="type" class="select" required>
                    <option value="bug"><?= htmlspecialchars(__('admin.feedback_type_bug'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="feature"><?= htmlspecialchars(__('admin.feedback_type_feature'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="feedback" selected><?= htmlspecialchars(__('admin.feedback_type_general'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="question"><?= htmlspecialchars(__('admin.feedback_type_question'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
            </div>
            <div class="field">
                <label class="field-label" for="feedback-category"><?= htmlspecialchars(__('admin.feedback_category_label'), ENT_QUOTES, 'UTF-8') ?></label>
                <select id="feedback-category" name="category" class="select" required>
                    <option value="admin"><?= htmlspecialchars(__('admin.feedback_category_admin'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="content"><?= htmlspecialchars(__('admin.feedback_category_content'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="commerce"><?= htmlspecialchars(__('admin.feedback_category_commerce'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="marketplace"><?= htmlspecialchars(__('admin.feedback_category_marketplace'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="plugins"><?= htmlspecialchars(__('admin.feedback_category_plugins'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="ai"><?= htmlspecialchars(__('admin.feedback_category_ai'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="other" selected><?= htmlspecialchars(__('admin.feedback_category_other'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
            </div>
            <div class="field">
                <label class="field-label" for="feedback-title"><?= htmlspecialchars(__('admin.feedback_subject_label'), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="text" id="feedback-title" name="title" class="input" maxlength="200" required placeholder="<?= htmlspecialchars(__('admin.feedback_subject_placeholder'), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="field">
                <label class="field-label" for="feedback-description"><?= htmlspecialchars(__('admin.feedback_description_label'), ENT_QUOTES, 'UTF-8') ?></label>
                <textarea id="feedback-description" name="description" class="textarea" rows="5" maxlength="5000" required placeholder="<?= htmlspecialchars(__('admin.feedback_description_placeholder'), ENT_QUOTES, 'UTF-8') ?>"></textarea>
                <p class="field-help">
                    <?= htmlspecialchars(__('admin.feedback_description_help'), ENT_QUOTES, 'UTF-8') ?>
                    <a href="mailto:hello@birkly.io?subject=Birkly%20alpha%20feedback%20screenshot">hello@birkly.io</a>
                </p>
            </div>
            <?php if ($feedbackSubmitterEmail !== ''): ?>
            <div class="field">
                <label class="field-checkbox feedback-contact-toggle" for="feedback-contact-allowed">
                    <input type="checkbox" id="feedback-contact-allowed" name="contact_allowed" value="1">
                    <span><?= sprintf(__('admin.feedback_contact_allow_label'), htmlspecialchars($feedbackSubmitterEmail, ENT_QUOTES, 'UTF-8')) ?></span>
                </label>
                <p class="field-help"><?= htmlspecialchars(__('admin.feedback_contact_allow_help'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <?php endif; ?>
            <div class="field">
                <label class="field-label" for="feedback-attachments"><?= htmlspecialchars(__('admin.feedback_attachments_label'), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="file" id="feedback-attachments" name="attachments[]" class="input" multiple accept="image/jpeg,image/png,image/gif,image/webp,video/mp4">
                <p class="field-help"><?= htmlspecialchars(__('admin.feedback_attachments_help'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div id="feedback-submit-error" class="callout callout-danger" hidden></div>
            <div id="feedback-submit-success" class="callout callout-success" hidden><?= htmlspecialchars(__('admin.feedback_submit_success'), ENT_QUOTES, 'UTF-8') ?></div>
        </form>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-close-feedback-submit><?= htmlspecialchars(__('admin.cancel'), ENT_QUOTES, 'UTF-8') ?></button>
            <button type="submit" form="feedback-submit-form" class="btn btn-primary" id="feedback-submit-btn"><?= htmlspecialchars(__('admin.feedback_submit_btn'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </div>
</div>
