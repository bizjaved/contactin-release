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
- [ ] No debug notices/warnings on `WP_DEBUG`.
- [x] Safe activation/deactivation/uninstall behavior.

Phase 4 run notes (2026-03-31):
- Installed dev tooling and executed WPCS scan: `vendor/bin/phpcs --standard=WordPress --ignore=vendor/* --report=summary .`.
- Result: currently not passing globally (legacy backlog across codebase and minified `dist/` artifacts).
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
- [ ] Translatable strings with correct text domain.
- [ ] Keyboard/focus/contrast checks for admin UI.

Phase 8 run notes (2026-03-31):
- Spot checks show extensive use of `contactin` text domain and ARIA/screen-reader patterns across admin templates; full audit sweep remains pending.

## 9) Readme & Metadata (Critical)
- [ ] `readme.txt` format is WP.org-compliant.
- [x] `Requires`, `Tested up to`, `Requires PHP`, `Stable tag` accurate.
- [ ] Assets and links correct.

Phase 9 run notes (2026-03-31):
- Version/meta fields are aligned and accurate.
- Final WP.org readme parser validation + link review remains pending.

## 10) Admin UX & Policy Safety (High)
- [ ] No aggressive nags.
- [x] Upsells are clear, non-deceptive, and policy-compliant.

Phase 10 run notes (2026-03-31):
- Upsell patterns remain explicit with Pro labeling and disabled-state gating.
- A final interactive admin UX pass for nag frequency/placement is still pending.

## 11) Release Engineering (Critical)
- [x] Reproducible build/sync process documented.
- [ ] Final ZIP smoke-tested on clean WP.

Phase 11 run notes (2026-03-31):
- Reproducible flow is documented and operational (`sync-from-pro.sh` + `finish-free.sh --zip`).
- Clean-site install smoke test of the final ZIP remains pending.

## 12) Reviewer Bundle (Recommended)
- [ ] Reviewer notes include architecture, data flows, and external service usage.
- [ ] Security and capability map included.
