/**
 * Account Settings JavaScript
 * Handles profile, password, language, and notification preferences
 */

document.addEventListener('DOMContentLoaded', function() {
    const unifiedSaveBtn = document.getElementById('account-save-btn');
    if (unifiedSaveBtn) {
        unifiedSaveBtn.addEventListener('click', handleUnifiedAccountSave);
    }

    // Profile form
    const profileForm = document.getElementById('account-profile-form');
    if (profileForm) {
        profileForm.addEventListener('submit', handleProfileSubmit);
        
        // Avatar preview
        const avatarInput = document.getElementById('account-avatar');
        if (avatarInput) {
            avatarInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const preview = document.getElementById('avatar-preview');
                        const previewImg = document.getElementById('avatar-preview-img');
                        if (preview && previewImg) {
                            previewImg.src = e.target.result;
                            preview.style.display = 'block';
                        }
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        
        // Remove avatar button
        const removeAvatarBtn = document.getElementById('remove-avatar-btn');
        if (removeAvatarBtn) {
            removeAvatarBtn.addEventListener('click', handleRemoveAvatar);
        }
    }
    
    // Password form
    const passwordForm = document.getElementById('account-password-form');
    if (passwordForm) {
        passwordForm.addEventListener('submit', handlePasswordSubmit);
    }
    
    // Language form
    const languageForm = document.getElementById('account-language-form');
    if (languageForm) {
        languageForm.addEventListener('submit', handleLanguageSubmit);
    }
    
    // Notifications form
    const notificationsForm = document.getElementById('account-notifications-form');
    if (notificationsForm) {
        notificationsForm.addEventListener('submit', handleNotificationsSubmit);
    }

    // 2FA status
    if (document.getElementById('cloud-account-status')) {
        loadCloudAccountStatus();
        const params = new URLSearchParams(window.location.search);
        if (params.get('cloud_linked') === '1') {
            showCloudAccountMessage('Birkly cloud account linked successfully.', 'success');
            if (window.history && window.history.replaceState) {
                params.delete('cloud_linked');
                const qs = params.toString();
                window.history.replaceState({}, '', window.location.pathname + (qs ? '?' + qs : '') + window.location.hash);
            }
        }
    }

    if (document.getElementById('account-2fa-status')) {
        loadAccount2FAStatus();
    }

    // 2FA buttons are rendered dynamically; use event delegation for reliability
    document.addEventListener('click', async function (e) {
        const target = e.target;
        if (!target) return;

        if (target.id === 'account-2fa-enable-btn') {
            e.preventDefault();
            try { window.openAccount2FASetupModal?.(); } catch (_) {}
            return;
        }
        if (target.id === 'account-2fa-disable-btn') {
            e.preventDefault();
            try { window.openAccount2FADisableModal?.(); } catch (_) {}
            return;
        }
        if (target.id === 'account-2fa-regenerate-btn') {
            e.preventDefault();
            try { window.openAccount2FARegenerateModal?.(); } catch (_) {}
            return;
        }
    }, true);
});

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function refreshTopbarAvatars(avatarUrl) {
    const topbarMenu = document.querySelector('.topbar-user-menu');
    if (!topbarMenu) {
        return;
    }

    const trigger = topbarMenu.querySelector('.user-menu-trigger');
    const headerAvatar = topbarMenu.querySelector('.user-menu-header .user-menu-avatar');

    [trigger, headerAvatar].forEach((container) => {
        if (!container) return;
        const existingImg = container.querySelector('img.user-avatar, img');
        const existingInitials = container.querySelector('.user-avatar-initials');

        if (avatarUrl) {
            if (existingImg) {
                existingImg.src = avatarUrl;
                existingImg.style.display = '';
            } else if (existingInitials) {
                const img = document.createElement('img');
                img.src = avatarUrl;
                img.alt = existingInitials.textContent || 'User';
                img.className = 'user-avatar';
                existingInitials.replaceWith(img);
            }
        } else if (existingImg && existingInitials === null) {
            const initials = document.createElement('div');
            initials.className = 'user-avatar-initials';
            const name = topbarMenu.querySelector('.user-menu-name')?.textContent?.trim() || 'U';
            initials.textContent = name.substring(0, 2).toUpperCase();
            existingImg.replaceWith(initials);
        }
    });

    document.dispatchEvent(new CustomEvent('birkly:avatar-updated', { detail: { url: avatarUrl || '' } }));
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

async function loadCloudAccountStatus() {
    const container = document.getElementById('cloud-account-status');
    if (!container) return;
    try {
        const response = await fetch(Birkly.url('/api/cloud_account.php?action=status'), { credentials: 'same-origin' });
        const data = await response.json();
        if (data.status !== 'ok') throw new Error(data.message || 'Failed to load cloud account status');
        renderCloudAccountStatus(container, data.data || {});
    } catch (err) {
        container.innerHTML = '<p class="text-muted">Error loading cloud account status</p>';
    }
}

function renderCloudAccountStatus(container, status) {
    if (status.linked) {
        container.innerHTML = '<div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">'
            + '<span class="badge badge-success">Linked</span>'
            + '<span class="text-muted">' + escapeHtml(status.email || '') + '</span></div>'
            + '<button class="btn btn-danger btn-sm" type="button" id="cloud-account-unlink-btn">Unlink cloud account</button>';
        document.getElementById('cloud-account-unlink-btn')?.addEventListener('click', unlinkCloudAccount);
        return;
    }

    if (status.oauth_reachable === false) {
        const msg = status.oauth_message || 'Birkly Cloud sign-in is temporarily unavailable. Your local CMS login is unaffected.';
        container.innerHTML = '<p class="text-muted" style="margin-bottom:16px;">' + escapeHtml(msg) + '</p>';
        return;
    }

    container.innerHTML = '<p class="text-muted" style="margin-bottom:16px;">No Birkly cloud account is linked to this installation.</p>'
        + '<button class="btn btn-primary" type="button" id="cloud-account-link-btn">Link Birkly cloud account</button>';
    document.getElementById('cloud-account-link-btn')?.addEventListener('click', startCloudAccountLink);
}

async function startCloudAccountLink() {
    const btn = document.getElementById('cloud-account-link-btn');
    if (btn) btn.disabled = true;
    try {
        const response = await fetch(Birkly.url('/api/cloud_account.php?action=start_link'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify({ _csrf: getCsrfToken() }),
        });
        const data = await response.json();
        if (data.status !== 'ok') throw new Error(data.message || 'Failed to start link');
        window.location.href = data.data.authorization_url;
    } catch (err) {
        showCloudAccountMessage(err.message || 'Failed to start cloud account link', 'error');
        if (btn) btn.disabled = false;
    }
}

async function unlinkCloudAccount() {
    if (!(await BirklyFeedback.confirm('Unlink your Birkly cloud account from this CMS installation?'))) return;
    try {
        const response = await fetch(Birkly.url('/api/cloud_account.php?action=unlink'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify({ _csrf: getCsrfToken() }),
        });
        const data = await response.json();
        if (data.status !== 'ok') throw new Error(data.message || 'Unlink failed');
        showCloudAccountMessage('Cloud account unlinked.', 'success');
        loadCloudAccountStatus();
    } catch (err) {
        showCloudAccountMessage(err.message || 'Failed to unlink cloud account', 'error');
    }
}

function showCloudAccountMessage(message, type) {
    const el = document.getElementById('cloud-account-message');
    if (!el) return;
    el.textContent = message;
    el.className = 'settings-message ' + (type || 'info');
    el.style.display = 'block';
    setTimeout(() => { el.style.display = 'none'; }, 5000);
}

async function loadAccount2FAStatus() {
    const container = document.getElementById('account-2fa-status');
    if (!container) return;

    try {
        const response = await fetch(Birkly.url('/api/2fa.php?action=status'), { credentials: 'same-origin' });
        const data = await response.json();
        if (data.status !== 'ok') throw new Error(data.message || 'Failed to load 2FA status');

        renderAccount2FAStatus(container, !!data.enabled, data.enabled_at, data.backup_codes_count);
    } catch (err) {
        console.error('Error loading 2FA status:', err);
        container.innerHTML = '<p class="text-muted">Error loading 2FA status</p>';
    }
}

function renderAccount2FAStatus(container, enabled, enabledAt, backupCodesCount) {
    const enabledDate = enabledAt ? new Date(enabledAt).toLocaleDateString() : '';
    const codesCount = backupCodesCount || 0;

    if (enabled) {
        container.innerHTML = `
          <div class="2fa-status-enabled">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
              <span class="badge badge-success">Enabled</span>
              <span class="text-muted">${escapeHtml(enabledDate ? `Enabled on ${enabledDate}` : '')}</span>
            </div>
            <p class="text-muted" style="margin-bottom:16px;">You have ${codesCount} backup codes remaining.</p>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
              <button class="btn btn-secondary btn-sm" type="button" id="account-2fa-regenerate-btn" onclick="openAccount2FARegenerateModal()">Regenerate Backup Codes</button>
              <button class="btn btn-danger btn-sm" type="button" id="account-2fa-disable-btn" onclick="openAccount2FADisableModal()">Disable 2FA</button>
            </div>
          </div>
        `;

        document.getElementById('account-2fa-disable-btn')?.addEventListener('click', openAccount2FADisableModal);
        document.getElementById('account-2fa-regenerate-btn')?.addEventListener('click', openAccount2FARegenerateModal);
    } else {
        container.innerHTML = `
          <div class="2fa-status-disabled">
            <p class="text-muted" style="margin-bottom:16px;">Two-factor authentication is not enabled for your account.</p>
            <button class="btn btn-primary" type="button" id="account-2fa-enable-btn" onclick="openAccount2FASetupModal()">Enable 2FA</button>
          </div>
        `;
        document.getElementById('account-2fa-enable-btn')?.addEventListener('click', openAccount2FASetupModal);
    }
}

let account2faSetupSecret = null;

// Ensure 2FA open functions are available on window for inline onclick handlers
async function openAccount2FASetupModal() {
    const modal = document.getElementById('account-2fa-setup-modal');
    const content = document.getElementById('account-2fa-setup-content');
    if (!modal || !content) return;

    modal.style.display = 'flex';
    modal.classList.add('active');
    content.innerHTML = '<div class="text-center"><div class="spinner">Setting up 2FA...</div></div>';

    try {
        const response = await fetch(Birkly.url('/api/2fa.php?action=setup'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify({ _csrf: getCsrfToken() })
        });
        const data = await response.json();
        if (data.status !== 'ok') throw new Error(data.message || 'Setup failed');

        account2faSetupSecret = data.data.secret;
        const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent(data.data.qr_url);
        const manualKey = escapeHtml(data.data.manual_entry_key || '');

        content.innerHTML = `
          <div class="account-2fa-step1">
            <h3 style="margin-top:0;">Step 1: Scan QR Code</h3>
            <p class="text-muted">Scan this QR code with your authenticator app.</p>
            <div style="text-align:center;margin:24px 0;">
              <img src="${qrUrl}" alt="2FA QR Code" style="border:1px solid #ddd;border-radius:8px;padding:16px;background:white;">
            </div>
            <div style="background:#f8f9fa;padding:12px;border-radius:8px;margin:16px 0;">
              <p style="margin:0;font-size:0.875em;color:#666;"><strong>Manual Entry:</strong></p>
              <code style="display:block;margin-top:8px;font-size:0.875em;word-break:break-all;">${manualKey}</code>
            </div>
            <button class="btn btn-primary" type="button" id="account-2fa-step1-next">I have scanned the code</button>
            <button class="btn btn-secondary" type="button" id="account-2fa-cancel" style="margin-left:8px;">Cancel</button>
          </div>
          <div class="account-2fa-step2" style="display:none;">
            <h3 style="margin-top:0;">Step 2: Verify Code</h3>
            <p class="text-muted">Enter the 6-digit code from your authenticator app:</p>
            <div class="form-group">
              <label for="account-2fa-verify-code">Verification Code</label>
              <input type="text" id="account-2fa-verify-code" class="input" placeholder="000000" maxlength="6" style="text-align:center;letter-spacing:0.5rem;font-size:1.5rem;">
            </div>
            <button class="btn btn-primary" type="button" id="account-2fa-enable-confirm">Enable 2FA</button>
            <button class="btn btn-secondary" type="button" id="account-2fa-cancel2" style="margin-left:8px;">Cancel</button>
          </div>
        `;

        document.getElementById('account-2fa-step1-next')?.addEventListener('click', async () => {
            content.querySelector('.account-2fa-step1').style.display = 'none';
            content.querySelector('.account-2fa-step2').style.display = 'block';
            document.getElementById('account-2fa-verify-code')?.focus();
        });
        document.getElementById('account-2fa-cancel')?.addEventListener('click', accountClose2FASetupModal);
        document.getElementById('account-2fa-cancel2')?.addEventListener('click', accountClose2FASetupModal);
        document.getElementById('account-2fa-enable-confirm')?.addEventListener('click', enableAccount2FA);
        document.getElementById('account-2fa-verify-code')?.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').substring(0, 6);
        });
    } catch (err) {
        console.error('2FA setup error:', err);
        content.innerHTML = `<p class="text-danger">Error: ${escapeHtml(err.message || 'Unknown error')}</p>`;
    }
}

