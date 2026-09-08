(function () {
    'use strict';

    var UI = window.Birkly && window.Birkly.DashboardMount;
    var widgets = window.BirklyDashboardWidgets = window.BirklyDashboardWidgets || {};
    if (!UI) {
        return;
    }

    widgets.automationRuns = function (element, widget, data) {
        UI.setContent(element, UI.statGrid([
            UI.statTile((data && data.enabled) || 0, 'Active', 'automation'),
            UI.statTile((data && data.queue && data.queue.pending) || 0, 'Queued', 'automation'),
        ]));
    };
})();
