/* Starbound Books — load stock-based suggestions through the same-origin PHP proxy.
 * The proxy makes a server-side HTTPS request to a separate Python Azure Function.
 * DOM nodes are constructed with textContent; no untrusted HTML is inserted.
 */
(() => {
  "use strict";
  const selector = document.getElementById("recommendation-genre");
  const results = document.getElementById("recommendation-results");
  const status = document.getElementById("recommendation-status");
  if (!selector || !results || !status) return;
  let activeRequest;

  function node(tag, className, content) {
    const element = document.createElement(tag);
    if (className) element.className = className;
    if (content !== undefined) element.textContent = String(content);
    return element;
  }

  function makeCard(book) {
    const card = node("article", "recommendation-card");
    const cover = node("div", "recommendation-cover");
    const title = String(book.title || "Untitled").slice(0, 120);
    const author = String(book.author || "").slice(0, 120);
    const image = String(book.cover_url || "");
    if (/^https:\/\/covers\.openlibrary\.org\/b\/isbn\/\d{13}-[SML]\.jpg(?:\?default=false)?$/.test(image)) {
      const img = document.createElement("img");
      img.src = image;
      img.alt = "Book cover: " + title;
      img.loading = "lazy";
      img.referrerPolicy = "no-referrer";
      img.addEventListener("error", () => {
        img.remove();
        cover.appendChild(node("span", "recommendation-cover-fallback", "✦"));
      });
      cover.appendChild(img);
    } else {
      cover.appendChild(node("span", "recommendation-cover-fallback", "✦"));
    }
    card.appendChild(cover);

    const info = node("div", "recommendation-info");
    const badge = node("span", "recommendation-genre", String(book.genre || selector.value));
    info.appendChild(badge);
    const heading = node("h3", "", title);
    info.appendChild(heading);
    info.appendChild(node("p", "recommendation-author", "by " + author));

    const price = Number(book.price_gbp);
    if (Number.isFinite(price) && price >= 0) {
      info.appendChild(node("strong", "recommendation-price",
        new Intl.NumberFormat("en-GB", { style: "currency", currency: "GBP" }).format(price)));
    }
    const link = node("a", "recommendation-link", "Find this book");
    link.href = "catalog.php?q=" + encodeURIComponent(title);
    info.appendChild(link);
    card.appendChild(info);
    return card;
  }

  async function loadRecommendations() {
    if (activeRequest) activeRequest.abort();
    activeRequest = new AbortController();
    results.replaceChildren();
    status.textContent = "Finding books in the " + selector.value + " collection…";
    try {
      const response = await fetch(
        "api/recommendations-proxy.php?genre=" + encodeURIComponent(selector.value),
        { method: "GET", signal: activeRequest.signal, headers: { Accept: "application/json" } }
      );
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || "Recommendations are unavailable.");
      if (!Array.isArray(data.recommendations) || data.recommendations.length === 0) {
        status.textContent = "No in-stock recommendations for this genre yet.";
        return;
      }
      status.textContent = "Popular picks from our available stock (not personalised).";
      for (const book of data.recommendations) results.appendChild(makeCard(book));
    } catch (error) {
      if (error.name === "AbortError") return;
      status.textContent = error.message || "Unable to load recommendations right now.";
    }
  }

  selector.addEventListener("change", loadRecommendations);
  loadRecommendations();
})();
