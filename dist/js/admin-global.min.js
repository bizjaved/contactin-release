!(function (e) {
  "use strict";
  ((window.cinShowMessage = function (n, t = "info", o = 4e3) {
    e("#cin-message-box").remove();
    window.cinInbox?.i18n?.message_box ||
      window.ContactINEmailLog?.i18n?.message_box;
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
      openCinInboxHelpModal: "cin-inbox-help-modal",
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
                  window.cinInbox?.nonce,
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
                else if (o?.data?.requires_confirmation)
                  (h(!1), w(n), cinShowMessage(o.data.message || "Action requires confirmation", "warning", 5e3));
                else
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
