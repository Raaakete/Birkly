/**
 * Birkly Admin Theme
 * Theme management for the admin interface
 */

window.Birkly = window.Birkly || {};

Birkly.Theme = {
    // Current theme
    currentTheme: 'light',
    
    // Initialize theme
    init() {
        // Load saved theme preference
        const savedTheme = localStorage.getItem('birkly_theme');
        if (savedTheme) {
            this.setTheme(savedTheme);
        }
        
        // Apply theme to body
        document.body.setAttribute('data-theme', this.currentTheme);
    },
    
    // Set theme
    setTheme(theme) {
        this.currentTheme = theme;
        document.body.setAttribute('data-theme', theme);
        localStorage.setItem('birkly_theme', theme);
    },
    
    // Toggle theme
    toggleTheme() {
        const newTheme = this.currentTheme === 'light' ? 'dark' : 'light';
        this.setTheme(newTheme);
    },
    
    // Get current theme
    getCurrentTheme() {
        return this.currentTheme;
    }
};

// Initialize theme when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    Birkly.Theme.init();
}); 