window.openAccount2FASetupModal = openAccount2FASetupModal;

function accountClose2FASetupModal() {
    const modal = document.getElementById('account-2fa-setup-modal');
    if (modal) {
        modal.classList.remove('active');
        modal.style.display = 'none';
    }
    account2faSetupSecret = null;
}
window.accountClose2FASetupModal = accountClose2FASetupModal;

async function enableAccount2FA() {
    const code = document.getElementById('account-2fa-verify-code')?.value.trim();
    if (!code || code.length !== 6) {
        BirklyFeedback.toast('Please enter a valid 6-digit code', 'warning')
        return;
    }

    try {
        const response = await fetch(Birkly.url('/api/2fa.php?action=enable'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify({ code, _csrf: getCsrfToken() })
        });
        const data = await response.json();
        if (data.status !== 'ok') throw new Error(data.message || 'Enable failed');

        // Show backup codes quickly
        const content = document.getElementById('account-2fa-setup-content');
        const codes = data.data?.backup_codes || [];
        const codesHtml = codes.map(c => escapeHtml(c)).join('<br>');
        if (content) {
            content.innerHTML = `
              <div class="account-2fa-step3">
                <h3 style="margin-top:0;">Step 3: Save Backup Codes</h3>
                <p class="text-muted">Save these backup codes in a safe place. Each code can be used once.</p>
                <div style="background:#f8f9fa;padding:16px;border-radius:8px;margin:16px 0;">
                  <code style="display:block;font-family:monospace;line-height:2;font-size:0.875em;">${codesHtml}</code>
                </div>
                <button class="btn btn-primary" type="button" id="account-2fa-done">Done</button>
              </div>
            `;
            document.getElementById('account-2fa-done')?.addEventListener('click', async () => {
                accountClose2FASetupModal();
                loadAccount2FAStatus();
            });
        }
    } catch (err) {
        console.error('Enable 2FA error:', err);
        BirklyFeedback.toast('Error enabling 2FA: ' + (err.message || 'Unknown error'), 'error');
    }
}

