!(function (e) {
  "use strict";
  var t = window.contactinbox_admin || {},
    n = t.i18n || {},
    a = n.messages || {},
    i = n.confirm || {};
  function c(t) {
    return new Promise(function (n) {
      var a = e.extend(
          {
            title: i.default_title || "Confirm",
            message: "",
            badge: "",
            badgeColor: "#dc3232",
            confirmLabel: i.default_proceed || "I understand, proceed",
            cancelLabel: i.default_cancel || "Cancel",
            confirmClass: "button-primary",
          },
          t,
        ),
        c = e("#cin-confirm-modal"),
        s = c.find("#cin-confirm-modal-badge"),
        o = c.find("#cin-confirm-modal-heading"),
        l = c.find("#cin-confirm-modal-body"),
        r = c.find("#cin-confirm-modal-confirm"),
        d = c.find("#cin-confirm-modal-cancel"),
        u = c.find("#cin-confirm-modal-x");
      function p(t) {
        (c.addClass("cin-modal-hidden"),
          r.off("click.cinconfirm"),
          d.off("click.cinconfirm"),
          u.off("click.cinconfirm"),
          e(document).off("keydown.cinconfirm"),
          n(t));
      }
      (a.badge
        ? s.text(a.badge).css("background", a.badgeColor).show()
        : s.hide(),
        o.text(a.title),
        l.html(a.message),
        r
          .text(a.confirmLabel)
          .removeClass()
          .addClass("button " + a.confirmClass),
        d.text(a.cancelLabel),
        c.removeClass("cin-modal-hidden"),
        setTimeout(function () {
          d.trigger("focus");
        }, 60),
        r.on("click.cinconfirm", function () {
          p(!0);
        }),
        d.on("click.cinconfirm", function () {
          p(!1);
        }),
        u.on("click.cinconfirm", function () {
          p(!1);
        }),
        e(document).on("keydown.cinconfirm", function (e) {
          "Escape" === e.key && p(!1);
        }));
    });
  }
  function s(e) {
    var t = "cin-tab-" + e;
    document.querySelectorAll(".nav-tab").forEach(function (e) {
      e.classList.remove("nav-tab-active");
    });
    var n = document.querySelector('[data-tab="' + e + '"]');
    (n && n.classList.add("nav-tab-active"),
      document.querySelectorAll(".cin-tab-content").forEach(function (e) {
        ((e.style.display = ""), e.classList.remove("is-active"));
      }));
    var a = document.getElementById(t);
    (a && ((a.style.display = ""), a.classList.add("is-active")),
      localStorage.setItem("contactin_settings_tab", e));
  }
  function o(e) {
    if (e && "1" !== e.dataset.cinBound) {
      ((e.dataset.cinBound = "1"),
        e.setAttribute("role", "dialog"),
        e.setAttribute("aria-modal", "true"));
      var t = e.querySelector(".cin-modal-overlay");
      (e.querySelectorAll(".cin-modal-close").forEach(function (t) {
        t.addEventListener("click", function (t) {
          (t && "function" == typeof t.preventDefault && t.preventDefault(),
            r(e));
        });
      }),
        t &&
          t.addEventListener("click", function () {
            r(e);
          }),
        e.addEventListener("keydown", function (t) {
          "Escape" === t.key && r(e);
        }));
    }
  }
  function l(e) {
    e &&
      (o(e),
      e.classList.remove("cin-modal-hidden"),
      e.setAttribute("aria-hidden", "false"));
  }
  function r(e) {
    e &&
      (e.classList.add("cin-modal-hidden"),
      e.setAttribute("aria-hidden", "true"));
  }
  function d(e, t) {
    "function" == typeof window.cinShowMessage
      ? window.cinShowMessage(e, t || "info", 5e3)
      : window.alert(e);
  }
  function u(e) {
    return String(e || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }
  function U() {
    var t = window.contactinbox_admin || {},
      n = t.upgradeTitle || "Unlock Premium Features",
      a = t.upgradeMessage || "This setting is available in ContactIn Pro.",
      i = t.upgradeCta || "Upgrade to Pro",
      c = t.upgradeDismiss || "Maybe later",
      s = t.upgradeUrl || "#";
    e("#cin-upgrade-export-modal").remove();
    var o =
      '<div id="cin-upgrade-export-modal" class="cin-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="cin-upgrade-export-title" style="position:fixed;inset:0;display:flex;align-items:center;justify-content:center;padding:24px;background:rgba(0,0,0,0.35);z-index:2147483647;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .2s ease;"><div class="cin-confirm-modal" style="margin:0;max-width:min(560px,calc(100vw - 32px));max-height:calc(100vh - 48px);overflow:auto;"><h3 id="cin-upgrade-export-title">' +
      u(n) +
      '</h3><div class="cin-confirm-details"><p>' +
      u(a) +
      '</p></div><div class="cin-confirm-actions"><button type="button" class="button cin-upgrade-export-dismiss">' +
      u(c) +
      '</button><a class="button button-primary" href="' +
      s +
      '">' +
      u(i) +
      "</a></div></div></div>";
    e("body").append(o);
    var l = e("#cin-upgrade-export-modal");
    function r() {
      (l.css({ opacity: "0", visibility: "hidden", pointerEvents: "none" }),
        setTimeout(function () {
          l.remove();
        }, 200));
    }
    (setTimeout(function () {
      l.css({ opacity: "1", visibility: "visible", pointerEvents: "auto" });
    }, 10),
      l.on("click", ".cin-upgrade-export-dismiss", function (e) {
        (e.preventDefault(), r());
      }),
      l.on("click", function (t) {
        e(t.target).is("#cin-upgrade-export-modal") && r();
      }),
      e(document).one("keyup.cinSettingsUpgrade", function (e) {
        "Escape" === e.key && r();
      }));
  }
  e(document).ready(function () {
    var i,
      u =
        e('#contactin-settings-form input[name="nonce"]').val() ||
        "" ||
        t.nonce ||
        "";
    function p() {
      var n =
        window.contactin_settings_nonce ||
        e('#contactin-settings-form input[name="nonce"]').val() ||
        t.nonce ||
        "";
      return (
        n ||
          console.error("⚠ ContactIn: no nonce available for AJAX requests."),
        n
      );
    }
    function m(e) {
      var t = JSON.parse(localStorage.getItem("contactin_dismissed_notices") || "{}");
      t[e] &&
        (delete t[e],
        localStorage.setItem("contactin_dismissed_notices", JSON.stringify(t)));
    }
    function f() {
      var t = e("#cin-settings-search").val().toLowerCase().trim(),
        n = e("#cin-search-results"),
        i = e("#cin-clear-search");
      if ((e(".cin-search-section-header").remove(), !t))
        return (
          i.addClass("cin-hidden"),
          n.addClass("cin-hidden"),
          s(localStorage.getItem("contactin_settings_tab") || "general"),
          e(".cin-tab-content tr").css("display", ""),
          void e(".nav-tab-wrapper .nav-tab").css("display", "")
        );
      (i.removeClass("cin-hidden"), e(".cin-tab-content tr").hide());
      var c = 0,
        o = new Map();
      (e("[data-search]").each(function () {
        var n = e(this).data("search").toLowerCase(),
          a = e(this).closest("tr"),
          i = a.find("th, label").first().text().toLowerCase(),
          s = a.find(".description").text().toLowerCase();
        if (n.includes(t) || i.includes(t) || s.includes(t)) {
          (a.show(), c++);
          var l = a.closest(".cin-tab-content");
          if (l.length) {
            var r = l.attr("id");
            o.has(r) || o.set(r, a);
          }
        }
      }),
        o.size > 1 &&
          o.forEach(function (t, n) {
            var a = e('.nav-tab[href="#' + n + '"]')
              .text()
              .trim();
            t.before(
              '<tr class="cin-search-section-header"><td colspan="2"><span class="cin-search-tab-label">' +
                e("<span>").text(a).html() +
                "</span></td></tr>",
            );
          }),
        e(".cin-tab-content").each(function () {
          var t = e(this).attr("id");
          (e(this).css("display", ""),
            o.has(t)
              ? e(this).addClass("is-active")
              : e(this).removeClass("is-active"));
        }),
        e(".nav-tab-wrapper .nav-tab").each(function () {
          var t = e(this).attr("href").substring(1);
          o.has(t) ? e(this).show() : e(this).hide();
        }));
      var l = a.search_no_matches || "No matches found";
      0 === c
        ? n
            .removeClass("matches-found")
            .addClass("no-matches")
            .html("⚠️ " + l)
            .removeClass("cin-hidden")
        : n
            .removeClass("no-matches")
            .addClass("matches-found")
            .html("✓ " + c + " match" + (1 !== c ? "es" : ""))
            .removeClass("cin-hidden");
    }
    function b(t, n, a) {
      ((n = n || "success"), (a = a || 5e3));
      var i = e("#cin-global-settings-notice");
      if (
        (e("#cin-notice-message").html(t),
        i
          .removeClass("notice-success notice-error notice-warning cin-hidden")
          .addClass("notice-" + n + " cin-show"),
        a > 0)
      ) {
        i.data("dismiss-timeout") && clearTimeout(i.data("dismiss-timeout"));
        var c = setTimeout(function () {
          i.removeClass("cin-show").addClass("cin-hidden");
        }, a);
        i.data("dismiss-timeout", c);
      }
      e("html, body").animate({ scrollTop: i.offset().top - 50 }, 300);
    }
    (e(document).on("click", "[data-upgrade-only='1']", function (t) {
      (t.preventDefault(), t.stopImmediatePropagation(), U());
    }),
      (window.contactin_settings_nonce = u),
      e(".notice.is-dismissible[data-notice-id]").each(function () {
        var t = e(this),
          n = t.data("notice-id");
        JSON.parse(localStorage.getItem("contactin_dismissed_notices") || "{}")[n] &&
          t.hide();
      }),
      e(document).on("click", ".cin-notice-dismiss", function (t) {
        t.preventDefault();
        var n = e(this).closest(".notice[data-notice-id]"),
          a = n.data("notice-id");
        if (a) {
          var i = JSON.parse(
            localStorage.getItem("contactin_dismissed_notices") || "{}",
          );
          ((i[a] = !0),
            localStorage.setItem("contactin_dismissed_notices", JSON.stringify(i)));
        }
        n.fadeOut(300, function () {
          e(this).remove();
        });
      }),
      document.querySelectorAll(".nav-tab").forEach(function (e) {
        e.addEventListener("click", function (e) {
          (e.preventDefault(), s(this.dataset.tab));
        });
      }),
      s(localStorage.getItem("contactin_settings_tab") || "general"),
      e("#cin-settings-search").on("input keyup", function () {
        (clearTimeout(i), (i = setTimeout(f, 150)));
      }),
      e("#cin-clear-search").on("click", function (t) {
        (t.preventDefault(),
          e("#cin-settings-search").val("").trigger("input").focus());
      }),
      e(".nav-tab").on("click", function () {
        e("#cin-settings-search").val("").trigger("input");
      }),
      e(document).on(
        "click",
        "#cin-global-settings-notice .cin-notice-dismiss",
        function (t) {
          t.preventDefault();
          var n = e("#cin-global-settings-notice");
          (n.data("dismiss-timeout") && clearTimeout(n.data("dismiss-timeout")),
            n.removeClass("cin-show").addClass("cin-hidden"));
        },
      ),
      e("#contactin-settings-form")
        .off("submit")
        .on("submit", function (t) {
          t.preventDefault();
          var n = e("#contactin-save-button"),
            i = new FormData(this);
          (n.attr("data-state", "saving").prop("disabled", !0),
            e.ajax({
              url: ajaxurl,
              type: "POST",
              data: i,
              processData: !1,
              contentType: !1,
              dataType: "json",
              success: function (e) {
                e.success
                  ? (n.attr("data-state", "saved").prop("disabled", !1),
                    b(
                      (e.data && e.data.message) ||
                        a.save_success ||
                        "Settings saved successfully!",
                      "success",
                      4e3,
                    ),
                    setTimeout(function () {
                      n.attr("data-state", "default").prop("disabled", !1);
                    }, 2e3))
                  : (n.attr("data-state", "default").prop("disabled", !1),
                    b(
                      (e.data && e.data.message) ||
                        a.save_error ||
                        "An error occurred while saving settings",
                      "error",
                      5e3,
                    ));
              },
              error: function () {
                (n.attr("data-state", "default").prop("disabled", !1),
                  b(
                    a.save_network_error ||
                      "Network error occurred while saving",
                    "error",
                    5e3,
                  ));
              },
            }));
        }),
      e("#contactin-test-smtp")
        .off("click")
        .on("click", function (n) {
          n.preventDefault();
          var i = e(this),
            c = e("#contactin-smtp-result"),
            s = i.find(".btn-text"),
            o = s.length ? s.text() : i.text();
          (i.prop("disabled", !0),
            s.length ? s.text("Testing SMTP…") : i.text("Testing SMTP…"),
            e
              .ajax({
                url: t.ajaxurl || ajaxurl,
                type: "POST",
                data: {
                  action: "contactin_test_smtp",
                  nonce: t.nonce_smtp,
                  host: e("#smtp_host").val(),
                  port: e("#smtp_port").val(),
                  username: e("#smtp_user").val(),
                  password: e("#smtp_pass").val(),
                  enc: e("#smtp_encryption").val(),
                  to: e("#admin_email").val(),
                  smtp_from_email: e("#smtp_from_email").val(),
                  smtp_from_name: e("#smtp_from_name").val(),
                },
                dataType: "json",
                timeout: 15e3,
              })
              .done(function (e) {
                var t = e && !0 === e.success,
                  n =
                    (e && e.data && e.data.message) ||
                    (t
                      ? a.smtp_success || "SMTP test email sent successfully!"
                      : a.smtp_error || "SMTP test failed."),
                  o = t ? "#155724" : "#721c24",
                  l = t ? "#d4edda" : "#f8d7da",
                  r = t ? "#c3e6cb" : "#f5c6cb";
                (s.length
                  ? s.text(t ? "Success!" : "Failed")
                  : i.text(t ? "Success!" : "Failed"),
                  c
                    .html(
                      '<span style="color:' +
                        o +
                        ";background:" +
                        l +
                        ";border-left:4px solid " +
                        r +
                        ';padding:10px 12px;margin:8px 0;border-radius:3px;display:block;font-weight:500;">' +
                        n +
                        "</span>",
                    )
                    .fadeIn(300));
              })
              .fail(function (e, t) {
                var n =
                  "timeout" === t
                    ? "SMTP test timed out. Check your settings."
                    : "Connection failed. Try again.";
                (s.length ? s.text("Failed") : i.text("Failed"),
                  c
                    .html(
                      '<span style="color:#721c24;background:#f8d7da;border-left:4px solid #f5c6cb;padding:10px 12px;margin:8px 0;border-radius:3px;display:block;font-weight:500;">' +
                        n +
                        "</span>",
                    )
                    .fadeIn(300));
              })
              .always(function () {
                setTimeout(function () {
                  (i.prop("disabled", !1), s.length ? s.text(o) : i.text(o));
                }, 4e3);
              }));
        }),
      e(".run-cron-now").on("click", function () {
        var n = e(this),
          a = n.data("event");
        (n.prop("disabled", !0).text("Running…"),
          e.ajax({
            url: ajaxurl,
            type: "POST",
            data: {
              action: "contactin_run_cron_now",
              event: a,
              nonce: t.nonce_cron || "",
            },
            success: function (e) {
              e.success
                ? (n.text("✓ Done"),
                  setTimeout(function () {
                    location.reload();
                  }, 1e3))
                : (alert("Error: " + (e.data || "Unknown error")),
                  n.prop("disabled", !1).text("Run Now"));
            },
            error: function () {
              (alert("AJAX error occurred"),
                n.prop("disabled", !1).text("Run Now"));
            },
          }));
      }),
      e(".cron-interval-select").on("change", function () {
        var n = e(this),
          a = n.data("event"),
          i = n.val(),
          c = n.data("old") || n.val(),
          s =
            "Change schedule interval for this job? The job will be rescheduled immediately.";
        ([
          "contactin_one_minute",
          "contactin_two_minutes",
          "contactin_five_minutes",
        ].includes(i) &&
          (s +=
            "\n\nWarning: Running more often than every 15 minutes can increase site load."),
          confirm(s)
            ? (n.prop("disabled", !0),
              e.ajax({
                url: ajaxurl,
                type: "POST",
                data: {
                  action: "contactin_update_cron_interval",
                  event: a,
                  interval: i,
                  nonce: t.nonce_cron || "",
                },
                success: function (e) {
                  e.success
                    ? (e.data && e.data.warning && alert(e.data.warning),
                      alert("Schedule updated successfully!"),
                      n.data("old", i),
                      location.reload())
                    : (alert("Error: " + (e.data || "Unknown error")),
                      n.val(c).prop("disabled", !1));
                },
                error: function () {
                  (alert("AJAX error occurred"), n.val(c).prop("disabled", !1));
                },
              }))
            : n.val(c));
      }));
    var h = document.getElementById("cin-min-name-words-input"),
      g = document.getElementById("cin-min-words-error");
    if (h && g) {
      function O() {
        var e = parseInt(h.value, 10),
          t = Number.isNaN(e) || e < 2;
        return (
          (g.style.display = t ? "block" : "none"),
          (h.style.borderColor = t ? "#d63638" : ""),
          (h.style.backgroundColor = t ? "#fff5f5" : ""),
          !t
        );
      }
      (h.addEventListener("change", O), h.addEventListener("input", O), O());
    }
    1 === e("#smtp-enable-btn").data("enabled") ||
      "1" === e("#smtp-enable-btn").data("enabled") ||
      e(".smtp-dependent-field").css("opacity", "0.5");

    function T(t, o) {
      t.prop("disabled", !0);
      e.ajax({
        url: ajaxurl,
        type: "POST",
        dataType: "json",
        data: { action: "contactin_toggle_smtp", enabled: o ? 0 : 1, nonce: p() },
        success: function (t) {
          if (t && t.success) {
            var r =
              t && t.data && (1 === t.data.enabled || "1" === t.data.enabled);
            if (
              ((function (t) {
                if (
                  (e("#contactin-smtp-status-label")
                    .text(t ? "Enabled" : "Disabled")
                    .removeClass("enabled disabled")
                    .addClass(t ? "enabled" : "disabled"),
                  e("#smtp-enable-btn")
                    .text(t ? "Disable SMTP" : "Enable SMTP")
                    .toggleClass("enabled", t)
                    .data("enabled", t ? "1" : "0")
                    .attr("data-enabled", t ? "1" : "0"),
                  e("#smtp-enable-hidden").val(t ? "1" : "0"),
                  m("smtp-disabled-notifications"),
                  m("smtp-domain-mismatch"),
                  e(
                    ".smtp-dependent-field input, .smtp-dependent-field select",
                  ).prop("disabled", !t),
                  e("#contactin-test-smtp").prop("disabled", !t),
                  e(".smtp-dependent-field").css("opacity", t ? "1" : "0.5"),
                  t)
                )
                  e("#cin-smtp-disabled-warning").hide();
                else {
                  var n = e("#cin-smtp-disabled-warning");
                  n.length &&
                    (JSON.parse(
                      localStorage.getItem("contactin_dismissed_notices") || "{}",
                    )["smtp-disabled-notifications"] ||
                      n.show());
                }
              })(r),
              r)
            ) {
              var a = n.smtp_enabled_notice || {},
                i =
                  '<div class="notice notice-info is-dismissible" style="margin:15px 0;padding:12px 15px;"><p style="margin:0.5em 0;"><strong>' +
                  (a.title || "SMTP Enabled Successfully!") +
                  '</strong></p><p style="margin:0.5em 0;">' +
                  (a.body || "") +
                  ' <a href="#cin-tab-notifications" class="cin-switch-tab-link" data-target-tab="notifications" style="font-weight:bold;">' +
                  (a.tab_link || "Notifications tab") +
                  '</a>.</p><p style="margin:0.5em 0;">☑️ ' +
                  (a.admin_hint || "") +
                  "<br>☑️ " +
                  (a.user_hint || "") +
                  '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
              e("#cin-tab-smtp > h3").after(i),
                document.addEventListener("click", function (t) {
                  var n = t.target.closest(".cin-switch-tab-link");
                  n &&
                    (t.preventDefault(),
                    s(n.getAttribute("data-target-tab")),
                    e("html, body").animate({ scrollTop: 0 }, 300));
                }),
                e(document).on("click", ".notice-dismiss", function () {
                  e(this)
                    .closest(".notice")
                    .fadeOut(300, function () {
                      e(this).remove();
                    });
                });
            }
          } else
            alert(
              "Error: " +
                (t && t.data && t.data.message ? t.data.message : "Unknown error"),
            );
        },
        error: function () {
          alert("Request failed. Please try again.");
        },
        complete: function () {
          t.prop("disabled", !1);
        },
      });
    }

    var v = !1;
    (e("#smtp-enable-btn")
      .off("click")
      .on("click", function () {
        var t = e(this),
          a = 1 === t.data("enabled") || "1" === t.data("enabled"),
          i = n.smtp_disable || {};

        if (a)
          return (
            v
              ? void 0
              : ((v = !0),
                void c({
                  title: i.title || "Disable SMTP?",
                  message:
                    "<strong>" +
                    (i.message_intro || "") +
                    '</strong><ul style="margin:10px 0 0 18px;padding:0"><li style="margin-bottom:4px">' +
                    (i.bullet_1 || "") +
                    '</li><li style="margin-bottom:4px">' +
                    (i.bullet_2 || "") +
                    '</li><li style="margin-bottom:4px">' +
                    (i.bullet_3 || "") +
                    "</li></ul>",
                  badge: i.badge || "Email Impact",
                  badgeColor: "#b45309",
                  confirmLabel: i.confirm_label || "Yes, disable SMTP",
                  cancelLabel: i.cancel_label || "Keep SMTP enabled",
                  confirmClass: "button-primary",
                }).then(function (e) {
                  ((v = !1), e && T(t, a));
                }))
          );
        T(t, a);
      }),
      e("#form-enable-subject-btn")
        .off("click")
        .on("click", function () {
          var t = e(this),
            n = 1 === t.data("enabled") || "1" === t.data("enabled");
          (t.prop("disabled", !0),
            e.ajax({
              url: ajaxurl,
              type: "POST",
              data: {
                action: "contactin_toggle_subject",
                enabled: n ? 0 : 1,
                nonce: p(),
              },
              success: function (t) {
                t && t.success
                  ? (function (t) {
                      (e("#contactin-subject-status-label")
                        .text(t ? "Enabled" : "Disabled")
                        .removeClass("enabled disabled")
                        .addClass(t ? "enabled" : "disabled"),
                        e("#form-enable-subject-btn")
                          .text(
                            t
                              ? "Disable Subject Field"
                              : "Enable Subject Field",
                          )
                          .toggleClass("enabled", t)
                          .data("enabled", t ? "1" : "0")
                          .attr("data-enabled", t ? "1" : "0"),
                        e("#form-enable-subject-hidden").val(t ? "1" : "0"));
                    })(!n)
                  : alert(
                      "Error: " +
                        (t && t.data && t.data.message
                          ? t.data.message
                          : "Unknown error"),
                    );
              },
              error: function () {
                alert("Request failed. Please try again.");
              },
              complete: function () {
                t.prop("disabled", !1);
              },
            }));
        }),
      e("#form-enable-salutation-btn")
        .off("click")
        .on("click", function () {
          var t = e(this),
            n = 1 === t.data("enabled") || "1" === t.data("enabled");
          (t.prop("disabled", !0),
            e.ajax({
              url: ajaxurl,
              type: "POST",
              data: {
                action: "contactin_toggle_salutation",
                enabled: n ? 0 : 1,
                nonce: p(),
              },
              success: function (t) {
                t && t.success
                  ? (function (t) {
                      (e("#contactin-salutation-status-label")
                        .text(t ? "Enabled" : "Disabled")
                        .removeClass("enabled disabled")
                        .addClass(t ? "enabled" : "disabled"),
                        e("#form-enable-salutation-btn")
                          .text(
                            t
                              ? "Disable Salutation Field"
                              : "Enable Salutation Field",
                          )
                          .toggleClass("enabled", t)
                          .data("enabled", t ? "1" : "0")
                          .attr("data-enabled", t ? "1" : "0"),
                        e("#form-enable-salutation-hidden").val(t ? "1" : "0"));
                    })(!n)
                  : alert(
                      "Error: " +
                        (t && t.data && t.data.message
                          ? t.data.message
                          : "Unknown error"),
                    );
              },
              error: function () {
                alert("Request failed. Please try again.");
              },
              complete: function () {
                t.prop("disabled", !1);
              },
            }));
        }));
    "1" !== String(e("#cin-select-recommended-types").data("upgradeOnly") || "") &&
        e("#cin-select-recommended-types")
          .off("click")
          .on("click", function (t) {
            (t.preventDefault(),
              e("#allowed_file_types")
                .val([
                  "pdf",
                  "docx",
                  "xlsx",
                  "jpg",
                  "jpeg",
                  "png",
                  "gif",
                  "txt",
                  "csv",
                ])
                .change());
                  });
    var D = n.clear_file_types || {};
    "1" !== String(e("#cin-clear-file-types").data("upgradeOnly") || "") &&
      e("#cin-clear-file-types")
        .off("click")
        .on("click", function (t) {
          (t.preventDefault(),
            c({
              title: D.title || "Clear all allowed file types?",
              message: (D.message || "") + "<br><br>" + (D.message_2 || ""),
              badge: D.badge || "Destructive",
              badgeColor: "#6b21a8",
              confirmLabel: D.confirm_label || "Yes, clear all",
              cancelLabel: D.cancel_label || "Cancel",
              confirmClass: "button-primary",
            }).then(function (t) {
              t && e("#allowed_file_types").val([]).change();
            }));
        });
    var A = !1,
      I = n.recaptcha_disable || {};
    e('input[name="recaptcha_enable"]').on("change", function () {
      var t = e(this);
      t.is(":checked") ||
        A ||
        (t.prop("checked", !0),
        (A = !0),
        c({
          title: I.title || "Disable reCAPTCHA?",
          message:
            "<strong>" +
            (I.message_intro || "") +
            '</strong><ul style="margin:10px 0 0 18px;padding:0"><li style="margin-bottom:4px">' +
            (I.bullet_1 || "") +
            '</li><li style="margin-bottom:4px">' +
            (I.bullet_2 || "") +
            "</li></ul>",
          badge: I.badge || "Security Risk",
          badgeColor: "#dc3232",
          confirmLabel: I.confirm_label || "Yes, disable reCAPTCHA",
          cancelLabel: I.cancel_label || "Keep reCAPTCHA enabled",
          confirmClass: "button-primary",
        }).then(function (e) {
          ((A = !1), e && t.prop("checked", !1));
        }));
    });
    var q = !1,
      N = n.ip_allowlist_enable || {};
    function F(e, t) {
      var a = parseInt(e.val(), 10);
      if (
        (e.closest("td").find(".cin-rate-advisory").remove(),
        !isNaN(a) && a < t)
      ) {
        var i =
          n.rate_limit_advisory ||
          "⚠ This value is very low and may block legitimate visitors.";
        e.after(
          '<span class="cin-rate-advisory" style="display:block;margin-top:5px;color:#b45309;font-size:12px;font-weight:600;">' +
            i +
            "</span>",
        );
      }
    }
    (e('input[name="ip_allowlist_enable"]').on("change", function () {
      var t = e(this);
      t.is(":checked") &&
        !q &&
        (t.prop("checked", !1),
        (q = !0),
        c({
          title: N.title || "Enable IP Allowlist Mode?",
          message:
            "<strong>" +
            (N.message_intro || "") +
            '</strong><ul style="margin:10px 0 0 18px;padding:0"><li style="margin-bottom:4px">' +
            (N.bullet_1 || "") +
            '</li><li style="margin-bottom:4px">' +
            (N.bullet_2 || "") +
            '</li><li style="margin-bottom:4px">' +
            (N.bullet_3 || "") +
            "</li></ul>",
          badge: N.badge || "Lockout Risk",
          badgeColor: "#b45309",
          confirmLabel: N.confirm_label || "Yes, restrict to allowlist",
          cancelLabel: N.cancel_label || "Cancel",
          confirmClass: "button-primary",
        }).then(function (e) {
          ((q = !1), e && t.prop("checked", !0));
        }));
    }),
      e("#rate_limit_per_minute").on("blur change", function () {
        F(e(this), 2);
      }),
      e("#rate_limit_per_hour").on("blur change", function () {
        F(e(this), 20);
      }),
      e("#rate_limit_per_day").on("blur change", function () {
        F(e(this), 100);
      }),
      e(
        "#rate_limit_per_minute, #rate_limit_per_hour, #rate_limit_per_day",
      ).trigger("change"),
        ["confetti_enable"].forEach(function (t) {
        var n = e('input[type="checkbox"][name="' + t + '"]'),
          a = e('input[type="hidden"][name="' + t + '"]');
        n.length &&
          a.length &&
          n.on("change", function () {
            a.val(this.checked ? "1" : "0");
          });
      }),
      (function () {
        var a = t.nonce || "",
          i = null,
          c = !1;
        function s(e) {
          return (e || "")
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, "-")
            .replace(/^-+|-+$/g, "")
            .substring(0, 50);
        }
        c = !1;
        function s(e) {
          return (e || "")
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, "-")
            .replace(/^-+|-+$/g, "")
            .substring(0, 50);
        }
        var o = n.profiles || {};
        function r(e) {
          return e
            ? '<span style="color:#00a32a">✓</span>'
            : '<span style="color:#888">—</span>';
        }
        function d(t) {
          var n = e("#cin-profiles-tbody");
          (n.empty(),
            0 !== Object.keys(t).length
              ? e.each(t, function (e, t) {
                  var a =
                    "default" === e
                      ? '<span style="color:#ccc;font-size:12px">protected</span>'
                      : '<button type="button" class="button button-small cin-delete-profile" data-slug="' +
                        e +
                        '" style="color:#dc3232;border-color:#dc3232;">Delete</button>';
                  n.append(
                    "<tr><td><code>" +
                      e +
                      "</code></td><td>" +
                      (t.label || "—") +
                      '</td><td style="text-align:center">' +
                      r(t.show_phone) +
                      '</td><td style="text-align:center">' +
                      r(t.show_subject) +
                      '</td><td style="text-align:center">' +
                      r(t.show_consent) +
                      "</td><td>" +
                      (t.notify_email ||
                        '<span style="color:#888">global</span>') +
                      '</td><td><button type="button" class="button button-small cin-edit-profile" data-slug="' +
                      e +
                      '" style="margin-right:4px">Edit</button>' +
                      a +
                      "</td></tr>",
                  );
                })
              : n.append(
                  '<tr><td colspan="7" style="text-align:center;padding:20px;">' +
                    (o.no_profiles || "No profiles yet.") +
                    "</td></tr>",
                ));
        }
        function u(t, n) {
          i = t || null;
          var a = !t;
          if (
            ((c = !1),
            e("#cin-editor-title").text(
              a
                ? o.new_profile || "New Profile"
                : (o.edit_profile || "Edit Profile") + ": " + t,
            ),
            e("#cin-p-label").val(n ? n.label : ""),
            a)
          ) {
            var l = n ? s(n.label) : "";
            (e("#cin-slug-preview").text(l || "—"),
              e("#cin-slug-display").show(),
              e("#cin-slug-edit-link").show(),
              e("#cin-slug-input-wrap").hide(),
              e("#cin-slug-error").hide(),
              e("#cin-slug-desc-new").show(),
              e("#cin-slug-desc-edit").hide());
          } else
            (e("#cin-slug-preview").text(t),
              e("#cin-slug-display").show(),
              e("#cin-slug-edit-link").hide(),
              e("#cin-slug-input-wrap").hide(),
              e("#cin-slug-desc-new").hide(),
              e("#cin-slug-desc-edit").show());
          (e("#cin-p-show-phone").prop("checked", !n || n.show_phone),
            e("#cin-p-require-phone").prop("checked", !!n && n.require_phone),
            e("#cin-p-show-salutation").prop(
              "checked",
              !!n && n.show_salutation,
            ),
            e("#cin-p-show-subject").prop("checked", !!n && n.show_subject),
            e("#cin-p-require-subject").prop(
              "checked",
              !!n && n.require_subject,
            ));
          (e("#cin-p-show-consent").prop("checked", !n || n.show_consent),
            e("#cin-p-recaptcha").val((n && n.recaptcha) || "auto"),
            e("#cin-p-confetti").val((n && n.confetti) || "auto"),
            e("#cin-p-notify-email").val(n ? n.notify_email : ""),
            e("#cin-p-success-message").val(n ? n.success_message : ""),
            e("#cin-p-consent-text").val(n ? n.consent_text : ""),
            e("#cin-profile-msg").hide(),
            e("#cin-profile-pro-notice").hide(),
            e("#cin-profile-modal").removeClass("cin-modal-hidden"));
        }
        function p() {
          (e("#cin-profile-modal").addClass("cin-modal-hidden"), (i = null));
        }
        (e.post(
          ajaxurl,
          { action: "contactin_get_form_profiles", nonce: a },
          function (e) {
            e.success && d(e.data.profiles);
          },
        ),
          e("#cin-p-label").on("input", function () {
            if (!i && !c) {
              var t = s(e(this).val());
              e("#cin-slug-preview").text(t || "—");
            }
          }),
          e(document).on("click", "#cin-slug-edit-link", function (t) {
            (t.preventDefault(), (c = !0));
            var n = e("#cin-slug-preview").text();
            (e("#cin-p-slug").val("—" === n ? "" : n),
              e("#cin-slug-display").hide(),
              e("#cin-slug-input-wrap").show(),
              e("#cin-p-slug").trigger("focus"));
          }),
          e(document).on("click", "#cin-slug-auto-link", function (t) {
            (t.preventDefault(), (c = !1));
            var n = s(e("#cin-p-label").val());
            (e("#cin-slug-preview").text(n || "—"),
              e("#cin-slug-input-wrap").hide(),
              e("#cin-slug-error").hide(),
              e("#cin-slug-display").show());
          }),
          e(document).on("input", "#cin-p-slug", function () {
            var t = e(this).val();
            t && !/^[a-z0-9_-]+$/.test(t)
              ? e("#cin-slug-error")
                  .text("Only: a-z, 0-9, hyphens, underscores.")
                  .show()
              : e("#cin-slug-error").hide();
          }),
          e("#cin-add-profile-btn").on("click", function () {
            u(null, null);
          }),
          e("#cin-cancel-profile-btn").on("click", p),
          e(document).on("click", "#cin-profile-modal .cin-modal-close", p),
          e(document).on("click", "#cin-profile-modal", function (t) {
            e(t.target).is("#cin-profile-modal") && p();
          }),
          e(document).on("keydown.cinProfileModal", function (t) {
            "Escape" !== t.key ||
              e("#cin-profile-modal").hasClass("cin-modal-hidden") ||
              p();
          }),
          e(document).on("click", ".cin-edit-profile", function () {
            var t = e(this).data("slug");
            e.post(
              ajaxurl,
              { action: "contactin_get_form_profiles", nonce: a },
              function (e) {
                e.success && e.data.profiles[t] && u(t, e.data.profiles[t]);
              },
            );
          }),
          e(document).on("click", ".cin-delete-profile", function () {
            var t = e(this).data("slug");
            window.confirm(
              o.delete_confirm ||
                "Delete this form profile? This cannot be undone.",
            ) &&
              e.post(
                ajaxurl,
                { action: "contactin_delete_form_profile", nonce: a, slug: t },
                function (e) {
                  e.success
                    ? d(e.data.profiles)
                    : alert(
                        (e.data && e.data.message) ||
                          o.delete_failed ||
                          "Could not delete profile.",
                      );
                },
              );
          }),
          e("#cin-save-profile-btn").on("click", function () {
            var t,
              n = e(this),
              r = e("#cin-profile-msg");
            if (
              (t =
                i ||
                (c
                  ? e("#cin-p-slug")
                      .val()
                      .trim()
                      .toLowerCase()
                      .replace(/[^a-z0-9_-]/g, "")
                  : s(e("#cin-p-label").val())))
            )
              if (/^[a-z0-9_-]+$/.test(t)) {
                var u = {
                  action: "contactin_save_form_profile",
                  nonce: a,
                  slug: t,
                  label: e("#cin-p-label").val(),
                  show_phone: e("#cin-p-show-phone").is(":checked") ? 1 : 0,
                  require_phone: e("#cin-p-require-phone").is(":checked")
                    ? 1
                    : 0,
                  show_salutation: e("#cin-p-show-salutation").is(":checked")
                    ? 1
                    : 0,
                  show_subject: e("#cin-p-show-subject").is(":checked") ? 1 : 0,
                  require_subject: e("#cin-p-require-subject").is(":checked")
                    ? 1
                    : 0,
                  show_consent: e("#cin-p-show-consent").is(":checked") ? 1 : 0,
                  recaptcha: e("#cin-p-recaptcha").val(),
                  confetti: e("#cin-p-confetti").val(),
                  notify_email: e("#cin-p-notify-email").val(),
                  success_message: e("#cin-p-success-message").val(),
                  consent_text: e("#cin-p-consent-text").val(),
                };
                (n.prop("disabled", !0),
                  e
                    .post(ajaxurl, u, function (t) {
                      if (t.success)
                        (d(t.data.profiles),
                          r
                            .css("color", "#00a32a")
                            .text(t.data.message)
                            .show(),
                          e("#cin-profile-pro-notice").hide(),
                          setTimeout(p, 1200));
                      else
                        r.css("color", "#dc3232")
                          .text(
                            (t.data && t.data.message) ||
                              o.save_failed ||
                              "Save failed.",
                          )
                          .show();
                    })
                    .always(function () {
                      n.prop("disabled", !1);
                    }));
              } else
                r.css("color", "#dc3232")
                  .text(
                    "Slug may only contain lowercase letters, numbers, hyphens, and underscores.",
                  )
                  .show();
            else
              r.css("color", "#dc3232")
                .text(o.slug_required || "Profile Name is required.")
                .show();
          }));
      })());
  });
})(jQuery);
