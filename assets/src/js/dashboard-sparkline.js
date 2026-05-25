const sparklineCharts = {},
  DashboardSparkline = {
    renderSparkline(e, t, r) {
      const n = document.getElementById(e);
      if (!n) return;
      if (!t || !Array.isArray(t) || 0 === t.length) return;
      if ("undefined" == typeof Chart) return;
      sparklineCharts[e] &&
        (sparklineCharts[e].destroy(), (sparklineCharts[e] = null));
      const a = parseInt(n.getAttribute("height"), 10),
        i = Number.isFinite(a) ? a : 40;
      ((n.height = i),
        (n.style.height = `${i}px`),
        (n.style.maxHeight = `${i}px`));
      const s = t.map((e) => (e.date || "").slice(5)),
        d = t.map((e) => parseInt(e.count || 0, 10));
      sparklineCharts[e] = new Chart(n.getContext("2d"), {
        type: "line",
        data: {
          labels: s,
          datasets: [
            {
              data: d,
              borderColor: r,
              backgroundColor: "rgba(0,0,0,0)",
              borderWidth: 2,
              tension: 0.35,
              pointRadius: 0,
            },
          ],
        },
        options: {
          responsive: !0,
          maintainAspectRatio: !1,
          plugins: { legend: { display: !1 }, tooltip: { enabled: !1 } },
          scales: {
            x: { display: !1, grid: { display: !1 }, border: { display: !1 } },
            y: { display: !1, grid: { display: !1 }, border: { display: !1 } },
          },
        },
      });
    },
    renderAll(e) {
      e &&
        "object" == typeof e &&
        (this.renderSparkline("spark-email-queue", e.email || [], "#f0ad4e"),
        this.renderSparkline("spark-crm-queue", e.crm || [], "#2196F3"));
    },
    init() {
      "undefined" != typeof contactinSparklineTrends &&
        this.renderAll(contactinSparklineTrends);
    },
  };
document.addEventListener("DOMContentLoaded", () => {
  DashboardSparkline.init();
});
