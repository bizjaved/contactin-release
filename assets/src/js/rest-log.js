!(function (t) {
  "use strict";
  function e(t, e, o) {
    t &&
      t.length &&
      (e
        ? (t.addClass("is-busy").prop("disabled", !0),
          o && t.data("orig-label", t.text()).text(o))
        : (t.removeClass("is-busy").prop("disabled", !1),
          t.data("orig-label") &&
            (t.text(t.data("orig-label")), t.removeData("orig-label"))));
  }
  function o() {
    if (t("table.wp-list-table").length) {
      var e = t("#messages-filter"),
        o = e.find('input[name="paged"]').val() || 1,
        n = e.find('input[name="per_page"]').val() || 20,
        r = e.find('input[name="orderby"]').val() || "created_at",
        a = e.find('input[name="order"]').val() || "DESC";
      t.post(
        ContactINRestLog.ajax_url,
        {
          action: "contactin_get_rest_logs",
          nonce: ContactINRestLog.nonce,
          paged: o,
          per_page: n,
          orderby: r,
          order: a,
        },
        function (e) {
          e &&
            e.success &&
            e.data &&
            e.data.html &&
            t(".wp-list-table-container").html(e.data.html);
        },
      );
    }
  }
  const n = "contactin_view_rest_log",
    r = "contactin_get_adjacent_rest_log",
    a = "contactin_prune_rest",
    s = "contactin_clear_rest_logs";
  function i(t) {
    return String(t)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }
  function c(e) {
    (t("#contactin-rest-meta")
      .data("log-id", e.id)
      .html(
        (function (t) {
          let e = "";
          return (
            (e += `<p><strong>Timestamp:</strong> ${t.timestamp || ""}</p>`),
            (e += `<p><strong>IP:</strong> ${t.ip_address || ""}</p>`),
            (e += `<p><strong>User Agent:</strong> ${t.user_agent || ""}</p>`),
            (e += `<p><strong>HTTP Method:</strong> ${t.method || ""}</p>`),
            (e += `<p><strong>Endpoint:</strong> ${t.endpoint || ""}</p>`),
            (e += `<p><strong>HTTP Code:</strong> ${t.code || ""}</p>`),
            (e += `<p><strong>Validated:</strong> ${t.validated ? "✔" : "✖"}</p>`),
            (e += `<p><strong>Token valid:</strong> ${t.token_valid ? "✔" : "✖"}</p>`),
            t.error_message &&
              (e += `<p><strong>Error:</strong> ${t.error_message}</p>`),
            e
          );
        })(e),
      ),
      t("#contactin-rest-headers").html(
        `<h4>Request Headers</h4><pre>${i(e.request_headers || "")}</pre>`,
      ),
      t("#contactin-rest-request").html(
        `<h4>Request Payload</h4><pre>${i(e.request_payload || "")}</pre>`,
      ),
      t("#contactin-rest-response").html(
        `<h4>Response Body</h4><pre>${i(e.response_body || "")}</pre>`,
      ));
  }
  function l(t, e) {
    t.is("button, input")
      ? t.prop("disabled", e)
      : t
          .toggleClass("is-disabled", e)
          .attr("aria-disabled", e ? "true" : "false");
  }
  function d(e, o) {
    (l(t("#contactin-prev-log"), !e), l(t("#contactin-next-log"), !o));
  }
  function p() {
    return "object" != typeof ContactINRestLog
      ? (console.error("[ContactIN] Missing ContactINRestLog localization."),
        m("Environment error: localization missing.", "error"),
        !1)
      : !!ContactINRestLog.nonce ||
          (console.error("[ContactIN] Missing nonce in ContactINRestLog."),
          m("Security error: nonce missing.", "error"),
          !1);
  }
  function g(t) {
    return Object.assign({}, t, { nonce: ContactINRestLog.nonce });
  }
  function u(e) {
    if (!p()) return;
    const o = t("#contactin-rest-meta").data("log-id");
    if (!o) return;
    const n = t("#method-filter").val() || "all",
      a = t("#endpoint-filter").val() || "all",
      s = t("#http-code-filter").val() || "all",
      i = t("#validated-filter").val() || "all",
      u = t("prev" === e ? "#contactin-prev-log" : "#contactin-next-log");
    (l(u, !0),
      cinAjax(
        r,
        g({
          direction: e,
          current_id: o,
          http_method: n,
          endpoint: a,
          http_code: s,
          validated: i,
        }),
        void 0,
        void 0,
        ContactINRestLog,
      )
        .then((t) => {
          (t.success
            ? c(t.data)
            : m(
                t.data?.message ||
                  ContactINRestLog.i18n?.errorDetails ||
                  "No more logs in this direction.",
                "error",
              ),
            d(!!t.data?.hasPrev, !!t.data?.hasNext));
        })
        .catch((t) => {
          (console.error("[ContactIN] Network/adjacent error:", t),
            m(
              ContactINRestLog.i18n?.network_error || "Network error.",
              "error",
            ),
            l(u, !1));
        }));
  }
  function m(e, o) {
    if ("function" != typeof window.cinShowMessage) {
      var n = t("#contactin-rest-notice");
      (n
        .removeClass("notice-success notice-error")
        .addClass("error" === o ? "notice-error" : "notice-success"),
        n.text(e),
        n.stop(!0, !0).fadeIn(150).delay(3500).fadeOut(600));
    } else window.cinShowMessage(e, "error" === o ? "error" : "success");
  }
  (t(document).on(
    "click",
    "#contactin-prev-log, #contactin-next-log",
    function (e) {
      const o = t(this),
        n = o.is("button, input") && o.prop("disabled"),
        r = "true" === o.attr("aria-disabled");
      if (n || r || o.hasClass("is-disabled"))
        return (e.preventDefault(), e.stopImmediatePropagation(), !1);
    },
  ),
    t(document).on("click", ".contactin-view-log", function (e) {
      if ((e.preventDefault(), !p())) return;
      const o = t(this).data("id");
      if (!o) return void m("Invalid log ID.", "error");
      const r = t("#method-filter").val() || "all",
        a = t("#endpoint-filter").val() || "all",
        s = t("#http-code-filter").val() || "all",
        i = t("#validated-filter").val() || "all";
      (t("#contactin-rest-meta").empty(),
        t("#contactin-rest-headers").html(
          "<h4>Request Headers</h4><pre>Loading…</pre>",
        ),
        t("#contactin-rest-request").html(
          "<h4>Request Payload</h4><pre>Loading…</pre>",
        ),
        t("#contactin-rest-response").html(
          "<h4>Response Body</h4><pre>Loading…</pre>",
        ),
        cinAjax(
          n,
          g({ id: o, http_method: r, endpoint: a, http_code: s, validated: i }),
          void 0,
          void 0,
          ContactINRestLog,
        )
          .then((e) => {
            e && e.success
              ? (c(e.data),
                t("#contactin-rest-modal").addClass("is-active"),
                t("body").addClass("cin-modal-open"),
                d(!!e.data.hasPrev, !!e.data.hasNext))
              : (t("#contactin-rest-meta").html(
                  e?.data?.message ||
                    ContactINRestLog.i18n?.errorDetails ||
                    "Failed to load log details.",
                ),
                d(!!e.data?.hasPrev, !!e.data?.hasNext));
          })
          .catch((e) => {
            (console.error("[ContactIN] Network/view error:", e),
              t("#contactin-rest-meta").html(
                ContactINRestLog.i18n?.network_error || "Network error.",
              ),
              d(!1, !1));
          }));
    }),
    t(document).on("click", ".contactin-modal-backdrop", function () {
      (t(this).closest(".contactin-modal").removeClass("is-active"),
        t("body").removeClass("cin-modal-open"));
    }),
    t("#contactin-prev-log").on("click", () => u("prev")),
    t("#contactin-next-log").on("click", () => u("next")),
    t("#contactin-prune-btn, #contactin-prune-logs").on("click", function (t) {
      if ((t.preventDefault(), !p())) return;
      const e = ContactINRestLog.retention_days || 30,
        o =
          ContactINRestLog.i18n?.confirmPrune ||
          `This will permanently delete all REST API logs older than ${e} days. Proceed?`;
      confirm(o) &&
        cinAjax(a, g({}), void 0, void 0, ContactINRestLog)
          .then((t) => {
            m(
              t.data?.message || ContactINRestLog.i18n?.pruning || "Pruning…",
              t.success ? "success" : "error",
            );
          })
          .catch((t) => {
            (console.error("[ContactIN] Network/prune error:", t),
              m(
                ContactINRestLog.i18n?.errorPrune || "Error pruning logs.",
                "error",
              ));
          });
    }),
    t("#contactin-prune-rest-btn").on("click", function (n) {
      if ((n.preventDefault(), !p())) return;
      if (
        !confirm(
          ContactINRestLog.i18n?.confirmPrune ||
            "This will delete REST logs older than 30 days. Continue?",
        )
      )
        return;
      const r = t(this);
      (e(r, !0, ContactINRestLog.i18n?.working || "Working..."),
        t
          .post(ContactINRestLog.ajax_url, g({ action: a }))
          .done((t) => {
            t.success
              ? (m(t.data.message || "Logs pruned successfully.", "success"),
                e(r, !1),
                setTimeout(() => o(), 1500))
              : (m(t.data?.message || "Failed to prune logs.", "error"),
                e(r, !1));
          })
          .fail((t) => {
            (console.error("[ContactIN] Network/prune error:", t),
              m(
                ContactINRestLog.i18n?.network_error || "Network error.",
                "error",
              ),
              e(r, !1));
          }));
    }),
    t("#contactin-clear-rest-logs").on("click", function (n) {
      if ((n.preventDefault(), !p())) return;
      if (
        !confirm(
          ContactINRestLog.i18n?.confirmClear ||
            "This will permanently delete ALL REST API logs. This action cannot be undone. Continue?",
        )
      )
        return;
      const r = t(this);
      (e(r, !0, ContactINRestLog.i18n?.working || "Working..."),
        t
          .post(ContactINRestLog.ajax_url, g({ action: s }))
          .done((t) => {
            t.success
              ? (m(
                  t.data.message || "All logs cleared successfully.",
                  "success",
                ),
                e(r, !1),
                setTimeout(() => o(), 1500))
              : (m(t.data?.message || "Failed to clear logs.", "error"),
                e(r, !1));
          })
          .fail((t) => {
            (console.error("[ContactIN] Network/clear error:", t),
              m(
                ContactINRestLog.i18n?.network_error || "Network error.",
                "error",
              ),
              e(r, !1));
          }));
    }),
    t("#contactin-download-rest-csv").on("click", function (e) {
      if ((e.preventDefault(), !p())) return;
      t(this);
      const o =
          ContactINRestLog.ajax_url +
          "?action=contactinbox_download_rest_csv&nonce=" +
          encodeURIComponent(ContactINRestLog.nonce),
        n = {
          method: t("#method-filter").val(),
          endpoint: t("#endpoint-filter").val(),
          http_code: t("#http-filter").val(),
          validated: t("#validated-filter").val(),
        };
      if ("function" == typeof window.cinExportHelper) {
        const t =
          o +
          "&method=" +
          encodeURIComponent(n.method || "all") +
          "&endpoint=" +
          encodeURIComponent(n.endpoint || "all") +
          "&http_code=" +
          encodeURIComponent(n.http_code || "all") +
          "&validated=" +
          encodeURIComponent(n.validated || "all");
        window.cinExportHelper({
          infoAction: "contactinbox_rest_export_info",
          baseUrl: t,
        });
      } else window.location.href = o;
    }));
})(jQuery);
