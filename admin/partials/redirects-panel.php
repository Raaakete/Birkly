<?php ?>
<div class="redirects-settings u-stack-4">
    <div class="section-header">
        <div>
            <h2>Redirects</h2>
            <p class="settings-help">Manage URL redirects for your site. Rules are enforced by the Birkly router when requests hit this installation.</p>
        </div>
    </div>

    <div class="settings-stack">
        <section class="card">
            <div class="card-header">
                <h3 class="card-title">Create or update redirect</h3>
            </div>
            <div class="card-body">
                <form id="redirect-form" class="settings-form u-stack-4">
                    <input type="hidden" id="redirect-id" value="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="redirect-from-path">From path</label>
                            <input type="text" id="redirect-from-path" class="input" placeholder="/old-path" required>
                        </div>
                        <div class="form-group">
                            <label for="redirect-to-path">To path or URL</label>
                            <input type="text" id="redirect-to-path" class="input" placeholder="/new-path or https://example.com" required>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="redirect-type">Redirect type</label>
                            <select id="redirect-type" class="input">
                                <option value="301">301 Permanent</option>
                                <option value="302">302 Temporary</option>
                            </select>
                        </div>
                        <div class="form-group u-justify-end">
                            <label class="check">
                                <input type="checkbox" id="redirect-enabled" checked>
                                <span>Enabled</span>
                            </label>
                        </div>
                    </div>
                    <div class="u-cluster-3">
                        <button type="submit" class="btn btn-primary">Save redirect</button>
                        <button type="button" class="btn btn-secondary" id="redirect-form-reset">Clear</button>
                    </div>
                    <div id="redirect-feedback" class="u-text-sm" aria-live="polite" hidden></div>
                </form>
            </div>
        </section>

        <section class="card">
            <div class="card-header">
                <h3 class="card-title">Saved redirects</h3>
            </div>
            <div class="card-body u-stack-3">
                <div id="redirects-empty" class="empty-state empty-state--compact" hidden>
                    <p class="empty-state__description">No redirects saved yet.</p>
                </div>
                <div class="table-container">
                    <table class="table">
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
            </div>
        </section>
    </div>
</div>
