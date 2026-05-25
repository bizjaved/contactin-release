# Asset Source Map

This document maps distributed minified assets to their readable source counterparts included in this plugin package.

- Minified JS: dist/js/*.min.js
- Readable JS source: assets/src/js/*.js
- Minified CSS: dist/css/*.min.css
- Readable CSS source: assets/src/css/*.css

## JavaScript Mapping
- dist/js/admin-email-log.min.js -> assets/src/js/admin-email-log.js
- dist/js/admin-global.min.js -> assets/src/js/admin-global.js
- dist/js/admin-inbox.min.js -> assets/src/js/admin-inbox.js
- dist/js/admin-settings.min.js -> assets/src/js/admin-settings.js
- dist/js/attachment-cleanup.min.js -> assets/src/js/attachment-cleanup.js
- dist/js/confetti.min.js -> assets/src/js/confetti.js
- dist/js/crm-settings.min.js -> assets/src/js/crm-settings.js
- dist/js/dashboard-analytics.min.js -> assets/src/js/dashboard-analytics.js
- dist/js/dashboard-chart-renderer.min.js -> assets/src/js/dashboard-chart-renderer.js
- dist/js/dashboard-date-utils.min.js -> assets/src/js/dashboard-date-utils.js
- dist/js/dashboard-render-helpers.min.js -> assets/src/js/dashboard-render-helpers.js
- dist/js/dashboard-sparkline.min.js -> assets/src/js/dashboard-sparkline.js
- dist/js/dashboard-tabs.min.js -> assets/src/js/dashboard-tabs.js
- dist/js/dashboard-widgets-live.min.js -> assets/src/js/dashboard-widgets-live.js
- dist/js/elementor-editor.min.js -> assets/src/js/elementor-editor.js
- dist/js/frontend.min.js -> assets/src/js/frontend.js
- dist/js/gutenberg-block.min.js -> assets/src/js/gutenberg-block.js
- dist/js/integration.min.js -> assets/src/js/integration.js
- dist/js/maintenance.min.js -> assets/src/js/maintenance.js
- dist/js/rest-log.min.js -> assets/src/js/rest-log.js
- dist/js/sf-attachment-settings.min.js -> assets/src/js/sf-attachment-settings.js

## CSS Mapping
- dist/css/admin-global.min.css -> assets/src/css/admin-global.css
- dist/css/admin-inbox.min.css -> assets/src/css/admin-inbox.css
- dist/css/admin-inbox-old.min.css -> assets/src/css/admin-inbox-old.css
- dist/css/admin-settings.min.css -> assets/src/css/admin-settings.css
- dist/css/attachment-cleanup.min.css -> assets/src/css/attachment-cleanup.css
- dist/css/contact-detail.min.css -> assets/src/css/contact-detail.css
- dist/css/contact-detail-tabs.min.css -> assets/src/css/contact-detail-tabs.css
- dist/css/contact-edit-modal.min.css -> assets/src/css/contact-edit-modal.css
- dist/css/crm-log.min.css -> assets/src/css/crm-log.css
- dist/css/crm-settings.min.css -> assets/src/css/crm-settings.css
- dist/css/dashboard-analytics.min.css -> assets/src/css/dashboard-analytics.css
- dist/css/dashboard-widgets.min.css -> assets/src/css/dashboard-widgets.css
- dist/css/elementor-editor.min.css -> assets/src/css/elementor-editor.css
- dist/css/frontend.min.css -> assets/src/css/frontend.css
- dist/css/gutenberg-editor.min.css -> assets/src/css/gutenberg-editor.css
- dist/css/inbox-consolidated.min.css -> assets/src/css/inbox-consolidated.css
- dist/css/integration.min.css -> assets/src/css/integration.css
- dist/css/logs.min.css -> assets/src/css/logs.css
- dist/css/maintenance.min.css -> assets/src/css/maintenance.css
- dist/css/sf-attachment-settings.min.css -> assets/src/css/sf-attachment-settings.css
- dist/css/tests.min.css -> assets/src/css/tests.css

## Rebuild Commands

```bash
# JavaScript (example using terser)
npx terser assets/src/js/<name>.js -c -m -o dist/js/<name>.min.js

# CSS (example using clean-css-cli)
npx cleancss -o dist/css/<name>.min.css assets/src/css/<name>.css
```
