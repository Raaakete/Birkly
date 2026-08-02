<?php
require_once __DIR__ . '/../../core/platform.php';
$privacyUrl = birkly_legal_privacy_url();
$termsUrl = birkly_legal_terms_url();
?>
<!-- Alpha feedback floating button + modal (P3-M10, P15-M4) -->
<div class="feedback-floating-wrap" id="feedback-floating-wrap">
    <button type="button" id="feedback-floating-btn" class="feedback-floating-btn" title="Send feedback" aria-label="Send feedback">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
        </svg>
    </button>
</div>

<div class="modal feedback-modal" id="feedback-submit-modal" style="display: none;">
    <div class="modal-backdrop" data-close-feedback-submit></div>
    <div class="modal-content" style="max-width: 520px;">
        <div class="modal-header">
            <h2>Send feedback</h2>
            <button type="button" class="modal-close" data-close-feedback-submit aria-label="Close">×</button>
        </div>
        <form id="feedback-submit-form" class="modal-body">
            <p class="feedback-privacy-notice text-secondary" style="font-size: 0.875rem; margin-bottom: 1rem;">
                Your feedback helps improve Birkly during the closed alpha. Submissions include your account email,
                the current page, and browser info. See our
                <a href="<?= htmlspecialchars($privacyUrl) ?>" target="_blank" rel="noopener noreferrer">privacy policy</a>
                and <a href="<?= htmlspecialchars($termsUrl) ?>" target="_blank" rel="noopener noreferrer">alpha terms</a>.
            </p>
            <div class="form-group">
                <label for="feedback-type">Type</label>
                <select id="feedback-type" name="type" class="form-control" required>
                    <option value="bug">Bug report</option>
                    <option value="feature">Feature request</option>
                    <option value="feedback" selected>General feedback</option>
                    <option value="question">Question / support</option>
                </select>
            </div>
            <div class="form-group">
                <label for="feedback-category">Category</label>
                <select id="feedback-category" name="category" class="form-control" required>
                    <option value="admin">Admin &amp; settings</option>
                    <option value="content">Content &amp; editor</option>
                    <option value="commerce">Commerce</option>
                    <option value="marketplace">Hub &amp; plugins</option>
                    <option value="plugins">Installed plugins</option>
                    <option value="ai">AI &amp; MCP</option>
                    <option value="other" selected>Other</option>
                </select>
            </div>
            <div class="form-group">
                <label for="feedback-title">Subject</label>
                <input type="text" id="feedback-title" name="title" class="form-control" maxlength="200" required placeholder="Brief summary">
            </div>
            <div class="form-group">
                <label for="feedback-description">Description</label>
                <textarea id="feedback-description" name="description" class="form-control" rows="5" maxlength="5000" required placeholder="What happened? What did you expect?"></textarea>
                <p class="text-secondary" style="font-size: 0.8125rem; margin-top: 0.5rem; margin-bottom: 0;">
                    Optional: paste a screenshot into the description, or email one to
                    <a href="mailto:hello@birkly.com?subject=Birkly%20alpha%20feedback%20screenshot">hello@birkly.com</a>
                    with the subject line above.
                </p>
            </div>
            <div id="feedback-submit-error" class="alert alert-error" style="display: none;"></div>
            <div id="feedback-submit-success" class="alert alert-success" style="display: none;">Thank you! Your feedback was submitted.</div>
        </form>
        <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
            <button type="button" class="btn btn-secondary" data-close-feedback-submit>Cancel</button>
            <button type="submit" form="feedback-submit-form" class="btn btn-primary" id="feedback-submit-btn">Submit</button>
        </div>
    </div>
</div>
