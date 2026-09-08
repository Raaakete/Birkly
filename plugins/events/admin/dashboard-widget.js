(function () {
    'use strict';

    var UI = window.Birkly && window.Birkly.DashboardMount;
    var widgets = window.BirklyDashboardWidgets = window.BirklyDashboardWidgets || {};
    if (!UI) {
        return;
    }

    widgets.eventsUpcoming = function (element, widget, data) {
        var events = data && Array.isArray(data.upcoming) ? data.upcoming : [];
        if (!events.length) {
            UI.setContent(element, UI.empty('No upcoming events.'));
            return;
        }
        var items = events.slice(0, 5).map(function (event) {
            var starts = event.start ? UI.formatTimeAgo(event.start) : '';
            return UI.feedItem({
                title: event.title || 'Untitled event',
                meta: starts ? UI.escapeHtml(starts) : '',
            });
        });
        var footer = data.events_url
            ? UI.footerLinks('<a href="' + UI.escapeHtml(data.events_url) + '">Manage events →</a>')
            : '';
        UI.setContent(element, UI.feedList(items) + footer);
    };
})();
