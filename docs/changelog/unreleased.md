# Unreleased

## Added

- Optional **custom 404 page** for Birkly-hosted project sites (`settings/project.json`: `custom_404_enabled`, `custom_404_path`). Settings → Project tab and MCP `project_site.save_settings` / `project_site.overview`. Missing HTML-like URLs can render a chosen `project/` page with HTTP 404, SSR, and `X-Robots-Tag: noindex`; assets and API paths keep the plain `Not found` body.
