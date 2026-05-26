!(function (a) {
  function t() {
    var t = a("#contactin-email-log-form");
    if (t.length) {
      t.serialize();
      a.post(
        ContactINEmailLog.ajax_url,
        {
          action: "contactinbox_get_email_logs",
          nonce: ContactINEmailLog.nonce,
          paged: t.find('input[name="paged"]').val() || 1,
          per_page: t.find('select[name="per_page"]').val() || 20,
          status: a("#status-filter").val() || "all",
          orderby: t.find('input[name="orderby"]').val() || "created_at",
          order: t.find('input[name="order"]').val() || "DESC",
        },
        function (t) {
          t &&
            t.success &&
            t.data &&
            t.data.html &&
            a(".wp-list-table-container").html(t.data.html);
        },
      );
    }
  }
  function n(a, t, n) {
    a &&
      a.length &&
      (t
        ? (a.addClass("is-busy").prop("disabled", !0),
          n && a.data("orig-label", a.text()).text(n))
        : (a.removeClass("is-busy").prop("disabled", !1),
          a.data("orig-label") &&
            (a.text(a.data("orig-label")), a.removeData("orig-label"))));
  }
  function o(t) {
    (a("#contactin-email-meta")
      .html(
        (function (a) {
          var t = "";
          return (
            (t +=
              "<p><strong>Timestamp:</strong> " + (a.timestamp || "") + "</p>"),
            (t +=
              "<p><strong>Recipient:</strong> " + (a.recipient || "") + "</p>"),
            (t += "<p><strong>Subject:</strong> " + (a.subject || "") + "</p>"),
            (t += "<p><strong>Status:</strong> " + (a.status || "") + "</p>"),
            a.error && (t += "<p><strong>Error:</strong> " + a.error + "</p>"),
            t
          );
        })(t),
      )
      .data("log-id", t.id),
      a("#contactin-email-headers").text(
        t.headers || ContactINEmailLog.i18n.noDetails || "No details",
      ),
      a("#contactin-email-body").text(
        t.body || ContactINEmailLog.i18n.noDetails || "No details",
      ));
  }
  function e(t, n) {
    (a("#contactin-prev-log").prop("disabled", !t),
      a("#contactin-next-log").prop("disabled", !n));
  }
  function i(t) {
    var n = a("#contactin-email-meta").data("log-id");
    n &&
      a
        .post(ContactINEmailLog.ajax_url, {
          action: "contactin_get_adjacent_email_log",
          direction: t,
          current_id: n,
          nonce: ContactINEmailLog.nonce,
        })
        .done(function (a) {
          if (a && a.success) {
            var n = a.data;
            (o(n),
              void 0 !== n.hasPrev && void 0 !== n.hasNext
                ? e(!!n.hasPrev, !!n.hasNext)
                : e(!0, !0));
          } else e("prev" !== t, "next" !== t);
        })
        .fail(function () {
          cinShowMessage(ContactINEmailLog.i18n.network_error, "error");
        });
  }
  (a("#contactin-prune-email-btn").on("click", function (o) {
    o.preventDefault();
    var e = a(this),
      i = ContactINEmailLog.retention_days || 90,
      c =
        ContactINEmailLog.i18n.confirmPrune ||
        "This will permanently delete all email logs older than " +
          i +
          " days. This cannot be undone. Proceed?";
    confirm(c) &&
      (n(e, !0, ContactINEmailLog.i18n?.working || "Working..."),
      a
        .post(ContactINEmailLog.ajax_url, {
          action: "contactin_prune_email_logs",
          nonce: ContactINEmailLog.nonce,
        })
        .done(function (a) {
          a && a.success
            ? (cinShowMessage(
                a.data && a.data.message
                  ? a.data.message
                  : ContactINEmailLog.i18n.pruning,
                "success",
              ),
              n(e, !1),
              setTimeout(function () {
                t();
              }, 1500))
            : (cinShowMessage(
                a.data && a.data.message
                  ? a.data.message
                  : ContactINEmailLog.i18n.errorPrune,
                "error",
              ),
              n(e, !1));
        })
        .fail(function (a) {
          (cinShowMessage(
            ContactINEmailLog.i18n.network_error + " (" + a.status + ")",
            "error",
          ),
            n(e, !1));
        }));
  }),
    a("#contactin-clear-email-logs").on("click", function (o) {
      o.preventDefault();
      var e =
        ContactINEmailLog.i18n.confirmClearAll ||
        "Are you sure you want to CLEAR ALL email logs? This will permanently delete all email logs and cannot be undone.";
      if (confirm(e)) {
        var i = a(this);
        (n(i, !0, ContactINEmailLog.i18n?.working || "Working..."),
          a
            .ajax({
              url: ContactINEmailLog.ajax_url,
              method: "POST",
              data: {
                action: "contactin_email_clear_all_logs",
                _ajax_nonce: ContactINEmailLog.clear_all_nonce,
              },
              dataType: "json",
            })
            .done(function (a) {
              a && a.success
                ? (cinShowMessage(
                    a.data && a.data.message
                      ? a.data.message
                      : "All email logs cleared.",
                    "success",
                  ),
                  n(i, !1),
                  setTimeout(function () {
                    t();
                  }, 1500))
                : (cinShowMessage(
                    a.data && a.data.message
                      ? a.data.message
                      : "Failed to clear email logs.",
                    "error",
                  ),
                  n(i, !1));
            })
            .fail(function (a) {
              (cinShowMessage(
                ContactINEmailLog.i18n.network_error + " (" + a.status + ")",
                "error",
              ),
                n(i, !1));
            }));
      }
    }),
    a(document).on("click", ".cin-download-csv", function (t) {
      t.preventDefault();
      t.stopImmediatePropagation();
      "function" == typeof window.cinShowMessage &&
        window.cinShowMessage(
          "CSV export is available in ContactIn Pro.",
          "info",
        );
    }),
    a(document).on("click", ".contactin-view-email", function () {
      var t = a(this).data("id");
      t &&
        (a("#contactin-email-meta").empty(),
        a("#contactin-email-headers").text(
          ContactINEmailLog.i18n.loadingHeaders || "Loading headers…",
        ),
        a("#contactin-email-body").text(
          ContactINEmailLog.i18n.loadingBody || "Loading body…",
        ),
        a
          .post(ContactINEmailLog.ajax_url, {
            action: "contactin_get_email_log",
            id: t,
            nonce: ContactINEmailLog.nonce,
          })
          .done(function (t) {
            if (t && t.success) {
              var n = t.data;
              (o(n),
                a("#contactin-email-modal").addClass("is-active"),
                void 0 !== n.hasPrev && void 0 !== n.hasNext
                  ? e(!!n.hasPrev, !!n.hasNext)
                  : ((i = n.id),
                    (c = a.post(ContactINEmailLog.ajax_url, {
                      action: "contactin_get_adjacent_email_log",
                      direction: "prev",
                      current_id: i,
                      nonce: ContactINEmailLog.nonce,
                    })),
                    (l = a.post(ContactINEmailLog.ajax_url, {
                      action: "contactin_get_adjacent_email_log",
                      direction: "next",
                      current_id: i,
                      nonce: ContactINEmailLog.nonce,
                    })),
                    a
                      .when(c, l)
                      .done(function (a, t) {
                        e(
                          a[0] && !0 === a[0].success,
                          t[0] && !0 === t[0].success,
                        );
                      })
                      .fail(function () {
                        e(!0, !0);
                      })));
            } else
              a("#contactin-email-meta").html(
                t.data && t.data.message
                  ? t.data.message
                  : ContactINEmailLog.i18n.errorDetails,
              );
            var i, c, l;
          })
          .fail(function () {
            a("#contactin-email-meta").html(
              ContactINEmailLog.i18n.network_error,
            );
          }));
    }),
    a(document).on("click", ".contactin-modal-backdrop", function () {
      a("#contactin-email-modal").removeClass("is-active");
    }),
    a("#contactin-prev-log").on("click", function () {
      i("prev");
    }),
    a("#contactin-next-log").on("click", function () {
      i("next");
    }),
    a("#filter-submit").on("click", function (t) {
      (t.preventDefault(),
        a
          .post(ContactINEmailLog.ajax_url, {
            action: "contactin_get_email_logs",
            nonce: ContactINEmailLog.nonce,
            status: a("#status-filter").val(),
            orderby: a('input[name="orderby"]').val() || "created_at",
            order: a('input[name="order"]').val() || "DESC",
            limit: 20,
            offset: 0,
          })
          .done(function (t) {
            var n = a("#the-list");
            if ((n.empty(), t && t.success)) {
              var o = t.data.rows || [];
              0 === o.length
                ? n.append(
                    '<tr class="no-items"><td class="colspanchange" colspan="5">' +
                      (t.data.message || "No email logs found.") +
                      "</td></tr>",
                  )
                : o.forEach(function (a) {
                    n.append(
                      "<tr><td>" +
                        (a.created_at || "") +
                        "</td><td>" +
                        (a.recipient || "") +
                        "</td><td>" +
                        (a.subject || "") +
                        "</td><td>" +
                        (a.status || "") +
                        "</td><td>" +
                        (a.error_message || "") +
                        "</td></tr>",
                    );
                  });
            } else
              n.append(
                '<tr class="no-items"><td class="colspanchange" colspan="5">' +
                  ((t.data && t.data.message) || "Failed to load logs.") +
                  "</td></tr>",
              );
          })
          .fail(function (t) {
            a("#the-list")
              .empty()
              .append(
                '<tr class="no-items"><td class="colspanchange" colspan="5">' +
                  ContactINEmailLog.i18n.network_error +
                  " (" +
                  t.status +
                  ")</td></tr>",
              );
          }));
    }),
    a(document).on("change", ".cin-per-page-select", function () {
      var t = new URL(window.location.href),
        n = a(this).val();
      (t.searchParams.set("per_page", n),
        t.searchParams.set("paged", "1"),
        (window.location.href = t.toString()));
    }));
})(jQuery);
