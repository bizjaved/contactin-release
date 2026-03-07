# WordPress.org Submission Checklist

**Plugin:** Contact Inbox  
**Version:** v1.0  
**Submission Date:** TBD  
**Author:** Javed Ahsan (bizjaved)

---

## ✅ COMPLETED

### Documentation
- [x] **readme.txt completely rewritten**
  - Correct plugin name: "Contact Inbox"
  - Correct shortcode: `[contact_inbox_form]`
  - Version: 1.0
  - Removed unsupported integration references
  - WordPress.org username format: `bizjaved`
  - Proper external services disclosure
  - Detailed feature list
  - FAQ section
  - Installation instructions

### Plugin Header
- [x] **Main plugin file (contact-inbox.php)**
  - Description enhanced with enterprise features
  - Shortcode example included
  - GitHub link present
  - Version: 1.0 (consistent)

### Versioning
- [x] **Version consistency**
  - contact-inbox.php: 1.0
  - Config.php: 1.0
  - readme.txt: 1.0

### Branding
- [x] **"Gold Standard 2025" removed**
  - Updated to "Enterprise-Grade" in 19 files

---

## ⚠️ CRITICAL ISSUES (MUST FIX)

### External Dependencies (WordPress.org Requirement)
**Status:** ❌ NOT COMPLIANT

WordPress.org requires all dependencies to be **bundled locally** or use WordPress core libraries. External CDN loading is not allowed due to security and availability concerns.

**Current Issues:**
```
Chart.js loaded from jsdelivr CDN:
- includes/Admin/Assets/AnalyticsWidgetsAssets.php (line ~)
- includes/Admin/Assets/AnalyticsDashboardAssets.php (line ~)
- includes/Admin/DashboardWidget.php (line ~)

Select2 loaded from jsdelivr CDN:
- includes/Admin/Assets/AnalyticsDashboardAssets.php (line ~)
```

**Required Actions:**

1. **Chart.js (4.4.0 & 3.9.1)**
   - Download from: https://github.com/chartjs/Chart.js/releases
   - License: MIT (compatible with GPL)
  - Location: Bundle to `assets/js/vendor/chart.min.js`
   - Update 5+ PHP files to load local version
   - Alternative: Use Google Charts (lighter, no bundle needed)

2. **Select2 (4.1.0-rc.0)**
   - Download from: https://github.com/select2/select2/releases
   - License: MIT (compatible with GPL)
  - Location: Bundle to `assets/js/vendor/select2.min.js`
  - Also bundle CSS: `assets/css/vendor/select2.min.css`
   - Update PHP files to load local version

3. **reCAPTCHA (ALLOWED - Exception)**
   - Google reCAPTCHA is allowed as it's a service, not a library
   - Properly disclosed in readme.txt ✓

**Implementation Steps:**
```bash
# Create vendor directories
mkdir -p assets/js/vendor
mkdir -p assets/css/vendor

# Download Chart.js
curl -L https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js \
  -o assets/js/vendor/chart.min.js

# Download Select2
curl -L https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js \
  -o assets/js/vendor/select2.min.js

curl -L https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css \
  -o assets/css/vendor/select2.min.css
```

**Files to Update After Bundling:**
- includes/Admin/Assets/AnalyticsWidgetsAssets.php
- includes/Admin/Assets/AnalyticsDashboardAssets.php
- includes/Admin/DashboardWidget.php

Replace:
```php
// OLD
wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js', [], '4.4.0', true);

// NEW
wp_enqueue_script('chartjs', plugin_dir_url(__FILE__) . 'js/vendor/chart.min.js', [], '4.4.0', true);
```

---

## 🔍 PENDING REVIEW

### Uninstall Process
**Status:** ⏳ NEEDS VERIFICATION

**Location:** `/var/www/html/wpdev/wp-content/plugins/contact-inbox-free/uninstall.php`

**Must Verify:**
- [ ] All 18 custom database tables dropped
- [ ] All WordPress options deleted (check for `contactin_*` options)
- [ ] All scheduled cron jobs removed
- [ ] All transients cleared
- [ ] User meta cleaned (if any stored)
- [ ] Uploaded files removed (if stored outside database)

**Test Command:**
```bash
# Check what options will be deleted
wp db query "SELECT option_name FROM wp_options WHERE option_name LIKE 'contactin%'"

# Test uninstall (dangerous - backup first!)
wp plugin uninstall contact-inbox-pro --yes
```

### Security Audit
**Status:** ⏳ NEEDS FULL AUDIT

WordPress.org reviewers will check for:

- [ ] **Direct Database Queries**
  - All using `$wpdb->prepare()` for user input?
  - No raw SQL with concatenation?
  - Check all `*Repository.php` files

- [ ] **Nonce Verification**
  - All AJAX handlers check nonces?
  - All form submissions validate nonces?
  - Check all `*Controller.php` AJAX methods

- [ ] **Input Sanitization**
  - All `$_POST`, `$_GET`, `$_REQUEST` sanitized?
  - Using `sanitize_text_field()`, `sanitize_email()`, etc.?
  - Check all form handlers

- [ ] **Output Escaping**
  - All template outputs escaped with `esc_html()`, `esc_attr()`, etc.?
  - Check all files in `templates/` directory

- [ ] **Capability Checks**
  - All admin actions check `current_user_can()`?
  - AJAX endpoints have permission callbacks?
  - Check all admin controllers

- [ ] **File Handling Security (if applicable)**
  - File type validation?
  - MIME type checking?
  - Upload directory permissions?

**Automated Check:**
```bash
# Install PHP_CodeSniffer with WordPress rules (already in vendor/)
./vendor/bin/phpcs --standard=WordPress includes/ --extensions=php
```

### Code Quality
**Status:** ⏳ NEEDS REVIEW

