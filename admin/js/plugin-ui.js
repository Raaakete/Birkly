/**
 * Plugin UI Component System for Birkly
 * 
 * Provides JavaScript utilities for plugin UI components.
 */

(function() {
    'use strict';
    
    // Global plugin UI registry
    window.HaruPluginUI = window.HaruPluginUI || {
        components: {},
        injections: {},
        initialized: false
    };
    
    /**
     * Register a plugin UI component
     * 
     * @param string $componentId Component identifier
     * @param object $component Component definition:
     *   - render: Function to render the component
     *   - mount: Function called when component is mounted
     *   - unmount: Function called when component is unmounted
     *   - update: Function called when component needs to update
     */
    HaruPluginUI.registerComponent = function(componentId, component) {
        this.components[componentId] = component;
    };
    
    /**
     * Get a registered component
     * 
     * @param string $componentId Component identifier
     * @return object|null Component or null if not found
     */
    HaruPluginUI.getComponent = function(componentId) {
        return this.components[componentId] || null;
    };
    
    /**
     * Render a component
     * 
     * @param string $componentId Component identifier
     * @param object $props Component props
     * @param HTMLElement $container Container element
     */
    HaruPluginUI.renderComponent = function(componentId, props, container) {
        const component = this.getComponent(componentId);
        if (!component) {
            console.warn('Component not found:', componentId);
            return;
        }
        
        if (!container) {
            console.warn('Container not provided for component:', componentId);
            return;
        }
        
        try {
            // Render component
            if (component.render) {
                const html = component.render(props);
                if (html) {
                    container.innerHTML = html;
                }
            }
            
            // Mount component
            if (component.mount) {
                component.mount(container, props);
            }
        } catch (error) {
            console.error('Error rendering component:', componentId, error);
        }
    };
    
    /**
     * Initialize plugin UI system
     */
    HaruPluginUI.init = function() {
        if (this.initialized) {
            return;
        }
        
        // Find all plugin injection points
        const injectionPoints = document.querySelectorAll('[data-plugin-injection]');
        injectionPoints.forEach(function(point) {
            const injectionId = point.getAttribute('data-plugin-injection');
            const componentId = point.getAttribute('data-plugin-component');
            
            if (componentId) {
                const props = {};
                // Get props from data attributes
                Array.from(point.attributes).forEach(function(attr) {
                    if (attr.name.startsWith('data-prop-')) {
                        const propName = attr.name.replace('data-prop-', '');
                        try {
                            props[propName] = JSON.parse(attr.value);
                        } catch (e) {
                            props[propName] = attr.value;
                        }
                    }
                });
                
                HaruPluginUI.renderComponent(componentId, props, point);
            }
        });
        
        // Initialize all registered components
        Object.keys(this.components).forEach(function(componentId) {
            const component = this.components[componentId];
            if (component.init) {
                try {
                    component.init();
                } catch (error) {
                    console.error('Error initializing component:', componentId, error);
                }
            }
        }.bind(this));
        
        this.initialized = true;
    };
    
    /**
     * Dispatch plugin event
     * 
     * @param string $eventName Event name
     * @param object $data Event data
     */
    HaruPluginUI.dispatchEvent = function(eventName, data) {
        const event = new CustomEvent('haru-plugin-' + eventName, {
            detail: data,
            bubbles: true,
            cancelable: true
        });
        document.dispatchEvent(event);
    };
    
    /**
     * Subscribe to plugin event
     * 
     * @param string $eventName Event name
     * @param function $callback Callback function
     */
    HaruPluginUI.subscribe = function(eventName, callback) {
        document.addEventListener('haru-plugin-' + eventName, function(event) {
            callback(event.detail);
        });
    };
    
    /**
     * Make API request to plugin endpoint
     * 
     * @param string $endpoint Endpoint path
     * @param object $options Request options
     * @return Promise
     */
    HaruPluginUI.apiRequest = function(endpoint, options) {
        options = options || {};
        
        const defaults = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        };
        
        // Merge options
        const config = Object.assign({}, defaults, options);
        
        // Add CSRF token if available
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (csrfToken && (config.method === 'POST' || config.method === 'PUT' || config.method === 'DELETE')) {
            if (!config.body) {
                config.body = JSON.stringify({});
            } else if (typeof config.body === 'string') {
                const bodyData = JSON.parse(config.body);
                bodyData._csrf = csrfToken.getAttribute('content');
                config.body = JSON.stringify(bodyData);
            } else {
                config.body._csrf = csrfToken.getAttribute('content');
                config.body = JSON.stringify(config.body);
            }
        }
        
        return fetch(endpoint, config)
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('API request failed: ' + response.statusText);
                }
                return response.json();
            })
            .catch(function(error) {
                console.error('Plugin API request error:', error);
                throw error;
            });
    };
    
    // Auto-initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            HaruPluginUI.init();
        });
    } else {
        HaruPluginUI.init();
    }
    
})();
