/**
 * Password Fieldtype JavaScript
 * Handles password input, strength checking, and password reset email functionality.
 */

(function() {
    'use strict';

    if (window.__haru_password_fieldtype_bound) return;
    window.__haru_password_fieldtype_bound = true;

    function initContainer(container) {
        if (!container || !(container instanceof HTMLElement)) return;
        if (container.dataset.passwordFieldtypeInit === '1') return;
        container.dataset.passwordFieldtypeInit = '1';

        const fieldName = container.getAttribute('data-field-name') || '';
        let entryId = container.getAttribute('data-entry-id') || '';
        let userId = container.getAttribute('data-user-id') || '';
        
        // Try to get user_id from entry data if available (for profile entries)
        if (!userId) {
            // Check if there's a user_id field in the form
            const userIdInput = document.querySelector('input[name="user_id"]');
            if (userIdInput && userIdInput.value) {
                userId = userIdInput.value;
                container.setAttribute('data-user-id', userId);
            }
            
            // Also try to get from entry data if available in page context
            if (!userId && window.currentEntry && window.currentEntry.user_id) {
                userId = window.currentEntry.user_id;
                container.setAttribute('data-user-id', userId);
            }
        }
        
        // Try to get entry ID if not set
        if (!entryId) {
            const entryIdInput = document.querySelector('input[name="id"]');
            if (entryIdInput && entryIdInput.value) {
                entryId = entryIdInput.value;
                container.setAttribute('data-entry-id', entryId);
            }
        }
        
        const newPasswordInput = container.querySelector('.password-fieldtype-new .password-fieldtype-input');
        const existingSection = container.querySelector('.password-fieldtype-existing');
        const changeBtn = container.querySelector('.password-fieldtype-change');
        const cancelChangeBtn = container.querySelector('.password-fieldtype-cancel-change');
        const changeForm = container.querySelector('.password-fieldtype-change-form');
        const changeInput = changeForm ? changeForm.querySelector('.password-fieldtype-input') : null;
        const resetEmailBtn = container.querySelector('.password-fieldtype-reset-email');
        const strengthIndicator = container.querySelector('.password-fieldtype-strength');

        // Password strength checker
        function checkPasswordStrength(password) {
            if (!password || password.length === 0) {
                return null;
            }
            
            let strength = 0;
            let feedback = [];
            
            if (password.length >= 8) strength++;
            else feedback.push('At least 8 characters');
            
            if (password.length >= 12) strength++;
            
            if (/[a-z]/.test(password)) strength++;
            else feedback.push('lowercase letter');
            
            if (/[A-Z]/.test(password)) strength++;
            else feedback.push('uppercase letter');
            
            if (/[0-9]/.test(password)) strength++;
            else feedback.push('number');
            
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            else feedback.push('special character');
            
            return {
                score: strength,
                maxScore: 6,
                level: strength <= 2 ? 'weak' : (strength <= 4 ? 'medium' : 'strong'),
                feedback: feedback
            };
        }

        function updateStrengthIndicator(input, indicator) {
            if (!indicator || !input) return;
            
            const password = input.value || '';
            const strength = checkPasswordStrength(password);
            
            if (!strength || password.length === 0) {
                indicator.style.display = 'none';
                indicator.className = 'password-fieldtype-strength';
                return;
            }
            
            indicator.style.display = 'block';
            indicator.className = 'password-fieldtype-strength ' + strength.level;
            
            const messages = {
                weak: 'Weak password',
                medium: 'Medium strength password',
                strong: 'Strong password'
            };
            
            indicator.textContent = messages[strength.level];
        }

        // Attach strength checking to password inputs
        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', function() {
                const indicator = container.querySelector('.password-fieldtype-new .password-fieldtype-strength');
                updateStrengthIndicator(this, indicator);
            });
        }

        if (changeInput) {
            changeInput.addEventListener('input', function() {
                const indicator = changeForm.querySelector('.password-fieldtype-strength');
                updateStrengthIndicator(this, indicator);
            });
        }

        // Handle "Change Password" button
        if (changeBtn && changeForm) {
            changeBtn.addEventListener('click', function() {
                changeForm.style.display = 'block';
                if (changeInput) {
                    changeInput.focus();
                }
            });
        }

        // Handle "Cancel" button
        if (cancelChangeBtn && changeForm) {
            cancelChangeBtn.addEventListener('click', function() {
                changeForm.style.display = 'none';
                if (changeInput) {
                    changeInput.value = '';
                    const indicator = changeForm.querySelector('.password-fieldtype-strength');
                    if (indicator) {
                        indicator.style.display = 'none';
                    }
                }
            });
        }

        // Handle "Send Password Reset Email" button
        if (resetEmailBtn) {
            resetEmailBtn.addEventListener('click', function() {
                const btn = this;
                const originalText = btn.innerHTML;
                
                if (!userId) {
                    alert('User ID is required to send password reset email');
                    return;
                }
                
                // Disable button and show loading
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending...';
                
                // Get CSRF token from form or meta tag
                let csrfToken = '';
                const csrfInput = document.querySelector('input[name="_csrf"]');
                if (csrfInput) {
                    csrfToken = csrfInput.value;
                } else {
                    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                    if (csrfMeta) {
                        csrfToken = csrfMeta.getAttribute('content');
                    }
                }
                
                // Send password reset email
                fetch('/api/index.php?route=password_reset&action=send_admin', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        user_id: userId,
                        _csrf: csrfToken
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'ok') {
                        alert('Password reset email sent successfully!');
                    } else {
                        alert('Error: ' + (data.message || 'Failed to send password reset email'));
                    }
                })
                .catch(error => {
                    console.error('Error sending password reset email:', error);
                    alert('Error: Failed to send password reset email');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
            });
        }

        // Handle form submission: if new password is provided, replace the hidden value
        const form = container.closest('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                // Check if there's a new password input with value
                const newPasswordField = container.querySelector('input[name="' + fieldName + '_new"]');
                if (newPasswordField && newPasswordField.value) {
                    // Update the hidden field with the new password (it will be hashed server-side)
                    const hiddenField = container.querySelector('input[name="' + fieldName + '"]');
                    if (hiddenField) {
                        hiddenField.value = newPasswordField.value;
                    }
                }
            });
        }
    }

    // Init existing
    function initAll() {
        document.querySelectorAll('.password-fieldtype').forEach(initContainer);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    // Init when admin entry editor dispatches fieldtype:init
    document.addEventListener('fieldtype:init', (e) => {
        const detail = e.detail || {};
        if (detail.fieldtype !== 'password') return;
        const el = detail.element;
        if (!(el instanceof HTMLElement)) return;
        const container = el.classList.contains('password-fieldtype') ? el : el.closest('.password-fieldtype');
        initContainer(container);
    });
})();