- [ ] Remove debug code (`var_dump`, `print_r`, `error_log` in production)
- [ ] Remove commented-out code blocks
- [ ] Check for test files in production (test-*.php should be in tests/ only)
- [ ] Verify no hardcoded credentials or API keys
- [ ] Check for TODO/FIXME comments that need resolution

**Find Debug Code:**
```bash
grep -r "var_dump\|print_r\|console\.log" includes/ templates/
grep -r "TODO\|FIXME\|HACK" includes/
```

### Asset Optimization
**Status:** ⏳ NEEDS OPTIMIZATION

- [ ] Minify non-minified CSS files
- [ ] Minify non-minified JS files
- [ ] Optimize images (screenshots for readme)
- [ ] Check file sizes (avoid bloating plugin)
- [ ] Remove source maps in production

### Licensing
**Status:** ⏳ NEEDS VERIFICATION

- [ ] LICENSE file exists (GPL v2 or later)
- [ ] All bundled libraries GPL-compatible (Chart.js MIT ✓, Select2 MIT ✓)
- [ ] License headers in all PHP files
- [ ] Third-party attributions documented

### Internationalization (i18n)
**Status:** ⏳ NEEDS CHECK

- [ ] Text domain consistent: `contact-inbox-pro`
- [ ] All strings use `__()`, `_e()`, `esc_html__()`, etc.
- [ ] No hardcoded English text in templates
- [ ] `.pot` file generated for translators

**Generate POT File:**
```bash
wp i18n make-pot . languages/contact-inbox-pro.pot
```

---

## 📋 WORDPRESS.ORG SUBMISSION REQUIREMENTS

### Required Files
- [x] readme.txt (WordPress.org format)
- [x] Main plugin file with proper header
- [ ] LICENSE file (GPL v2+)
- [x] uninstall.php (exists, needs verification)
- [ ] .wordpress-org/ directory with assets (optional screenshots)

### Forbidden Content
- [x] No external service calls without disclosure ✓ (disclosed in readme)
- [ ] No CDN dependencies (MUST FIX - Chart.js, Select2)
- [x] No obfuscated code ✓
- [x] No malicious code ✓
- [x] No cryptocurrency miners ✓
- [x] No tracking without consent ✓

### Best Practices
- [x] Unique plugin name ✓
- [x] Unique function/class prefixes ✓ (ContactIN, contactin_)
- [x] No conflicts with WordPress core ✓
- [ ] PSR-4 autoloading (implemented ✓)
- [ ] Namespaces used properly (partially - some global functions remain)
- [x] Follows WordPress Coding Standards (mostly)

---

## 🚀 PRE-SUBMISSION CHECKLIST

Before submitting to WordPress.org:

1. **[⚠️ CRITICAL]** Bundle Chart.js and Select2 locally
2. **[⚠️ CRITICAL]** Update all CDN references to local files
3. **[ ]** Run full security audit (PHPCS WordPress rules)
4. **[ ]** Verify uninstall.php completeness
5. **[ ]** Test plugin installation from zip
6. **[ ]** Test plugin uninstallation (clean removal)
7. **[ ]** Generate .pot file for translations
8. **[ ]** Create LICENSE file (GPL v2+)
9. **[ ]** Prepare 8 screenshots for .wordpress-org/
10. **[ ]** Create plugin icon (256x256 and 128x128)
11. **[ ]** Create plugin banner (1544x500 and 772x250)
12. **[ ]** Test on fresh WordPress install (6.4+)
13. **[ ]** Test with default theme (Twenty Twenty-Four)
14. **[ ]** Test with PHP 7.4 and 8.0+
15. **[ ]** Remove development files (node_modules, tests/, etc.)
16. **[ ]** Final readme.txt validation: https://wordpress.org/plugins/developers/readme-validator/

---

## 📊 ESTIMATED TIMELINE

| Task | Priority | Estimated Time | Status |
|------|----------|---------------|--------|
| Bundle Chart.js/Select2 | CRITICAL | 2 hours | ⏳ Pending |
| Update asset loading | CRITICAL | 2 hours | ⏳ Pending |
| Security audit (PHPCS) | HIGH | 3 hours | ⏳ Pending |
| Verify uninstall.php | HIGH | 1 hour | ⏳ Pending |
| Code cleanup | MEDIUM | 2 hours | ⏳ Pending |
| i18n/POT generation | MEDIUM | 1 hour | ⏳ Pending |
| Asset optimization | LOW | 2 hours | ⏳ Pending |
| Screenshots/graphics | LOW | 3 hours | ⏳ Pending |
| **TOTAL** | | **16 hours** | |

---

## 📚 RESOURCES

- [WordPress Plugin Developer Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [Plugin Submission Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [Readme Validator](https://wordpress.org/plugins/developers/readme-validator/)
- [Plugin Check Tool](https://wordpress.org/plugins/plugin-check/)

---

## 🔗 USEFUL COMMANDS

```bash
# Validate readme.txt
curl --data-urlencode "readme_contents@readme.txt" https://wordpress.org/plugins/about/validator/

# Run security checks
./vendor/bin/phpcs --standard=WordPress includes/ templates/

# Find todos
grep -rn "TODO\|FIXME" includes/

# Check for debug code
grep -rn "var_dump\|print_r\|error_log" includes/

# Count lines of code
find includes/ -name "*.php" | xargs wc -l

# Generate translation file
wp i18n make-pot . languages/contact-inbox-pro.pot

# Create plugin zip
cd .. && zip -r contact-inbox-pro.zip contact-inbox-pro/ -x "*.git*" "*/node_modules/*" "*/vendor/*" "*/tests/*"
```

---

**Last Updated:** 2026-02-08  
**Next Review:** After CDN bundling complete
