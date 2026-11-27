/* US VESTA Admin – UX layer (works on top of dashboard.js; does not replace it). */
(function () {
  "use strict";

  var META = {
    dashboard:          { g: "Overview",   t: "Dashboard",              i: "fa-gauge-high",        d: "Today's snapshot: what needs your attention, sales and recent activity." },
    analytics:          { g: "Overview",   t: "Analytics",              i: "fa-chart-line",        d: "Sales trends, top products and business performance." },
    products:           { g: "Catalog",    t: "Products",               i: "fa-shirt",             d: "Add, edit or remove products, prices, sizes, colours and photos." },
    categories:         { g: "Catalog",    t: "Categories",             i: "fa-layer-group",       d: "Top-level groups such as T-Shirts or Shirts." },
    subcategories:      { g: "Catalog",    t: "Sub Categories",         i: "fa-sitemap",           d: "Collections inside a category; each one gets its own collection page." },
    orders:             { g: "Sales",      t: "Orders",                 i: "fa-bag-shopping",      d: "Every order. Update its status, view details or remove test orders." },
    returns:            { g: "Sales",      t: "Returns & Cancellations", i: "fa-rotate-left",      d: "Return and cancellation requests raised by customers." },
    abandonedcheckouts: { g: "Sales",      t: "Abandoned Checkouts",    i: "fa-cart-arrow-down",   d: "Shoppers who filled in billing details but did not finish paying." },
    homepage_editor:    { g: "Storefront", t: "Homepage Editor",        i: "fa-wand-magic-sparkles", d: "Change the homepage, header, menu, footer and colours. Click Save to publish." },
    banners:            { g: "Storefront", t: "Carousel Banners",       i: "fa-images",            d: "Slider images shown at the top of pages. Choose which pages show each banner." },
    Coordinatorsco:     { g: "Storefront", t: "Coordinators (SEO text)", i: "fa-align-left",       d: "Text blocks shown on collection pages, useful for SEO." },
    aplus:              { g: "Storefront", t: "A+ Listings",            i: "fa-star-of-life",      d: "Large images shown on every product page." },
    instagram:          { g: "Storefront", t: "Instagram Feed",         i: "fa-camera-retro",      d: "Pictures shown in the Instagram strip on the homepage." },
    blogs:              { g: "Storefront", t: "Blog",                   i: "fa-pen-nib",           d: "Write blog posts and manage blog categories." },
    Reviews:            { g: "Engagement", t: "Reviews",                i: "fa-star",              d: "Customer reviews. Switch a review on to show it on the product page." },
    testimonials:       { g: "Engagement", t: "Testimonials",           i: "fa-quote-left",        d: "Client quotes shown on the homepage." },
    trustedclients:     { g: "Engagement", t: "Trusted Clients",        i: "fa-handshake",         d: "Client logos shown in the \"trusted by\" strip." },
    contacts:           { g: "Engagement", t: "Contact Messages",       i: "fa-envelope-open-text", d: "Messages sent through the Contact Us form." },
    apikeys:            { g: "Settings",   t: "API Keys",               i: "fa-key",               d: "Keys that let partner websites read your product catalog." }
  };
  var ALIASES = { "paymentgateway": "apikeys" };

  function $(s, r) { return (r || document).querySelector(s); }
  function $all(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }

  function toast(msg) {
    var t = $(".av-toast");
    if (!t) { t = document.createElement("div"); t.className = "av-toast"; document.body.appendChild(t); }
    t.textContent = msg; t.classList.add("show");
    clearTimeout(t._h); t._h = setTimeout(function () { t.classList.remove("show"); }, 1800);
  }
  window.avToast = toast;

  window.avCopy = function (text) {
    var done = function () { toast("Copied to clipboard"); };
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(text).then(done, function () { fallback(); });
    else fallback();
    function fallback() { var a = document.createElement("textarea"); a.value = text; document.body.appendChild(a); a.select(); try { document.execCommand("copy"); done(); } catch (e) {} a.remove(); }
  };

  /* ---------------- section headers: icon + help line */
  function decorateHeaders() {
    Object.keys(META).forEach(function (key) {
      var sec = document.getElementById(key + "-section");
      if (!sec) return;
      var h = $(".page-header h1, .page-header h2", sec);
      if (!h || h.dataset.avDone) return;
      h.dataset.avDone = "1";
      var m = META[key];
      var title = h.textContent.trim();
      if (key === "subcategories") title = "Sub Categories";
      h.innerHTML = "";
      var ico = document.createElement("span"); ico.className = "av-h-icon"; ico.innerHTML = '<i class="fas ' + m.i + '"></i>';
      var wrap = document.createElement("span"); wrap.className = "av-h-wrap";
      var tt = document.createElement("span"); tt.textContent = title || m.t;
      wrap.appendChild(tt);
      if (key !== "homepage_editor") { var sub = document.createElement("span"); sub.className = "av-h-sub"; sub.textContent = m.d; wrap.appendChild(sub); }
      h.appendChild(ico); h.appendChild(wrap);
    });
  }

  /* ---------------- top bar crumb + URL hash */
  function setCrumb(key) {
    var m = META[key] || META.dashboard;
    var p = $(".av-crumb-path"), t = $(".av-crumb-title");
    if (p) p.innerHTML = '<span class="av-crumb-home">US VESTA Admin &nbsp;›&nbsp; </span><b>' + m.g + "</b>";
    if (t) t.textContent = m.t;
    document.title = m.t + " · US VESTA Admin";
  }

  function go(key) {
    key = ALIASES[key] || key;
    var link = $('.sidebar .nav-link[data-section="' + key + '"]');
    if (link) link.click();
  }
  window.avGo = go;

  function onNav(e) {
    var key = this.getAttribute("data-section");
    if (!key) return;
    setCrumb(key);
    if (history.replaceState) history.replaceState(null, "", "#" + key);
    document.body.classList.remove("av-side-open");
    window.scrollTo({ top: 0, behavior: "instant" in window ? "instant" : "auto" });
  }

  /* ---------------- status pills */
  var TONES = {
    "ordered": "info", "shipped": "violet", "out for delivery": "violet", "delivered": "success", "cancelled": "danger",
    "return in process": "warn", "return accepte": "warn", "return reject": "danger", "return": "warn", "refund": "gold",
    "paid": "success", "pending": "warn", "failed": "danger", "publish": "success", "published": "success", "draft": "warn",
    "online": "info", "cash on delivery": "gold", "cod": "gold", "active": "success", "inactive": "danger", "revoked": "danger",
    "visible": "success", "hidden": "warn", "test": "info", "live": "success"
  };
  function pillify(root) {
    $all(".badge, .status-badge", root).forEach(function (b) {
      if (b.dataset.avPill) return;
      var txt = b.textContent.replace(/\s+/g, " ").trim().toLowerCase();
      var tone = TONES[txt];
      if (!tone) return;
      b.dataset.avPill = "1";
      $all("i, svg, .dot, .status-dot", b).forEach(function (x) { x.remove(); });
      b.textContent = b.textContent.replace(/^[\s•●·]+/, "").trim();
      b.className = "av-pill tone-" + tone;
    });
  }
  window.avPillify = pillify;

  /* ---------------- sidebar filter */
  function sideFilter() {
    var input = $("#avSideFilter");
    if (!input) return;
    input.addEventListener("input", function () {
      var q = this.value.trim().toLowerCase();
      $all(".av-nav > li").forEach(function (li) {
        if (li.classList.contains("av-nav-group")) { li.style.display = q ? "none" : ""; return; }
        li.style.display = !q || li.textContent.toLowerCase().indexOf(q) > -1 ? "" : "none";
      });
    });
    input.addEventListener("keydown", function (e) {
      if (e.key !== "Enter") return;
      var first = $all(".av-nav > li").filter(function (li) { return li.style.display !== "none" && !li.classList.contains("av-nav-group"); })[0];
      if (first) { var a = $(".nav-link", first); if (a) a.click(); this.value = ""; this.dispatchEvent(new Event("input")); }
    });
  }

  /* ---------------- quick actions */
  window.avQuick = function (what) {
    if (what === "add-product") { go("products"); setTimeout(function () { if (typeof add_product_form === "function") add_product_form(); }, 60); return; }
    if (what === "bulk-upload") { go("products"); setTimeout(function () { if (typeof add_product_form_in_bulk === "function") add_product_form_in_bulk(); }, 60); return; }
    if (what === "add-banner") { go("banners"); openModal("#addBannerModal"); return; }
    if (what === "add-blog") { go("blogs"); openModal("#addBlogPostModal"); return; }
    if (what === "homepage") { go("homepage_editor"); return; }
    if (what === "orders") { go("orders"); return; }
  };
  function openModal(sel) {
    setTimeout(function () {
      var el = $(sel);
      if (el && window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(el).show();
    }, 80);
  }

  /* ---------------- relative time for notifications */
  function relTimes() {
    $all("[data-av-time]").forEach(function (el) {
      var t = Date.parse(el.getAttribute("data-av-time"));
      if (isNaN(t)) return;
      var s = Math.max(1, Math.round((Date.now() - t) / 1000));
      var out = s < 60 ? "just now" : s < 3600 ? Math.round(s / 60) + " min ago" : s < 86400 ? Math.round(s / 3600) + " h ago" : Math.round(s / 86400) + " d ago";
      el.textContent = out;
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    decorateHeaders();
    pillify(document);
    sideFilter();
    relTimes();

    $all(".sidebar .nav-link[data-section]").forEach(function (a) { a.addEventListener("click", onNav); });

    // initial section: URL hash wins, then the last one used (dashboard.js keeps it in localStorage)
    var hash = (location.hash || "").replace(/^#/, "").replace(/-section$/, "");
    hash = ALIASES[hash] || hash;
    setTimeout(function () {
      if (hash && META[hash]) go(hash);
      else { var act = $(".sidebar .nav-link.active[data-section]"); setCrumb(act ? act.getAttribute("data-section") : "dashboard"); }
    }, 0);

    window.addEventListener("hashchange", function () {
      var h = (location.hash || "").replace(/^#/, "").replace(/-section$/, "");
      h = ALIASES[h] || h;
      var act = $(".sidebar .nav-link.active[data-section]");
      if (META[h] && (!act || act.getAttribute("data-section") !== h)) go(h);
    });

    // mobile drawer
    var burger = $(".av-burger"), bd = $(".av-backdrop");
    if (burger) burger.addEventListener("click", function () { document.body.classList.toggle("av-side-open"); });
    if (bd) bd.addEventListener("click", function () { document.body.classList.remove("av-side-open"); });

    // "/" focuses search, Esc closes drawer
    document.addEventListener("keydown", function (e) {
      var tag = (e.target.tagName || "").toLowerCase();
      if (e.key === "/" && tag !== "input" && tag !== "textarea" && !e.target.isContentEditable) {
        var s = $("#dashboardSearchInput"); if (s) { e.preventDefault(); s.focus(); }
      }
      if (e.key === "Escape") document.body.classList.remove("av-side-open");
    });

    // re-colour statuses after dynamic updates (order status changes, tables redrawn)
    if (window.MutationObserver) {
      var pending = false;
      new MutationObserver(function () {
        if (pending) return; pending = true;
        setTimeout(function () { pending = false; pillify(document); }, 150);
      }).observe($(".main-content") || document.body, { childList: true, subtree: true });
    }
  });
})();
