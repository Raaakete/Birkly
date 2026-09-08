(function () {
    'use strict';

    var UI = window.Birkly && window.Birkly.DashboardMount;
    var widgets = window.BirklyDashboardWidgets = window.BirklyDashboardWidgets || {};
    if (!UI) {
        return;
    }

    widgets.studioPublishing = function (element, widget, data) {
        UI.setContent(element, UI.statGrid([
            UI.statTile((data && data.published) || 0, 'Published', 'studio'),
            UI.statTile((data && data.drafts) || 0, 'Drafts', 'studio'),
        ]));
    };
})();
