<?php ?>
<div class="settings-section redirects-settings">
    <div class="section-header">
        <div>
            <h2>Redirects</h2>
            <p class="settings-help">Manage URL redirects for your site. Rules are stored in <code>settings/redirects.json</code>.</p>
        </div>
    </div>

    <div class="settings-stack">
        <section class="card">
            <div class="section-header">
                <h3>Create or update redirect</h3>
            </div>
            <form id="redirect-form" class="settings-form" style="display:grid; gap: 1rem;">
                <input type="hidden" id="redirect-id" value="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="redirect-from-path">From path</label>
                        <input type="text" id="redirect-from-path" class="input" placeholder="/old-path" required>
                    </div>
                    <div class="form-group">
                        <label for="redirect-to-path">To path or URL</label>
                        <input type="text" id="redirect-to-path" class="input" placeholder="/new-path or https://example.com" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="redirect-type">Redirect type</label>
                        <select id="redirect-type" class="input">
                            <option value="301">301 Permanent</option>
                            <option value="302">302 Temporary</option>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex; align-items:end;">
                        <label class="checkbox-label" style="margin:0;">
                            <input type="checkbox" id="redirect-enabled" checked>
                            <span>Enabled</span>
                        </label>
                    </div>
                </div>
                <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                    <button type="submit" class="btn btn-primary">Save redirect</button>
                    <button type="button" class="btn btn-secondary" id="redirect-form-reset">Clear</button>
                </div>
                <div id="redirect-feedback" style="display:none; font-size:0.875rem;" aria-live="polite"></div>
            </form>
        </section>

        <section class="card">
            <div class="section-header">
                <h3>Saved redirects</h3>
            </div>
            <div id="redirects-empty" class="empty-state" style="display:none;">
                <p>No redirects saved yet.</p>
            </div>
            <div class="table-viewport">
                <table class="data-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th>From</th>
                            <th>To</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="redirects-table-body"></tbody>
                </table>
            </div>
        </section>
    </div>
</div>
