<?php
/**
 * Shared AI connections panel (P60-M4 / P60.1).
 *
 * Mounted on Settings → AI — the sole Connections home (P62-D2). Legacy note: Settings → AI was
 * removed in P60.1 — this partial is the single home. The connection modules
 * (mcp-connections.js / ai-connections.js) init against these DOM ids.
 */
?>
        <div class="settings-stack ai-settings">
          <div id="ai-integrations" class="settings-pane-section" data-settings-search="ai assistant mcp connections service agents outbound personal connector"><div class="u-stack-6">
          <div class="section-header">
            <div class="u-stack-1">
              <h2 class="section-title"><?= htmlspecialchars(__('admin.ai_integrations')) ?></h2>
              <p class="settings-help"><?= htmlspecialchars(__('admin.ai_conn_panel_help')) ?></p>
            </div>
            <p class="u-cluster-3 u-text-sm">
              <a href="<?= htmlspecialchars(birkly_url('/admin/index.php?page=help')) ?>#mcp-connections"><?= htmlspecialchars(__('admin.ai_conn_step_guide')) ?></a>
              <a href="https://github.com/Raaakete/Birkly-Docs/blob/main/01-admin/mcp-connections.md" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars(__('admin.documentation')) ?></a>
            </p>
          </div>

          <div class="callout callout-warning" id="ai-https-warning" role="alert" hidden>
            <div class="callout-body u-stack-1">
              <p class="callout-title"><?= htmlspecialchars(__('admin.ai_conn_https_title')) ?></p>
              <p><?= htmlspecialchars(__('admin.ai_conn_https_body')) ?></p>
            </div>
          </div>

          <div class="card" id="ai-localhost-card">
            <div class="card-header">
              <div class="card-heading">
                <h3 class="card-title"><?= htmlspecialchars(__('admin.ai_conn_localhost_title')) ?></h3>
                <p class="card-description"><?= htmlspecialchars(__('admin.ai_conn_localhost_desc')) ?></p>
              </div>
            </div>
            <div class="card-body u-stack-3">
              <ul class="settings-list">
                <li><?= htmlspecialchars(__('admin.ai_conn_localhost_bullet_assistant')) ?></li>
                <li><?= htmlspecialchars(__('admin.ai_conn_localhost_bullet_remote')) ?></li>
                <li><?= htmlspecialchars(__('admin.ai_conn_localhost_bullet_bridge')) ?></li>
                <li><?= htmlspecialchars(__('admin.ai_conn_localhost_bullet_tunnel')) ?></li>
              </ul>
              <p class="form-help"><?= htmlspecialchars(__('admin.ai_conn_localhost_security_note')) ?></p>
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
              <h2 id="mcp-modal-title">Add AI agent</h2>
              <button class="btn-icon modal-close" data-mcp-modal-close aria-label="Close">
                <?= birkly_icon('x', ['size' => 16]) ?>
              </button>
            </div>
            <form id="add-mcp-form" class="modal-body">
              <fieldset class="u-stack-3">
                <legend class="u-label u-mb-2">What type of agent do you need?</legend>
                <div class="u-grid-auto-sm" role="radiogroup" aria-label="Agent type">
                  <label class="mcp-agent-type-option">
                    <input type="radio" name="agent_type" value="personal" id="mcp-agent-type-personal" checked>
                    <span class="mcp-agent-type-card">
                      <span class="mcp-agent-type-name">Personal <span class="badge badge-success">Dynamic</span></span>
                      <span class="mcp-agent-type-desc">Each user connects with <strong>their own</strong> Birkly role and permissions. One shared URL for the whole team.</span>
                    </span>
                  </label>
                  <label class="mcp-agent-type-option">
                    <input type="radio" name="agent_type" value="service" id="mcp-agent-type-service">
                    <span class="mcp-agent-type-card">
                      <span class="mcp-agent-type-name">Service <span class="badge badge-info">Fixed role</span></span>
                      <span class="mcp-agent-type-desc">A shared bot that <strong>always</strong> acts as a specific CMS role (e.g. always editor). You choose who can use it.</span>
                    </span>
                  </label>
                </div>
              </fieldset>

              <div id="mcp-personal-agent-panel" class="u-stack-4 u-mt-4">
                <p class="modal-intro-text">The personal connector is already set up for your site. Copy its URL and paste it in Claude, ChatGPT, or Cursor — each team member signs in with their own account.</p>
                <div class="mcp-snippet-block">
                  <span class="u-label u-text-sm">Personal connector URL</span>
                  <div class="connector-url-row" id="mcp-modal-personal-url-row">
                    <button type="button" class="connector-url-value" data-copy-source="mcp-modal-personal-url-text" title="Click to copy">
                      <span id="mcp-modal-personal-url-text" class="connector-url-text">Loading...</span>
                    </button>
                    <button type="button" class="btn-icon connector-url-copy-icon" data-copy-source="mcp-modal-personal-url-text" aria-label="Copy connector URL">
                      <?= birkly_icon('copy', ['size' => 16]) ?>
                    </button>
                  </div>
                </div>
              </div>

              <div id="mcp-service-agent-panel" class="u-stack-4 u-mt-4" hidden>
                <p class="modal-intro-text">Create a shared service agent with a fixed CMS role. Choose who can see and connect to it.</p>

                <div class="u-stack-2" role="group" aria-label="Service agent presets">
                  <div class="u-cluster">
                    <span class="u-label">Quick presets</span>
                    <button type="button" class="btn btn-sm btn-secondary mcp-preset-btn" data-mcp-preset="content">Content assistant</button>
                    <button type="button" class="btn btn-sm btn-secondary mcp-preset-btn" data-mcp-preset="site-builder">Site builder</button>
                  </div>
                  <small class="form-help">Site builder uses the <strong>Admin</strong> role so connected AI can read and write <code>project/</code> HTML, CSS, and JS alongside CMS content.</small>
                </div>

                <div class="form-group">
                  <label for="mcp-endpoint-name">Agent name</label>
                  <input type="text" id="mcp-endpoint-name" name="name" class="input" placeholder="e.g. Content assistant">
                  <small>A name you'll recognize later</small>
                </div>

                <div class="form-group">
                  <label for="mcp-agent-description">Description <span class="u-fg-tertiary">(optional)</span></label>
                  <input type="text" id="mcp-agent-description" name="description" class="input" placeholder="e.g. Helps draft blog posts">
                </div>

                <div class="form-group">
                  <label for="mcp-endpoint-role">
                    Fixed CMS role
                    <button type="button" class="help-tip" aria-label="Role help"
                      data-tip="Viewer = read only. Editor = suggest changes (often needs your approval). Admin = full access. This role applies to everyone who connects to this service agent.">?</button>
                  </label>
                  <select id="mcp-endpoint-role" name="role" class="input">
                    <option value="">Loading roles...</option>
                  </select>
                </div>

                <div class="form-group">
                  <label>Who can use this agent</label>
                  <small class="form-help">Admins always see all service agents. Select roles and/or individual users who may connect.</small>
                  <div id="mcp-visible-roles" class="mcp-checkbox-group" role="group" aria-label="Roles that can use this agent">
                    <div class="loading-inline">Loading roles...</div>
                  </div>
                </div>

                <div class="form-group">
                  <label>Individual users <span class="u-fg-tertiary">(optional)</span></label>
                  <div id="mcp-allowed-users" class="mcp-checkbox-group" role="group" aria-label="Users that can use this agent">
                    <div class="loading-inline">Loading users...</div>
                  </div>
                </div>

                <p class="settings-help">Write approvals are handled in the AI app that connects (e.g. Claude tool permissions), not in Birkly.</p>
              </div>

              <input type="hidden" id="mcp-auth-mode" name="auth_mode" value="oauth">

              <div id="mcp-create-success" class="callout callout-success u-stack-3" hidden>
                <p><strong>Service agent created!</strong> Copy the Connector URL and paste it in your AI app. Leave Client ID and Client Secret empty.</p>
                <div class="mcp-snippet-block">
                  <span class="u-label u-text-sm">Connector URL</span>
                  <div class="connector-url-row" id="mcp-new-connector-url-row">
                    <button type="button" class="connector-url-value" data-copy-source="mcp-new-connector-url-text" title="Click to copy">
                      <span id="mcp-new-connector-url-text" class="connector-url-text"></span>
                    </button>
                    <button type="button" class="btn-icon connector-url-copy-icon" data-copy-source="mcp-new-connector-url-text" aria-label="Copy connector URL">
                      <?= birkly_icon('copy', ['size' => 16]) ?>
                    </button>
                  </div>
                </div>
              </div>

              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-mcp-modal-close>Cancel</button>
                <button type="button" class="btn btn-primary" id="mcp-personal-done-btn">Use personal connector</button>
                <button type="submit" class="btn btn-primary" id="mcp-submit-btn" hidden>Create service agent</button>
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
