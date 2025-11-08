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
    
    // Language switcher
    const languageSelect = document.getElementById('language-select');
    if (languageSelect) {
        languageSelect.addEventListener('change', function() {
            const language = this.value;
            
            fetch('/api/settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=set_language&language=' + encodeURIComponent(language) + '&_csrf=' + encodeURIComponent(document.querySelector('meta[name="csrf-token"]').getAttribute('content'))
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
    </script>
    
    <!-- No SPA router/scripts in MPA mode -->
</body>
</html>

