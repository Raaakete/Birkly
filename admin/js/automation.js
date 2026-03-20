/**
 * Automation Page JavaScript
 * Handles workflow creation, editing, and management
 */

(function() {
    'use strict';

    const automationData = window.automationData || {};
    const csrfToken = automationData.csrfToken || '';
    const collections = automationData.collections || [];
    
    console.log('Automation system initialized');
    
    // Workflow list interactions
    const workflowItems = document.querySelectorAll('.workflow-item');
    workflowItems.forEach(item => {
        // Toggle workflow enabled/disabled
        const toggleBtn = item.querySelector('.workflow-toggle-btn');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                const workflowId = this.getAttribute('data-workflow-id');
                const isEnabled = this.getAttribute('data-enabled') === 'true';
                
                toggleWorkflow(workflowId, !isEnabled);
            });
        }
        
        // Delete workflow
        const deleteBtn = item.querySelector('.workflow-delete-btn');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function() {
                const workflowId = this.getAttribute('data-workflow-id');
                
                if (confirm('Are you sure you want to delete this workflow?')) {
                    deleteWorkflow(workflowId);
                }
            });
        }
    });
    
    // Create/Edit workflow modal
    const createBtn = document.getElementById('create-workflow-btn');
    if (createBtn) {
        createBtn.addEventListener('click', function() {
            showWorkflowModal();
        });
    }
    
    function toggleWorkflow(workflowId, enabled) {
        fetch('/api/automation.php', {
            method: 'PATCH',
            headers: {
                'X-CSRF-Token': csrfToken,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                workflow_id: workflowId,
                enabled: enabled
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Error toggling workflow: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
    }
    
    function deleteWorkflow(workflowId) {
        fetch('/api/automation.php?id=' + workflowId, {
            method: 'DELETE',
            headers: {
                'X-CSRF-Token': csrfToken,
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Error deleting workflow: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
    }
    
    function showWorkflowModal(workflowData) {
        // Modal logic here
        console.log('Show workflow modal:', workflowData);
    }
})();
