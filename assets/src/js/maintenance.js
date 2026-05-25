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
        if (
          e &&
          e.success &&
          e.data &&
          ("email" === n &&
            (l("email", e.data.email),
            0 !== e.data.email?.pending_total ||
              e.data.email?.in_progress ||
              d("email")),
          "crm" === n)
        ) {
          const t = Object.assign({}, e.data.crm || {}),
            n = e.data.attachments?.pending_total || 0;
          ((t.pending_total = (t.pending_total || 0) + n),
            l("crm", t),
            0 !== (t.pending_total || 0) ||
              e.data.crm?.in_progress ||
              d("crm"));
        }
      });
    };
    (d(n), a(), (i[n] = setInterval(a, t.progressPollMs || 2e3)));
  }
  (e(".js-maint-action").on("click", function () {
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
        case "contactin_maint_retry_crm_dlq":
          return t.confirm?.retryCrmDlq;
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
    if (
      "contactin_maint_reschedule_email_queue" === i ||
      "contactin_maint_reschedule_crm_queue" === i
    ) {
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
                "contactin_maint_reschedule_crm_queue",
                "contactin_maint_retry_email_dlq",
                "contactin_maint_retry_crm_dlq",
                "contactin_maint_retry_dlq",
              ],
              l = (function (e) {
                return "contactin_maint_run_queue_email" === e
                  ? "email"
                  : "contactin_maint_run_queue_crm" === e
                    ? "crm"
                    : null;
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
              const t = Object.assign({}, e.data.crm || {});
              ((t.pending_total =
                (t.pending_total || 0) +
                (e.data.attachments?.pending_total || 0)),
                l("crm", t),
                (e.data.email?.in_progress ||
                  e.data.email?.pending_total > 0) &&
                  u("email"),
                (e.data.crm?.in_progress || t.pending_total > 0) && u("crm"));
            }));
    }),
    e(".cin-gdpr-queue-delete-btn").on("click", function (n) {
      if ((n.preventDefault(), !t.ajaxUrl)) return;
      const i = e(this),
        c = i.data("nonce");
      window.confirm(
        t.gdprMessages?.confirmQueueDelete ||
          "Queue synced contacts for CRM deletion processing? This will be processed in the background.",
      ) &&
        (o(i),
        e
          .post(t.ajaxUrl || window.ajaxurl, {
            action: "contactin_maint_gdpr_queue_delete",
            nonce: c,
          })
          .done(function (e) {
            if (e && e.success) {
              (a(
                (e.data?.message || "Contacts queued for deletion.") +
                  ' <a href="javascript:void(0)" onclick="window.location.reload()">Reload page</a>',
              ),
                s(i));
            } else
              (a(
                (t.messages?.ajaxError || "Error") +
                  " " +
                  (e && e.data ? e.data : ""),
                !0,
              ),
                s(i));
          })
          .fail(function () {
            (a(t.messages?.ajaxError || "AJAX error occurred.", !0), s(i));
          }));
    }),
    e(".cin-gdpr-immediate-delete-btn").on("click", function (n) {
      if ((n.preventDefault(), !t.ajaxUrl)) return;
      const i = e(this),
        c = i.data("nonce");
      window.confirm(
        t.gdprMessages?.confirmImmediateDelete ||
          "Delete synced contacts from CRM immediately? This action cannot be undone and will use the same sync logic as form submissions with fallback support.",
      ) &&
        (o(i),
        e
          .post(t.ajaxUrl || window.ajaxurl, {
            action: "contactin_maint_gdpr_immediate_delete",
            nonce: c,
          })
          .done(function (e) {
            if (e && e.success) {
              (a(
                (e.data?.message || "Contacts deleted from CRM.") +
                  " Reloading...",
              ),
                setTimeout(function () {
                  window.location.reload();
                }, 1500));
            } else
              (a(
                (t.messages?.ajaxError || "Error") +
                  " " +
                  (e && e.data ? e.data : ""),
                !0,
              ),
                s(i));
          })
          .fail(function () {
            (a(t.messages?.ajaxError || "AJAX error occurred.", !0), s(i));
          }));
    }));
})(jQuery);
