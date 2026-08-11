<?php
/**
 * Settings → Connections panel (Phase 65).
 *
 * Email-first composition. Lists email connections and lets admins add, edit,
 * delete, set default, and test them.
 */
?>
<div class="settings-stack connections-settings" id="connections-settings-root">
  <div class="settings-pane-section" data-settings-search="connections email smtp resend postmark ses mailgun log mail sender provider">
    <section class="card">
      <div class="card-header">
        <div>
          <h2 class="section-title">Email connections</h2>
          <p class="settings-help">Choose how Birkly sends email. Add one connection per provider; mark one as default.</p>
        </div>
        <button type="button" class="btn btn-primary" id="add-connection-btn" data-connection-action="open-create">
          <?= birkly_icon('plus', ['size' => 16]) ?>
          Add connection
        </button>
      </div>
      <div class="card-body u-stack-4">
        <div id="connections-list">
          <div class="loading-state"><p>Loading connections…</p></div>
        </div>
      </div>
    </section>
  </div>

  <div class="settings-pane-section" data-settings-search="connections http credential webhook basic auth bearer token header">
    <section class="card">
      <div class="card-header">
        <div>
          <h2 class="section-title">HTTP credentials</h2>
          <p class="settings-help">Stored credentials for outbound webhooks and API calls.</p>
        </div>
        <button type="button" class="btn btn-primary" id="add-http-credential-btn" data-connection-action="open-create" data-connection-type="http_credential">
          <?= birkly_icon('plus', ['size' => 16]) ?>
          Add credential
        </button>
      </div>
      <div class="card-body u-stack-4">
        <div id="http-credentials-list">
          <div class="loading-state"><p>Loading credentials…</p></div>
        </div>
      </div>
    </section>
  </div>
</div>

<div class="modal" id="connection-modal">
  <div class="modal-content modal--md">
    <div class="modal-header">
      <h2 id="connection-modal-title">Add email connection</h2>
      <button class="btn-icon modal-close" data-connection-action="close-modal" aria-label="Close">
        <?= birkly_icon('x', ['size' => 16]) ?>
      </button>
    </div>
    <form id="connection-form" class="modal-body">
      <input type="hidden" id="connection-id" name="id">
      <input type="hidden" id="connection-type" name="type" value="email">

      <div id="connection-form-error" class="message message-error" role="alert" hidden></div>

      <div class="form-group">
        <label for="connection-provider">Provider</label>
        <input type="text" id="connection-provider" name="provider" class="input" required list="connection-provider-list" placeholder="e.g. smtp, resend, or a custom name">
        <datalist id="connection-provider-list"></datalist>
        <small class="form-help">Choose a built-in provider or type any custom identifier. Custom providers store config as free-form key/value pairs.</small>
      </div>

      <div class="form-group">
        <label for="connection-alias">Alias</label>
        <input type="text" id="connection-alias" name="alias" class="input" required pattern="[a-z0-9_.-]+" placeholder="e.g. primary-smtp">
        <small class="form-help">Used when other parts of Birkly reference this connection. Lowercase letters, numbers, dots, dashes and underscores.</small>
      </div>

      <div class="form-group">
        <label for="connection-name">Name</label>
        <input type="text" id="connection-name" name="name" class="input" required placeholder="e.g. Primary SMTP">
      </div>

      <div class="form-grid">
        <div class="form-group">
          <label for="connection-from-email">From email</label>
          <input type="email" id="connection-from-email" name="from_email" class="input" required placeholder="noreply@example.com">
        </div>
        <div class="form-group">
          <label for="connection-from-name">From name</label>
          <input type="text" id="connection-from-name" name="from_name" class="input" placeholder="Birkly">
        </div>
      </div>

      <div id="connection-provider-fields" class="u-stack-3"></div>

      <div class="form-group">
        <label class="check">
          <input type="checkbox" id="connection-enabled" name="enabled" value="1" checked>
          <span>Enabled</span>
        </label>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-connection-action="close-modal">Cancel</button>
        <button type="submit" class="btn btn-primary" id="connection-submit-btn">Save connection</button>
      </div>
    </form>
  </div>
</div>
