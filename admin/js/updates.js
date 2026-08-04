/**
 * Birkly system updates UI (Settings → General and Settings → Updates page).
 */
(function () {
    'use strict';

    window.Birkly = window.Birkly || {};

    let updateData = null;
    let downloadedPackage = null;

    function url(path) {
        return window.Birkly && typeof window.Birkly.url === 'function'
            ? window.Birkly.url(path)
            : path;
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function el(id) {
        return document.getElementById(id);
    }

    async function updatesApi(action, extra) {
        const response = await fetch(url('/api/updates.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(Object.assign({ action: action, _csrf: csrfToken() }, extra || {})),
        });

        let result;
        try {
            result = await response.json();
        } catch (e) {
            throw new Error('Invalid response from update server');
        }

        if (!response.ok && result) {
            const detail = result.detail || result.error || '';
            const msg = result.message || 'Request failed';
            throw new Error(detail ? msg + ': ' + detail : msg);
        }

        return result;
    }

    function setLoading() {
        const statusIndicator = el('update-status');
        const updateDetails = el('update-details');
        if (!statusIndicator) return;

        statusIndicator.innerHTML = '<span class="badge badge-neutral">Checking for updates...</span>';
        if (updateDetails) updateDetails.hidden = true;
    }

    function showError(message) {
        const statusIndicator = el('update-status');
        const updateDetails = el('update-details');
        const updateActions = el('update-actions');
        const updateInfo = el('update-info');

        if (statusIndicator) {
            statusIndicator.innerHTML = '<span class="badge badge-danger">Update check failed</span>';
        }
        if (updateInfo) {
            updateInfo.innerHTML =
                '<p class="u-fg-danger">' + escapeHtml(message || 'Unknown error') + '</p>';
        }
        if (updateDetails) updateDetails.hidden = false;
        if (updateActions) updateActions.hidden = false;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function renderChangelog(notes) {
        const changelog = el('changelog-content');
        if (!changelog) return;
        if (!notes) {
            changelog.innerHTML = '<p class="u-fg-secondary">No release notes available.</p>';
            return;
        }
        changelog.innerHTML = '<div class="u-text-sm u-leading-normal">' + escapeHtml(notes).replace(/\n/g, '<br>') + '</div>';
    }

    function updateVersionLabels(version) {
        const current = version || '—';
        if (el('current-version')) el('current-version').textContent = current;
        if (el('current-version-text')) el('current-version-text').textContent = current;
    }

    async function loadCurrentVersion() {
        try {
            const result = await updatesApi('get_update_status');
            if (result.status === 'ok' && result.current_version) {
                updateVersionLabels(result.current_version);
            } else if (result.status === 'error') {
                updateVersionLabels('—');
            }
        } catch (e) {
            updateVersionLabels('—');
        }
    }

    function ensureInstallButton() {
        const actions = el('update-actions');
        if (!actions || el('install-update-btn')) return;

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-primary';
        btn.id = 'install-update-btn';
        btn.hidden = true;
        btn.innerHTML =
            '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
            '<path d="M12 2v6m0 0l3-3m-3 3L9 5"></path>' +
            '<path d="M4 14v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4"></path>' +
            '</svg> Install Update';
        btn.addEventListener('click', installUpdate);
        actions.insertBefore(btn, actions.firstChild);
    }

    function displayUpdateStatus(data) {
        const statusIndicator = el('update-status');
        const updateDetails = el('update-details');
        const updateActions = el('update-actions');
        const updateInfo = el('update-info');

        if (!statusIndicator || !updateInfo) return;

        updateVersionLabels(data.current_version);
        renderChangelog(data.release_notes || '');

        if (data.update_available) {
            statusIndicator.innerHTML = '<span class="badge badge-warning">Update available</span>';
            updateInfo.innerHTML =
                '<div class="u-stack-2">' +
                '<h3 class="u-text-md u-weight-semibold">Version ' + escapeHtml(data.latest_version) + ' is available</h3>' +
                '<p>You are running version ' + escapeHtml(data.current_version) + '.</p>' +
                (data.published_at ? '<p class="u-text-sm u-fg-secondary">Published: ' + escapeHtml(data.published_at) + '</p>' : '') +
                '</div>';
        } else {
            statusIndicator.innerHTML = '<span class="badge badge-success">Up to date</span>';
            updateInfo.innerHTML =
                '<div class="u-stack-2">' +
                '<h3 class="u-text-md u-weight-semibold">You are up to date</h3>' +
                '<p>Running version ' + escapeHtml(data.current_version) + '.</p>' +
                '</div>';
        }

        if (updateActions) {
            updateActions.hidden = false;
            const downloadBtn = el('download-update-btn');
            if (downloadBtn) {
                downloadBtn.hidden = !data.update_available;
            }
            const installBtn = el('install-update-btn');
            if (installBtn) {
                installBtn.hidden = true;
            }
        }
        if (updateDetails) updateDetails.hidden = false;
        downloadedPackage = null;
    }

    async function checkForUpdates() {
        if (!el('update-status')) return;
        setLoading();

        try {
            const result = await updatesApi('check_updates');
            if (result.status === 'ok' && result.data) {
                updateData = result.data;
                displayUpdateStatus(result.data);
            } else {
                showError(result.message || 'Could not check for updates');
            }
        } catch (error) {
            showError(error.message || 'Failed to check for updates');
        }
    }

    async function downloadUpdate() {
        if (!updateData || !updateData.update_available) {
            BirklyFeedback.toast('No update available to download.', 'error')
            return;
        }

        const downloadBtn = el('download-update-btn');
        if (downloadBtn) {
            downloadBtn.disabled = true;
            downloadBtn.textContent = 'Downloading...';
        }

        try {
            const result = await updatesApi('download_update', { version: updateData.latest_version });
            if (result.status === 'ok' && result.data) {
                downloadedPackage = result.data;
                ensureInstallButton();
                const installBtn = el('install-update-btn');
                if (installBtn) installBtn.hidden = false;
                const updateInfo = el('update-info');
                if (updateInfo) {
                    updateInfo.innerHTML +=
                        '<p class="u-fg-success">Update downloaded (' +
                        Math.round((result.data.size || 0) / 1024) +
                        ' KB). Click <strong>Install Update</strong> to apply.</p>';
                }
            } else {
                BirklyFeedback.toast(result.message || 'Download failed', 'error')
            }
        } catch (error) {
            BirklyFeedback.toast(error.message || 'Download failed', 'error')
        } finally {
            if (downloadBtn) {
                downloadBtn.disabled = false;
                downloadBtn.innerHTML =
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                    '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>' +
                    '<polyline points="7,10 12,15 17,10"></polyline>' +
                    '<line x1="12" y1="15" x2="12" y2="3"></line>' +
                    '</svg> Download Update';
            }
        }
    }

    async function installUpdate() {
        if (!downloadedPackage || !updateData) {
            BirklyFeedback.toast('Download an update first.', 'error')
            return;
        }

        if (!(await BirklyFeedback.confirm('Install Birkly ' + updateData.latest_version + '? A backup will be created first.'))) {
            return;
        }

        const installBtn = el('install-update-btn');
        if (installBtn) {
            installBtn.disabled = true;
            installBtn.textContent = 'Installing...';
        }

        try {
            const result = await updatesApi('install_update', {
                version: downloadedPackage.version || updateData.latest_version,
                temp_file: downloadedPackage.temp_file,
            });

            if (result.status === 'ok') {
                BirklyFeedback.toast('Update installed successfully. The page will reload.', 'success')
                window.location.reload();
            } else {
                BirklyFeedback.toast(result.message || 'Installation failed', 'error')
            }
        } catch (error) {
            BirklyFeedback.toast(error.message || 'Installation failed', 'error')
        } finally {
            if (installBtn) {
                installBtn.disabled = false;
                installBtn.textContent = 'Install Update';
            }
        }
    }

    function initUpdatesManager() {
        if (!el('update-status')) return;

        ensureInstallButton();
        loadCurrentVersion();
        checkForUpdates();

        el('check-updates-btn')?.addEventListener('click', checkForUpdates);
        el('download-update-btn')?.addEventListener('click', downloadUpdate);
    }

    window.initUpdatesManager = initUpdatesManager;
    window.initUpdates = initUpdatesManager;
})();
