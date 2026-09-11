<?php
/**
 * Settings → Connections panel (Phase 65).
 *
 * Email-first composition with a guided setup wizard. All element IDs use the
 * sys-conn- prefix to avoid collisions with Settings → AI connection UI.
 */
?>
<div class="settings-stack connections-settings" id="sys-conn-settings-root">
  <div class="settings-pane-section" id="sys-conn-email-section" data-settings-search="connections email smtp resend postmark ses mailgun log mail sender provider notifications forms">
    <section class="card">
      <div class="card-header">
        <div>
          <h2 class="section-title">Email</h2>
          <p class="settings-help">Set up how Birkly sends email — form notifications, order receipts, password resets, and more. You only need to do this once.</p>
        </div>
        <button type="button" class="btn btn-primary" data-sys-conn-action="open-email-wizard">
          <?= birkly_icon('plus', ['size' => 16]) ?>
          Set up email
        </button>
      </div>
      <div class="card-body u-stack-4">
        <div id="sys-conn-email-status" class="sys-conn-status-banner" hidden aria-live="polite"></div>
        <div id="sys-conn-email-list">
          <div class="loading-state"><p>Loading email connections…</p></div>
        </div>
      </div>
    </section>
  </div>

  <div class="settings-pane-section" data-settings-search="connections http credential webhook basic auth bearer token header">
    <section class="card">
      <div class="card-header">
        <div>
          <h2 class="section-title">HTTP credentials</h2>
          <p class="settings-help">Saved login details for plugins that call external APIs (shipping, CRM, webhooks).</p>
        </div>
        <button type="button" class="btn btn-primary" data-sys-conn-action="open-http-wizard" data-connection-type="http_credential">
          <?= birkly_icon('plus', ['size' => 16]) ?>
          Add credential
        </button>
      </div>
      <div class="card-body u-stack-4">
        <div id="sys-conn-http-list">
          <div class="loading-state"><p>Loading credentials…</p></div>
        </div>
      </div>
    </section>
  </div>
</div>

<!-- Email setup wizard -->
<div class="modal" id="sys-conn-email-modal" role="dialog" aria-labelledby="sys-conn-email-modal-title" aria-modal="true">
  <div class="modal-content modal--md">
    <div class="modal-header">
      <h2 id="sys-conn-email-modal-title">Set up email</h2>
      <button type="button" class="btn-icon modal-close" data-sys-conn-action="close-email-modal" aria-label="Close">
        <?= birkly_icon('x', ['size' => 16]) ?>
      </button>
    </div>
    <form id="sys-conn-email-form" class="modal-body conn-wizard">
      <input type="hidden" id="sys-conn-email-id" name="id">
      <input type="hidden" id="sys-conn-email-type" name="type" value="email">
      <input type="hidden" id="sys-conn-email-provider" name="provider" value="">

      <div class="conn-wizard-steps" role="tablist" aria-label="Email setup steps">
        <span class="conn-wizard-step-indicator active" data-step="1">1. Choose method</span>
        <span class="conn-wizard-step-indicator" data-step="2">2. Enter details</span>
        <span class="conn-wizard-step-indicator" data-step="3">3. Test &amp; save</span>
      </div>

      <div id="sys-conn-wizard-error" class="message message-error" role="alert" hidden></div>

      <!-- Step 1: Provider picker -->
      <section class="conn-wizard-panel" data-wizard-step="1">
        <p class="conn-wizard-intro">How would you like Birkly to send email? Pick the option that matches your situation.</p>
        <div id="sys-conn-provider-grid" class="conn-provider-grid" role="listbox" aria-label="Email sending methods"></div>
        <p class="form-help">Not sure? Use <strong>Log only</strong> on your computer to try things out, or <strong>Resend</strong> / <strong>Postmark</strong> for production sites.</p>
      </section>

      <!-- Step 2: Configuration -->
      <section class="conn-wizard-panel" data-wizard-step="2" hidden>
        <div id="sys-conn-provider-summary" class="callout callout-info u-mb-4" hidden>
          <div class="callout-body">
            <p class="callout-title" id="sys-conn-provider-summary-title"></p>
            <p id="sys-conn-provider-summary-help" class="u-text-sm"></p>
          </div>
        </div>

        <div class="form-group">
          <label for="sys-conn-email-name">Connection name</label>
          <input type="text" id="sys-conn-email-name" name="name" class="input" required placeholder="e.g. My website email">
          <small class="form-help">A label you will recognize in the list — only you see this.</small>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label for="sys-conn-email-from">Sender email address</label>
            <input type="email" id="sys-conn-email-from" name="from_email" class="input" required placeholder="hello@yourdomain.com">
            <small class="form-help">Recipients see this as the “From” address.</small>
          </div>
          <div class="form-group">
            <label for="sys-conn-email-from-name">Sender name</label>
            <input type="text" id="sys-conn-email-from-name" name="from_name" class="input" placeholder="Your site name">
          </div>
        </div>

        <div id="sys-conn-provider-fields" class="u-stack-3"></div>

        <details class="sys-conn-advanced">
          <summary>Advanced options</summary>
          <div class="u-stack-3 u-pt-3">
            <div class="form-group">
              <label for="sys-conn-email-alias">Internal reference name</label>
              <input type="text" id="sys-conn-email-alias" name="alias" class="input" pattern="[a-z0-9_.-]+" placeholder="auto-generated">
              <small class="form-help">Leave blank to auto-generate. Plugins use this to pick a specific connection.</small>
            </div>
            <label class="check">
              <input type="checkbox" id="sys-conn-email-enabled" name="enabled" value="1" checked>
              <span>Connection is enabled</span>
            </label>
          </div>
        </details>
      </section>

      <!-- Step 3: Test & save -->
      <section class="conn-wizard-panel" data-wizard-step="3" hidden>
        <p class="conn-wizard-intro">Send yourself a quick test to make sure everything works.</p>
        <div class="form-group">
          <label for="sys-conn-test-email">Send test email to</label>
          <div class="input-with-action">
            <input type="email" id="sys-conn-test-email" class="input" placeholder="you@example.com" autocomplete="email">
            <button type="button" class="btn btn-secondary" id="sys-conn-send-test-btn">Send test</button>
          </div>
          <p id="sys-conn-test-status" class="form-help sys-conn-test-status" aria-live="polite"></p>
        </div>

        <label class="check">
          <input type="checkbox" id="sys-conn-set-default" name="set_default" value="1" checked>
          <span>Use this as the <strong>default</strong> email connection for form notifications, Commerce, and other features</span>
        </label>

        <p class="form-help">You can change the default later from the connection list.</p>
      </section>

      <div class="conn-wizard-actions modal-footer">
        <button type="button" class="btn btn-secondary" data-sys-conn-action="close-email-modal"><?= __('admin.cancel') ?></button>
        <button type="button" class="btn btn-secondary" id="sys-conn-wizard-back" hidden><?= __('admin.connections_panel_wizard_back') ?></button>
        <button type="button" class="btn btn-primary" id="sys-conn-wizard-next"><?= __('admin.connections_panel_wizard_continue') ?></button>
        <button type="submit" class="btn btn-primary" id="sys-conn-wizard-save" hidden><?= __('admin.connections_panel_save_connection') ?></button>
      </div>
    </form>
  </div>
