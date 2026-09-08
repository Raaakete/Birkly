(function () {
    'use strict';

    var UI = window.Birkly && window.Birkly.DashboardMount;
    var widgets = window.BirklyDashboardWidgets = window.BirklyDashboardWidgets || {};
    if (!UI) {
        return;
    }

    widgets.commerceStats = function (element, widget, data) {
        var summary = data && data.summary;
        if (!summary) {
            UI.setContent(element, UI.empty('No commerce orders yet.'));
            return;
        }
        UI.setContent(element, UI.statGrid([
            UI.statTile(UI.formatCents(summary.revenue_cents), 'Revenue', 'commerce'),
            UI.statTile(summary.order_count || 0, 'Orders', 'commerce'),
        ]));
    };
})();
