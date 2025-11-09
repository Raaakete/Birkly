        </main>
    </div>

    <script src="/admin/js/utils.js"></script>
    <script src="/admin/js/theme.js"></script>
    <script src="/admin/js/fieldtypes-shim.js"></script>
    <script src="/admin/js/media-modal.js?v=20250804-15"></script>
    <script src="/admin/js/accessibility.js"></script>
    
    <!-- Logout functionality -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const logoutBtn = document.getElementById('logout-btn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', function() {
                if (confirm('Are you sure you want to logout?')) {
                    logoutBtn.textContent = 'Logging out...';
                    logoutBtn.disabled = true;
                    
                    fetch('/admin/logout.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: '_csrf=' + encodeURIComponent(document.querySelector('meta[name="csrf-token"]').getAttribute('content'))
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'ok') {
                            window.location.href = '/admin/login.php';
                        } else {
                            alert('Error logging out: ' + data.message);
                            logoutBtn.textContent = 'Logout';
                            logoutBtn.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Logout error:', error);
                        alert('Error logging out. Please try again.');
                        logoutBtn.textContent = 'Logout';
                        logoutBtn.disabled = false;
                    });
                }
            });
        }
    });
    
    // User menu dropdown
    const userMenuTrigger = document.getElementById('user-menu-trigger');
    const userMenuDropdown = document.getElementById('user-menu-dropdown');
    
    if (userMenuTrigger && userMenuDropdown) {
        userMenuTrigger.addEventListener('click', function(e) {
            e.stopPropagation();
            const isExpanded = userMenuTrigger.getAttribute('aria-expanded') === 'true';
            userMenuDropdown.style.display = isExpanded ? 'none' : 'block';
            userMenuTrigger.setAttribute('aria-expanded', !isExpanded);
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!userMenuTrigger.contains(e.target) && !userMenuDropdown.contains(e.target)) {
                userMenuDropdown.style.display = 'none';
                userMenuTrigger.setAttribute('aria-expanded', 'false');
            }
        });
    }
    
    // Language switcher (user-level)
    const languageSelect = document.getElementById('user-language-select');
    if (languageSelect) {
        languageSelect.addEventListener('change', function() {
            const language = this.value;
            
            fetch('/api/users.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'set_language',
                    language: language,
                    _csrf: document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'ok') {
                    // Reload page to apply new language
                    window.location.reload();
                } else {
                    alert('Error changing language: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Language change error:', error);
                alert('Error changing language. Please try again.');
            });
        });
    }
    
    // Onboarding completion modal
    document.addEventListener('DOMContentLoaded', function() {
        // Check if we should show onboarding completion modal
        const urlParams = new URLSearchParams(window.location.search);
        const demoWebsiteUrl = urlParams.get('demo_website_url');
        const demoChoice = urlParams.get('demo_choice');
        
        // Also check session data (from server)
        <?php if (isset($_SESSION['onboarding_demo_website_url']) || isset($_SESSION['onboarding_demo_choice'])): ?>
        const showModal = true;
        const sessionDemoUrl = '<?= htmlspecialchars($_SESSION['onboarding_demo_website_url'] ?? '') ?>';
        const sessionDemoChoice = '<?= htmlspecialchars($_SESSION['onboarding_demo_choice'] ?? '') ?>';
        <?php else: ?>
        const showModal = false;
        const sessionDemoUrl = '';
        const sessionDemoChoice = '';
        <?php endif; ?>
        
        if (showModal || demoWebsiteUrl || demoChoice) {
            const modal = document.getElementById('onboarding-complete-modal');
            if (modal) {
                const demoUrl = demoWebsiteUrl || sessionDemoUrl;
                const choice = demoChoice || sessionDemoChoice;
                
                // Update demo website link if available
                if (demoUrl && choice === 'keep') {
                    const demoCard = modal.querySelector('#demo-website-card');
                    const demoLink = modal.querySelector('#demo-website-link');
                    if (demoCard) {
                        demoCard.style.display = 'block';
                    }
                    if (demoLink) {
                        demoLink.href = demoUrl;
                        demoLink.style.display = 'inline-block';
                    }
                } else {
                    const demoCard = modal.querySelector('#demo-website-card');
                    const demoLink = modal.querySelector('#demo-website-link');
                    if (demoCard) {
                        demoCard.style.display = 'none';
                    }
                    if (demoLink) {
                        demoLink.style.display = 'none';
                    }
                }
                
                // Show modal
                modal.style.display = 'flex';
                
                // Clear session data
                <?php 
                unset($_SESSION['onboarding_demo_website_url']);
                unset($_SESSION['onboarding_demo_choice']);
                ?>
            }
        }
        
        // Close modal handler
        const closeModalBtn = document.getElementById('onboarding-complete-close');
        const closeModalBtnFooter = document.getElementById('onboarding-complete-close-btn');
        const modalBackdrop = document.getElementById('onboarding-complete-modal');
        
        function closeModal() {
            if (modalBackdrop) {
                modalBackdrop.style.display = 'none';
                // Remove URL parameters
                const url = new URL(window.location);
                url.searchParams.delete('demo_website_url');
                url.searchParams.delete('demo_choice');
                window.history.replaceState({}, '', url);
            }
        }
        
        if (closeModalBtn) {
            closeModalBtn.addEventListener('click', closeModal);
        }
        
        if (closeModalBtnFooter) {
            closeModalBtnFooter.addEventListener('click', closeModal);
        }
        
        if (modalBackdrop) {
            // Close on backdrop click
            modalBackdrop.addEventListener('click', function(e) {
                if (e.target === modalBackdrop) {
                    closeModal();
                }
            });
        }
    });
    </script>
    
    <!-- No SPA router/scripts in MPA mode -->
</body>
</html>

