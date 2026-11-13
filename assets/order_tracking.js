/* Account-protected order tracking refreshes every 15 seconds while visible.
 * The PHP endpoint authorises each request against the signed-in customer.
 */
(() => {
  "use strict";
  const root = document.getElementById("order-tracking");
  if (!root) return;
  const token = root.dataset.orderToken;
  if (!/^[a-f0-9]{32}$/.test(token || "")) return;
  const state = document.getElementById("order-status-live");
  const timeline = document.getElementById("tracking-list");
  const note = document.getElementById("tracking-last-checked");
  if (!state || !timeline || !note) return;

  async function refresh() {
    if (document.hidden) return;
    try {
      const response = await fetch(
        "api/order_status.php?order=" + encodeURIComponent(token),
        { cache: "no-store", headers: { Accept: "application/json" } }
      );
      if (!response.ok) return;
      const data = await response.json();
      if (!["placed","processing","dispatched","delivered","cancelled"].includes(data.status)
          || !Array.isArray(data.events)) return;
      state.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
      timeline.replaceChildren();
      for (const event of data.events.slice(0, 100)) {
        const li = document.createElement("li");
        const strong = document.createElement("strong");
        strong.textContent = String(event.status || "").slice(0, 24);
        li.appendChild(strong);
        li.appendChild(document.createTextNode(" — " + String(event.note || "").slice(0, 250)));
        const small = document.createElement("small");
        small.textContent = String(event.changed_at || "");
        li.appendChild(small);
        timeline.appendChild(li);
      }
      note.textContent = "Tracking updates automatically every 15 seconds while this page is open.";
    } catch (_) {
      note.textContent = "Tracking refresh unavailable. Your saved order is unaffected.";
    }
  }
  refresh();
  window.setInterval(refresh, 15000);
})();
