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
          <h2 class="section-title"><?= __('admin.connections_panel_email_title') ?></h2>
          <p class="settings-help"><?= __('admin.connections_panel_email_help') ?></p>
        </div>
        <button type="button" class="button is-main" data-sys-conn-action="open-email-wizard">
          <?= birkly_icon('plus', ['size' => 16]) ?>
          <?= __('admin.connections_panel_setup_email_btn') ?>
        </button>
      </div>
      <div class="card-body u-stack-4">
        <div id="sys-conn-email-status" class="sys-conn-status-banner" hidden aria-live="polite"></div>
        <div id="sys-conn-email-list">
          <div class="loading-state"><p><?= __('admin.connections_panel_loading_email') ?></p></div>
        </div>
      </div>
    </section>
  </div>

  <div class="settings-pane-section" data-settings-search="connections http credential webhook basic auth bearer token header">
    <section class="card">
      <div class="card-header">
        <div>
          <h2 class="section-title"><?= __('admin.connections_panel_http_title') ?></h2>
          <p class="settings-help"><?= __('admin.connections_panel_http_help') ?></p>
        </div>
        <button type="button" class="button is-main" data-sys-conn-action="open-http-wizard" data-connection-type="http_credential">
          <?= birkly_icon('plus', ['size' => 16]) ?>
          <?= __('admin.connections_panel_add_credential_btn') ?>
        </button>
      </div>
      <div class="card-body u-stack-4">
        <div id="sys-conn-http-list">
          <div class="loading-state"><p><?= __('admin.connections_panel_loading_credentials') ?></p></div>
        </div>
      </div>
    </section>
  </div>
</div>

<!-- Email setup wizard -->
<div class="modal is-sheet is-wide" id="sys-conn-email-modal" role="dialog" aria-labelledby="sys-conn-email-modal-title" aria-modal="true">
  <div class="modal-content modal--md">
    <div class="modal-header">
      <h2 id="sys-conn-email-modal-title"><?= __('admin.connections_panel_wizard_title') ?></h2>
      <button type="button" class="icon-button modal-close" data-sys-conn-action="close-email-modal" aria-label="<?= htmlspecialchars(__('admin.ui_feedback_close')) ?>">
        <?= birkly_icon('x', ['size' => 16]) ?>
      </button>
    </div>
    <form id="sys-conn-email-form" class="modal-body conn-wizard">
      <input type="hidden" id="sys-conn-email-id" name="id">
      <input type="hidden" id="sys-conn-email-type" name="type" value="email">
      <input type="hidden" id="sys-conn-email-provider" name="provider" value="">

      <div class="conn-wizard-steps" role="tablist" aria-label="<?= htmlspecialchars(__('admin.connections_panel_wizard_steps_aria')) ?>">
        <span class="conn-wizard-step-indicator active" data-step="1"><?= __('admin.connections_panel_step_choose') ?></span>
        <span class="conn-wizard-step-indicator" data-step="2"><?= __('admin.connections_panel_step_details') ?></span>
        <span class="conn-wizard-step-indicator" data-step="3"><?= __('admin.connections_panel_step_test') ?></span>
      </div>

      <div id="sys-conn-wizard-error" class="message message-error" role="alert" hidden></div>

      <!-- Step 1: Provider picker -->
      <section class="conn-wizard-panel" data-wizard-step="1">
        <p class="conn-wizard-intro"><?= __('admin.connections_panel_wizard_intro_choose') ?></p>
        <div id="sys-conn-provider-grid" class="conn-provider-grid" role="listbox" aria-label="Email sending methods"></div>
        <p class="form-help"><?= __('admin.connections_panel_wizard_pick_help_before') ?><?= __('admin.connections_panel_wizard_pick_help_after') ?></p>
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
          <label for="sys-conn-email-name"><?= __('admin.connections_panel_connection_name_label') ?></label>
          <input type="text" id="sys-conn-email-name" name="name" class="input" required placeholder="<?= htmlspecialchars(__('admin.connections_panel_connection_name_ph')) ?>">
          <small class="form-help"><?= __('admin.connections_panel_connection_name_help') ?></small>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label for="sys-conn-email-from"><?= __('admin.connections_panel_from_email_label') ?></label>
            <input type="email" id="sys-conn-email-from" name="from_email" class="input" required placeholder="<?= htmlspecialchars(__('admin.connections_panel_from_email_ph')) ?>">
            <small class="form-help"><?= __('admin.connections_panel_from_email_help') ?></small>
          </div>
          <div class="form-group">
            <label for="sys-conn-email-from-name"><?= __('admin.connections_panel_from_name_label') ?></label>
            <input type="text" id="sys-conn-email-from-name" name="from_name" class="input" placeholder="<?= htmlspecialchars(__('admin.connections_panel_from_name_ph')) ?>">
          </div>
        </div>

        <div id="sys-conn-provider-fields" class="u-stack-3"></div>

        <details class="sys-conn-advanced">
          <summary><?= __('admin.connections_panel_advanced_options') ?></summary>
          <div class="u-stack-3 u-pt-3">
            <div class="form-group">
              <label for="sys-conn-email-alias"><?= __('admin.connections_panel_alias_label') ?></label>
              <input type="text" id="sys-conn-email-alias" name="alias" class="input" pattern="[a-z0-9_.-]+" placeholder="<?= htmlspecialchars(__('admin.connections_panel_alias_ph')) ?>">
              <small class="form-help"><?= __('admin.connections_panel_alias_help') ?></small>
            </div>
            <label class="check">
              <input type="checkbox" id="sys-conn-email-enabled" name="enabled" value="1" checked>
              <span><?= __('admin.connections_panel_connection_enabled') ?></span>
            </label>
          </div>
        </details>
      </section>

      <!-- Step 3: Test & save -->
      <section class="conn-wizard-panel" data-wizard-step="3" hidden>
        <p class="conn-wizard-intro"><?= __('admin.connections_panel_wizard_intro_test') ?></p>
        <div class="form-group">
          <label for="sys-conn-test-email"><?= __('admin.connections_panel_send_test_label') ?></label>
          <div class="input-with-action">
            <input type="email" id="sys-conn-test-email" class="input" placeholder="<?= htmlspecialchars(__('admin.connections_panel_test_email_ph')) ?>" autocomplete="email">
            <button type="button" class="button" id="sys-conn-send-test-btn"><?= __('admin.connections_panel_send_test_btn') ?></button>
          </div>
          <p id="sys-conn-test-status" class="form-help sys-conn-test-status" aria-live="polite"></p>
        </div>

        <label class="check">
          <input type="checkbox" id="sys-conn-set-default" name="set_default" value="1" checked>
          <span><?= __('admin.connections_panel_default_checkbox_before') ?><strong><?= __('admin.connections_panel_default_checkbox_strong') ?></strong><?= __('admin.connections_panel_default_checkbox_after') ?></span>
        </label>

        <p class="form-help"><?= __('admin.connections_panel_default_help_later') ?></p>
      </section>

      <div class="conn-wizard-actions modal-footer">
        <button type="button" class="button" data-sys-conn-action="close-email-modal"><?= __('admin.cancel') ?></button>
        <button type="button" class="button" id="sys-conn-wizard-back" hidden><?= __('admin.connections_panel_wizard_back') ?></button>
        <button type="button" class="button is-main" id="sys-conn-wizard-next"><?= __('admin.connections_panel_wizard_continue') ?></button>
        <button type="submit" class="button is-main" id="sys-conn-wizard-save" hidden><?= __('admin.connections_panel_save_connection') ?></button>
      </div>
    </form>
  </div>
