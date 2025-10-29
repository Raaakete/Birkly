console.log('utils.js loaded');
/**
 * HaruCMS Admin Utils
 * Basic utility functions for the admin interface
 */

window.HaruCMS = window.HaruCMS || {};

HaruCMS.Utils = {
    // Format date for display
    formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString();
    },
    
    // Format datetime for display
    formatDateTime(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleString();
    },
    
    // Show notification
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 3000);
    },
    
    // Confirm action
    confirmAction(message) {
        return confirm(message);
    },
    
    // Escape HTML
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}; 