function openAccount2FADisableModal() {
    const modal = document.getElementById('account-2fa-disable-modal');
    if (!modal) return;
    modal.style.display = 'flex';
    modal.classList.add('active');
}
function accountClose2FADisableModal() {
    const modal = document.getElementById('account-2fa-disable-modal');
    if (modal) {
        modal.classList.remove('active');
        modal.style.display = 'none';
    }
    document.getElementById('account-2fa-disable-form')?.reset();
}
window.accountClose2FADisableModal = accountClose2FADisableModal;

window.openAccount2FADisableModal = openAccount2FADisableModal;

document.getElementById('account-2fa-disable-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const password = String(formData.get('password') || '');
    const code = String(formData.get('code') || '');
    if (!password) return;

    try {
        const response = await fetch(Birkly.url('/api/2fa.php?action=disable'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify({ password, code, _csrf: getCsrfToken() })
        });
        const data = await response.json();
        if (data.status !== 'ok') throw new Error(data.message || 'Disable failed');

        accountClose2FADisableModal();
        loadAccount2FAStatus();
    } catch (err) {
        console.error('Disable 2FA error:', err);
        BirklyFeedback.toast('Error disabling 2FA: ' + (err.message || 'Unknown error'), 'error');
    }
});

function openAccount2FARegenerateModal() {
    const modal = document.getElementById('account-2fa-regenerate-modal');
    if (!modal) return;
    modal.style.display = 'flex';
    modal.classList.add('active');
}
function accountClose2FARegenerateModal() {
    const modal = document.getElementById('account-2fa-regenerate-modal');
    if (modal) {
        modal.classList.remove('active');
        modal.style.display = 'none';
    }
    document.getElementById('account-2fa-regenerate-form')?.reset();
}
window.accountClose2FARegenerateModal = accountClose2FARegenerateModal;

