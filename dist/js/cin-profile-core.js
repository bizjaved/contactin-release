/* ContactIn Pro – Profile Manager Core
 *
 * Single source of truth for profile data, state, and AJAX service.
 * Consumed by gutenberg-block.min.js and elementor-editor.min.js.
 * Contains zero rendering code and zero framework dependencies beyond jQuery.
 *
 * Public API: window.CinProfileCore
 */
(function ($) {
    'use strict';

    // ── PHP-localised config ──────────────────────────────────────────────────
    var cfg = window.cinProfileCore || {};

    var nonce       = cfg.nonce       || '';
    var ajaxurl     = cfg.ajaxurl     || (window.ajaxurl || '');
    var settingsUrl = cfg.settingsUrl || '#';
    var i18n        = cfg.i18n        || {};
    var isPremium   = !!(cfg.isPremium);
    var upgradeUrl  = cfg.upgradeUrl  || '';
    var globalAttachmentEnabled = !!(cfg.globalAttachmentEnabled);

    // ── Shared SELECT options (same across every renderer) ────────────────────
    var AUTO_ON_OFF = [
        { value: 'auto', label: i18n.optAuto || 'Use Global Setting' },
        { value: 'on',   label: i18n.optOn   || 'Always On'          },
        { value: 'off',  label: i18n.optOff  || 'Always Off'         },
    ];

    // ── Live shared state ─────────────────────────────────────────────────────
    // Both arrays are mutated in-place after every successful save.
    // Renderers should read them at call-time, not cache them.
    var profiles     = (cfg.formProfiles && cfg.formProfiles.length)
                           ? cfg.formProfiles.slice()
                           : [{ slug: 'default', label: 'Default' }];
    var profilesData = $.extend({}, cfg.profilesData || {});

    // ── Blank form constant ───────────────────────────────────────────────────
    var emptyForm = Object.freeze({
        label:           '',
        slug:            '',
        slugEdited:      false,
        show_phone:      true,
        require_phone:   false,
        show_salutation: false,
        show_subject:    false,
        show_attachment: false,
        show_consent:    true,
        consent_text:    '',
        recaptcha:       'auto',
        confetti:        'auto',
        success_message: '',
        notify_email:    '',
    });

    // ── Pure helpers ──────────────────────────────────────────────────────────
    function slugify(str) {
        return String(str || '').toLowerCase()
            .replace(/\s+/g, '-')
            .replace(/[^a-z0-9-]/g, '');
    }

    function profileToForm(slug, profile) {
        return {
            label:           profile.label           || '',
            slug:            slug,
            slugEdited:      true,
            show_phone:      profile.show_phone      !== undefined ? !!profile.show_phone      : true,
            require_phone:   profile.require_phone   !== undefined ? !!profile.require_phone   : false,
            show_salutation: profile.show_salutation !== undefined ? !!profile.show_salutation : false,
            show_subject:    profile.show_subject    !== undefined ? !!profile.show_subject    : false,
            show_attachment: profile.show_attachment !== undefined ? !!profile.show_attachment : false,
            show_consent:    profile.show_consent    !== undefined ? !!profile.show_consent    : true,
            consent_text:    profile.consent_text    || '',
            recaptcha:       profile.recaptcha       || 'auto',
            confetti:        profile.confetti        || 'auto',
            success_message: profile.success_message || '',
            notify_email:    profile.notify_email    || '',
        };
    }

    // ── AJAX payload builder ──────────────────────────────────────────────────
    function buildPayload(formData) {
        return {
            action:          'cin_save_form_profile',
            nonce:           nonce,
            slug:            formData.slug,
            label:           formData.label,
            show_phone:      formData.show_phone      ? 1 : 0,
            require_phone:   formData.require_phone   ? 1 : 0,
            show_salutation: formData.show_salutation ? 1 : 0,
            show_subject:    formData.show_subject    ? 1 : 0,
            show_attachment: formData.show_attachment ? 1 : 0,
            show_consent:    formData.show_consent    ? 1 : 0,
            consent_text:    formData.consent_text,
            recaptcha:       formData.recaptcha,
            confetti:        formData.confetti,
            success_message: formData.success_message,
            notify_email:    formData.notify_email,
        };
    }

    // ── Update-listener bus ───────────────────────────────────────────────────
    // Renderers subscribe via onUpdate(fn) and are called after every save.
    // Callback signature: fn({ profiles, profilesData })
    var _listeners = [];

    function onUpdate(fn) {
        if (typeof fn === 'function') {
            _listeners.push(fn);
        }
    }

    function _notifyAll(result) {
        _listeners.forEach(function (fn) {
            try { fn(result); } catch (e) { /* isolate per-subscriber errors */ }
        });
    }

    // ── AJAX service ──────────────────────────────────────────────────────────
    /**
     * checkGlobalAttachment( onResult )
     *
     * Re-fetches the current global file-attachment state from the server.
     * Also refreshes the shared profiles/profilesData in-place.
     * Calls onResult(bool) — true if attachment is globally enabled.
     */
    function checkGlobalAttachment(onResult) {
        $.post(ajaxurl, { action: 'cin_get_form_profiles', nonce: nonce })
            .done(function (res) {
                if (res && res.success) {
                    // Refresh shared profile state while we have a fresh response.
                    profiles.length = 0;
                    (res.data.options_list || []).forEach(function (p) { profiles.push(p); });
                    Object.keys(profilesData).forEach(function (k) { delete profilesData[k]; });
                    var map = res.data.profiles || {};
                    Object.keys(map).forEach(function (k) { profilesData[k] = map[k]; });

                    var enabled = !!(res.data.global_attachment_enabled);
                    globalAttachmentEnabled = enabled;
                    if (typeof onResult === 'function') onResult(enabled);
                }
            });
    }

    /**
     * saveProfile( formData, onSuccess, onFail )
     *
     * Posts to `cin_save_form_profile`, then on HTTP 200 + res.success:
     *   1. Mutates `profiles` and `profilesData` in-place with fresh server data.
     *   2. Calls all onUpdate listeners.
     *   3. Calls onSuccess({ profiles, profilesData }).
     * On failure calls onFail(response|null).
     */
    function saveProfile(formData, onSuccess, onFail) {
        $.post(ajaxurl, buildPayload(formData))
            .done(function (res) {
                if (res && res.success) {
                    // Mutate profiles array in-place
                    profiles.length = 0;
                    (res.data.options_list || []).forEach(function (p) {
                        profiles.push(p);
                    });

                    // Replace profilesData keys in-place
                    Object.keys(profilesData).forEach(function (k) {
                        delete profilesData[k];
                    });
                    var map = res.data.profiles || {};
                    Object.keys(map).forEach(function (k) {
                        profilesData[k] = map[k];
                    });

                    var result = {
                        profiles:          profiles,
                        profilesData:      profilesData,
                        pro_fields_ignored: res.data.pro_fields_ignored || [],
                        pro_upgrade_url:    res.data.pro_upgrade_url    || '',
                    };
                    _notifyAll(result);
                    if (typeof onSuccess === 'function') onSuccess(result);
                } else {
                    if (typeof onFail === 'function') onFail(res);
                }
            })
            .fail(function () {
                if (typeof onFail === 'function') onFail(null);
            });
    }

    // ── Public API ────────────────────────────────────────────────────────────
    window.CinProfileCore = {
        // Config (frozen at page load)
        nonce:        nonce,
        ajaxurl:      ajaxurl,
        settingsUrl:  settingsUrl,
        i18n:         i18n,
        AUTO_ON_OFF:  AUTO_ON_OFF,
        isPremium:    isPremium,
        upgradeUrl:   upgradeUrl,
        globalAttachmentEnabled: globalAttachmentEnabled,

        // Live state — same array/object instances mutated by saveProfile.
        // Always read at call-time; never cache the reference.
        profiles:     profiles,
        profilesData: profilesData,

        // Data helpers
        emptyForm:     emptyForm,
        slugify:       slugify,
        profileToForm: profileToForm,

        // AJAX service
        saveProfile:            saveProfile,
        checkGlobalAttachment:  checkGlobalAttachment,
        onUpdate:               onUpdate,
    };

})(jQuery);
