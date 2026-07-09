/**
 * Studio creative runtime (P26) — scroll/click animations, popups with a11y.
 */
(function () {
  'use strict';

  const cfg = window.__STUDIO_CREATIVE__ || { animations: [], popups: [] };

  function qs(sel, root) {
    return (root || document).querySelector(sel);
  }

  function qsa(sel, root) {
    return Array.from((root || document).querySelectorAll(sel));
  }

  function byElId(id) {
    return qs('[data-studio-el="' + id + '"]');
  }

  // ——— Animations ———
  function initAnimations() {
    const list = cfg.animations || [];
    list.forEach(function (anim) {
      const target = byElId(anim.targetId);
      if (!target) return;
      const trigger = anim.trigger || 'scroll';
      if (trigger === 'load') {
        target.classList.add('studio-anim-ready');
        return;
      }
      if (trigger === 'click') {
        const clickEl = anim.clickTargetId ? byElId(anim.clickTargetId) : target;
        if (!clickEl) return;
        clickEl.addEventListener('click', function (e) {
          e.preventDefault();
          target.classList.remove('studio-anim-pending');
          target.classList.add('studio-anim-ready');
        });
        target.classList.add('studio-anim-pending');
        return;
      }
      // scroll (default)
      target.classList.add('studio-anim-pending');
      if (!('IntersectionObserver' in window)) {
        target.classList.remove('studio-anim-pending');
        target.classList.add('studio-anim-ready');
        return;
      }
      const offset = typeof anim.scrollOffset === 'number' ? anim.scrollOffset : 0.15;
      const obs = new IntersectionObserver(
        function (entries) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) {
              entry.target.classList.remove('studio-anim-pending');
              entry.target.classList.add('studio-anim-ready');
              obs.unobserve(entry.target);
            }
          });
        },
        { threshold: Math.min(1, Math.max(0, offset)) }
      );
      obs.observe(target);
    });
  }

  // ——— Popups ———
  let activePopup = null;
  let focusTrapHandler = null;
  let lastFocus = null;

  function getFocusable(root) {
    return qsa(
      'a[href],button:not([disabled]),textarea,input,select,[tabindex]:not([tabindex="-1"])',
      root
    ).filter(function (el) {
      return el.offsetParent !== null || el === document.activeElement;
    });
  }

  function trapFocus(panel) {
    focusTrapHandler = function (e) {
      if (e.key !== 'Tab' || !activePopup) return;
      const nodes = getFocusable(panel);
      if (!nodes.length) return;
      const first = nodes[0];
      const last = nodes[nodes.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    };
    document.addEventListener('keydown', focusTrapHandler);
  }

  function openPopup(root) {
    if (activePopup) closePopup(activePopup);
    lastFocus = document.activeElement;
    root.hidden = false;
    activePopup = root;
    const panel = qs('.studio-popup-panel', root);
    const closeBtn = qs('[data-popup-close]', panel || root);
    document.body.style.overflow = 'hidden';
    if (closeBtn) closeBtn.focus();
    if (panel) trapFocus(panel);
  }

  function closePopup(root) {
    root.hidden = true;
    if (activePopup === root) activePopup = null;
    document.body.style.overflow = '';
    if (focusTrapHandler) {
      document.removeEventListener('keydown', focusTrapHandler);
      focusTrapHandler = null;
    }
    if (lastFocus && typeof lastFocus.focus === 'function') {
      lastFocus.focus();
    }
  }

  function initPopups() {
    qsa('.studio-popup-root').forEach(function (root) {
      const trigger = root.getAttribute('data-popup-trigger') || 'click';
      const clickTargetId = root.getAttribute('data-popup-click-target');
      const delay = parseInt(root.getAttribute('data-popup-delay') || '0', 10);
      const exitIntent = root.getAttribute('data-popup-exit') === '1';

      qsa('[data-popup-close]', root).forEach(function (btn) {
        btn.addEventListener('click', function () {
          closePopup(root);
        });
      });

      if (trigger === 'delay' || delay > 0) {
        setTimeout(function () {
          openPopup(root);
        }, delay || 3000);
      }

      if (trigger === 'click' && clickTargetId) {
        const btn = byElId(clickTargetId);
        if (btn) {
          btn.addEventListener('click', function (e) {
            e.preventDefault();
            openPopup(root);
          });
        }
      }

      if (exitIntent || trigger === 'exit_intent') {
        var shown = false;
        document.addEventListener('mouseout', function (e) {
          if (shown || activePopup) return;
          if (!e.relatedTarget && e.clientY <= 0) {
            shown = true;
            openPopup(root);
          }
        });
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && activePopup) {
        closePopup(activePopup);
      }
    });
  }

  function init() {
    initAnimations();
    initPopups();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
