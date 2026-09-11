<?php
/**
 * Shared AI connections panel (P60-M4 / P60.1).
 *
 * Mounted on Settings → AI — the sole Connections home (P62-D2). Legacy note: Settings → AI was
 * removed in P60.1 — this partial is the single home. The connection modules
 * (mcp-connections.js / ai-connections.js) init against these DOM ids.
 */
$aiPanelRich = static function (string $key, string $allowed = '<code><strong><em><a>') {
    return strip_tags(__('admin.' . $key), $allowed);
};
$aiPanelText = static function (string $key): string {
    return htmlspecialchars(__('admin.' . $key), ENT_QUOTES, 'UTF-8');
};
?>
        <div class="settings-stack ai-settings">
          <div id="ai-integrations" class="settings-pane-section" data-settings-search="ai assistant mcp connections service agents outbound personal connector"><div class="u-stack-6">
          <div class="section-header">
            <div class="u-stack-1">
              <h2 class="section-title"><?= $aiPanelText('ai_panel_section_title') ?></h2>
              <p class="settings-help"><?= $aiPanelText('ai_panel_section_help') ?></p>
            </div>
            <p class="u-cluster-3 u-text-sm">
              <a href="<?= htmlspecialchars(birkly_url('/admin/index.php?page=help')) ?>#mcp-connections"><?= $aiPanelText('ai_panel_step_by_step_guide') ?></a>
              <a href="https://github.com/Raaakete/Birkly-Docs/blob/main/01-admin/mcp-connections.md" target="_blank" rel="noopener noreferrer"><?= $aiPanelText('documentation') ?></a>
            </p>
          </div>

          <div class="callout callout-warning" id="ai-https-warning" role="alert" hidden>
            <div class="callout-body u-stack-1">
              <p class="callout-title"><?= $aiPanelText('ai_panel_https_title') ?></p>
              <p><?= $aiPanelRich('ai_panel_https_body') ?></p>
            </div>
          </div>

          <div class="card" id="ai-localhost-card">
            <div class="card-header">
              <div class="card-heading">
                <h3 class="card-title"><?= $aiPanelText('ai_panel_localhost_title') ?></h3>
                <p class="card-description"><?= $aiPanelRich('ai_panel_localhost_desc') ?></p>
              </div>
            </div>
            <div class="card-body u-stack-3">
              <ul class="settings-list">
                <li><?= $aiPanelRich('ai_panel_localhost_li_assistant') ?></li>
                <li><?= $aiPanelRich('ai_panel_localhost_li_remote') ?></li>
                <li><?= $aiPanelRich('ai_panel_localhost_li_bridge', '<code><strong><em><a>') ?></li>
                <li><?= $aiPanelRich('ai_panel_localhost_li_tunnel') ?></li>
              </ul>
              <p class="form-help"><?= $aiPanelText('ai_panel_localhost_security_help') ?></p>
            </div>
          </div>

          <div class="callout callout-info" id="ai-agents-migration-banner" role="status" hidden>
            <div class="callout-body u-stack-1">
              <p class="callout-title"><?= $aiPanelText('ai_panel_migration_title') ?></p>
              <p><?= $aiPanelRich('ai_panel_migration_body', '<em>') ?></p>
            </div>
          </div>

          <div class="ai-connection-kinds-grid u-grid-auto-lg">
            <div class="card" id="ai-kind-personal">
              <div class="card-header">
                <div class="card-heading">
                  <h3 class="card-title"><?= $aiPanelText('ai_panel_personal_title') ?></h3>
                  <p class="card-description"><?= $aiPanelText('ai_panel_personal_desc') ?></p>
                </div>
              </div>
              <div id="ai-personal-agent-card" class="card-body">
                <div class="loading-state"><p><?= htmlspecialchars(__('admin.ai_connections_loading_personal'), ENT_QUOTES, 'UTF-8') ?></p></div>
              </div>
            </div>

            <div class="card" id="ai-kind-service">
              <div class="card-header">
                <div class="u-stack-1">
                  <h3 class="card-title"><?= $aiPanelText('ai_panel_service_title') ?></h3>
                  <p class="card-description" id="ai-service-intro"><?= $aiPanelText('ai_panel_service_intro') ?></p>
                </div>
                <button class="btn btn-primary" id="add-mcp-endpoint-btn" type="button" hidden>
                  <?= birkly_icon('plus', ['size' => 16]) ?>
                  <?= $aiPanelText('ai_panel_add_agent') ?>
                </button>
              </div>
              <div class="connections-list card-body" id="ai-service-agents-list">
                <div class="loading-state"><p><?= htmlspecialchars(__('admin.ai_connections_loading'), ENT_QUOTES, 'UTF-8') ?></p></div>
              </div>
            </div>

            <div class="card" id="ai-kind-assistant">
              <div class="card-header">
                <div class="u-stack-1">
                  <h3 class="card-title"><?= $aiPanelText('ai_panel_assistant_title') ?></h3>
                  <p class="card-description"><?= $aiPanelText('ai_panel_assistant_desc') ?></p>
                </div>
                <button class="btn btn-primary" id="add-connection-btn" type="button">
                  <?= birkly_icon('plus', ['size' => 16]) ?>
                  <?= $aiPanelText('ai_panel_quick_connect') ?>
                </button>
              </div>
              <div class="connections-list card-body" id="connections-list">
                <div class="loading-state"><p><?= htmlspecialchars(__('admin.ai_connections_loading'), ENT_QUOTES, 'UTF-8') ?></p></div>
              </div>
            </div>
          </div>

          <details class="card u-p-4">
            <summary class="u-clickable u-weight-medium"><?= $aiPanelText('ai_panel_dev_summary') ?></summary>
            <div class="u-stack-2 u-pt-3 u-text-sm u-fg-secondary">
              <p><?= $aiPanelText('ai_panel_dev_lead') ?></p>
              <p><?= $aiPanelRich('ai_panel_dev_bridge', '<a>') ?></p>
            </div>
          </details>
        </div>

        <!-- Built-in assistant quick connect modal -->
        <div class="modal" id="add-connection-modal">
          <div class="modal-content modal--md">
            <div class="modal-header">
              <h2 id="connection-modal-title"><?= $aiPanelText('ai_conn_modal_quick_connect') ?></h2>
              <button class="btn-icon modal-close" id="close-modal-btn">
                <?= birkly_icon('x', ['size' => 16]) ?>
              </button>
            </div>
            <form id="add-connection-form" class="modal-body">
              <input type="hidden" id="connection-id" name="id">

              <div id="wizard-error" class="message message-error" role="alert" hidden></div>

              <p class="modal-intro-text" id="assistant-modal-intro"><?= $aiPanelText('ai_panel_assistant_modal_intro') ?></p>

              <div class="form-group">
                <label for="connection-provider"><?= __('admin.ai_provider') ?></label>
                <select id="connection-provider" name="provider" class="input" required>
                  <option value=""><?= $aiPanelText('ai_conn_select_provider') ?></option>
                </select>
              </div>

              <div class="form-group">
                <label for="connection-name"><?= __('admin.connection_name') ?></label>
                <input type="text" id="connection-name" name="name" class="input" placeholder="<?= __('admin.my_ai_assistant_example') ?>">
                <small><?= __('admin.connection_friendly_name_help') ?></small>
              </div>

              <div class="form-group" id="api-url-group" hidden>
                <label for="connection-api-url"><?= __('admin.api_url') ?></label>
                <input type="url" id="connection-api-url" name="api_url" class="input" placeholder="https://api.example.com/v1/chat/completions">
                <small id="api-url-hint"><?= $aiPanelText('ai_panel_api_url_hint') ?></small>
              </div>

              <div class="form-group" id="api-key-group">
                <label for="connection-api-key"><?= __('admin.api_key') ?></label>
                <input type="password" id="connection-api-key" name="api_key" class="input" autocomplete="off">
                <small><?= __('admin.api_key_help') ?></small>
              </div>

              <div class="form-group" id="ollama-hint" hidden>
                <div class="callout callout-info">
                  <div class="callout-body u-stack-1">
                    <p class="callout-title"><?= $aiPanelText('ai_panel_ollama_title') ?></p>
                    <p><?= $aiPanelRich('ai_panel_ollama_body') ?></p>
                  </div>
                </div>
              </div>

              <div class="form-group" id="test-connection-group">
                <button type="button" class="btn btn-secondary" id="test-connection-btn"><?= $aiPanelText('ai_panel_test_connection') ?></button>
                <span id="test-connection-status" class="test-connection-status"></span>
              </div>

              <div class="form-group" id="connection-model-group">
                <label for="connection-model-search"><?= __('admin.model') ?></label>
                <div class="u-stack-2" id="connection-model-picker">
                  <div class="u-cluster">
                    <input type="search" id="connection-model-search" class="input" placeholder="<?= $aiPanelText('ai_panel_search_models') ?>" autocomplete="off" aria-controls="connection-model-list">
                    <button type="button" class="btn btn-sm btn-secondary" id="connection-model-refresh"><?= $aiPanelText('ai_panel_refresh_models') ?></button>
                  </div>
                  <input type="hidden" id="connection-model" name="model" value="">
                  <div id="connection-model-list" class="ai-model-picker-list" role="listbox" aria-label="<?= $aiPanelText('ai_panel_models_list_aria') ?>"></div>
                  <p id="connection-model-status" class="form-help ai-model-picker-status"></p>
                </div>
                <input type="text" id="connection-model-custom" name="model_custom" class="input" placeholder="<?= __('admin.enter_model_name') ?>">
                <small class="form-help"><?= $aiPanelText('ai_panel_models_help') ?></small>
              </div>

              <div class="form-group">
                <label for="connection-role"><?= __('admin.role') ?></label>
                <select id="connection-role" name="role" class="input" required>
                  <option value=""><?= htmlspecialchars(__('admin.mcp_loading_roles'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
              </div>

              <input type="hidden" id="connection-shared-type" name="shared_type" value="none">

              <div class="form-group">
                <label class="check">
                  <input type="checkbox" id="connection-require-approval" name="require_approval" value="1" checked>
                  <span><?= $aiPanelText('ai_panel_require_approval') ?></span>
                </label>
                <small><?= __('admin.ai_approval_mode_help') ?></small>
              </div>

              <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close"><?= __('admin.cancel') ?></button>
                <button type="submit" class="btn btn-primary" id="connection-submit-btn"><?= __('admin.connect') ?></button>
              </div>
            </form>
          </div>
        </div>

        <!-- Add agent modal -->
        <div class="modal" id="add-mcp-modal">
          <div class="modal-content modal--md">
            <div class="modal-header">
              <h2 id="mcp-modal-title"><?= $aiPanelText('ai_panel_mcp_modal_title') ?></h2>
              <button class="btn-icon modal-close" data-mcp-modal-close aria-label="<?= $aiPanelText('close') ?>">
                <?= birkly_icon('x', ['size' => 16]) ?>
              </button>
            </div>
            <form id="add-mcp-form" class="modal-body">
              <fieldset class="u-stack-3">
                <legend class="u-label u-mb-2"><?= $aiPanelText('ai_panel_mcp_type_legend') ?></legend>
                <div class="u-grid-auto-sm" role="radiogroup" aria-label="<?= $aiPanelText('ai_panel_mcp_type_aria') ?>">
                  <label class="mcp-agent-type-option">
                    <input type="radio" name="agent_type" value="personal" id="mcp-agent-type-personal" checked>
                    <span class="mcp-agent-type-card">
                      <span class="mcp-agent-type-name"><?= $aiPanelText('ai_panel_mcp_type_personal_name') ?> <span class="badge badge-success"><?= $aiPanelText('ai_panel_mcp_badge_dynamic') ?></span></span>
                      <span class="mcp-agent-type-desc"><?= $aiPanelRich('ai_panel_mcp_type_personal_desc', '<strong>') ?></span>
                    </span>
                  </label>
                  <label class="mcp-agent-type-option">
                    <input type="radio" name="agent_type" value="service" id="mcp-agent-type-service">
                    <span class="mcp-agent-type-card">
                      <span class="mcp-agent-type-name"><?= $aiPanelText('ai_panel_mcp_type_service_name') ?> <span class="badge badge-info"><?= $aiPanelText('ai_panel_mcp_badge_fixed') ?></span></span>
                      <span class="mcp-agent-type-desc"><?= $aiPanelRich('ai_panel_mcp_type_service_desc', '<strong>') ?></span>
                    </span>
                  </label>
                </div>
              </fieldset>

              <div id="mcp-personal-agent-panel" class="u-stack-4 u-mt-4">
                <p class="modal-intro-text"><?= $aiPanelText('ai_panel_mcp_personal_intro') ?></p>
                <div class="mcp-snippet-block">
                  <span class="u-label u-text-sm"><?= $aiPanelText('ai_panel_mcp_personal_url_label') ?></span>
                  <div class="connector-url-row" id="mcp-modal-personal-url-row">
                    <button type="button" class="connector-url-value" data-copy-source="mcp-modal-personal-url-text" title="<?= $aiPanelText('mcp_copy_click_title') ?>">
                      <span id="mcp-modal-personal-url-text" class="connector-url-text"><?= htmlspecialchars(__('admin.mcp_loading'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                    <button type="button" class="btn-icon connector-url-copy-icon" data-copy-source="mcp-modal-personal-url-text" aria-label="<?= $aiPanelText('mcp_copy_url_aria') ?>">
                      <?= birkly_icon('copy', ['size' => 16]) ?>
                    </button>
                  </div>
                </div>
              </div>

              <div id="mcp-service-agent-panel" class="u-stack-4 u-mt-4" hidden>
                <p class="modal-intro-text"><?= $aiPanelText('ai_panel_mcp_service_intro') ?></p>

                <div class="u-stack-2" role="group" aria-label="<?= $aiPanelText('ai_panel_mcp_presets_label') ?>">
                  <div class="u-cluster">
                    <span class="u-label"><?= $aiPanelText('ai_panel_mcp_presets_label') ?></span>
                    <button type="button" class="btn btn-sm btn-secondary mcp-preset-btn" data-mcp-preset="content"><?= $aiPanelText('mcp_preset_content_name') ?></button>
                    <button type="button" class="btn btn-sm btn-secondary mcp-preset-btn" data-mcp-preset="site-builder"><?= $aiPanelText('mcp_preset_site_builder_name') ?></button>
                  </div>
                  <small class="form-help"><?= $aiPanelRich('ai_panel_mcp_presets_help', '<strong><code>') ?></small>
                </div>

                <div class="form-group">
                  <label for="mcp-endpoint-name"><?= $aiPanelText('ai_panel_mcp_agent_name') ?></label>
                  <input type="text" id="mcp-endpoint-name" name="name" class="input" placeholder="<?= $aiPanelText('ai_panel_mcp_agent_name_placeholder') ?>">
                  <small><?= $aiPanelText('ai_panel_mcp_agent_name_help') ?></small>
                </div>

                <div class="form-group">
                  <label for="mcp-agent-description"><?= $aiPanelText('ai_panel_mcp_description') ?> <span class="u-fg-tertiary"><?= $aiPanelText('ai_panel_mcp_optional') ?></span></label>
                  <input type="text" id="mcp-agent-description" name="description" class="input" placeholder="<?= $aiPanelText('ai_panel_mcp_description_placeholder') ?>">
                </div>

                <div class="form-group">
                  <label for="mcp-endpoint-role">
                    <?= $aiPanelText('ai_panel_mcp_fixed_role') ?>
                    <button type="button" class="help-tip" aria-label="<?= $aiPanelText('ai_panel_mcp_role_help_aria') ?>"
                      data-tip="<?= $aiPanelText('ai_panel_mcp_role_help_tip') ?>">?</button>
                  </label>
                  <select id="mcp-endpoint-role" name="role" class="input">
                    <option value=""><?= htmlspecialchars(__('admin.mcp_loading_roles'), ENT_QUOTES, 'UTF-8') ?></option>
                  </select>
                </div>

                <div class="form-group">
                  <label><?= $aiPanelText('ai_panel_mcp_who_can_use') ?></label>
                  <small class="form-help"><?= $aiPanelText('ai_panel_mcp_who_can_use_help') ?></small>
                  <div id="mcp-visible-roles" class="mcp-checkbox-group" role="group" aria-label="<?= $aiPanelText('ai_panel_mcp_visible_roles_aria') ?>">
                    <div class="loading-inline"><?= htmlspecialchars(__('admin.mcp_loading_roles'), ENT_QUOTES, 'UTF-8') ?></div>
                  </div>
                </div>

                <div class="form-group">
                  <label><?= $aiPanelText('ai_panel_mcp_individual_users') ?> <span class="u-fg-tertiary"><?= $aiPanelText('ai_panel_mcp_optional') ?></span></label>
                  <div id="mcp-allowed-users" class="mcp-checkbox-group" role="group" aria-label="<?= $aiPanelText('ai_panel_mcp_allowed_users_aria') ?>">
                    <div class="loading-inline"><?= htmlspecialchars(__('admin.mcp_loading_users'), ENT_QUOTES, 'UTF-8') ?></div>
                  </div>
                </div>

                <p class="settings-help"><?= $aiPanelText('ai_panel_mcp_approvals_note') ?></p>
              </div>

              <input type="hidden" id="mcp-auth-mode" name="auth_mode" value="oauth">

              <div id="mcp-create-success" class="callout callout-success u-stack-3" hidden>
                <p><strong><?= $aiPanelText('ai_panel_mcp_created_title') ?></strong> <?= $aiPanelText('ai_panel_mcp_created_body') ?></p>
                <div class="mcp-snippet-block">
                  <span class="u-label u-text-sm"><?= $aiPanelText('mcp_connector_url_label') ?></span>
                  <div class="connector-url-row" id="mcp-new-connector-url-row">
                    <button type="button" class="connector-url-value" data-copy-source="mcp-new-connector-url-text" title="<?= $aiPanelText('mcp_copy_click_title') ?>">
                      <span id="mcp-new-connector-url-text" class="connector-url-text"></span>
                    </button>
                    <button type="button" class="btn-icon connector-url-copy-icon" data-copy-source="mcp-new-connector-url-text" aria-label="<?= $aiPanelText('mcp_copy_url_aria') ?>">
                      <?= birkly_icon('copy', ['size' => 16]) ?>
                    </button>
                  </div>
                </div>
              </div>

              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-mcp-modal-close><?= __('admin.cancel') ?></button>
                <button type="button" class="btn btn-primary" id="mcp-personal-done-btn"><?= $aiPanelText('ai_panel_mcp_use_personal') ?></button>
                <button type="submit" class="btn btn-primary" id="mcp-submit-btn" hidden><?= $aiPanelText('mcp_btn_create_service_agent') ?></button>
              </div>
            </form>
          </div>
        </div></div>
        </div>

<!-- First-visit explainer — connection methods on Settings → AI -->
<div id="ai-connections-intro-modal" class="modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="ai-connections-intro-title">
  <div class="modal-backdrop" data-ai-conn-intro-close></div>
  <div class="modal-content modal--md">
    <div class="modal-header">
      <h2 id="ai-connections-intro-title" class="modal-title"><?= htmlspecialchars(__('admin.ai_connections_intro_title')) ?></h2>
      <button type="button" class="modal-close" data-ai-conn-intro-close aria-label="<?= htmlspecialchars(__('admin.close')) ?>">&times;</button>
    </div>
    <div class="modal-body u-stack-4">
      <p class="u-m-0"><?= htmlspecialchars(__('admin.ai_connections_intro_body')) ?></p>
      <div class="u-stack-3">
        <div class="card-section u-stack-1">
          <h3 class="u-text-sm u-weight-semibold u-m-0"><?= htmlspecialchars(__('admin.ai_connections_intro_personal_title')) ?></h3>
          <p class="u-text-sm u-m-0"><?= htmlspecialchars(__('admin.ai_connections_intro_personal_body')) ?></p>
        </div>
        <div class="card-section u-stack-1">
          <h3 class="u-text-sm u-weight-semibold u-m-0"><?= htmlspecialchars(__('admin.ai_connections_intro_service_title')) ?></h3>
          <p class="u-text-sm u-m-0"><?= htmlspecialchars(__('admin.ai_connections_intro_service_body')) ?></p>
        </div>
        <div class="card-section u-stack-1">
          <h3 class="u-text-sm u-weight-semibold u-m-0"><?= htmlspecialchars(__('admin.ai_connections_intro_assistant_title')) ?></h3>
          <p class="u-text-sm u-m-0"><?= htmlspecialchars(__('admin.ai_connections_intro_assistant_body')) ?></p>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-primary" data-ai-conn-intro-close><?= htmlspecialchars(__('admin.ai_connections_intro_got_it')) ?></button>
    </div>
  </div>
</div>
