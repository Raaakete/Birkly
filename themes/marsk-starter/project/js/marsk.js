/* ==========================================================================
   MARSK — Template scripts
   --------------------------------------------------------------------------
   1. Overlay menu (open / close, ESC key)
   2. Header behavior (frosted on scroll, hide on scroll down)
   3. Scroll reveal (IntersectionObserver)
   4. Parallax (elements with [data-parallax])
   5. Custom cursor (desktop only)
   All features are progressive enhancements — the site works without JS.
   ========================================================================== */
(function () {
  "use strict";

  function bindOverlayMenu() {
    if (document.body.dataset.marskMenuDelegated) {
      return;
    }
    document.body.dataset.marskMenuDelegated = "1";

    document.addEventListener("click", function (e) {
      var toggle = e.target.closest(".menu-toggle");
      if (toggle) {
        var open = document.body.classList.toggle("menu-open");
        toggle.setAttribute("aria-expanded", open ? "true" : "false");
        return;
      }

      var menuLink = e.target.closest(".overlay-menu a");
      if (menuLink && document.body.classList.contains("menu-open")) {
        document.body.classList.remove("menu-open");
        var activeToggle = document.querySelector(".menu-toggle");
        if (activeToggle) {
          activeToggle.setAttribute("aria-expanded", "false");
        }
      }
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && document.body.classList.contains("menu-open")) {
        document.body.classList.remove("menu-open");
        var toggle = document.querySelector(".menu-toggle");
        if (toggle) {
          toggle.setAttribute("aria-expanded", "false");
        }
      }
    });
  }

  function refreshScrollReveals() {
    var prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    var revealSelector = ".reveal:not(.is-visible), .reveal-stagger:not(.is-visible)";
    var revealEls = document.querySelectorAll(revealSelector);

    function revealIfInView(el) {
      var rect = el.getBoundingClientRect();
      var margin = 160;
      if (rect.top < window.innerHeight + margin && rect.bottom > -margin) {
        el.classList.add("is-visible");
        return true;
      }
      return false;
    }

    if (prefersReducedMotion) {
      document.querySelectorAll(".reveal, .reveal-stagger").forEach(function (el) {
        el.classList.add("is-visible");
      });
      return;
    }

    if (!revealEls.length) {
      return;
    }

    if ("IntersectionObserver" in window) {
      var revealObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add("is-visible");
            revealObserver.unobserve(entry.target);
          }
        });
      }, { threshold: 0.01, rootMargin: "160px 0px 160px 0px" });

      revealEls.forEach(function (el) {
        if (!revealIfInView(el)) {
          revealObserver.observe(el);
        }
      });
    } else {
      revealEls.forEach(function (el) { el.classList.add("is-visible"); });
    }
  }

  function initMarsk() {
    var prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    bindOverlayMenu();

    /* ------------------------------------------------------------------
       2. HEADER BEHAVIOR
       ------------------------------------------------------------------ */
    var header = document.querySelector(".site-header");
    if (header && !header.dataset.marskScrollBound) {
      header.dataset.marskScrollBound = "1";
      var lastY = 0;
      function onScrollHeader() {
        if (!header) return;
        var y = window.scrollY;
        header.classList.toggle("is-scrolled", y > 24);
        if (!document.body.classList.contains("menu-open")) {
          header.classList.toggle("is-hidden", y > 500 && y > lastY);
        }
        lastY = y;
      }
      window.addEventListener("scroll", onScrollHeader, { passive: true });
      onScrollHeader();
    }

    /* ------------------------------------------------------------------
       3. SCROLL REVEAL
       ------------------------------------------------------------------ */
    refreshScrollReveals();

    /* ------------------------------------------------------------------
       4. PARALLAX
       ------------------------------------------------------------------ */
    var parallaxEls = document.querySelectorAll("[data-parallax]");
    if (parallaxEls.length && !prefersReducedMotion) {
      var ticking = false;
      function updateParallax() {
        var vh = window.innerHeight;
        parallaxEls.forEach(function (el) {
          var speed = parseFloat(el.getAttribute("data-parallax")) || 0.1;
          var rect = el.getBoundingClientRect();
          var progress = (rect.top + rect.height / 2 - vh / 2) / vh;
          el.style.transform = "translateY(" + (-progress * speed * 100).toFixed(2) + "px)";
        });
        ticking = false;
      }
      window.addEventListener("scroll", function () {
        if (!ticking) { requestAnimationFrame(updateParallax); ticking = true; }
      }, { passive: true });
      updateParallax();
    }

    /* ------------------------------------------------------------------
       5. CUSTOM CURSOR
       ------------------------------------------------------------------ */
    var dot = document.querySelector(".cursor-dot");
    var ring = document.querySelector(".cursor-ring");
    if (dot && ring && window.matchMedia("(hover: hover) and (pointer: fine)").matches && !prefersReducedMotion && !document.body.dataset.marskCursorBound) {
      document.body.dataset.marskCursorBound = "1";
      var mouseX = -100, mouseY = -100;
      var ringX = -100, ringY = -100;

      document.addEventListener("mousemove", function (e) {
        mouseX = e.clientX;
        mouseY = e.clientY;
        dot.style.left = mouseX + "px";
        dot.style.top = mouseY + "px";
      });

      (function animateRing() {
        ringX += (mouseX - ringX) * 0.16;
        ringY += (mouseY - ringY) * 0.16;
        ring.style.left = ringX + "px";
        ring.style.top = ringY + "px";
        requestAnimationFrame(animateRing);
      })();

      document.querySelectorAll("a, button, input, textarea, select, summary").forEach(function (el) {
        el.addEventListener("mouseenter", function () { ring.classList.add("is-hovering"); });
        el.addEventListener("mouseleave", function () { ring.classList.remove("is-hovering"); });
      });
    }

    /* ------------------------------------------------------------------
       6. FILTER BAR (Gallery & Blog)
       ------------------------------------------------------------------ */
    var filterBar = document.querySelector(".filter-bar");
    if (filterBar && !filterBar.dataset.marskFilterBound) {
      filterBar.dataset.marskFilterBound = "1";
      var filterables = document.querySelectorAll("[data-cat]");
      filterBar.addEventListener("click", function (e) {
        var btn = e.target.closest("[data-filter]");
        if (!btn) return;
        filterBar.querySelectorAll(".tag").forEach(function (t) { t.classList.remove("is-active"); });
        btn.classList.add("is-active");
        var filter = btn.getAttribute("data-filter");
        filterables.forEach(function (item) {
          item.classList.toggle("is-hidden", filter !== "all" && item.getAttribute("data-cat") !== filter);
        });
      });
    }

    /* ------------------------------------------------------------------
       7. READING PROGRESS (Blog post)
       ------------------------------------------------------------------ */
    var progress = document.querySelector(".read-progress span");
    if (progress && !progress.dataset.marskProgressBound) {
      progress.dataset.marskProgressBound = "1";
      window.addEventListener("scroll", function () {
        var max = document.documentElement.scrollHeight - window.innerHeight;
        progress.style.width = (max > 0 ? (window.scrollY / max) * 100 : 0) + "%";
      }, { passive: true });
    }
  }

  var usesBirkly = !!document.querySelector('script[src*="birkly-client"]');
  if (usesBirkly) {
    document.addEventListener("birkly:templates-processed", initMarsk);
    document.addEventListener("birkly:reveals-refresh", refreshScrollReveals);
  } else {
    initMarsk();
  }
})();
