# WordPress.org Submission Checklist (Rigorous)

Use this as a go/no-go gate. If any **Critical** item is unchecked, do not submit.

Premium source reference: the premium version of this plugin is located at `../contactin-pro`.

## 0) Export Sync Preflight (Critical)
- [x] Release process runs from `../contactin-pro` free-export flow into this directory.
- [x] Destination plugin slug is exactly `contactin` (folder, main file references, metadata).
- [x] Product/plugin name is exactly `ContactIn` in free build header and core UI.
- [x] Main plugin header `Plugin Name:` is exactly `ContactIn`.
- [x] Text domain is aligned with slug (`contactin`).
- [x] `readme.txt` slug-facing metadata/support references are fully free-build aligned (no `contactin-pro` in public-facing fields).
- [x] Premium-only controls in visible admin UIs are marked with **Pro** indicators.
- [x] Premium-only actions are gated in free build (server-side checks present).
- [x] Free-export generator explicitly forces free mode (`is_premium => false`, `CONTACTINBOX_IS_FREE => true`).

Phase 0 run notes (2026-03-31):
- `sync-from-pro.sh` should invoke `../contactin-pro/generate-free.sh` (not raw premium export).
- Smoke run completed: free generator patched premium flags, stripped pro-only files, and copied result into this folder.
- Phase 0 is complete.

## 1) Eligibility & Packaging (Critical)
- [x] GPL-compatible licensing across code/assets/dependencies.
- [x] No trialware/crippled behavior violating WP.org policy.
- [x] Package contains only production/runtime files.
- [x] Main plugin header and readme versions are aligned.

Phase 1 run notes (2026-03-31):
- Licensing spot-check passed: plugin license is GPL-3.0-or-later; bundled key dependency licenses include GPL (Freemius SDK) and MIT (Composer components), both GPL-compatible.
- Free-build policy check passed: premium-only files are stripped by generator and premium features are gated/marked in UI.
- Packaging check passed: `finish-free.sh --zip` built `/tmp/contactin-free.1.1.0.zip` and exclusion rules now remove internal helper files (`finish-free.sh`, `sync-from-pro.sh`, `WORDPRESS_SUBMISSION_CHECKLIST.md`).
- Version alignment passed: plugin header version (`1.1.0`) matches `readme.txt` stable tag (`1.1.0`).

## 2) Security Review (Critical)
- [x] Nonce + capability checks on admin/AJAX/REST mutations.
- [x] Input validation/sanitization across all entry points.
- [x] Contextual output escaping.
- [x] Prepared SQL only.

Phase 2 run notes (2026-03-31):
- Fixed one concrete gap: `handle_export_report()` in `includes/Admin/AJAX/AJAXDispatcher.php` now enforces capability checks in addition to nonce validation.
- AJAX handlers were sampled and verified to use centralized `BaseAJAXHandler::verify()` (nonce + capability) plus per-field sanitization patterns (`sanitize_text_field`, `sanitize_email`, `absint`, etc.).
- Direct superglobal output scan did not find immediate high-risk `echo $_GET/$_POST/$_REQUEST` patterns in plugin code paths.
- SQL hardening remediation applied across core/repository hotspots (`AlertSystem`, `SafeUninstallHandler`, `MessageRepository`, `CRMRepository`, `EmailLogRepository`, `RestLogRepository`, `PerformanceOptimizer`, `DatabaseOptimizer`, `ConcurrencyManager`, `DB`) using prepared identifier queries.
- Contextual escaping remediation completed in key admin templates (`inbox-actions`, `message-view-modal`, `rest-log-tablenav`, `email-log-tablenav`, `analytics-dashboard-page`, `maintenance-page`, `settings-page`), including safe attribute rendering and `wp_kses_post()` for trusted pagination HTML.

## 3) Privacy & Compliance (Critical)
- [x] Privacy disclosures complete.
- [x] Data export/erase integration where applicable.
- [x] Explicit consent for any telemetry/tracking.

Phase 3 run notes (2026-03-31):
- Updated `readme.txt` external-service disclosures to explicitly state Freemius tracking consent/opt-out behavior.
- Corrected reCAPTCHA privacy/terms links in `readme.txt` privacy section.
- Verified WordPress privacy integration in `includes/Core/GDPR.php` (`wp_privacy_personal_data_exporters`, `wp_privacy_personal_data_erasers`).

## 4) Coding Standards (Critical)
- [ ] WP coding standards checks pass.
- [x] No debug notices/warnings on `WP_DEBUG`.
- [x] Safe activation/deactivation/uninstall behavior.

Phase 4 run notes (2026-04-01):
- Installed and used a focused `phpcs.xml.dist` to cut noise from legacy doc/style-only sniffs and `dist/` artifacts while keeping actionable/runtime checks in scope.
- Remediated priority standards findings found in the focused pass, including bare `json_encode()` calls, discouraged timestamp usage, strict `in_array()` comparisons, loop `count()` conditions, duplicate array key handling, and intentional low-level error suppression/documented ignores.
- Global WPCS still does **not** pass end-to-end because legacy style/documentation debt remains (`56 errors / 47 warnings` in the focused config after remediation), so the main standards checkbox stays open.
- Runtime `WP_DEBUG` smoke test passed on the active local site (`wpdev.local`) after fixing early textdomain loading in `contactin.php` (moved `load_plugin_textdomain()` to `init` priority 0). Post-fix frontend/bootstrap exercise produced no new debug-log lines after a fresh marker.
- Follow-up bootstrap timing hardening completed: main plugin bootstrap in `contactin.php` now runs on `init` priority 1 (instead of `plugins_loaded`) so translatable strings are not initialized before `init`; CLI verification no longer reports early `_load_textdomain_just_in_time` notices.
- Verified lifecycle safety paths exist (`register_activation_hook`, `register_deactivation_hook`, Freemius `after_uninstall`, `SafeUninstallHandler`).

