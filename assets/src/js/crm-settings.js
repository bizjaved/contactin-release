!(function ($) {
  "use strict";

  function escapeHtml(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function showUpgradeModal() {
    var l10n = window.cinCRMSettings || {};
    var title = l10n.upgradeTitle || "Unlock Premium Features";
    var message =
      l10n.upgradeMessage ||
      "CRM integration controls are available in ContactIn Pro.";
    var cta = l10n.upgradeCta || "Upgrade to Pro";
    var dismiss = l10n.upgradeDismiss || "Maybe later";
    var upgradeUrl = l10n.upgradeUrl || "#";

    $("#cin-upgrade-export-modal").remove();

    var html =
      '<div id="cin-upgrade-export-modal" class="cin-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="cin-upgrade-export-title" style="position:fixed;inset:0;display:flex;align-items:center;justify-content:center;padding:24px;background:rgba(0,0,0,0.35);z-index:2147483647;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .2s ease;"><div class="cin-confirm-modal" style="margin:0;max-width:min(560px,calc(100vw - 32px));max-height:calc(100vh - 48px);overflow:auto;"><h3 id="cin-upgrade-export-title">' +
      escapeHtml(title) +
      '</h3><div class="cin-confirm-details"><p>' +
      escapeHtml(message) +
      '</p></div><div class="cin-confirm-actions"><button type="button" class="button cin-upgrade-export-dismiss">' +
      escapeHtml(dismiss) +
      '</button><a class="button button-primary" href="' +
      upgradeUrl +
      '">' +
      escapeHtml(cta) +
      "</a></div></div></div>";

    $("body").append(html);
    var modal = $("#cin-upgrade-export-modal");

    function closeModal() {
      modal.css({ opacity: "0", visibility: "hidden", pointerEvents: "none" });
      setTimeout(function () {
        modal.remove();
      }, 200);
    }

    setTimeout(function () {
      modal.css({ opacity: "1", visibility: "visible", pointerEvents: "auto" });
    }, 10);

    modal.on("click", ".cin-upgrade-export-dismiss", function (event) {
      event.preventDefault();
      closeModal();
    });

    modal.on("click", function (event) {
      if ($(event.target).is("#cin-upgrade-export-modal")) {
        closeModal();
      }
    });

    $(document).one("keyup.cinCRMUpgrade", function (event) {
      if (event.key === "Escape") {
        closeModal();
      }
    });
  }

  $(document).ready(function () {
    if (!window.cinCRMSettings || !window.cinCRMSettings.disabledMode) {
      return;
    }

    // Disable CRM settings form controls in UI-only mode.
    $("#crm-settings-form")
      .find("input, select, textarea, button")
      .each(function () {
        var $el = $(this);
        if ($el.is("button") || $el.is("input[type='submit']")) {
          $el
            .prop("disabled", false)
            .attr("aria-disabled", "true")
            .attr("data-upgrade-only", "1")
            .addClass("disabled");
          return;
        }

        $el.prop("disabled", true).attr("aria-disabled", "true");
      });

    $("[data-cin-help-open]").each(function () {
      var $el = $(this);
      $el
        .removeAttr("data-cin-help-open")
        .attr("data-upgrade-only", "1")
        .attr("aria-disabled", "true")
        .addClass("disabled");
    });

    $("a.nav-tab").removeAttr("href");

    $(document).on("click", "[data-upgrade-only='1']", function (event) {
      event.preventDefault();
      event.stopImmediatePropagation();
      showUpgradeModal();
    });

    // Block form submission defensively.
    $(document).on("submit", "#crm-settings-form", function (event) {
      event.preventDefault();
      showUpgradeModal();
    });
  });
})(jQuery);