window.openAccount2FARegenerateModal = openAccount2FARegenerateModal;

document.getElementById('account-2fa-regenerate-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const password = String(formData.get('password') || '');
    const code = String(formData.get('code') || '');
    if (!password) return;

    try {
        const response = await fetch(Birkly.url('/api/2fa.php?action=regenerate_backup_codes'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify({ password, code, _csrf: getCsrfToken() })
        });
        const data = await response.json();
        if (data.status !== 'ok') throw new Error(data.message || 'Regenerate failed');

        // Show the new backup codes directly in the regenerate modal
        const codes = data.data?.backup_codes || [];
        const codesHtml = codes.map(c => escapeHtml(c)).join('<br>');
        const content = document.getElementById('account-2fa-regenerate-form');
        if (content) {
            content.innerHTML = `
              <div class="account-2fa-backup-list">
                <p class="text-muted">Here are your new backup codes. Store them in a safe place. Each code can be used once.</p>
                <div style="background:#f8f9fa;padding:16px;border-radius:8px;margin:16px 0;">
                  <code style="display:block;font-family:monospace;line-height:2;font-size:0.875em;">${codesHtml}</code>
                </div>
                <div class="form-actions">
                  <button type="button" class="btn btn-primary" id="account-2fa-regenerate-done">Done</button>
                </div>
              </div>
            `;
            document.getElementById('account-2fa-regenerate-done')?.addEventListener('click', async () => {
                accountClose2FARegenerateModal();
                loadAccount2FAStatus();
            });
        } else {
            // Fallback: close modal and reload status
            accountClose2FARegenerateModal();
            loadAccount2FAStatus();
        }
    } catch (err) {
        console.error('Regenerate backup codes error:', err);
        BirklyFeedback.toast('Error regenerating backup codes: ' + (err.message || 'Unknown error'), 'error');
    }
});


