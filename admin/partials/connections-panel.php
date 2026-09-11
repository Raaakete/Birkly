<?php
/**
 * Settings → Connections panel (Phase 65).
 *
 * Email-first composition with a guided setup wizard. All element IDs use the
 * sys-conn- prefix to avoid collisions with Settings → AI connection UI.
 */
$connText = static function (string $key): string {
    return htmlspecialchars(__('admin.' . $key), ENT_QUOTES, 'UTF-8');
};
$connRich = static function (string $key, string $allowed = '<strong><em>') {
    return strip_tags(__('admin.' . $key), $allowed);
};
?>
<div class="settings-stack connections-settings" id="sys-conn-settings-root">
  <div class="settings-pane-section" id="sys-conn-email-section" data-settings-search="connections email smtp resend postmark ses mailgun log mail sender provider notifications forms">
    <section class="card">
      <div class="card-header">
        <div>
          <h2 class="section-title"><?= htmlspecialchars(__('admin.conn_panel_email_title'), ENT_QUOTES, 'UTF-8') ?></h2>
          <p class="settings-help"><?= htmlspecialchars(__('admin.conn_panel_email_help'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <button type="button" class="btn btn-primary" data-sys-conn-action="open-email-wizard">
          <?= birkly_icon('plus', ['size' => 16]) ?>
          <?= htmlspecialchars(__('admin.conn_wizard_setup'), ENT_QUOTES, 'UTF-8') ?>
        </button>
      </div>
      <div class="card-body u-stack-4">
        <div id="sys-conn-email-status" class="sys-conn-status-banner" hidden aria-live="polite"></div>
        <div id="sys-conn-email-list">
          <div class="loading-state"><p><?= htmlspecialchars(__('admin.connections_loading_email'), ENT_QUOTES, 'UTF-8') ?></p></div>
        </div>
      </div>
    </section>
  </div>

  <div class="settings-pane-section" data-settings-search="connections http credential webhook basic auth bearer token header">
    <section class="card">
      <div class="card-header">
        <div>
          <h2 class="section-title"><?= htmlspecialchars(__('admin.conn_panel_http_title'), ENT_QUOTES, 'UTF-8') ?></h2>
          <p class="settings-help"><?= htmlspecialchars(__('admin.conn_panel_http_help'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <button type="button" class="btn btn-primary" data-sys-conn-action="open-http-wizard" data-connection-type="http_credential">
          <?= birkly_icon('plus', ['size' => 16]) ?>
          <?= htmlspecialchars(__('admin.conn_panel_add_credential'), ENT_QUOTES, 'UTF-8') ?>
        </button>
      </div>
      <div class="card-body u-stack-4">
        <div id="sys-conn-http-list">
          <div class="loading-state"><p><?= htmlspecialchars(__('admin.connections_loading_credentials'), ENT_QUOTES, 'UTF-8') ?></p></div>
        </div>
      </div>
    </section>
  </div>
</div>

<!-- Email setup wizard -->
<div class="modal" id="sys-conn-email-modal" role="dialog" aria-labelledby="sys-conn-email-modal-title" aria-modal="true">
  <div class="modal-content modal--md">
    <div class="modal-header">
      <h2 id="sys-conn-email-modal-title"><?= $connText('conn_wizard_setup') ?></h2>
      <button type="button" class="btn-icon modal-close" data-sys-conn-action="close-email-modal" aria-label="<?= $connText('close') ?>">
        <?= birkly_icon('x', ['size' => 16]) ?>
      </button>
    </div>
    <form id="sys-conn-email-form" class="modal-body conn-wizard">
      <input type="hidden" id="sys-conn-email-id" name="id">
      <input type="hidden" id="sys-conn-email-type" name="type" value="email">
      <input type="hidden" id="sys-conn-email-provider" name="provider" value="">

      <div class="conn-wizard-steps" role="tablist" aria-label="<?= $connText('conn_wizard_steps_aria') ?>">
        <span class="conn-wizard-step-indicator active" data-step="1"><?= $connText('conn_wizard_step_choose') ?></span>
        <span class="conn-wizard-step-indicator" data-step="2"><?= $connText('conn_wizard_step_details') ?></span>
        <span class="conn-wizard-step-indicator" data-step="3"><?= $connText('conn_wizard_step_test') ?></span>
      </div>

      <div id="sys-conn-wizard-error" class="message message-error" role="alert" hidden></div>

      <!-- Step 1: Provider picker -->
      <section class="conn-wizard-panel" data-wizard-step="1">
        <p class="conn-wizard-intro"><?= $connText('conn_wizard_intro_pick') ?></p>
        <div id="sys-conn-provider-grid" class="conn-provider-grid" role="listbox" aria-label="<?= $connText('conn_wizard_provider_grid_aria') ?>"></div>
        <p class="form-help"><?= $connRich('conn_wizard_intro_tip') ?></p>
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
          <label for="sys-conn-email-name"><?= $connText('conn_wizard_label_connection_name') ?></label>
          <input type="text" id="sys-conn-email-name" name="name" class="input" required placeholder="<?= $connText('conn_wizard_name_placeholder') ?>">
          <small class="form-help"><?= $connText('conn_wizard_name_help') ?></small>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label for="sys-conn-email-from"><?= $connText('conn_wizard_label_from_email') ?></label>
            <input type="email" id="sys-conn-email-from" name="from_email" class="input" required placeholder="<?= $connText('conn_wizard_from_placeholder') ?>">
            <small class="form-help"><?= $connText('conn_wizard_from_email_help') ?></small>
          </div>
          <div class="form-group">
            <label for="sys-conn-email-from-name"><?= $connText('conn_wizard_label_from_name') ?></label>
            <input type="text" id="sys-conn-email-from-name" name="from_name" class="input" placeholder="<?= $connText('conn_wizard_from_name_placeholder') ?>">
          </div>
        </div>

        <div id="sys-conn-provider-fields" class="u-stack-3"></div>

        <details class="sys-conn-advanced">
          <summary><?= $connText('conn_wizard_advanced_summary') ?></summary>
          <div class="u-stack-3 u-pt-3">
            <div class="form-group">
              <label for="sys-conn-email-alias"><?= $connText('conn_wizard_label_alias') ?></label>
              <input type="text" id="sys-conn-email-alias" name="alias" class="input" pattern="[a-z0-9_.-]+" placeholder="<?= $connText('conn_wizard_alias_placeholder') ?>">
              <small class="form-help"><?= $connText('conn_wizard_alias_help') ?></small>
            </div>
            <label class="check">
              <input type="checkbox" id="sys-conn-email-enabled" name="enabled" value="1" checked>
              <span><?= $connText('conn_wizard_enabled_label') ?></span>
            </label>
          </div>
        </details>
      </section>

      <!-- Step 3: Test & save -->
      <section class="conn-wizard-panel" data-wizard-step="3" hidden>
        <p class="conn-wizard-intro"><?= $connText('conn_wizard_intro_test') ?></p>
        <div class="form-group">
          <label for="sys-conn-test-email"><?= $connText('conn_wizard_label_test_to') ?></label>
          <div class="input-with-action">
            <input type="email" id="sys-conn-test-email" class="input" placeholder="<?= $connText('conn_wizard_test_placeholder') ?>" autocomplete="email">
            <button type="button" class="btn btn-secondary" id="sys-conn-send-test-btn"><?= $connText('conn_btn_send_test') ?></button>
          </div>
          <p id="sys-conn-test-status" class="form-help sys-conn-test-status" aria-live="polite"></p>
        </div>

        <label class="check">
          <input type="checkbox" id="sys-conn-set-default" name="set_default" value="1" checked>
          <span><?= $connRich('conn_wizard_default_label') ?></span>
        </label>

        <p class="form-help"><?= $connText('conn_wizard_default_help') ?></p>
      </section>

      <div class="conn-wizard-actions modal-footer">
        <button type="button" class="btn btn-secondary" data-sys-conn-action="close-email-modal"><?= $connText('cancel') ?></button>
        <button type="button" class="btn btn-secondary" id="sys-conn-wizard-back" hidden><?= $connText('back') ?></button>
        <button type="button" class="btn btn-primary" id="sys-conn-wizard-next"><?= $connText('continue') ?></button>
        <button type="submit" class="btn btn-primary" id="sys-conn-wizard-save" hidden><?= $connText('conn_wizard_save_connection') ?></button>
      </div>
    </form>
  </div>
</div>

<!-- HTTP credential modal (simpler form) -->
<div class="modal" id="sys-conn-http-modal" role="dialog" aria-labelledby="sys-conn-http-modal-title" aria-modal="true">
  <div class="modal-content modal--md">
    <div class="modal-header">
      <h2 id="sys-conn-http-modal-title"><?= $connText('conn_http_modal_add_title') ?></h2>
      <button type="button" class="btn-icon modal-close" data-sys-conn-action="close-http-modal" aria-label="<?= $connText('close') ?>">
        <?= birkly_icon('x', ['size' => 16]) ?>
      </button>
    </div>
    <form id="sys-conn-http-form" class="modal-body">
      <input type="hidden" id="sys-conn-http-id" name="id">
      <input type="hidden" id="sys-conn-http-type" name="type" value="http_credential">

      <div id="sys-conn-http-error" class="message message-error" role="alert" hidden></div>

      <div class="form-group">
        <label for="sys-conn-http-provider"><?= $connText('conn_http_label_credential_type') ?></label>
        <select id="sys-conn-http-provider" name="provider" class="input" required></select>
      </div>

      <div class="form-group">
        <label for="sys-conn-http-name"><?= $connText('conn_http_label_name') ?></label>
        <input type="text" id="sys-conn-http-name" name="name" class="input" required placeholder="<?= $connText('conn_http_name_placeholder') ?>">
      </div>

      <div class="form-group">
        <label for="sys-conn-http-alias"><?= $connText('conn_http_label_reference') ?></label>
        <input type="text" id="sys-conn-http-alias" name="alias" class="input" required pattern="[a-z0-9_.-]+" placeholder="<?= $connText('conn_http_reference_placeholder') ?>">
        <small class="form-help"><?= $connText('conn_http_reference_help') ?></small>
      </div>

      <div id="sys-conn-http-provider-fields" class="u-stack-3"></div>

      <label class="check">
        <input type="checkbox" id="sys-conn-http-enabled" name="enabled" value="1" checked>
        <span><?= $connText('conn_http_enabled_label') ?></span>
      </label>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-sys-conn-action="close-http-modal"><?= $connText('cancel') ?></button>
        <button type="submit" class="btn btn-primary" id="sys-conn-http-submit"><?= $connText('conn_http_submit_save') ?></button>
      </div>
    </form>
  </div>
</div>
