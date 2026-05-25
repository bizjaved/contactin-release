!(function (t) {
  "use strict";
  const n = window.cinSFAttachmentSettings || {};
  function e() {
    const n = t("#attachment-sync-enable"),
      e = t("#max-attachment-size"),
      i = t("#attachment-visibility");
    n.is(":checked")
      ? (e.removeAttr("disabled"), i.removeAttr("disabled"))
      : (e.attr("disabled", "disabled"), i.attr("disabled", "disabled"));
  }
  (t(function () {
    !(function () {
      const n = t("#attachment-sync-enable");
      t(".sf-attachment-field");
      n.length &&
        (e(),
        n.on("change", function () {
          e();
        }));
    })();
  }),
    (window.cinSFAttachmentSettings = n));
})(jQuery);
