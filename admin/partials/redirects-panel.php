<?php ?>
<div class="redirects-settings u-stack-4">
    <div class="section-header">
        <div>
            <h2 class="section-title"><?= htmlspecialchars(__('admin.redirects'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="settings-help"><?= htmlspecialchars(__('admin.settings_redirects_help'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    </div>

    <div class="settings-stack">
        <section class="card">
            <div class="card-header">
                <h3 class="card-title"><?= htmlspecialchars(__('admin.settings_redirects_form_title'), ENT_QUOTES, 'UTF-8') ?></h3>
            </div>
            <div class="card-body">
                <form id="redirect-form" class="settings-form redirect-form">
                    <input type="hidden" id="redirect-id" value="">
                    <div class="form-group setting-row">
                        <div class="setting-label">
                            <label for="redirect-from-path"><?= htmlspecialchars(__('admin.discovery_seo_redirect_from'), ENT_QUOTES, 'UTF-8') ?></label>
                        </div>
                        <div class="setting-value">
                            <input type="text" id="redirect-from-path" class="input" placeholder="<?= htmlspecialchars(__('admin.settings_redirects_from_placeholder'), ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                    </div>
                    <div class="form-group setting-row">
                        <div class="setting-label">
                            <label for="redirect-to-path"><?= htmlspecialchars(__('admin.discovery_seo_redirect_to'), ENT_QUOTES, 'UTF-8') ?></label>
                        </div>
                        <div class="setting-value">
                            <input type="text" id="redirect-to-path" class="input" placeholder="<?= htmlspecialchars(__('admin.settings_redirects_to_placeholder'), ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                    </div>
                    <div class="form-group setting-row">
                        <div class="setting-label">
                            <label for="redirect-type"><?= htmlspecialchars(__('admin.discovery_seo_redirect_type'), ENT_QUOTES, 'UTF-8') ?></label>
                        </div>
                        <div class="setting-value">
                            <select id="redirect-type" class="input">
                                <option value="301"><?= htmlspecialchars(__('admin.discovery_seo_redirect_301'), ENT_QUOTES, 'UTF-8') ?></option>
                                <option value="302"><?= htmlspecialchars(__('admin.discovery_seo_redirect_302'), ENT_QUOTES, 'UTF-8') ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group setting-row">
                        <div class="setting-label">
                            <label for="redirect-enabled"><?= htmlspecialchars(__('admin.enabled'), ENT_QUOTES, 'UTF-8') ?></label>
                        </div>
                        <div class="setting-value">
                            <label class="switch"><input type="checkbox" id="redirect-enabled" checked><span class="switch__slider"></span></label>
                        </div>
                    </div>
                    <div class="setting-actions">
                        <button type="submit" class="button is-main"><?= htmlspecialchars(__('admin.discovery_seo_redirect_save'), ENT_QUOTES, 'UTF-8') ?></button>
                        <button type="button" class="button" id="redirect-form-reset"><?= htmlspecialchars(__('admin.clear'), ENT_QUOTES, 'UTF-8') ?></button>
                    </div>
                    <div id="redirect-feedback" class="u-text-sm" aria-live="polite" hidden></div>
                </form>
            </div>
        </section>

        <section class="card">
            <div class="card-header">
                <h3 class="card-title"><?= htmlspecialchars(__('admin.settings_redirects_list_title'), ENT_QUOTES, 'UTF-8') ?></h3>
            </div>
            <div class="card-body u-stack-3">
                <div id="redirects-empty" class="empty-state empty-state--compact" hidden>
                    <p class="empty-state__description"><?= htmlspecialchars(__('admin.settings_redirects_empty'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= htmlspecialchars(__('admin.settings_redirects_col_from'), ENT_QUOTES, 'UTF-8') ?></th>
                                <th><?= htmlspecialchars(__('admin.settings_redirects_col_to'), ENT_QUOTES, 'UTF-8') ?></th>
                                <th><?= htmlspecialchars(__('admin.discovery_seo_redirect_type'), ENT_QUOTES, 'UTF-8') ?></th>
                                <th><?= htmlspecialchars(__('admin.status'), ENT_QUOTES, 'UTF-8') ?></th>
                                <th><?= htmlspecialchars(__('admin.updated'), ENT_QUOTES, 'UTF-8') ?></th>
                                <th><?= htmlspecialchars(__('admin.actions'), ENT_QUOTES, 'UTF-8') ?></th>
                            </tr>
                        </thead>
                        <tbody id="redirects-table-body"></tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