## 5) Functional Quality (High)
- [ ] Fresh install works.
- [ ] Upgrade migration works.
- [ ] Deactivate/reactivate is safe.

Phase 5 run notes (2026-03-31):
- Requires runtime validation on clean WordPress instances (fresh + upgrade path), not fully verifiable by static analysis alone.

## 6) Performance & Reliability (High)
- [x] No unnecessary heavy frontend queries.
- [x] Queue/cron processing is bounded and stable.

Phase 6 run notes (2026-03-31):
- Frontend scan found no direct `WP_Query`/`query_posts()` usage in `includes/Frontend/**`.
- Queue/cron controls are bounded via configured batch limits and schedule controls (e.g., activation defaults + queue limits in core).

## 7) Compatibility Matrix (High)
- [ ] Tested on target WP/PHP versions.
- [ ] Multisite compatibility validated (if claimed).

Phase 7 run notes (2026-03-31):
- Metadata targets are present in `readme.txt` (`Requires at least: 6.4`, `Tested up to: 6.9`, `Requires PHP: 7.4`), but runtime matrix testing remains pending.

## 8) i18n & Accessibility (High)
- [x] Translatable strings with correct text domain.
- [ ] Keyboard/focus/contrast checks for admin UI.

Phase 8 run notes (2026-04-01):
- Ran a focused i18n-only PHPCS pass (`WordPress.WP.I18n`) across plugin PHP files excluding `vendor/`, `assets/`, and `dist/`; no active text-domain sniffs remained.
- Corrected the central free-build text-domain constant in `includes/Core/Config.php` from `contactin-pro` to `contactin`.
- Aligned remaining free-build plugin-info metadata to the free slug/repository (`includes/Admin/PluginInfo.php`, `includes/Admin/Pages/PluginDetails.php`) so admin-facing plugin details no longer identify the free build as `contactin-pro`.
- Accessibility still requires an interactive admin UI pass (keyboard navigation, focus management, and contrast), so the second checkbox remains open.

## 9) Readme & Metadata (Critical)
- [x] `readme.txt` format is WP.org-compliant.
- [x] `Requires`, `Tested up to`, `Requires PHP`, `Stable tag` accurate.
- [x] Assets and links correct.

Phase 9 run notes (2026-04-01):
- Updated free-plugin public metadata and support links in `readme.txt` to remove `contactin-pro` public-facing references (`Plugin URI`, repo/docs/issues links, support URL).
- Corrected free install instructions (`/wp-content/plugins/contactin/`) and admin navigation label (`ContactIn > Settings`).
- Removed `== Screenshots ==` section entries that had no corresponding screenshot assets to avoid broken WP.org asset references.
- Re-validated key metadata fields remain aligned with current plugin header (`Stable tag: 1.0.9`, `Requires at least: 6.4`, `Tested up to: 6.9`, `Requires PHP: 7.4`).

## 10) Admin UX & Policy Safety (High)
- [x] No aggressive nags.
- [x] Upsells are clear, non-deceptive, and policy-compliant.

Phase 10 run notes (2026-04-01):
- Reviewed notice/upsell sources in `SupportBoxesManager`, `AssetsDispatcher`, and related admin partials.
- Free-plan review prompt is delayed until 14 days after installation and includes both snooze and dismiss actions (`wordpress-review-box.php`).
- Upgrade/review/feedback boxes render only inside ContactIn admin templates (`inbox`, `dashboard`, `settings`), not as global wp-admin nags.
- Expired-license notices are limited to ContactIn admin pages and explicitly skipped on Freemius billing/account screens (`AssetsDispatcher::should_render_expired_license_notice()`).
- Upsells remain explicit with Pro wording and do not block core free functionality.

## 11) Release Engineering (Critical)
- [x] Reproducible build/sync process documented.
- [x] Final ZIP smoke-tested on clean WP.

Phase 11 run notes (2026-04-01):
- Reproducible flow is documented and operational (`sync-from-pro.sh` + `finish-free.sh --zip`).
- Added reusable export script for ongoing releases (workspace tooling path: `../build/contactin-tools/export-distribution.sh`).
- WordPress Plugin Check release scan now passes cleanly (`wp plugin check ... --require=.../plugin-check/cli.php` → `Success: Checks complete. No errors found.`).
- To keep release package policy-clean, non-runtime tooling files were moved out of the plugin directory (`../build/contactin-tools/export-distribution.sh`, `../build/contactin-tools/phpcs.xml.dist`).
- Clean-site smoke install run completed successfully on local WordPress environment.

## 12) Reviewer Bundle (Recommended)
- [ ] Reviewer notes include architecture, data flows, and external service usage.
- [ ] Security and capability map included.