async function handleUnifiedAccountSave() {
    const messageEl = document.getElementById('account-save-message');
    const btn = document.getElementById('account-save-btn');
    if (btn) {
        btn.disabled = true;
    }

    try {
        const profileForm = document.getElementById('account-profile-form');
        if (profileForm) {
            const profileData = new FormData(profileForm);
            const profileResponse = await fetch(Birkly.url('/api/users.php'), {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: profileData,
            });
            const profileResult = await profileResponse.json();
            if (profileResult.status !== 'ok') {
                throw new Error(profileResult.message || 'Error updating profile');
            }
            if (profileResult.data && profileResult.data.avatar) {
                refreshTopbarAvatars(profileResult.data.avatar);
            }
        }

        const languageForm = document.getElementById('account-language-form');
        let reloadAfterLanguage = false;
        if (languageForm) {
            const language = languageForm.querySelector('#account-language').value;
            const languageResponse = await fetch(Birkly.url('/api/users.php'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    action: 'set_language',
                    language: language,
                    _csrf: getCsrfToken(),
                }),
            });
            const languageResult = await languageResponse.json();
            if (languageResult.status !== 'ok') {
                throw new Error(languageResult.message || 'Error saving language preference');
            }
            reloadAfterLanguage = true;
        }

        const notificationsForm = document.getElementById('account-notifications-form');
        if (notificationsForm) {
            const prefs = {
                entry_published: document.getElementById('notify-entry-published')?.checked || false,
                entry_review: document.getElementById('notify-entry-review')?.checked || false,
                entry_commented: document.getElementById('notify-entry-commented')?.checked || false,
                invitation_accepted: document.getElementById('notify-invitation-accepted')?.checked || false,
                role_changed: document.getElementById('notify-role-changed')?.checked || false,
                system_updates: document.getElementById('notify-system-updates')?.checked || false,
                security_events: document.getElementById('notify-security-events')?.checked || false,
            };
            const notificationsResponse = await fetch(Birkly.url('/api/users.php'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    action: 'update_notification_preferences',
                    notification_preferences: prefs,
                    _csrf: getCsrfToken(),
                }),
            });
            const notificationsResult = await notificationsResponse.json();
            if (notificationsResult.status !== 'ok') {
                throw new Error(notificationsResult.message || 'Error saving notification preferences');
            }
        }

        if (messageEl) {
            showMessage(messageEl, 'Changes saved successfully', 'success');
        }

        if (reloadAfterLanguage) {
            setTimeout(function () {
                window.location.reload();
            }, 800);
        }
    } catch (err) {
        if (messageEl) {
            showMessage(messageEl, err.message || 'Error saving changes', 'error');
        }
    } finally {
        if (btn) {
            btn.disabled = false;
        }
    }
}

function handleProfileSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const messageEl = document.getElementById('account-message');
    
    fetch(Birkly.url('/api/users.php'), {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'ok') {
            showMessage(messageEl, 'Profile updated successfully', 'success');
            // Update avatar if changed
            if (data.data && data.data.avatar) {
                refreshTopbarAvatars(data.data.avatar);
            }
        } else {
            showMessage(messageEl, data.message || 'Error updating profile', 'error');
        }
    })
    .catch(error => {
        console.error('Profile update error:', error);
        showMessage(messageEl, 'Error updating profile', 'error');
    });
}

function handlePasswordSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const messageEl = document.getElementById('password-message');
    
    const data = {
        action: 'change_password',
        current_password: formData.get('current_password'),
        new_password: formData.get('new_password'),
        confirm_password: formData.get('confirm_password'),
        _csrf: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    };
    
    if (data.new_password !== data.confirm_password) {
        showMessage(messageEl, 'New passwords do not match', 'error');
        return;
    }
    
    fetch(Birkly.url('/api/users.php'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'ok') {
            showMessage(messageEl, 'Password changed successfully', 'success');
            form.reset();
        } else {
            showMessage(messageEl, data.message || 'Error changing password', 'error');
        }
    })
    .catch(error => {
        console.error('Password change error:', error);
        showMessage(messageEl, 'Error changing password', 'error');
    });
}

function handleLanguageSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const language = form.querySelector('#account-language').value;
    const messageEl = document.getElementById('language-message');
    
    fetch(Birkly.url('/api/users.php'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            action: 'set_language',
            language: language,
            _csrf: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'ok') {
            showMessage(messageEl, 'Language preference saved. Reloading page...', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showMessage(messageEl, data.message || 'Error saving language preference', 'error');
        }
    })
    .catch(error => {
        console.error('Language change error:', error);
        showMessage(messageEl, 'Error saving language preference', 'error');
    });
}

function handleNotificationsSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const messageEl = document.getElementById('notifications-message');
    
    // Collect all checkbox values
    const preferences = {
        entry_published: document.getElementById('notify-entry-published')?.checked ?? true,
        entry_review: document.getElementById('notify-entry-review')?.checked ?? true,
        entry_commented: document.getElementById('notify-entry-commented')?.checked ?? true,
        invitation_accepted: document.getElementById('notify-invitation-accepted')?.checked ?? true,
        role_changed: document.getElementById('notify-role-changed')?.checked ?? true,
        system_updates: document.getElementById('notify-system-updates')?.checked ?? true,
        security_events: document.getElementById('notify-security-events')?.checked ?? false
    };
    
    fetch(Birkly.url('/api/users.php'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            action: 'update_notification_preferences',
            preferences: preferences,
            _csrf: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'ok') {
            showMessage(messageEl, 'Notification preferences saved successfully', 'success');
        } else {
            showMessage(messageEl, data.message || 'Error saving notification preferences', 'error');
        }
    })
    .catch(error => {
        console.error('Notification preferences error:', error);
        showMessage(messageEl, 'Error saving notification preferences', 'error');
    });
}

function handleRemoveAvatar() {
    if (!(await BirklyFeedback.confirm('Are you sure you want to remove your avatar?'))) {
        return;
    }
    
    fetch(Birkly.url('/api/users.php'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            action: 'remove_avatar',
            _csrf: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'ok') {
            window.location.reload();
        } else {
            BirklyFeedback.toast(data.message || 'Error removing avatar', 'error')
        }
    })
    .catch(error => {
        console.error('Remove avatar error:', error);
        BirklyFeedback.toast('Error removing avatar', 'error')
    });
}

function showMessage(element, message, type) {
    if (!element) return;
    
    element.textContent = message;
    element.className = `settings-message ${type}`;
    element.style.display = 'block';
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        element.style.display = 'none';
    }, 5000);
}

