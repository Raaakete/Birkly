<?php
/**
 * Shared AI connections panel (P60-M4 / P60.1).
 *
 * Mounted on Settings → AI — the sole Connections home (P62-D2). Legacy note: Settings → AI was
 * removed in P60.1 — this partial is the single home. The connection modules
 * (mcp-connections.js / ai-connections.js) init against these DOM ids.
 */
if (!function_exists('birkly_icon')) {
    require_once __DIR__ . '/../../core/icons.php';
}
$aiConnSettingsSearch = __('admin.ai_conn_settings_search');
$helpMcpConnectionsUrl = birkly_url('/admin/index.php?page=help&topic=mcp-connections');
$helpAiLocalUrl = birkly_url('/admin/index.php?page=help&topic=ai-on-localhost');
?>
        <div class="settings-stack ai-settings">
          <div id="ai-integrations" class="settings-pane-section" data-settings-search="<?= htmlspecialchars($aiConnSettingsSearch) ?>"><div class="u-stack-6">
          <div class="section-header">
            <div class="u-stack-1">
              <h2 class="section-title"><?= htmlspecialchars(__('admin.ai_integrations')) ?></h2>
              <p class="settings-help"><?= htmlspecialchars(__('admin.ai_conn_panel_help')) ?></p>
            </div>
            <p class="u-cluster-3 u-text-sm">
              <a href="<?= htmlspecialchars($helpMcpConnectionsUrl) ?>"><?= htmlspecialchars(__('admin.ai_conn_step_guide')) ?></a>
              <a href="<?= htmlspecialchars($helpMcpConnectionsUrl) ?>"><?= htmlspecialchars(__('admin.documentation')) ?></a>
            </p>
          </div>

          <div class="callout callout-info" id="ai-local-address-callout" role="status" hidden>
            <div class="callout-body u-stack-2">
              <p class="callout-title"><?= htmlspecialchars(__('admin.ai_conn_local_address_title')) ?></p>
              <p class="u-text-sm"><?= htmlspecialchars(__('admin.ai_conn_local_address_body')) ?></p>
              <p class="u-text-sm">
                <a href="<?= htmlspecialchars($helpAiLocalUrl) ?>"><?= htmlspecialchars(__('admin.ai_conn_local_address_help_link')) ?></a>
              </p>
            </div>
          </div>

          <div class="card desktop-mcp-card" id="ai-desktop-connector-card" hidden>
            <div class="card-header">
              <div class="u-stack-1">
                <h3 class="card-title"><?= htmlspecialchars(__('admin.ai_conn_desktop_title')) ?></h3>
                <p class="card-description"><?= htmlspecialchars(__('admin.ai_conn_desktop_desc')) ?></p>
              </div>
            </div>
            <div class="card-body u-stack-4" id="ai-desktop-connector-body">
              <div class="loading-state"><p><?= htmlspecialchars(__('admin.ai_conn_desktop_loading')) ?></p></div>
            </div>
          </div>

          <div class="callout callout-info" id="ai-agents-migration-banner" role="status" hidden>
            <div class="callout-body u-stack-1">
              <p class="callout-title"><?= htmlspecialchars(__('admin.ai_conn_migration_title')) ?></p>
              <p><?= htmlspecialchars(__('admin.ai_conn_migration_body')) ?></p>
            </div>
          </div>

          <div class="ai-connection-kinds-grid u-grid-auto-lg">
            <div class="card" id="ai-kind-personal">
              <div class="card-header">
                <div class="card-heading">
                  <h3 class="card-title"><?= htmlspecialchars(__('admin.ai_connections_intro_personal_title')) ?></h3>
                  <p class="card-description"><?= htmlspecialchars(__('admin.ai_connections_intro_personal_body')) ?></p>
                </div>
              </div>
              <div id="ai-personal-agent-card" class="card-body">
                <div class="loading-state"><p><?= htmlspecialchars(__('admin.ai_conn_loading_personal')) ?></p></div>
              </div>
            </div>

            <div class="card" id="ai-kind-service">
              <div class="card-header">
                <div class="u-stack-1">
                  <h3 class="card-title"><?= htmlspecialchars(__('admin.ai_connections_intro_service_title')) ?></h3>
                  <p class="card-description" id="ai-service-intro"><?= htmlspecialchars(__('admin.ai_conn_service_card_desc')) ?></p>
                </div>
                <button class="btn btn-primary" id="add-mcp-endpoint-btn" type="button" hidden>
                  <?= birkly_icon('plus', ['size' => 16]) ?>
                  <?= htmlspecialchars(__('admin.ai_conn_add_agent')) ?>
                </button>
              </div>
              <div class="connections-list card-body" id="ai-service-agents-list">
                <div class="loading-state"><p><?= htmlspecialchars(__('admin.ai_conn_loading_list')) ?></p></div>
              </div>
            </div>

            <div class="card" id="ai-kind-assistant">
              <div class="card-header">
                <div class="u-stack-1">
                  <h3 class="card-title"><?= htmlspecialchars(__('admin.ai_connections_intro_assistant_title')) ?></h3>
                  <p class="card-description"><?= htmlspecialchars(__('admin.ai_conn_assistant_card_desc')) ?></p>
                </div>
                <button class="btn btn-primary" id="add-connection-btn" type="button">
                  <?= birkly_icon('plus', ['size' => 16]) ?>
                  <?= htmlspecialchars(__('admin.ai_conn_quick_connect')) ?>
                </button>
              </div>
              <div class="connections-list card-body" id="connections-list">
                <div class="loading-state"><p><?= htmlspecialchars(__('admin.ai_conn_loading_list')) ?></p></div>
              </div>
            </div>
          </div>

          <details class="card u-p-4">
            <summary class="u-clickable u-weight-medium"><?= htmlspecialchars(__('admin.ai_conn_dev_summary')) ?></summary>
            <div class="u-stack-2 u-pt-3 u-text-sm u-fg-secondary">
              <p><?= htmlspecialchars(__('admin.ai_conn_dev_intro')) ?></p>
              <p><a href="#" id="mcp-bridge-dev-link" target="_blank" rel="noopener noreferrer">mcp-bridge.js</a> <?= htmlspecialchars(__('admin.ai_conn_dev_bridge_note')) ?></p>
            </div>
          </details>
        </div>

        <!-- Built-in assistant quick connect modal -->
        <div class="modal" id="add-connection-modal">
          <div class="modal-content modal--md">
            <div class="modal-header">
              <h2 id="connection-modal-title"><?= htmlspecialchars(__('admin.ai_conn_modal_quick_connect')) ?></h2>
              <button class="btn-icon modal-close" id="close-modal-btn">
                <?= birkly_icon('x', ['size' => 16]) ?>
              </button>
            </div>
            <form id="add-connection-form" class="modal-body">
              <input type="hidden" id="connection-id" name="id">

              <div id="wizard-error" class="message message-error" role="alert" hidden></div>

              <p class="modal-intro-text" id="assistant-modal-intro"><?= htmlspecialchars(__('admin.ai_conn_modal_assistant_intro')) ?></p>

              <div class="form-group">
                <label for="connection-provider"><?= __('admin.ai_provider') ?></label>
                <select id="connection-provider" name="provider" class="input" required>
                  <option value=""><?= htmlspecialchars(__('admin.ai_conn_select_provider')) ?></option>
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
                <small id="api-url-hint"><?= htmlspecialchars(__('admin.ai_conn_api_url_custom_help')) ?></small>
              </div>

              <div class="form-group" id="api-key-group">
                <label for="connection-api-key"><?= __('admin.api_key') ?></label>
                <input type="password" id="connection-api-key" name="api_key" class="input" autocomplete="off">
                <small><?= __('admin.api_key_help') ?></small>
              </div>

              <div class="form-group" id="ollama-hint" hidden>
                <div class="callout callout-info">
                  <div class="callout-body u-stack-1">
                    <p class="callout-title"><?= htmlspecialchars(__('admin.ai_conn_ollama_title')) ?></p>
                    <p><?= htmlspecialchars(__('admin.ai_conn_ollama_body')) ?></p>
                  </div>
                </div>
              </div>

              <div class="form-group" id="test-connection-group">
                <button type="button" class="btn btn-secondary" id="test-connection-btn"><?= htmlspecialchars(__('admin.test_connection')) ?></button>
                <span id="test-connection-status" class="test-connection-status"></span>
              </div>

              <div class="form-group" id="connection-model-group">
                <label for="connection-model-search"><?= __('admin.model') ?></label>
                <div class="u-stack-2" id="connection-model-picker">
                  <div class="u-cluster">
                    <input type="search" id="connection-model-search" class="input" placeholder="<?= htmlspecialchars(__('admin.ai_conn_search_models_placeholder')) ?>" autocomplete="off" aria-controls="connection-model-list">
                    <button type="button" class="btn btn-sm btn-secondary" id="connection-model-refresh"><?= htmlspecialchars(__('admin.refresh')) ?></button>
                  </div>
                  <input type="hidden" id="connection-model" name="model" value="">
                  <div id="connection-model-list" class="ai-model-picker-list" role="listbox" aria-label="<?= htmlspecialchars(__('admin.ai_conn_models_list_aria')) ?>"></div>
                  <p id="connection-model-status" class="form-help ai-model-picker-status"></p>
                </div>
                <input type="text" id="connection-model-custom" name="model_custom" class="input" placeholder="<?= __('admin.enter_model_name') ?>">
                <small class="form-help"><?= htmlspecialchars(__('admin.ai_conn_models_picker_help')) ?></small>
              </div>

              <div class="form-group">
                <label for="connection-role"><?= __('admin.role') ?></label>
                <select id="connection-role" name="role" class="input" required>
                  <option value=""><?= htmlspecialchars(__('admin.ai_conn_loading_roles')) ?></option>
                </select>
              </div>

              <input type="hidden" id="connection-shared-type" name="shared_type" value="none">

              <div class="form-group">
                <label class="check">
                  <input type="checkbox" id="connection-require-approval" name="require_approval" value="1" checked>
                  <span><?= htmlspecialchars(__('admin.ai_conn_require_approval_label')) ?></span>
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
              <h2 id="mcp-modal-title"><?= htmlspecialchars(__('admin.ai_mcp_modal_title')) ?></h2>
              <button class="btn-icon modal-close" data-mcp-modal-close aria-label="<?= htmlspecialchars(__('admin.close')) ?>">
                <?= birkly_icon('x', ['size' => 16]) ?>
              </button>
            </div>
            <form id="add-mcp-form" class="modal-body">
              <fieldset class="u-stack-3">
                <legend class="u-label u-mb-2"><?= htmlspecialchars(__('admin.ai_mcp_agent_type_legend')) ?></legend>
                <div class="u-grid-auto-sm" role="radiogroup" aria-label="<?= htmlspecialchars(__('admin.ai_mcp_agent_type_aria')) ?>">
                  <label class="mcp-agent-type-option">
                    <input type="radio" name="agent_type" value="personal" id="mcp-agent-type-personal" checked>
                    <span class="mcp-agent-type-card">
                      <span class="mcp-agent-type-name"><?= htmlspecialchars(__('admin.ai_mcp_personal_name')) ?> <span class="badge badge-success"><?= htmlspecialchars(__('admin.ai_mcp_badge_dynamic')) ?></span></span>
                      <span class="mcp-agent-type-desc"><?= htmlspecialchars(__('admin.ai_mcp_personal_desc')) ?></span>
                    </span>
                  </label>
                  <label class="mcp-agent-type-option">
                    <input type="radio" name="agent_type" value="service" id="mcp-agent-type-service">
                    <span class="mcp-agent-type-card">
                      <span class="mcp-agent-type-name"><?= htmlspecialchars(__('admin.ai_mcp_service_name')) ?> <span class="badge badge-info"><?= htmlspecialchars(__('admin.ai_mcp_badge_fixed_role')) ?></span></span>
                      <span class="mcp-agent-type-desc"><?= htmlspecialchars(__('admin.ai_mcp_service_desc')) ?></span>
                    </span>
                  </label>
                </div>
              </fieldset>

              <div id="mcp-personal-agent-panel" class="u-stack-4 u-mt-4">
                <p class="modal-intro-text"><?= htmlspecialchars(__('admin.ai_mcp_personal_panel_intro')) ?></p>
                <div class="mcp-snippet-block">
                  <span class="u-label u-text-sm"><?= htmlspecialchars(__('admin.ai_mcp_personal_url_label')) ?></span>
                  <div class="connector-url-row" id="mcp-modal-personal-url-row">
                    <button type="button" class="connector-url-value" data-copy-source="mcp-modal-personal-url-text" title="<?= htmlspecialchars(__('admin.ai_mcp_click_copy')) ?>">
                      <span id="mcp-modal-personal-url-text" class="connector-url-text"><?= htmlspecialchars(__('admin.ai_mcp_loading')) ?></span>
                    </button>
                    <button type="button" class="btn-icon connector-url-copy-icon" data-copy-source="mcp-modal-personal-url-text" aria-label="<?= htmlspecialchars(__('admin.ai_mcp_copy_url_aria')) ?>">
                      <?= birkly_icon('copy', ['size' => 16]) ?>
                    </button>
                  </div>
                </div>
              </div>

              <div id="mcp-service-agent-panel" class="u-stack-4 u-mt-4" hidden>
                <p class="modal-intro-text"><?= htmlspecialchars(__('admin.ai_mcp_service_panel_intro')) ?></p>

                <div class="u-stack-2" role="group" aria-label="<?= htmlspecialchars(__('admin.ai_mcp_quick_presets')) ?>">
                  <div class="u-cluster">
                    <span class="u-label"><?= htmlspecialchars(__('admin.ai_mcp_quick_presets')) ?></span>
                    <button type="button" class="btn btn-sm btn-secondary mcp-preset-btn" data-mcp-preset="content"><?= htmlspecialchars(__('admin.ai_mcp_preset_content')) ?></button>
                    <button type="button" class="btn btn-sm btn-secondary mcp-preset-btn" data-mcp-preset="site-builder"><?= htmlspecialchars(__('admin.ai_mcp_preset_site_builder')) ?></button>
                  </div>
                  <small class="form-help"><?= htmlspecialchars(__('admin.ai_mcp_preset_site_builder_help')) ?></small>
                </div>

                <div class="form-group">
                  <label for="mcp-endpoint-name"><?= htmlspecialchars(__('admin.ai_mcp_agent_name_label')) ?></label>
                  <input type="text" id="mcp-endpoint-name" name="name" class="input" placeholder="<?= htmlspecialchars(__('admin.ai_mcp_agent_name_placeholder')) ?>">
                  <small><?= htmlspecialchars(__('admin.ai_mcp_agent_name_help')) ?></small>
                </div>

                <div class="form-group">
                  <label for="mcp-agent-description"><?= htmlspecialchars(__('admin.ai_mcp_description_label')) ?> <span class="u-fg-tertiary">(<?= htmlspecialchars(__('admin.optional')) ?>)</span></label>
                  <input type="text" id="mcp-agent-description" name="description" class="input" placeholder="<?= htmlspecialchars(__('admin.ai_mcp_description_placeholder')) ?>">
                </div>

                <div class="form-group">
                  <label for="mcp-endpoint-role">
                    <?= htmlspecialchars(__('admin.ai_mcp_fixed_role_label')) ?>
                    <button type="button" class="help-tip" aria-label="<?= htmlspecialchars(__('admin.role')) ?>"
                      data-tip="<?= htmlspecialchars(__('admin.ai_mcp_role_help_tip')) ?>">?</button>
                  </label>
                  <select id="mcp-endpoint-role" name="role" class="input">
                    <option value=""><?= htmlspecialchars(__('admin.ai_conn_loading_roles')) ?></option>
                  </select>
                </div>

                <div class="form-group">
                  <label><?= htmlspecialchars(__('admin.ai_mcp_who_can_use')) ?></label>
                  <small class="form-help"><?= htmlspecialchars(__('admin.ai_mcp_who_can_use_help')) ?></small>
                  <div id="mcp-visible-roles" class="mcp-checkbox-group" role="group" aria-label="<?= htmlspecialchars(__('admin.ai_mcp_visible_roles_aria')) ?>">
                    <div class="loading-inline"><?= htmlspecialchars(__('admin.ai_conn_loading_roles')) ?></div>
                  </div>
                </div>

                <div class="form-group">
                  <label><?= htmlspecialchars(__('admin.ai_mcp_individual_users')) ?> <span class="u-fg-tertiary">(<?= htmlspecialchars(__('admin.optional')) ?>)</span></label>
                  <div id="mcp-allowed-users" class="mcp-checkbox-group" role="group" aria-label="<?= htmlspecialchars(__('admin.ai_mcp_allowed_users_aria')) ?>">
                    <div class="loading-inline"><?= htmlspecialchars(__('admin.ai_mcp_loading_users')) ?></div>
                  </div>
                </div>

                <p class="settings-help"><?= htmlspecialchars(__('admin.ai_mcp_write_approvals_settings')) ?></p>
              </div>

              <input type="hidden" id="mcp-auth-mode" name="auth_mode" value="oauth">

              <div id="mcp-create-success" class="callout callout-success u-stack-3" hidden>
                <p><strong><?= htmlspecialchars(__('admin.ai_mcp_create_success_title')) ?></strong> <?= htmlspecialchars(__('admin.ai_mcp_create_success_body')) ?></p>
                <div class="mcp-snippet-block">
                  <span class="u-label u-text-sm"><?= htmlspecialchars(__('admin.ai_mcp_connector_url_label')) ?></span>
                  <div class="connector-url-row" id="mcp-new-connector-url-row">
                    <button type="button" class="connector-url-value" data-copy-source="mcp-new-connector-url-text" title="<?= htmlspecialchars(__('admin.ai_mcp_click_copy')) ?>">
                      <span id="mcp-new-connector-url-text" class="connector-url-text"></span>
                    </button>
                    <button type="button" class="btn-icon connector-url-copy-icon" data-copy-source="mcp-new-connector-url-text" aria-label="<?= htmlspecialchars(__('admin.ai_mcp_copy_url_aria')) ?>">
                      <?= birkly_icon('copy', ['size' => 16]) ?>
                    </button>
                  </div>
                </div>
              </div>

              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-mcp-modal-close><?= htmlspecialchars(__('admin.cancel')) ?></button>
                <button type="button" class="btn btn-primary" id="mcp-personal-done-btn"><?= htmlspecialchars(__('admin.ai_mcp_use_personal')) ?></button>
                <button type="submit" class="btn btn-primary" id="mcp-submit-btn" hidden><?= htmlspecialchars(__('admin.ai_mcp_create_service')) ?></button>
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
