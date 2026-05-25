!(function (e) {
  "use strict";
  ((window.cinShowMessage = function (n, t = "info", o = 4e3) {
    e("#cin-message-box").remove();
    window.cinInbox?.i18n?.message_box ||
      window.ContactINEmailLog?.i18n?.message_box ||
      window.cinRestLog?.i18n?.message_box ||
      window.contactinCrmLog?.i18n?.message_box;
    const i = e('<div id="cin-message-box" class="cin-message-box"></div>')
        .addClass(t)
        .attr("role", "alert")
        .text(n),
      a = e(".wrap .cin-page-header").first(),
      c = e(".wrap h1, .wrap h1.wp-heading-inline");
    (a.length ? a.after(i) : c.length ? c.after(i) : e(".wrap").prepend(i),
      o > 0 && setTimeout(() => i.fadeOut(400, () => i.remove()), o));
  }),
    (window.cinAjax = function (e, n = {}, t, o, i = {}) {
      const a = i.ajax_url || cinInbox?.ajax_url || window.ajaxurl,
        c = i.nonce || cinInbox?.nonce,
        s = new URLSearchParams();
      return (
        s.set("action", e),
        c && s.set("nonce", c),
        Object.entries(n).forEach(([e, n]) => {
          null != n &&
            "" !== n &&
            !1 !== n &&
            (Array.isArray(n)
              ? n.forEach((n) => s.append(`${e}[]`, n))
              : s.set(e, n));
        }),
        fetch(a, {
          method: "POST",
          credentials: "same-origin",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
            "X-Requested-With": "XMLHttpRequest",
          },
          body: s.toString(),
        })
          .then((e) => {
            if (!e.ok) throw new Error(`HTTP ${e.status}`);
            return e.json();
          })
          .then((e) => {
            if (e.success) "function" == typeof t && t(e);
            else {
              const n =
                e.data && e.data.message
                  ? e.data.message
                  : i.i18n?.progress?.error || "Error";
              cinShowMessage(n, "error");
            }
            return e;
          })
          .catch((e) => {
            throw (
              console.error("CIN AJAX failed:", e),
              "function" == typeof o
                ? o(e)
                : cinShowMessage(i.i18n?.progress?.error || "Error", "error"),
              e
            );
          })
      );
    }),
    (window.cinExportHelper = function (n = {}) {
      const t = n.infoAction || "ci_export_info",
        o = n.baseUrl || "",
        i = n.search || "",
        a = n.status || "all",
        c = n.contact_id || 0,
        s =
          n.ajax_url ||
          window.cinInbox?.ajax_url ||
          window.ContactINEmailLog?.ajax_url ||
          window.contactinCrmLog?.ajaxUrl ||
          window.ContactINRestLog?.ajax_url ||
          window.ajaxurl,
        r =
          n.nonce ||
          window.cinInbox?.nonce ||
          window.ContactINEmailLog?.nonce ||
          window.contactinCrmLog?.nonce ||
          window.ContactINRestLog?.nonce;
      o
        ? cinAjax(
            t,
            {
              s: i,
              status: a,
              contact_id: c,
              crm_status: n.crm_status || "",
              deletion_status: n.deletion_status || "",
            },
            function (n) {
              if (!n.success || !n.data)
                return void cinShowMessage(
                  "Failed to prepare export.",
                  "error",
                );
              const t = n.data.total || 0,
                i = n.data.limit || 1e3;
              n.data.batches;
              if (!t)
                return void cinShowMessage("No records to export.", "info");
              const a = e("#cin-export-modal");
              if (!a.length)
                return void cinShowMessage("Export modal not found.", "error");
              (e("#cin-export-total").text(t),
                e("#cin-export-max").text(i),
                e("#cin-export-chunk").attr("max", i).val(Math.min(i, 500)),
                e("#cin-export-links").empty(),
                a.addClass("active"),
                setTimeout(() => {
                  document.getElementById("cin-export-chunk")?.focus();
                }, 10));
              let c = null;
              function s(n) {
                e("#cin-export-chunk").prop("disabled", !!n);
              }
              const r = e("#cin-export-chunk");
              function l(n = !1) {
                c && clearTimeout(c);
                const a = () => {
                  const n = parseInt(r.val(), 10) || i;
                  (s(!0),
                    e("#cin-export-links").html(
                      '<div style="display:flex;align-items:center;gap:8px;padding:8px 4px;font-size:13px;color:#444;"><span class="dashicons dashicons-update dashicons-spin"></span><span>Preparing download links...</span></div>',
                    ),
                    setTimeout(
                      () =>
                        (function (n) {
                          n = Math.max(1, Math.min(i, n || i));
                          const a = Math.max(1, Math.ceil(t / n)),
                            c = e("#cin-export-links"),
                            r = [];
                          for (let e = 0; e < a; e++) {
                            const i = e * n + 1,
                              c = Math.min(t, (e + 1) * n),
                              s = `${o}&batch=${e + 1}&limit=${n}&total_batches=${a}`;
                            r.push(
                              `<div style="margin:4px 0;"><a class="cin-export-link" href="${s}" data-batch="${e + 1}" data-chunk="${n}">Download ${i}–${c}</a></div>`,
                            );
                          }
                          (c.html(r.join("")), s(!1));
                        })(n),
                      180,
                    ));
                };
                n ? a() : (c = setTimeout(a, 400));
              }
              l(!0);
              (a.on("click", "#cin-export-close", function (e) {
                (c && clearTimeout(c), a.removeClass("active"));
              }),
                a.on("click", function (e) {
                  "cin-export-modal" === e.target.id &&
                    (c && clearTimeout(c), a.removeClass("active"));
                }),
                a.on("click", ".cin-export-link", function (n) {
                  n.preventDefault();
                  !(function (e) {
                    const n = document.createElement("iframe");
                    ((n.style.display = "none"),
                      (n.src = e),
                      document.body.appendChild(n),
                      setTimeout(() => {
                        n.remove();
                      }, 15e3));
                  })(e(this).attr("href"));
                }),
                a.on("change keyup", "#cin-export-chunk", function (e) {
                  "keyup" !== e.type || "Enter" === e.key ? l(!0) : l(!1);
                }));
            },
            function () {
              cinShowMessage("Failed to export records.", "error");
            },
            {
              ajax_url: s,
              nonce: r,
              nonce_key: "nonce",
              timeout: 15e3,
              i18n:
                window.cinInbox?.i18n ||
                window.ContactINEmailLog?.i18n ||
                window.contactinCrmLog?.i18n ||
                window.ContactINRestLog?.i18n ||
                {},
            },
          )
        : cinShowMessage("Export URL missing.", "error");
    }),
    (window.cinAttachAutoSubmit = function (n = {}) {
      const t = n.form,
        o = n.fields || [];
      if (!t || !o.length) return;
      const i = e(t);
      i.length &&
        o.forEach((n) => {
          const t = e(n);
          t.length && t.on("change", () => i.trigger("submit"));
        });
    }),
    e(document).on("change", "#cb-select-all-1, #cb-select-all-2", function () {
      const n = this.checked;
      e('input[name="message_ids[]"]').prop("checked", n);
    }),
    e(function () {
      (window.cinAttachAutoSubmit({
        form: "#contactin-email-log-form",
        fields: ["#status-filter", "#per-page-filter-email"],
      }),
        window.cinAttachAutoSubmit({
          form: "#contactin-crm-log-form",
          fields: [
            "#status-filter-crm",
            "#operation-filter-crm",
            "#per-page-filter-crm",
          ],
        }),
        window.cinAttachAutoSubmit({
          form: "#contactin-rest-log-form",
          fields: [
            "#method-filter",
            "#endpoint-filter",
            "#http-code-filter",
            "#validated-filter",
            "#per-page-filter-rest",
          ],
        }));
    }));
  const n = "data-cin-help-modal",
    t = "data-cin-help-open",
    o =
      'a[href]:not([tabindex="-1"]), button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])',
    i = window.requestAnimationFrame
      ? window.requestAnimationFrame.bind(window)
      : (e) => setTimeout(e, 0),
    a = [],
    c = new WeakMap(),
    s = new WeakMap();
  let r = "";
  function l(e) {
    if (!e || e.classList.contains("cin-modal-hidden")) return;
    (u(e),
      e.classList.add("cin-modal-hidden"),
      e.setAttribute("aria-hidden", "true"),
      (function (e) {
        const n = c.get(e);
        n && (e.removeEventListener("keydown", n), c.delete(e));
      })(e));
    const n = a.indexOf(e);
    (n > -1 && a.splice(n, 1),
      a.length ||
        (document.body &&
          0 === a.length &&
          (document.body.style.overflow = r)));
    const t = s.get(e);
    (t && "function" == typeof t.focus && t.focus(), s.delete(e));
  }
  function d(e, n = {}) {
    e &&
      (u(e),
      e.classList.contains("cin-modal-hidden") &&
        (!document.body ||
          a.length > 0 ||
          ((r = document.body.style.overflow || ""),
          (document.body.style.overflow = "hidden")),
        e.classList.remove("cin-modal-hidden"),
        e.setAttribute("aria-hidden", "false"),
        e.hasAttribute("role") || e.setAttribute("role", "dialog"),
        e.setAttribute("aria-modal", "true"),
        a.push(e),
        (function (e) {
          if (c.has(e)) return;
          const n = (n) => {
            if ("Tab" !== n.key) return;
            const t = e.querySelectorAll(o);
            if (!t.length) return void n.preventDefault();
            const i = t[0],
              a = t[t.length - 1];
            n.shiftKey && document.activeElement === i
              ? (n.preventDefault(), a.focus())
              : n.shiftKey ||
                document.activeElement !== a ||
                (n.preventDefault(), i.focus());
          };
          (e.addEventListener("keydown", n), c.set(e, n));
        })(e),
        (function (e) {
          const n =
            e.querySelector("[data-cin-help-focus]") ||
            e.querySelector(".cin-modal-close") ||
            e.querySelector(o);
          n && "function" == typeof n.focus && i(() => n.focus());
        })(e),
        n.trigger && s.set(e, n.trigger)));
  }
  function u(e) {
    if (!e || "1" === e.dataset.cinHelpBound) return;
    ((e.dataset.cinHelpBound = "1"),
      e.setAttribute(
        "aria-hidden",
        e.classList.contains("cin-modal-hidden") ? "true" : "false",
      ),
      e.hasAttribute("role") || e.setAttribute("role", "dialog"),
      e.setAttribute("aria-modal", "true"));
    const n = e.querySelector(".cin-modal-overlay"),
      t = e.querySelectorAll(".cin-modal-close, .cin-modal-dismiss"),
      o = e.querySelectorAll(".cin-help-link");
    (t.forEach((n) => {
      n.addEventListener("click", (n) => {
        (n.preventDefault(), l(e));
      });
    }),
      n && n.addEventListener("click", () => l(e)),
      e.addEventListener("click", (n) => {
        n.target === e && l(e);
      }),
      o.forEach((n) => {
        n.addEventListener("click", (t) => {
          const o = n.getAttribute("href");
          o &&
            "#" === o.charAt(0) &&
            (t.preventDefault(),
            (function (e, n) {
              if (!n || "#" !== n.charAt(0)) return;
              const t = e.querySelector(n);
              if (!t) return;
              const o = e.querySelector(".cin-modal-body");
              if (o) {
                const e = o.getBoundingClientRect(),
                  n = t.getBoundingClientRect().top - e.top + o.scrollTop - 12;
                "function" == typeof o.scrollTo
                  ? o.scrollTo({ top: n, behavior: "smooth" })
                  : (o.scrollTop = n);
              } else t.scrollIntoView({ behavior: "smooth", block: "start" });
            })(e, o));
        });
      }));
  }
  function f(e) {
    e &&
      "1" !== e.dataset.cinHelpTrigger &&
      ((e.dataset.cinHelpTrigger = "1"),
      e.addEventListener("click", (n) => {
        const o = e.getAttribute(t);
        if (!o) return;
        n.preventDefault();
        const i = document.getElementById(o);
        i && (u(i), d(i, { trigger: e }));
      }));
  }
  (e(function () {
    (document.querySelectorAll(".cin-modal[" + n + "]").forEach(u),
      document.querySelectorAll("[" + t + "]").forEach(f),
      document.addEventListener("keydown", (e) => {
        if ("Escape" === e.key && a.length) {
          l(a[a.length - 1]);
        }
      }));
  }),
    (window.ContactINHelpModals = {
      open: (e) => d(document.getElementById(e)),
      close: (e) => l(document.getElementById(e)),
      refresh: () => {
        (document.querySelectorAll(".cin-modal[" + n + "]").forEach(u),
          document.querySelectorAll("[" + t + "]").forEach(f));
      },
    }));
  if (
    (Object.entries({
      openCinHelpModal: "cin-help-modal",
      openCinCrmHelpModal: "cin-crm-help-modal",
      openCinInboxHelpModal: "cin-inbox-help-modal",
      openCinRestApiHelpModal: "cin-restapi-help-modal",
      openCinMaintHelpModal: "contactin-maint-help-modal",
    }).forEach(([e, n]) => {
      window[e] = () => window.ContactINHelpModals.open(n);
    }),
    !document.getElementById("cin-toggle-button-styles"))
  ) {
    const e = document.createElement("style");
    ((e.id = "cin-toggle-button-styles"),
      (e.textContent =
        "\n            /* Gold Standard Toggle Button Styles */\n            .button.button-small.enabled {\n                background: #00a32a;\n                color: #fff;\n                border-color: #008a20;\n            }\n            .button.button-small.enabled:hover {\n                background: #008a20;\n                border-color: #007017;\n            }\n            .cin-status-label {\n                font-weight: 600;\n                padding: 4px 10px;\n                border-radius: 3px;\n                font-size: 12px;\n                display: inline-block;\n                margin-left: 10px;\n            }\n            .cin-status-label.enabled {\n                color: #00a32a;\n                background: #f0f6f0;\n            }\n            .cin-status-label.disabled {\n                color: #d63638;\n                background: #fcf0f1;\n            }\n        "),
      document.head.appendChild(e));
  }
  ((window.cinInitToggleButton = function (n) {
    const {
        buttonId: t,
        statusLabelId: o,
        hiddenFieldId: i,
        ajaxAction: a,
        enabledText: c,
        disabledText: s,
        enabledLabel: r = "Enabled",
        disabledLabel: f = "Disabled",
        onToggle: m,
      } = n,
      p = e("#" + t),
      g = e("#" + o),
      b = e("#" + i);
    if (!p.length) return;
    function h(e) {
      (p.prop("disabled", e), p.toggleClass("cin-toggle-busy", e));
    }
    function w(e) {
      (p.text(e ? c : s),
        p.toggleClass("enabled", e),
        p.data("enabled", e ? "1" : "0"),
        g.length &&
          (g.text(e ? r : f),
          g
            .removeClass("enabled disabled")
            .addClass(e ? "enabled" : "disabled")),
        b.length && b.val(e ? "1" : "0"),
        "function" == typeof m && m(e));
    }
    (w(1 === p.data("enabled") || "1" === p.data("enabled")),
      p.off("click").on("click", function () {
        const n = 1 === p.data("enabled") || "1" === p.data("enabled"),
          t = !n;
        (h(!0),
          e
            .post(
              ajaxurl,
              {
                action: a,
                enabled: t ? 1 : 0,
                nonce:
                  window.contactinbox_admin?.nonce ||
                  window.cinInbox?.nonce ||
                  window.contactinIntegrationL10n?.nonce ||
                  window.cinCRMSettings?.nonce,
              },
              function (o) {
                if (o && o.success)
                  (h(!1),
                    w(t),
                    cinShowMessage(
                      o.data?.message || "Settings updated successfully",
                      "success",
                      3e3,
                    ));
                else if (o?.data?.requires_confirmation) {
                  h(!1);
                  const i = document.getElementById(
                      "contactin-restapi-disable-modal",
                    ),
                    c =
                      o.data.message ||
                      "Disabling REST API will also disable file attachments.";
                  if (!i) {
                    return void (confirm(
                      c +
                        "\n\nDo you want to disable the REST API service anyway?",
                    )
                      ? (h(!0),
                        e
                          .post(
                            ajaxurl,
                            {
                              action: a,
                              enabled: t ? 1 : 0,
                              force_disable: "1",
                              nonce:
                                window.contactinbox_admin?.nonce ||
                                window.cinInbox?.nonce ||
                                window.contactinIntegrationL10n?.nonce ||
                                window.cinCRMSettings?.nonce,
                            },
                            function (e) {
                              (h(!1),
                                e && e.success
                                  ? (w(t),
                                    cinShowMessage(
                                      e.data?.message ||
                                        "REST API service disabled successfully",
                                      "success",
                                      3e3,
                                    ))
                                  : cinShowMessage(
                                      e.data?.message ||
                                        "Failed to update settings",
                                      "error",
                                      5e3,
                                    ));
                            },
                          )
                          .fail(function () {
                            (h(!1),
                              cinShowMessage(
                                "Network error. Please try again.",
                                "error",
                                5e3,
                              ));
                          }))
                      : w(n));
                  }
                  const s = document.getElementById(
                    "contactin-restapi-disable-message",
                  );
                  s && (s.textContent = c);
                  const r = i.querySelector(
                      "#contactin-restapi-disable-confirm",
                    ),
                    f = i.querySelector("#contactin-restapi-disable-cancel"),
                    m = i.querySelectorAll(".cin-modal-close"),
                    g = i.querySelector(".cin-modal-overlay"),
                    b = () => {
                      (r && r.removeEventListener("click", y),
                        f && f.removeEventListener("click", x),
                        m.forEach((e) => e.removeEventListener("click", x)),
                        g && g.removeEventListener("click", x));
                    },
                    x = (e) => {
                      (e &&
                        "function" == typeof e.preventDefault &&
                        e.preventDefault(),
                        b(),
                        l(i),
                        w(n));
                    },
                    y = (n) => {
                      (n &&
                        "function" == typeof n.preventDefault &&
                        n.preventDefault(),
                        b(),
                        l(i),
                        h(!0),
                        e
                          .post(
                            ajaxurl,
                            {
                              action: a,
                              enabled: t ? 1 : 0,
                              force_disable: "1",
                              nonce:
                                window.contactinbox_admin?.nonce ||
                                window.cinInbox?.nonce ||
                                window.contactinIntegrationL10n?.nonce ||
                                window.cinCRMSettings?.nonce,
                            },
                            function (e) {
                              (h(!1),
                                e && e.success
                                  ? (w(t),
                                    cinShowMessage(
                                      e.data?.message ||
                                        "REST API service disabled successfully",
                                      "success",
                                      3e3,
                                    ))
                                  : cinShowMessage(
                                      e.data?.message ||
                                        "Failed to update settings",
                                      "error",
                                      5e3,
                                    ));
                            },
                          )
                          .fail(function () {
                            (h(!1),
                              cinShowMessage(
                                "Network error. Please try again.",
                                "error",
                                5e3,
                              ));
                          }));
                    };
                  (r && r.addEventListener("click", y),
                    f && f.addEventListener("click", x),
                    m.forEach((e) => e.addEventListener("click", x)),
                    g && g.addEventListener("click", x),
                    u(i),
                    d(i, { trigger: p[0] }));
                } else
                  (h(!1),
                    cinShowMessage(
                      o?.data?.message || "Failed to update settings",
                      "error",
                      5e3,
                    ));
              },
            )
            .fail(function () {
              (h(!1),
                cinShowMessage(
                  "Network error. Please try again.",
                  "error",
                  5e3,
                ));
            }));
      }));
  }),
    (window.cinCreateToggleButtonHTML = function (e) {
      const {
        buttonId: n,
        statusLabelId: t,
        hiddenFieldId: o,
        enabled: i = !1,
        enabledText: a = "Disable",
        disabledText: c = "Enable",
        enabledLabel: s = "Enabled",
        disabledLabel: r = "Disabled",
        fieldName: l,
      } = e;
      return `\n            <div class="cin-toggle-control">\n                ${o && l ? `<input type="hidden" name="${l}" id="${o}" value="${i ? "1" : "0"}" />` : ""}\n                <button type="button" \n                    id="${n}" \n                    class="button button-small${i ? " enabled" : ""}" \n                    data-enabled="${i ? "1" : "0"}">\n                    ${i ? a : c}\n                </button>\n                ${t ? `<span id="${t}" class="cin-status-label ${i ? "enabled" : "disabled"}">\n                    ${i ? s : r}\n                </span>` : ""}\n            </div>\n        `;
    }));
})(jQuery);
