# PRO Features Cleanup Plan - Contact Inbox Free
## Goal
Remove or disable PRO features to prevent users from easily enabling them in the free version.

## PRO Features to Restrict

### 1. File Attachments ❌ REMOVE
**Components:**
- `includes/Admin/Controllers/AttachmentUploadController.php` - REST API upload endpoint
- `includes/Core/AttachmentHelper.php` - File handling utilities
- `includes/Core/AttachmentRenderer.php` - Display logic
- `includes/Core/AttachmentCleanupService.php` - Cleanup service
- `includes/Admin/Assets/AttachmentCleanupAssets.php` - Admin assets
- `includes/Core/Traits/AttachmentCleanupTrait.php`
- `includes/Core/Traits/AttachmentScannerTrait.php`
- `includes/Core/Traits/AttachmentStatsTrait.php`
- `includes/Core/Traits/SalesforceAttachmentHandler.php`
- `includes/Core/Repositories/SalesforceAttachmentRepository.php`

**Actions:**
1. **Remove entire classes** (delete files completely)
2. **Remove REST API routes** in `includes/Integrations/RestApiRoutes.php` for attachment upload
3. **Remove attachment field** from frontend form submission handling
4. **Keep database schema** (for data integrity if migrating to pro)
5. **Add early return** in form handler if attachment is present

**Frontend Impact:** ✅ **KEEP INTACT**
- Form display stays the same
- Hidden file input can remain
- Form submission will reject attachments with error message

---

### 2. CRM Sync Processing ❌ DISABLE CORE PROCESSING
**Components:**
- `includes/Core/CRMConnector.php` - Salesforce API connector
- `includes/Core/CRMAuth.php` - OAuth authentication
- `includes/Core/CRMQueueService.php` - Queue management
- `includes/Core/CRMQueuePayloadBuilder.php` - Data preparation
- `includes/Core/CRMFieldMapper.php` - Field mapping
- `includes/Core/CRMMonitor.php` - Health monitoring
- `includes/Core/CRMStatus.php` - Status tracking
- `includes/Core/Traits/CRMErrorClassifier.php`
- `includes/Core/Traits/CRMDuplicateResolver.php`
- `includes/Core/Repositories/CRMRepository.php`
- `includes/Core/Repositories/CRMErrorRepository.php`
- `includes/Cron/CronJobs.php` - CRM cron job (line 38: **already conditionally disabled** ✅)

**Already Protected:**
- ✅ CRM cron jobs only register in Pro version
- ✅ CRM settings page marked as PRO
- ✅ CRM JavaScript not loaded in free version
- ✅ Dashboard CRM tab shows placeholder data

**Actions:**
1. **Add early return guards** in critical methods:
   - `CRMConnector::sync()` - return false if free version
   - `CRMQueueService::queue_message_sync()` - return false if free version
   - `CRMAuth::authenticate()` - return false if free version
2. **Keep classes** (for type hints, interfaces, and future pro upgrade)
3. **Add CONTACTINBOX_IS_FREE checks** at entry points

**Frontend Impact:** ✅ **NO CHANGE**

---

### 3. GDPR Deletion ❌ DISABLE DELETION LOGIC
**Components:**
- `includes/Admin/GDPRHandler.php` - Admin AJAX handlers
  - `handle_link()` - Generate deletion link
  - `handle_send_email()` - Send deletion email  
  - `handle_delete()` - Process deletion
  - `handle_delete_contact()` - Delete contact records
  - `handle_delete_from_crm()` - Delete from CRM
- `includes/Core/GDPR.php` - Core GDPR logic
  - `generate_token()` - Generate deletion token
  - `validate_token()` - Validate token
  - `delete_by_token()` - Execute deletion
- `includes/Core/Repositories/GDPRRepository.php` - Database operations
- `includes/Admin/Pages/GDPRLog.php` - GDPR log page (keep as PRO display)
- `includes/Admin/GDPRHandler.php.bak` - Backup file (delete)
- `includes/Cron/CronJobs.php` - GDPR cron jobs (line 39-40: **already conditionally disabled** ✅)

**Already Protected:**
- ✅ GDPR cron jobs only register in Pro version
- ✅ GDPR link button in contact detail marked as PRO
- ✅ GDPR link button triggers upgrade modal in free version

**Actions:**
1. **Add early return guards** in GDPR methods:
   - `GDPRHandler::handle_link()` - Check `CONTACTINBOX_IS_FREE`, return error
   - `GDPRHandler::handle_send_email()` - Check `CONTACTINBOX_IS_FREE`, return error
   - `GDPRHandler::handle_delete()` - Check `CONTACTINBOX_IS_FREE`, return error
   - `GDPRHandler::handle_delete_contact()` - Check `CONTACTINBOX_IS_FREE`, return error
   - `GDPR::generate_token()` - Check `CONTACTINBOX_IS_FREE`, return false
   - `GDPR::delete_by_token()` - Check `CONTACTINBOX_IS_FREE`, return false
2. **Keep classes** (for structure and pro upgrade path)
3. **Remove backup file** GDPRHandler.php.bak

**Frontend Impact:** ✅ **NO CHANGE**
- Frontend GDPR deletion form can remain (will show upgrade notice if implemented)

---

## Implementation Strategy

### Phase 1: Critical Blocks (Immediate)
1. **File Attachments** - Add rejection in form handler
2. **CRM Sync** - Add guards in CRMConnector and CRMQueueService
3. **GDPR Deletion** - Add guards in GDPRHandler methods

### Phase 2: Removal (After testing)
1. **Delete AttachmentUploadController.php** and related files
2. **Remove REST API attachment routes**
3. **Delete GDPRHandler.php.bak**
4. **Remove unused attachment assets**

### Phase 3: Database Cleanup (Optional)
1. Keep all tables for data integrity
2. Consider removing unused indexes

---

## Code Pattern for Guards

```php
// Standard guard pattern
if (defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE) {
    Logger::debug('Feature disabled in free version', [
        'feature' => 'feature_name',
        'method' => __METHOD__
    ]);
    return false; // or wp_send_json_error() for AJAX
}
```

---

## Testing Checklist
- [ ] Form submission without attachment works
- [ ] Form submission with attachment is rejected
- [ ] CRM sync does not execute in free version
- [ ] GDPR deletion link cannot be generated
- [ ] GDPR deletion email cannot be sent
- [ ] GDPR deletion cannot be executed
- [ ] All PRO pages display upgrade notices
- [ ] No PHP errors or warnings

---

## Files to Delete (Phase 2)
```
includes/Admin/Controllers/AttachmentUploadController.php
includes/Core/AttachmentHelper.php
includes/Core/AttachmentRenderer.php
includes/Core/AttachmentCleanupService.php
includes/Admin/Assets/AttachmentCleanupAssets.php
includes/Core/Traits/AttachmentCleanupTrait.php
includes/Core/Traits/AttachmentScannerTrait.php
includes/Core/Traits/AttachmentStatsTrait.php
includes/Core/Traits/SalesforceAttachmentHandler.php
includes/Core/Repositories/SalesforceAttachmentRepository.php
includes/Admin/GDPRHandler.php.bak
```

---

## Already Protected (No Action Needed) ✅
- CRM Integration page - marked as PRO, JS disabled
- REST API Integration page - marked as PRO, JS disabled
- Dashboard tabs (Performance, Users, CRM, Background Jobs) - marked as PRO, data disabled
- Maintenance page CRM/GDPR cards - marked as PRO, disabled
- CRM cron jobs - conditionally registered
- GDPR cron jobs - conditionally registered
