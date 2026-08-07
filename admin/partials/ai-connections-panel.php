<?php
/**
 * Shared AI connections panel (P60-M4 / P60.1).
 *
 * Mounted on Agent Guide → Connections (ai-presence.html). Settings → AI was
 * removed in P60.1 — this partial is the single home. The connection modules
 * (mcp-connections.js / ai-connections.js) init against these DOM ids.
 */
?>
        <div class="settings-stack ai-settings">
          <div id="ai-integrations" class="settings-pane-section" data-settings-search="ai assistant mcp connections service agents outbound personal connector"><div class="u-stack-6">
          <div class="section-header">
            <div class="u-stack-1">
              <h2 class="section-title">AI</h2>
              <p class="settings-help">Connect AI apps to Birkly or chat inside the admin.</p>
            </div>
            <p class="u-cluster-3 u-text-sm">
              <a href="<?= htmlspecialchars(birkly_url('/admin/index.php?page=help')) ?>#mcp-connections">Step-by-step guide</a>
              <a href="https://github.com/Raaakete/Birkly-Docs/blob/main/01-admin/mcp-connections.md" target="_blank" rel="noopener noreferrer">Documentation</a>
            </p>
          </div>

          <div class="callout callout-warning" id="ai-https-warning" role="alert" hidden>
            <div class="callout-body u-stack-1">
              <p class="callout-title">Claude requires an HTTPS Connector URL</p>
              <p>Claude, ChatGPT, and Cursor reject <code>http://</code> connector URLs. Open Birkly using <code>https://</code> on a public address, then copy the Connector URL shown here. HTTP and localhost URLs will not work in Claude.</p>
            </div>
          </div>

          <div class="card" id="ai-localhost-card">
            <div class="card-header">
              <div class="card-heading">
                <h3 class="card-title">Working locally?</h3>
                <p class="card-description">How AI connects when Birkly runs on <code>localhost</code> — without exposing admin insecurely.</p>
              </div>
            </div>
            <div class="card-body u-stack-3">
              <ul class="settings-list">
                <li><strong>Built-in assistant / Ollama on localhost</strong> — OK (outbound BYOAI or <code>http://localhost:11434</code>).</li>
                <li><strong>Remote connectors</strong> (Claude / ChatGPT / Cursor cloud) need a <strong>public HTTPS</strong> Connector URL — plain <code>http://localhost/mcp</code> is rejected.</li>
                <li><strong>mcp-bridge recipe</strong> — local stdio bridge for desktop AI tools: open <a href="#" data-mcp-bridge-link target="_blank" rel="noopener noreferrer">mcp-bridge.js</a> (also under Developer / debug below).</li>
                <li><strong>Tunnel recipe</strong> — <code>cloudflared</code> or ngrok → HTTPS → your local <code>/mcp</code>, then paste that HTTPS URL as the Connector URL.</li>
              </ul>
              <p class="form-help">Keep security warnings on: do not expose the admin panel to the public internet as a default. Prefer mcp-bridge or a short-lived tunnel.</p>
            </div>
          </div>

          <div class="callout callout-info" id="ai-agents-migration-banner" role="status" hidden>
            <div class="callout-body u-stack-1">
              <p class="callout-title">Service agent visibility updated</p>
              <p>Existing service agents are admin-only until you assign roles or users. Edit each service agent's <em>Who can use</em> settings to share access.</p>
            </div>
          </div>

          <div class="ai-connection-kinds-grid u-grid-auto-lg">
            <div class="card" id="ai-kind-personal">
              <div class="card-header">
                <div class="card-heading">
                  <h3 class="card-title">Personal connector</h3>
                  <p class="card-description">Connect Claude, ChatGPT, or Cursor with your own Birkly permissions. Every team member uses the same URL but signs in with their own account.</p>
                </div>
              </div>
              <div id="ai-personal-agent-card" class="card-body">
                <div class="loading-state"><p>Loading personal connector...</p></div>
              </div>
            </div>

            <div class="card" id="ai-kind-service">
              <div class="card-header">
                <div class="u-stack-1">
                  <h3 class="card-title">Service agents</h3>
                  <p class="card-description" id="ai-service-intro">Shared AI connections with a fixed CMS role. Only admins can create service agents.</p>
                </div>
                <button class="btn btn-primary" id="add-mcp-endpoint-btn" type="button" hidden>
                  <?= birkly_icon('plus', ['size' => 16]) ?>
                  Add agent
                </button>
              </div>
              <div class="connections-list card-body" id="ai-service-agents-list">
                <div class="loading-state"><p>Loading connections...</p></div>
              </div>
            </div>

            <div class="card" id="ai-kind-assistant">
              <div class="card-header">
                <div class="u-stack-1">
                  <h3 class="card-title">Built-in assistant</h3>
                  <p class="card-description">Chat with AI inside Birkly using your provider API key.</p>
                </div>
                <button class="btn btn-primary" id="add-connection-btn" type="button">
                  <?= birkly_icon('plus', ['size' => 16]) ?>
                  Quick connect
                </button>
              </div>
              <div class="connections-list card-body" id="connections-list">
                <div class="loading-state"><p>Loading connections...</p></div>
              </div>
            </div>
          </div>

          <details class="card u-p-4">
            <summary class="u-clickable u-weight-medium">Developer / debug</summary>
            <div class="u-stack-2 u-pt-3 u-text-sm u-fg-secondary">
              <p>For local development only. Not needed for normal setup.</p>
              <p><a href="#" id="mcp-bridge-dev-link" target="_blank" rel="noopener noreferrer">mcp-bridge.js</a> — local development helper for Claude Desktop (advanced only).</p>
            </div>
          </details>
        </div>

        <!-- Built-in assistant quick connect modal -->
        <div class="modal" id="add-connection-modal">
          <div class="modal-content modal--md">
            <div class="modal-header">
              <h2 id="connection-modal-title">Quick connect assistant</h2>
              <button class="btn-icon modal-close" id="close-modal-btn">
                <?= birkly_icon('x', ['size' => 16]) ?>
              </button>
            </div>
            <form id="add-connection-form" class="modal-body">
              <input type="hidden" id="connection-id" name="id">

              <div id="wizard-error" class="message message-error" role="alert" hidden></div>

              <p class="modal-intro-text" id="assistant-modal-intro">Connect an AI provider for the in-admin chat button. You need a developer API key — consumer subscriptions (ChatGPT Plus, Claude Pro) do not include API access.</p>

              <div class="form-group">
                <label for="connection-provider"><?= __('admin.ai_provider') ?></label>
                <select id="connection-provider" name="provider" class="input" required>
                  <option value="">Select a provider...</option>
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
                <small id="api-url-hint">API endpoint URL (required for custom providers)</small>
              </div>

              <div class="form-group" id="api-key-group">
                <label for="connection-api-key"><?= __('admin.api_key') ?></label>
                <input type="password" id="connection-api-key" name="api_key" class="input" autocomplete="off">
                <small><?= __('admin.api_key_help') ?></small>
              </div>

              <div class="form-group" id="ollama-hint" hidden>
                <div class="callout callout-info">
                  <div class="callout-body u-stack-1">
                    <p class="callout-title">Ollama runs locally</p>
                    <p>No API key needed. Make sure Ollama is running (default: <code>http://localhost:11434</code>).</p>
                  </div>
                </div>
              </div>

              <div class="form-group" id="test-connection-group">
                <button type="button" class="btn btn-secondary" id="test-connection-btn">Test connection</button>
                <span id="test-connection-status" class="test-connection-status"></span>
              </div>

              <div class="form-group" id="connection-model-group">
                <label for="connection-model-search"><?= __('admin.model') ?></label>
                <div class="u-stack-2" id="connection-model-picker">
                  <div class="u-cluster">
                    <input type="search" id="connection-model-search" class="input" placeholder="Search models…" autocomplete="off" aria-controls="connection-model-list">
                    <button type="button" class="btn btn-sm btn-secondary" id="connection-model-refresh">Refresh</button>
                  </div>
                  <input type="hidden" id="connection-model" name="model" value="">
                  <div id="connection-model-list" class="ai-model-picker-list" role="listbox" aria-label="Models"></div>
                  <p id="connection-model-status" class="form-help ai-model-picker-status"></p>
                </div>
                <input type="text" id="connection-model-custom" name="model_custom" class="input" placeholder="<?= __('admin.enter_model_name') ?>">
                <small class="form-help">Models are loaded live from your provider. Search the list or type a model ID.</small>
              </div>

              <div class="form-group">
                <label for="connection-role"><?= __('admin.role') ?></label>
                <select id="connection-role" name="role" class="input" required>
                  <option value="">Loading roles...</option>
                </select>
              </div>

              <input type="hidden" id="connection-shared-type" name="shared_type" value="none">

              <div class="form-group">
                <label class="check">
                  <input type="checkbox" id="connection-require-approval" name="require_approval" value="1" checked>
                  <span>Require approval for AI actions</span>
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
                  <label for="mcp-modal-personal-url">Personal connector URL</label>
                  <div class="copy-snippet-row">
                    <input type="text" id="mcp-modal-personal-url" class="input" readonly placeholder="Loading...">
                    <button type="button" class="btn btn-sm btn-primary" id="mcp-copy-personal-url-btn" type="button">Copy URL</button>
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
                  <label for="mcp-new-connector-url">Connector URL</label>
                  <div class="copy-snippet-row">
                    <input type="text" id="mcp-new-connector-url" class="input" readonly>
                    <button type="button" class="btn btn-sm btn-primary" data-admin-object="mcpConnections" data-admin-method="copySnippet" data-admin-args='["mcp-new-connector-url", true]'>Copy URL</button>
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
