!(function (e) {
  "use strict";
  var t = window.CinProfileCore;
  if (t) {
    var i = t.i18n,
      n = null;
    window.elementor && elementor.hooks
      ? h()
      : (e(window).on("elementor/init", h),
        window.addEventListener("elementor:init", h));
  }
  function o(t) {
    return e("<span>")
      .text(String(t || ""))
      .html();
  }
  function l(e, i) {
    var n =
      '<select data-key="' +
      e +
      '" style="width:100%;height:30px;margin-top:3px;">';
    return (
      t.AUTO_ON_OFF.forEach(function (e) {
        n +=
          '<option value="' +
          e.value +
          '"' +
          (e.value === i ? " selected" : "") +
          ">" +
          o(e.label) +
          "</option>";
      }),
      n + "</select>"
    );
  }
  function a(e, t) {
    return (
      '<div style="margin-bottom:10px;"><label style="display:block;font-size:12px;font-weight:600;margin-bottom:3px;">' +
      e +
      "</label>" +
      t +
      "</div>"
    );
  }
  function r(e, t, i, n) {
    return (
      '<label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;margin-bottom:6px;' +
      (n ? "padding-left:18px;" : "") +
      '"><input type="checkbox" class="' +
      e +
      '"' +
      ((i ? " checked" : "") + ' style="margin:0;"> ') +
      o(t) +
      "</label>"
    );
  }
  function c(i) {
    var n = e('.elementor-panel select[data-setting="form_id"]');
    if (n.length) {
      var o = i || n.val();
      (n.empty(),
        t.profiles.forEach(function (t) {
          n.append(e("<option>").val(t.slug).text(t.label));
        }),
        n.val(o),
        i && n.trigger("change"));
    }
  }
  function s() {
    try {
      elementor.channels.editor.trigger("change");
    } catch (e) {}
    try {
      var e = g();
      e && "function" == typeof e.renderRemoteServer && e.renderRemoteServer();
    } catch (e) {}
  }
  function d() {
    e("#cin-profile-modal").hide();
  }
  function p(n, p) {
    e("#cin-profile-modal").length ||
      (e("body").append(
        '<div id="cin-profile-modal" style="display:none;position:fixed;z-index:99999;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.55);"><div id="cin-profile-modal-inner" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:6px;width:520px;max-width:92vw;max-height:82vh;overflow-y:auto;box-shadow:0 12px 48px rgba(0,0,0,.35);display:flex;flex-direction:column;"><div id="cin-profile-modal-head" style="padding:18px 20px 14px;border-bottom:1px solid #e0e0e0;font-size:15px;font-weight:700;flex-shrink:0;"></div><div id="cin-profile-modal-body" style="padding:16px 20px;overflow-y:auto;flex:1;"></div><div id="cin-profile-modal-foot" style="padding:12px 20px;border-top:1px solid #e0e0e0;display:flex;gap:8px;justify-content:flex-end;flex-shrink:0;"></div></div></div>',
      ),
      e("#cin-profile-modal").on("click", function (t) {
        e(t.target).is("#cin-profile-modal") && d();
      }),
      e(document).on("keydown.cin-modal", function (e) {
        "Escape" === e.key && d();
      }));
    var f = n.slug,
      g = p ? i.save || "Save changes" : i.createApply || "Create & Apply",
      h = p
        ? (i.editPrefix || "Edit: ") + o(n.label)
        : i.newProfile || "New Profile";
    (e("#cin-profile-modal-head").text(h),
      e("#cin-profile-modal-body").html(
        (function (e, n) {
          var c =
              "font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#ababab;margin:14px 0 6px;",
            s = n
              ? '<p style="font-size:11px;color:#757575;margin:-6px 0 12px;">' +
                o(i.slugLabel || "Slug (ID)") +
                ": <code>" +
                o(e.slug) +
                "</code></p>"
              : a(
                  o(i.slugLabel || "Slug (ID)"),
                  '<input type="text" class="cin-dlg-slug" value="' +
                    o(e.slug || t.slugify(e.label)) +
                    '" style="width:100%;height:30px;box-sizing:border-box;">',
                );
          return (
            '<div style="padding:4px 2px;">' +
            a(
              o(i.profileName || "Profile name"),
              '<input type="text" class="cin-dlg-label" value="' +
                o(e.label) +
                '" placeholder="' +
                o(i.namePlaceholder || "e.g. Sales Enquiry") +
                '" style="width:100%;height:30px;box-sizing:border-box;">',
            ) +
            s +
            '<p style="' +
            c +
            '">' +
            o(i.secFields || "Fields") +
            "</p>" +
            r(
              "cin-dlg-show-phone",
              i.phoneField || "Phone field",
              e.show_phone,
            ) +
            r(
              "cin-dlg-require-phone",
              i.requirePhone || "↳ Require phone",
              e.require_phone,
              !0,
            ) +
            r(
              "cin-dlg-show-salutation",
              i.salutation || "Salutation dropdown",
              e.show_salutation,
            ) +
            r(
              "cin-dlg-show-subject",
              i.subjectField || "Subject field",
              e.show_subject,
            ) +
            (t.globalAttachmentEnabled
              ? r(
                  "cin-dlg-show-attachment",
                  i.fileAttachment || "File attachment",
                  e.show_attachment,
                )
              : '<div class="cin-attach-global-off" style="margin-bottom:6px;"><label style="display:flex;align-items:center;gap:6px;font-size:12px;color:#aaa;cursor:not-allowed;"><input type="checkbox" class="cin-dlg-show-attachment" disabled style="margin:0;"> ' +
                o(i.fileAttachment || "File attachment") +
                '</label><div style="margin-top:6px;padding:8px 10px;background:#fff3cd;border-left:3px solid #f0a500;border-radius:2px;font-size:11px;line-height:1.6;">' +
                o(
                  i.attachGlobalOff ||
                    "File attachment is available in ContactIn Pro.",
                ) +
                '<div style="margin-top:6px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">' +
                '<button type="button" class="cin-attach-check-btn" style="background:none;border:1px solid #996800;border-radius:3px;color:#996800;font-size:11px;padding:2px 8px;cursor:pointer;font-weight:600;">' +
                o(i.checkAgain || "Check again") +
                "</button></div></div></div>") +
            r(
              "cin-dlg-show-consent",
              i.privacyConsent || "Privacy consent checkbox",
              e.show_consent,
            ) +
            a(
              o(i.consentText || "Consent text"),
              '<textarea class="cin-dlg-consent-text" rows="2" style="width:100%;box-sizing:border-box;resize:vertical;" placeholder="' +
                o(
                  i.consentPlaceholder || "Leave empty to use global setting.",
                ) +
                '">' +
                o(e.consent_text) +
                "</textarea>",
            ) +
            '<p style="' +
            c +
            '">' +
            o(i.secBehaviour || "Behaviour") +
            "</p>" +
            a(o(i.recaptcha || "reCAPTCHA"), l("recaptcha", e.recaptcha)) +
            a(
              o(i.confetti || "Confetti on success"),
              l("confetti", e.confetti),
            ) +
            a(
              o(i.successMsg || "Success message"),
              '<textarea class="cin-dlg-success-message" rows="2" style="width:100%;box-sizing:border-box;resize:vertical;" placeholder="' +
                o(i.globalFallback || "Leave empty to use global setting.") +
                '">' +
                o(e.success_message) +
                "</textarea>",
            ) +
            '<p style="' +
            c +
            '">' +
            o(i.secRouting || "Routing") +
            "</p>" +
            a(
              o(i.notifyEmail || "Notification email"),
              '<input type="email" class="cin-dlg-notify-email" value="' +
                o(e.notify_email) +
                '" style="width:100%;height:30px;box-sizing:border-box;" placeholder="' +
                o(
                  i.emailPlaceholder ||
                    "Leave empty to use global admin email.",
                ) +
                '">',
            ) +
            '<p class="cin-dlg-error" style="color:#cc1818;font-size:12px;margin:6px 0 0;display:none;"></p></div>'
          );
        })(n, p),
      ),
      e("#cin-profile-modal-body")
        .off("click.cin-attach")
        .on("click.cin-attach", ".cin-attach-check-btn", function () {
          var n = e(this),
            o = e("#cin-profile-modal-body");
          (n.prop("disabled", !0).text(i.checking || "Checking…"),
            t.checkGlobalAttachment(function (e) {
              ((t.globalAttachmentEnabled = e),
                e
                  ? o
                      .find(".cin-attach-global-off")
                      .replaceWith(
                        r(
                          "cin-dlg-show-attachment",
                          i.fileAttachment || "File attachment",
                          !1,
                        ),
                      )
                  : n.prop("disabled", !1).text(i.checkAgain || "Check again"));
            }));
        }));
    var u = e("#cin-profile-modal-foot").empty(),
      m = e(
        '<button type="button" class="elementor-button elementor-button-success" style="min-width:110px;">' +
          o(g) +
          "</button>",
      ),
      b = e(
        '<button type="button" class="elementor-button elementor-button-default">' +
          o(i.cancel || "Cancel") +
          "</button>",
      );
    (m.on("click", function () {
      var n = e("#cin-profile-modal-body"),
        l = n.find(".cin-dlg-error"),
        a = (function (e, i, n) {
          return {
            label: e.find(".cin-dlg-label").val(),
            slug: n
              ? i
              : e.find(".cin-dlg-slug").val() ||
                t.slugify(e.find(".cin-dlg-label").val()),
            slugEdited: !0,
            show_phone: e.find(".cin-dlg-show-phone").is(":checked"),
            require_phone: e.find(".cin-dlg-require-phone").is(":checked"),
            show_salutation: e.find(".cin-dlg-show-salutation").is(":checked"),
            show_subject: e.find(".cin-dlg-show-subject").is(":checked"),
            show_attachment: e.find(".cin-dlg-show-attachment").is(":checked"),
            show_consent: e.find(".cin-dlg-show-consent").is(":checked"),
            consent_text: e.find(".cin-dlg-consent-text").val(),
            recaptcha: e.find('select[data-key="recaptcha"]').val() || "auto",
            confetti: e.find('select[data-key="confetti"]').val() || "auto",
            success_message: e.find(".cin-dlg-success-message").val(),
            notify_email: e.find(".cin-dlg-notify-email").val(),
          };
        })(n, f, p);
      if (a.label.trim() && (p || a.slug)) {
        if ((l.hide(), a.show_attachment))
          return (
            m.prop("disabled", !0).text(i.checking || "Checking…"),
            void t.checkGlobalAttachment(function (e) {
              if (((t.globalAttachmentEnabled = e), !e)) {
                var c = n.find(".cin-dlg-show-attachment").closest("label");
                return (
                  c.length &&
                    c.replaceWith(
                      '<div class="cin-attach-global-off" style="margin-bottom:6px;"><label style="display:flex;align-items:center;gap:6px;font-size:12px;color:#aaa;cursor:not-allowed;"><input type="checkbox" class="cin-dlg-show-attachment" disabled style="margin:0;"> ' +
                        o(i.fileAttachment || "File attachment") +
                        '</label><div style="margin-top:6px;padding:8px 10px;background:#fff3cd;border-left:3px solid #f0a500;border-radius:2px;font-size:11px;line-height:1.6;">' +
                        o(
                          i.attachGlobalOff ||
                            "File attachment is available in ContactIn Pro.",
                        ) +
                        '<div style="margin-top:6px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">' +
                        '<button type="button" class="cin-attach-check-btn" style="background:none;border:1px solid #996800;border-radius:3px;color:#996800;font-size:11px;padding:2px 8px;cursor:pointer;font-weight:600;">' +
                        o(i.checkAgain || "Check again") +
                        "</button></div></div></div>",
                    ),
                  m.prop("disabled", !1).text(g),
                  void l
                    .css("color", "#cc1818")
                    .text(
                      i.attachGlobalOff ||
                        "File attachment is available in ContactIn Pro.",
                    )
                    .show()
                );
              }
              (m.prop("disabled", !0).text(i.saving || "Saving…"),
                t.saveProfile(a, r, h));
            })
          );
        (m.prop("disabled", !0).text(i.saving || "Saving…"),
          t.saveProfile(a, r, h));
      } else l.text(i.nameRequired || "Name is required.").show();
      function r(e) {
        (c(p ? null : a.slug), s(), d());
      }
      function h(e) {
        m.prop("disabled", !1).text(g);
        var t =
          (e && e.data && e.data.message) ||
          i.couldNotSave ||
          "Could not save profile.";
        l.text(t).show();
      }
    }),
      b.on("click", d),
      u.append(b).append(m),
      e("#cin-profile-modal").show(),
      e("#cin-profile-modal-body .cin-dlg-label").trigger("focus"));
  }
  function f() {
    f._bound ||
      ((f._bound = !0),
      e(document).on(
        "click.cin-profile",
        ".cin-create-new-profile",
        function () {
          p(e.extend({}, t.emptyForm), !1);
        },
      ),
      e(document).on(
        "click.cin-profile",
        ".cin-edit-current-profile",
        function () {
          var i = (function () {
              try {
                var t = n && n.get("settings").get("form_id");
                if (t) return t;
              } catch (e) {}
              return e('select[data-setting="form_id"]').val() || "default";
            })(),
            o = t.profilesData[i];
          p(
            o
              ? t.profileToForm(i, o)
              : e.extend({}, t.emptyForm, { slug: i, slugEdited: !0 }),
            !0,
          );
        },
      ),
      e(document).on(
        "change.cin-profile",
        'select[data-setting="form_id"]',
        function () {
          try {
            var e = g();
            (e &&
              "function" == typeof e.renderRemoteServer &&
              e.renderRemoteServer(),
              elementor.channels.editor.trigger("change"));
          } catch (e) {}
        },
      ));
  }
  function g() {
    try {
      var e = (function () {
        try {
          var e = elementor.getPanelView().getCurrentPageView();
          return e && e.getOption("editedElementView");
        } catch (e) {
          return null;
        }
      })();
      return e ? e.getEditModel() : null;
    } catch (e) {
      return null;
    }
  }
  function h() {
    h._done ||
      ((h._done = !0),
      f(),
      elementor.hooks.addAction(
        "panel/open_editor/widget/contactin_contact_form",
        function (e, t, i) {
          !(function (e, t, i) {
            ((n = e), i || null, c(null));
          })(t, 0, i);
        },
      ));
  }
})(jQuery);
