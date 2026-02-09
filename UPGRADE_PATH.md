# Contact Inbox: Free to Premium Upgrade Path

## Overview
This document explains how users can smoothly upgrade from Contact Inbox (free) to Contact Inbox Pro without data loss or conflicts.

## How It Works

### 1. Database Tables (No Recreation Issue)
**The free version creates ALL database tables with the same schema as premium.**

WordPress's `dbDelta()` function handles table creation safely:
- ✅ Checks if tables exist before creating
- ✅ Only creates missing tables
- ✅ Adds missing columns to existing tables  
- ✅ **Never drops or recreates existing tables**
- ✅ Preserves all data

**Upgrade Flow:**
1. User installs free version → Tables created
2. User purchases and installs premium → `dbDelta()` runs
3. `dbDelta()` detects tables exist → Skips creation
4. All messages, contacts, and settings preserved

### 2. Plugin Conflict Prevention
**Both plugins cannot be active simultaneously.**

The `PluginConflictDetector` class prevents conflicts by:
- Detecting when both free and premium versions are active
- Auto-deactivating the free version when premium activates
- Showing a friendly success message to the user
- Running on every admin page load for safety

**Conflict Detection Logic:**
```
If Premium is active:
  └─> Detect if Free is active
      └─> Deactivate Free automatically
      └─> Show success notice: "Contact Inbox Pro activated! Free version deactivated. All data preserved."

If Free is active:
  └─> Detect if Premium is active
      └─> Deactivate self (Free)
      └─> Redirect to plugins page
      └─> Show notice on next page load
```

### 3. Upgrade Scenarios

#### Scenario A: User installs Premium while Free is active
1. User clicks "Activate" on Contact Inbox Pro
2. Premium plugin loads → `PluginConflictDetector::init()` runs
3. Detector sees free version is active
4. Detector deactivates free version automatically
5. User sees success notice: "Contact Inbox Pro activated! The free version has been automatically deactivated to prevent conflicts. All your data, settings, and messages have been preserved."
6. Premium takes over seamlessly

#### Scenario B: User installs Premium after deactivating Free
1. User manually deactivates free version
2. User activates premium version
3. Premium activation runs → Tables already exist
4. `dbDelta()` skips table creation
5. All data remains intact

#### Scenario C: Fresh Premium installation
1. User installs premium without free
2. Premium creates all tables
3. Normal activation flow

### 4. Data Preservation

**What gets preserved during upgrade:**
- ✅ All contact form submissions (wp_contactin_messages)
- ✅ Contact records (wp_contactin_contacts)
- ✅ Email sending history (wp_contactin_email_log)
- ✅ Queue items (wp_contactin_queue)
- ✅ Plugin settings (wp_options: contactinbox_settings)
- ✅ Analytics data (wp_contactin_analytics_daily, weekly, monthly)
- ✅ Form configurations

**What changes during upgrade:**
- ❌ Free version deactivated
- ✅ Premium features become available
  - CRM integration (Salesforce/HubSpot)
  - File attachments
  - REST API
  - GDPR deletion requests
  - CSV export
  - Advanced email scheduling

### 5. Cron Jobs

**Free version registers only basic cron jobs:**
- `contactinbox_process_email` - Email sending queue
- `contactin_cleanup` - Cleanup old analytics data

**Premium adds additional cron jobs:**
- `contactin_process_crm` - CRM sync queue
- `contactin_gdpr` - GDPR request processing
- `contactin_gdpr_cleanup` - GDPR token expiration

When premium activates:
1. Free version's cron jobs remain scheduled (safe, both use same hooks)
2. Premium's `CronJobs::register()` adds premium-only jobs
3. No duplicate processing occurs (same hook names are intentional)

### 6. Settings Migration

**All settings are preserved because both versions use the same option keys:**
- `contactinbox_settings` - Core plugin settings
- `contactinbox_db_version` - Database schema version
- `contactin_intent_patterns` - Intent classification data

Premium version simply adds new settings fields (CRM credentials, file upload limits, etc.) while preserving all existing free version settings.

### 7. User Experience Flow

**From the user's perspective:**

1. **Using Free Version:**
   - User sees "Upgrade to Pro" badges on premium features
   - User clicks "Upgrade" → Redirected to pricing page
   - User purchases premium license

2. **Installing Premium:**
   - User receives download link for premium plugin
   - User uploads `contact-inbox-pro.zip` via Plugins → Add New → Upload
   - WordPress shows both plugins in plugins list

3. **Activating Premium:**
   - User clicks "Activate" on Contact Inbox Pro
   - Page refreshes automatically
   - Success message appears: "Contact Inbox Pro activated! The free version has been automatically deactivated to prevent conflicts. All your data, settings, and messages have been preserved."
   - Free version shows "Inactive" status
   - Premium version shows "Active" status

4. **Access Premium Features:**
   - User navigates to Contact Inbox menu
   - All premium features now available
   - Settings show new CRM integration options
   - File upload fields appear in forms
   - Export buttons work without restrictions

### 8. Technical Implementation

**Files involved:**

**Free Version:**
- `/includes/PluginConflictDetector.php` - Conflict detection class
- `/includes/Plugin.php` - Initializes conflict detector early
- `/includes/Core/Config.php` - Defines `CONTACTINBOX_IS_FREE = true`
- `/includes/Core/DB.php` - Creates all tables (same as premium)

**Premium Version:**
- `/includes/PluginConflictDetector.php` - Conflict detection class
- `/includes/Plugin.php` - Initializes conflict detector early
- `/includes/Core/Config.php` - No `CONTACTINBOX_IS_FREE` constant
- `/includes/Core/DB.php` - Creates all tables (same as free)

**Key detection code:**
```php
// Check if this is free version
$is_free = defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE;

// Premium version detects free version and deactivates it
if (!$is_free && is_plugin_active('contact-inbox/contact-inbox.php')) {
    deactivate_plugins('contact-inbox/contact-inbox.php');
}
```

### 9. Safety Features

**Multiple safety layers:**
1. **Early detection** - Conflict detector runs on `admin_init` (every admin page)
2. **Graceful deactivation** - Uses WordPress core `deactivate_plugins()` function
3. **Transient notices** - User always informed about what happened
4. **No data loss** - Tables and settings never deleted during switch
5. **Idempotent operations** - Safe to activate/deactivate multiple times

### 10. Testing the Upgrade Path

**To test manually:**

```bash
# 1. Activate free version
cd /var/www/html/wpdev
wp plugin activate contact-inbox --allow-root

# 2. Verify tables created
wp db query "SHOW TABLES LIKE 'wp_contactin%'" --allow-root

# 3. Create test submission
# (Use contact form in browser)

# 4. Activate premium (should auto-deactivate free)
wp plugin activate contact-inbox-pro --allow-root

# 5. Verify free is now inactive
wp plugin list --status=inactive --allow-root | grep contact-inbox

# 6. Verify data still exists
wp db query "SELECT COUNT(*) FROM wp_contactin_messages" --allow-root
```

## Summary

The upgrade path is **fully automated and safe**:
- ✅ No manual steps required from user
- ✅ No data loss ever occurs
- ✅ No table conflicts or recreation
- ✅ No duplicate cron jobs or admin menus
- ✅ Clear success message confirms the upgrade
- ✅ User can downgrade to free version if needed (just deactivate premium, reactivate free)

The system is designed to "just work" with minimal user confusion and maximum data safety.
