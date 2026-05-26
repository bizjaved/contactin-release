!(function (e, n, t, o, i, a) {
  "use strict";
  if (e && e.registerBlockType) {
    var r = o.__,
      l = t.createElement,
      c = t.Fragment,
      s = t.useState,
      p = n.useBlockProps,
      d = n.InspectorControls,
      u = i.PanelBody,
      f = i.PanelRow,
      g = i.SelectControl,
      m = i.TextControl,
      h = i.TextareaControl,
      b = i.ToggleControl,
      x = i.Button,
      y = i.Notice,
      w = i.Spinner,
      v = i.Placeholder,
      _ = a,
      k = window.CinProfileCore,
      C = window.contactinBlock || {},
      S = k.profiles.slice(),
      P = Object.assign({}, k.profilesData),
      I = {};
    e.registerBlockType("contactin/contact-form", {
      apiVersion: 3,
      title: C.title || r("ContactIn Form", "contactin-pro"),
      description:
        C.description ||
        r("Drop a form profile anywhere on the page.", "contactin-pro"),
      icon: "email-alt",
      category: "widgets",
      keywords: [
        r("contact", "contactin-pro"),
        r("form", "contactin-pro"),
        r("gdpr", "contactin-pro"),
      ],
      supports: { html: !1, align: ["wide", "full"], multiple: !0 },
      attributes: { formId: { type: "string", default: "default" } },
      edit: function (e) {
        var n = e.attributes,
          t = e.setAttributes,
          o = p ? p() : {},
          i = e.clientId || "cin",
          a = s(S),
          C = a[0],
          O = a[1],
          A = s(P),
          B = A[0],
          R = A[1],
          j = s("idle"),
          N = j[0],
          E = j[1],
          L = s(F()),
          U = L[0],
          D = L[1],
          W = s("idle"),
          q = W[0],
          G = W[1],
          H = s(0),
          M = H[0],
          V = H[1],
          X = s(!!k.globalAttachmentEnabled),
          Y = X[0],
          Z = X[1],
          $ = s(!1),
          ee = $[0],
          ne = $[1];
        function te(e) {
          D(Object.assign({}, U, e));
        }
        function oe(e, n) {
          var t = Object.assign({}, U, e);
          (D(t),
            I[i] && clearTimeout(I[i]),
            G("saving"),
            (I[i] = setTimeout(
              function () {
                var e;
                ((e = t),
                  k.saveProfile(
                    e,
                    function (e) {
                      (O(e.profiles.slice()),
                        R(Object.assign({}, e.profilesData)),
                        V(function (e) {
                          return e + 1;
                        }),
                        G("saved"),
                        (I[i] = setTimeout(function () {
                          G("idle");
                        }, 2e3)));
                    },
                    function () {
                      G("error");
                    },
                  ));
              },
              n ? 600 : 0,
            )));
        }
        function ie() {
          var e = U.slug || k.slugify(U.label);
          U.label && e
            ? (te({ busy: !0, error: "" }),
              k.saveProfile(
                Object.assign({}, U, { slug: e }),
                function (n) {
                  (O(n.profiles.slice()),
                    R(Object.assign({}, n.profilesData)),
                    V(function (e) {
                      return e + 1;
                    }),
                    t({ formId: e }),
                    E("idle"),
                    D(F()));
                },
                function (e) {
                  te({
                    busy: !1,
                    error:
                      (e && e.data && e.data.message) ||
                      k.i18n.couldNotSave ||
                      r("Could not save profile.", "contactin-pro"),
                  });
                },
              ))
            : te({
                error:
                  k.i18n.nameRequired ||
                  r("Name is required.", "contactin-pro"),
              });
        }
        function ae() {
          var e = B[n.formId];
          e &&
            (G("idle"),
            D(
              (function (e, n) {
                return Object.assign(
                  { busy: !1, error: "" },
                  k.profileToForm(e, n),
                );
              })(n.formId, e),
            ),
            E("edit"));
        }
        function re() {
          (I[i] && (clearTimeout(I[i]), delete I[i]),
            E("idle"),
            D(F()),
            G("idle"));
        }
        var le,
          ce =
            (
              T(C).find(function (e) {
                return e.value === n.formId;
              }) || {}
            ).label || n.formId,
          se = !!B[n.formId];
        function pe(e) {
          var n = k.i18n,
            t = e
              ? function (e) {
                  oe(e, !1);
                }
              : te,
            o = e
              ? function (e) {
                  oe(e, !0);
                }
              : te;
          return l(
            c,
            null,
            l(m, {
              label: n.profileName || r("Profile name", "contactin-pro"),
              placeholder:
                n.namePlaceholder || r("e.g. Sales Enquiry", "contactin-pro"),
              value: U.label,
              onChange: function (n) {
                o({
                  label: n,
                  slug: e || U.slugEdited ? U.slug : k.slugify(n),
                });
              },
            }),
            e
              ? l(
                  "p",
                  {
                    style: {
                      fontSize: "11px",
                      color: "#757575",
                      margin: "-4px 0 12px",
                    },
                  },
                  (n.slugLabel || r("Slug (ID)", "contactin-pro")) + ": ",
                  l("code", null, U.slug),
                )
              : l(m, {
                  label: n.slugLabel || r("Slug (ID)", "contactin-pro"),
                  help: r(
                    "Auto-generated. Letters, numbers and hyphens only.",
                    "contactin-pro",
                  ),
                  value: U.slug || k.slugify(U.label),
                  onChange: function (e) {
                    te({
                      slug: e.toLowerCase().replace(/[^a-z0-9-]/g, ""),
                      slugEdited: !0,
                    });
                  },
                }),
            z(n.secFields || r("Fields", "contactin-pro")),
            l(b, {
              label: n.phoneField || r("Phone field", "contactin-pro"),
              checked: U.show_phone,
              onChange: function (e) {
                t({ show_phone: e, require_phone: !!e && U.require_phone });
              },
            }),
            U.show_phone &&
              l(b, {
                label: n.requirePhone || r("↳ Require phone", "contactin-pro"),
                checked: U.require_phone,
                onChange: function (e) {
                  t({ require_phone: e });
                },
              }),
            l(b, {
              label: n.salutation || r("Salutation dropdown", "contactin-pro"),
              checked: U.show_salutation,
              onChange: function (e) {
                t({ show_salutation: e });
              },
            }),
            l(b, {
              label: n.subjectField || r("Subject field", "contactin-pro"),
              checked: U.show_subject,
              onChange: function (e) {
                t({ show_subject: e });
              },
            }),
            Y
              ? l(b, {
                  label: ee
                    ? r("Checking…", "contactin-pro")
                    : n.fileAttachment || r("File attachment", "contactin-pro"),
                  checked: U.show_attachment,
                  disabled: ee,
                  onChange: function (e) {
                    e
                      ? (ne(!0),
                        k.checkGlobalAttachment(function (e) {
                          (ne(!1), Z(e), e && t({ show_attachment: !0 }));
                        }))
                      : t({ show_attachment: !1 });
                  },
                })
              : l(
                  "div",
                  { style: { marginBottom: "8px" } },
                  l(
                    "div",
                    {
                      style: {
                        display: "flex",
                        alignItems: "center",
                        gap: "6px",
                        fontSize: "13px",
                        color: "#aaa",
                        cursor: "not-allowed",
                      },
                    },
                    l("input", {
                      type: "checkbox",
                      disabled: !0,
                      readOnly: !0,
                      style: {
                        margin: "0 4px 0 0",
                        verticalAlign: "middle",
                        pointerEvents: "none",
                      },
                    }),
                    n.fileAttachment || r("File attachment", "contactin-pro"),
                  ),
                  l(
                    "div",
                    {
                      style: {
                        marginTop: "6px",
                        padding: "8px 10px",
                        background: "#fff3cd",
                        borderLeft: "3px solid #f0a500",
                        borderRadius: "2px",
                        fontSize: "11px",
                        lineHeight: "1.6",
                      },
                    },
                    n.attachGlobalOff ||
                      r(
                        "File attachment is available in ContactIn Pro.",
                        "contactin-pro",
                      ),
                    l(
                      "div",
                      {
                        style: {
                          marginTop: "6px",
                          display: "flex",
                          gap: "8px",
                          alignItems: "center",
                          flexWrap: "wrap",
                        },
                      },
                      l(
                        "button",
                        {
                          type: "button",
                          disabled: ee,
                          style: {
                            background: "none",
                            border: "1px solid #996800",
                            borderRadius: "3px",
                            color: "#996800",
                            fontSize: "11px",
                            padding: "2px 8px",
                            cursor: ee ? "wait" : "pointer",
                            fontWeight: 600,
                          },
                          onClick: function () {
                            (ne(!0),
                              k.checkGlobalAttachment(function (e) {
                                (ne(!1), Z(e));
                              }));
                          },
                        },
                        r(ee ? "Checking…" : "Check again", "contactin-pro"),
                      ),
                    ),
                  ),
                ),
            l(b, {
              label:
                n.privacyConsent ||
                r("Privacy consent checkbox", "contactin-pro"),
              checked: U.show_consent,
              onChange: function (e) {
                t({ show_consent: e, consent_text: e ? U.consent_text : "" });
              },
            }),
            U.show_consent &&
              l(h, {
                label: n.consentText || r("Consent text", "contactin-pro"),
                placeholder:
                  n.consentPlaceholder ||
                  r("Leave empty to use global setting.", "contactin-pro"),
                value: U.consent_text,
                rows: 2,
                onChange: function (e) {
                  o({ consent_text: e });
                },
              }),
            z(n.secBehaviour || r("Behaviour", "contactin-pro")),
            l(g, {
              label: n.recaptcha || r("reCAPTCHA", "contactin-pro"),
              value: U.recaptcha,
              options: k.AUTO_ON_OFF,
              onChange: function (e) {
                t({ recaptcha: e });
              },
            }),
            l(g, {
              label: n.confetti || r("Confetti on success", "contactin-pro"),
              value: U.confetti,
              options: k.AUTO_ON_OFF,
              onChange: function (e) {
                t({ confetti: e });
              },
            }),
            l(h, {
              label: n.successMsg || r("Success message", "contactin-pro"),
              placeholder:
                n.globalFallback ||
                r("Leave empty to use global setting.", "contactin-pro"),
              value: U.success_message,
              rows: 2,
              onChange: function (e) {
                o({ success_message: e });
              },
            }),
            z(n.secRouting || r("Routing", "contactin-pro")),
            l(m, {
              label: n.notifyEmail || r("Notification email", "contactin-pro"),
              help:
                n.emailPlaceholder ||
                r(
                  "Leave empty to use the global admin email.",
                  "contactin-pro",
                ),
              type: "email",
              value: U.notify_email,
              onChange: function (e) {
                o({ notify_email: e });
              },
            }),
          );
        }
        return l(
          c,
          null,
          l(
            d,
            null,
            l(
              u,
              { title: r("Form Profile", "contactin-pro"), initialOpen: !0 },
              "idle" === N &&
                l(
                  c,
                  null,
                  l(
                    y,
                    {
                      status: "info",
                      isDismissible: !1,
                      __nextHasNoMarginBottom: !0,
                      className: "cin-block-notice",
                    },
                    k.i18n.profileInfo ||
                      r(
                        "Profiles define fields, routing, and behaviour. All submissions share one inbox.",
                        "contactin-pro",
                      ),
                  ),
                  l(
                    "div",
                    { style: { marginTop: "12px" } },
                    l(g, {
                      label: r("Active profile", "contactin-pro"),
                      value: n.formId,
                      options: T(C),
                      onChange: function (e) {
                        t({ formId: e });
                      },
                    }),
                    l(
                      "div",
                      {
                        style: {
                          display: "flex",
                          alignItems: "center",
                          gap: "12px",
                          marginTop: "-4px",
                          marginBottom: "8px",
                          fontSize: "12px",
                        },
                      },
                      se &&
                        l(
                          "a",
                          {
                            href: "#",
                            onClick: function (e) {
                              (e.preventDefault(), ae());
                            },
                            style: {
                              textDecoration: "none",
                              cursor: "pointer",
                            },
                          },
                          "✏ " +
                            (k.i18n.editProfile ||
                              r("Edit this profile", "contactin-pro")),
                        ),
                      l(
                        "a",
                        {
                          href: k.settingsUrl,
                          target: "_blank",
                          rel: "noopener",
                        },
                        (k.i18n.allProfiles ||
                          r("All profiles", "contactin-pro")) + " ↗",
                      ),
                    ),
                  ),
                  l("hr", { style: { margin: "12px 0" } }),
                  l(
                    f,
                    null,
                    l(
                      x,
                      {
                        variant: "secondary",
                        icon: "plus-alt2",
                        onClick: function () {
                          (D(F()), E("create"));
                        },
                      },
                      k.i18n.createNew ||
                        r("Create new profile", "contactin-pro"),
                    ),
                  ),
                ),
              "edit" === N &&
                ((le = k.i18n),
                l(
                  "div",
                  {
                    style: {
                      background: "#f6f7f7",
                      border: "1px solid #c3c4c7",
                      borderRadius: "4px",
                      padding: "12px",
                      marginTop: "8px",
                    },
                  },
                  l(
                    "div",
                    {
                      style: {
                        display: "flex",
                        justifyContent: "space-between",
                        alignItems: "center",
                        marginBottom: "10px",
                      },
                    },
                    l(
                      "p",
                      {
                        style: { margin: 0, fontWeight: 700, fontSize: "12px" },
                      },
                      (le.editPrefix || r("Edit: ", "contactin-pro")) + U.label,
                    ),
                    (function () {
                      var e = k.i18n;
                      return "saving" === q
                        ? l(
                            "span",
                            {
                              style: {
                                fontSize: "11px",
                                color: "#757575",
                                display: "flex",
                                alignItems: "center",
                                gap: "4px",
                              },
                            },
                            l(w, {
                              style: {
                                width: "14px",
                                height: "14px",
                                margin: 0,
                              },
                            }),
                            e.saving || r("Saving…", "contactin-pro"),
                          )
                        : "saved" === q
                          ? l(
                              "span",
                              { style: { fontSize: "11px", color: "#008a00" } },
                              "✓ " + (e.saved || r("Saved", "contactin-pro")),
                            )
                          : "error" === q
                            ? l(
                                "span",
                                {
                                  style: { fontSize: "11px", color: "#cc1818" },
                                },
                                "✗ " +
                                  (e.saveFailed ||
                                    r("Save failed", "contactin-pro")),
                              )
                            : l("span", null);
                    })(),
                  ),
                  pe(!0),
                  l(
                    "div",
                    { style: { marginTop: "12px" } },
                    l(
                      x,
                      { variant: "secondary", onClick: re },
                      le.done || r("← Done", "contactin-pro"),
                    ),
                  ),
                )),
              "create" === N &&
                (function () {
                  var e = k.i18n;
                  return l(
                    "div",
                    {
                      style: {
                        background: "#f6f7f7",
                        border: "1px solid #c3c4c7",
                        borderRadius: "4px",
                        padding: "12px",
                        marginTop: "8px",
                      },
                    },
                    l(
                      "p",
                      {
                        style: {
                          margin: "0 0 10px",
                          fontWeight: 700,
                          fontSize: "12px",
                        },
                      },
                      e.newProfile || r("New profile", "contactin-pro"),
                    ),
                    pe(!1),
                    U.error &&
                      l(
                        "p",
                        {
                          style: {
                            color: "#cc1818",
                            fontSize: "12px",
                            margin: "4px 0 8px",
                          },
                        },
                        U.error,
                      ),
                    l(
                      f,
                      null,
                      l(
                        x,
                        {
                          variant: "primary",
                          isBusy: U.busy,
                          disabled: U.busy,
                          onClick: ie,
                        },
                        e.createApply || r("Create & apply", "contactin-pro"),
                      ),
                      l(
                        x,
                        {
                          variant: "tertiary",
                          style: { marginLeft: "8px" },
                          onClick: function () {
                            (E("idle"), D(F()));
                          },
                        },
                        e.cancel || r("Cancel", "contactin-pro"),
                      ),
                    ),
                  );
                })(),
            ),
          ),
          l(
            "div",
            o,
            l(_, {
              key: "ssr-" + M + "-" + n.formId,
              block: "contactin/contact-form",
              attributes: n,
              LoadingPlaceholder: function () {
                return l(
                  v,
                  {
                    icon: "email-alt",
                    label: r("ContactIn Form", "contactin-pro"),
                  },
                  l(w, null),
                );
              },
              EmptyPlaceholder: function () {
                return l(
                  "div",
                  { className: "contactin-gutenberg-block" },
                  l("div", { className: "contactin-block-icon" }, "✉"),
                  l("h4", null, r("ContactIn Form", "contactin-pro")),
                  l("p", null, ce),
                  l(
                    "p",
                    { className: "contactin-block-hint" },
                    r("Select a profile in the panel →", "contactin-pro"),
                  ),
                );
              },
            }),
          ),
        );
      },
      save: function () {
        return null;
      },
    });
  }
  function T(e) {
    return e.map(function (e) {
      return { value: e.slug, label: e.label };
    });
  }
  function F() {
    return Object.assign({ busy: !1, error: "" }, k.emptyForm);
  }
  function z(e) {
    return l(
      "p",
      {
        style: {
          margin: "12px 0 2px",
          fontWeight: 600,
          fontSize: "11px",
          textTransform: "uppercase",
          letterSpacing: "0.05em",
          color: "#757575",
        },
      },
      e,
    );
  }
})(
  window.wp && window.wp.blocks,
  window.wp && window.wp.blockEditor,
  window.wp && window.wp.element,
  window.wp && window.wp.i18n,
  window.wp && window.wp.components,
  window.wp && window.wp.serverSideRender,
);