</div>

<!-- HTTP credential modal (simpler form) -->
<div class="modal is-sheet is-wide" id="sys-conn-http-modal" role="dialog" aria-labelledby="sys-conn-http-modal-title" aria-modal="true">
  <div class="modal-content modal--md">
    <div class="modal-header">
      <h2 id="sys-conn-http-modal-title"><?= __('admin.connections_panel_add_http_credential') ?></h2>
      <button type="button" class="icon-button modal-close" data-sys-conn-action="close-http-modal" aria-label="<?= htmlspecialchars(__('admin.ui_feedback_close')) ?>">
        <?= birkly_icon('x', ['size' => 16]) ?>
      </button>
    </div>
    <form id="sys-conn-http-form" class="modal-body">
      <input type="hidden" id="sys-conn-http-id" name="id">
      <input type="hidden" id="sys-conn-http-type" name="type" value="http_credential">

      <div id="sys-conn-http-error" class="message message-error" role="alert" hidden></div>

      <div class="form-group">
        <label for="sys-conn-http-provider"><?= __('admin.connections_panel_http_credential_type') ?></label>
        <select id="sys-conn-http-provider" name="provider" class="input" required></select>
      </div>

      <div class="form-group">
        <label for="sys-conn-http-name"><?= __('admin.name') ?></label>
        <input type="text" id="sys-conn-http-name" name="name" class="input" required placeholder="<?= htmlspecialchars(__('admin.connections_panel_http_name_ph')) ?>">
      </div>

      <div class="form-group">
        <label for="sys-conn-http-alias"><?= __('admin.connections_panel_http_alias_label') ?></label>
        <input type="text" id="sys-conn-http-alias" name="alias" class="input" required pattern="[a-z0-9_.-]+" placeholder="<?= htmlspecialchars(__('admin.connections_panel_http_alias_ph')) ?>">
        <small class="form-help"><?= __('admin.connections_panel_http_alias_help') ?></small>
      </div>

      <div id="sys-conn-http-provider-fields" class="u-stack-3"></div>

      <label class="check">
        <input type="checkbox" id="sys-conn-http-enabled" name="enabled" value="1" checked>
        <span><?= __('admin.connections_panel_http_enabled') ?></span>
      </label>

      <div class="modal-footer">
        <button type="button" class="button" data-sys-conn-action="close-http-modal"><?= __('admin.cancel') ?></button>
        <button type="submit" class="button is-main" id="sys-conn-http-submit"><?= __('admin.connections_js_save_credential') ?></button>
      </div>
    </form>
  </div>
</div>