</div>

<!-- HTTP credential modal (simpler form) -->
<div class="modal" id="sys-conn-http-modal" role="dialog" aria-labelledby="sys-conn-http-modal-title" aria-modal="true">
  <div class="modal-content modal--md">
    <div class="modal-header">
      <h2 id="sys-conn-http-modal-title"><?= __('admin.connections_panel_add_http_credential') ?></h2>
      <button type="button" class="btn-icon modal-close" data-sys-conn-action="close-http-modal" aria-label="<?= htmlspecialchars(__('admin.ui_feedback_close')) ?>">
        <?= birkly_icon('x', ['size' => 16]) ?>
      </button>
    </div>
    <form id="sys-conn-http-form" class="modal-body">
      <input type="hidden" id="sys-conn-http-id" name="id">
      <input type="hidden" id="sys-conn-http-type" name="type" value="http_credential">

      <div id="sys-conn-http-error" class="message message-error" role="alert" hidden></div>

      <div class="form-group">
        <label for="sys-conn-http-provider">Credential type</label>
        <select id="sys-conn-http-provider" name="provider" class="input" required></select>
      </div>

      <div class="form-group">
        <label for="sys-conn-http-name">Name</label>
        <input type="text" id="sys-conn-http-name" name="name" class="input" required placeholder="e.g. Shipping API">
      </div>

      <div class="form-group">
        <label for="sys-conn-http-alias">Reference name</label>
        <input type="text" id="sys-conn-http-alias" name="alias" class="input" required pattern="[a-z0-9_.-]+" placeholder="e.g. shipping-api">
        <small class="form-help">Lowercase letters, numbers, dots, dashes and underscores.</small>
      </div>

      <div id="sys-conn-http-provider-fields" class="u-stack-3"></div>

      <label class="check">
        <input type="checkbox" id="sys-conn-http-enabled" name="enabled" value="1" checked>
        <span>Enabled</span>
      </label>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-sys-conn-action="close-http-modal"><?= __('admin.cancel') ?></button>
        <button type="submit" class="btn btn-primary" id="sys-conn-http-submit"><?= __('admin.connections_js_save_credential') ?></button>
      </div>
    </form>
  </div>
</div>
