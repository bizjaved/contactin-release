!(function (e) {
  const t = window.ContactINMaintenance || {},
    n = e("#contactin-maint-message");
  function a(t, a) {
    (n
      .removeClass("notice-success notice-error")
      .addClass(
        a ? "notice notice-error" : "notice notice-success is-dismissible",
      ),
      n
        .html(
          "<p>" +
            t +
            '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>',
        )
        .show(),
      n.find(".notice-dismiss").on("click", function () {
        n.hide();
      }),
      e("html, body").animate({ scrollTop: n.offset().top - 50 }, 400));
  }
  function o(e) {
    (e.data("orig", e.text()),
      e.prop("disabled", !0).text(t.messages?.processing || "Processing..."));
  }
  function s(e) {
    const t = e.data("orig");
    e.prop("disabled", !1).text(t || "Submit");
  }
  function m(e) {
    return String(e || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }
  function p() {
    const n = window.ContactINMaintenance || {},
      a = n.upgradeTitle || "Unlock Premium Features",
      o = n.upgradeMessage || "This maintenance action is available in ContactIn Pro.",
      s = n.upgradeCta || "Upgrade to Pro",
      i = n.upgradeDismiss || "Maybe later",
      c = n.upgradeUrl || "#";
    e("#cin-upgrade-export-modal").remove();
    const r =
      '<div id="cin-upgrade-export-modal" class="cin-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="cin-upgrade-export-title" style="position:fixed;inset:0;display:flex;align-items:center;justify-content:center;padding:24px;background:rgba(0,0,0,0.35);z-index:2147483647;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .2s ease;"><div class="cin-confirm-modal" style="margin:0;max-width:min(560px,calc(100vw - 32px));max-height:calc(100vh - 48px);overflow:auto;"><h3 id="cin-upgrade-export-title">' +
      m(a) +
      '</h3><div class="cin-confirm-details"><p>' +
      m(o) +
      '</p></div><div class="cin-confirm-actions"><button type="button" class="button cin-upgrade-export-dismiss">' +
      m(i) +
      '</button><a class="button button-primary" href="' +
      c +
      '">' +
      m(s) +
      "</a></div></div></div>";
    e("body").append(r);
    const l = e("#cin-upgrade-export-modal");
    function d() {
      l.css({ opacity: "0", visibility: "hidden", pointerEvents: "none" }),
        setTimeout(function () {
          l.remove();
        }, 200);
    }
    (setTimeout(function () {
      l.css({ opacity: "1", visibility: "visible", pointerEvents: "auto" });
    }, 10),
      l.on("click", ".cin-upgrade-export-dismiss", function (e) {
        e.preventDefault(), d();
      }),
      l.on("click", function (t) {
        e(t.target).is("#cin-upgrade-export-modal") && d();
      }),
      e(document).one("keyup.cinMaintUpgrade", function (e) {
        "Escape" === e.key && d();
      }));
  }
  const i = {},
    c = {},
    r = {};
  function l(n, o) {
    const s = e('.contactin-progress[data-progress-scope="' + n + '"]');
    if (!s.length) return;
    const i = s.find(".contactin-progress-fill"),
      l = s.find(".contactin-progress-text"),
      d = s.find("[data-progress-count]"),
      u = o?.pending_total ?? 0,
      m = !!o?.in_progress,
      p = c[n] || { total: null };
    (null === p.total && (p.total = u), u > p.total && (p.total = u));
    const _ = Math.max(0, (p.total || 0) - u),
      g =
        p.total > 0
          ? Math.min(100, Math.round((_ / p.total) * 100))
          : 0 === u
            ? 100
            : 0;
    ((c[n] = p),
      i.css("width", g + "%"),
      d.text("Pending: " + u + (p.total ? " / " + p.total : "")),
      0 !== u || m
        ? l.text(t.messages?.progressRunning || "Processing in background...")
        : (l.text(t.messages?.progressDone || "Processing complete."),
          r[n] &&
            ((r[n] = !1),
            a(
              (t.messages?.progressDone || "Processing complete.") +
                " Reloading...",
            ),
            setTimeout(function () {
              window.location.reload();
            }, t.reloadDelay || 800)),
          setTimeout(function () {
            s.removeClass("is-active");
          }, 3e3)));
  }
  function d(t) {
    i[t] && (clearInterval(i[t]), delete i[t]);
    e('.contactin-progress[data-progress-scope="' + t + '"]').removeClass(
      "is-active",
    );
  }
  function u(n) {
    if (!n || !t.progressNonce || !t.ajaxUrl) return;
    (e('.contactin-progress[data-progress-scope="' + n + '"]').addClass(
      "is-active",
    ),
      (c[n] = { total: null }));
    const a = function () {
      e.post(t.ajaxUrl, {
        action: "contactin_maint_queue_progress",
        nonce: t.progressNonce,
      }).done(function (e) {
        e &&
          e.success &&
          e.data &&
          "email" === n &&
          (l("email", e.data.email),
          0 !== e.data.email?.pending_total || e.data.email?.in_progress ||
            d("email"));
      });
    };
    (d(n), a(), (i[n] = setInterval(a, t.progressPollMs || 2e3)));
  }
  (e(document).on("click", "[data-upgrade-only='1']", function (e) {
    e.preventDefault(), e.stopImmediatePropagation(), p();
  }),
    e(".js-maint-action").on("click", function () {
    const n = e(this),
      i = n.data("action"),
      c = n.data("nonce");
    if (!i || !c) return;
    const l = (function (e) {
      switch (e) {
        case "contactin_maint_retry_dlq":
          return t.confirm?.retryDlq;
        case "contactin_maint_retry_email_dlq":
          return t.confirm?.retryEmailDlq;
        case "contactin_maint_reset_circuits":
          return t.confirm?.resetCircuits;
        case "contactin_maint_skip_email":
          return t.confirm?.skipEmail;
        default:
          return null;
      }
    })(i);
    if (l && !window.confirm(l)) return;
    const d = { action: i, nonce: c };
    if ("contactin_maint_reschedule_email_queue" === i) {
      const e =
          parseInt(n.data("delay-default"), 10) ||
          t.defaults?.delaySeconds ||
          120,
        o = (
          t.messages?.delayPrompt ||
          "Enter seconds until next run (default %s):"
        ).replace("%s", e),
        s = window.prompt(o, e);
      if (null === s) return;
      const i = parseInt(s, 10);
      if ((Number.isNaN ? Number.isNaN(i) : isNaN(i)) || i <= 0)
        return void a(
          t.messages?.invalidDelay ||
            "Please provide a valid number of seconds.",
          !0,
        );
      d.delay_seconds = i;
    }
    (o(n),
      e
        .post(t.ajaxUrl || window.ajaxurl, d)
        .done(function (e) {
          if (e && e.success) {
            let o = t.messages?.actionCompleted || "Action completed.";
            e.data && e.data.message && (o = e.data.message);
            const c = [
                "contactin_maint_reschedule_email_queue",
                "contactin_maint_retry_email_dlq",
                "contactin_maint_retry_dlq",
              ],
              l = (function (e) {
                return "contactin_maint_run_queue_email" === e ? "email" : null;
              })(i);
            l
              ? (a(o), (r[l] = !0), u(l), s(n))
              : c.includes(i)
                ? (a(o + " Reloading..."),
                  setTimeout(function () {
                    window.location.reload();
                  }, 1e3))
                : ((o +=
                    ' <a href="javascript:void(0)" onclick="window.location.reload()">Reload page</a>'),
                  a(o),
                  s(n));
          } else
            (a(
              (t.messages?.ajaxError || "Error") +
                " " +
                (e && e.data ? e.data : ""),
              !0,
            ),
              s(n));
        })
        .fail(function () {
          (a(t.messages?.ajaxError || "AJAX error occurred.", !0), s(n));
        }));
  }),
    e(function () {
      (e(document).on(
        "click",
        ".notice.is-dismissible .notice-dismiss",
        function (t) {
          (t.preventDefault(),
            e(this)
              .closest(".notice")
              .fadeOut(300, function () {
                e(this).remove();
              }));
        },
      ),
        t.progressNonce &&
          t.ajaxUrl &&
          e
            .post(t.ajaxUrl, {
              action: "contactin_maint_queue_progress",
              nonce: t.progressNonce,
            })
            .done(function (e) {
              if (!e || !e.success || !e.data) return;
              l("email", e.data.email);
              (e.data.email?.in_progress || e.data.email?.pending_total > 0) &&
                u("email");
            }));
    }));
})(jQuery);
