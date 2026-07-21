(function() {
    'use strict';

    const handlers = new Map();
    const Birkly = window.Birkly = window.Birkly || {};
    const pluginHandlers = window.BirklyDashboardWidgets = window.BirklyDashboardWidgets || {};

    function getMountName(widget) {
        if (!widget || typeof widget !== 'object') return '';
        return widget.mount || widget.id || '';
    }

    function registerMount(name, handler) {
        if (typeof name !== 'string' || name.trim() === '' || typeof handler !== 'function') {
            throw new TypeError('Dashboard widget handlers require a name and function.');
        }
        handlers.set(name, handler);
    }

    function getNamedHandler(name) {
        if (handlers.has(name)) return handlers.get(name);
        // Legacy plugins may expose handlers on this dedicated registry only.
        // Never resolve arbitrary globals from widget-provided mount names.
        if (Object.prototype.hasOwnProperty.call(pluginHandlers, name)
            && typeof pluginHandlers[name] === 'function') {
            return pluginHandlers[name];
        }
        const prefix = 'BirklyDashboardWidgets.';
        const pluginName = name.startsWith(prefix) ? name.slice(prefix.length) : '';
        return pluginName && Object.prototype.hasOwnProperty.call(pluginHandlers, pluginName)
            && typeof pluginHandlers[pluginName] === 'function'
            ? pluginHandlers[pluginName]
            : null;
    }

    function mountWidget(element, widget, data) {
        const name = getMountName(widget);
        const handler = getNamedHandler(name);
        if (typeof handler !== 'function') return false;

        handler(element, widget, data);
        return true;
    }

    function fetchWidgetData(widget) {
        const endpoint = widget && widget.endpoint;
        if (!endpoint) {
            return Promise.resolve(null);
        }

        const url = typeof Birkly.url === 'function' ? Birkly.url(endpoint) : endpoint;
        return fetch(url, { credentials: 'same-origin' })
            .then(function(response) {
                if (!response.ok) throw new Error('Widget endpoint request failed.');
                return response.json();
            })
            .then(function(payload) {
                return payload.data !== undefined ? payload.data : payload;
            });
    }

    function loadWidget(element, widget) {
        return fetchWidgetData(widget)
            .then(function(data) {
                return mountWidget(element, widget, data);
            })
            .catch(function(error) {
                if (element) {
                    element.dispatchEvent(new CustomEvent('birkly:dashboard-widget-error', {
                    bubbles: true,
                        detail: { widget: widget, error: error },
                    }));
                }
                return false;
            });
    }

    pluginHandlers.register = registerMount;
    Birkly.Dashboard = Object.assign(Birkly.Dashboard || {}, {
        registerMount: registerMount,
        mountWidget: mountWidget,
        fetchWidgetData: fetchWidgetData,
        loadWidget: loadWidget,
    });
})();
