const DashboardTabs = {
  storageKey: "contactin-analytics-active-tab",
  defaultTab: "submissions",
  init() {
    const t = Array.from(document.querySelectorAll(".tab-button")),
      a = Array.from(document.querySelectorAll(".tab-pane"));
    if (0 === t.length || 0 === a.length) return;
    const e = window.localStorage.getItem(this.storageKey) || this.defaultTab;
    (this.activateTab(e, t, a),
      t.forEach((e) => {
        e.addEventListener("click", () => {
          const o = e.dataset.tab;
          (this.activateTab(o, t, a),
            window.localStorage.setItem(this.storageKey, o));
        });
      }),
      window.localStorage.getItem(this.storageKey) ||
        window.localStorage.setItem(this.storageKey, this.defaultTab));
  },
  activateTab(t, a, e) {
    (a.forEach((a) => a.classList.toggle("active", a.dataset.tab === t)),
      e.forEach((a) => a.classList.toggle("active", a.dataset.tab === t)));
  },
};
document.addEventListener("DOMContentLoaded", () => DashboardTabs.init());
