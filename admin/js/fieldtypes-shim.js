// Birkly Fieldtypes MPA Compatibility Shim (safe, minimal)
(function() {
  // Ensure namespace objects exist
  if (!window.HaruFieldtypes) { window.HaruFieldtypes = {}; }

  // Interface helpers expected by various fieldtypes
  if (!window.HaruFieldtypeInterface) {
    window.HaruFieldtypeInterface = {
      getElement: function(selector) {
        try {
          if (typeof selector === 'string' && selector && selector[0] !== '#' && selector[0] !== '.' && selector[0] !== '[' && selector.indexOf(' ') === -1) {
            var byId = document.getElementById(selector);
            if (byId) return byId;
          }
          return document.querySelector(selector);
        } catch (e) { return null; }
      },
      getElements: function(selector, ctx) {
        try {
          if (typeof selector === 'string' && selector && selector[0] !== '#' && selector[0] !== '.' && selector[0] !== '[' && selector.indexOf(' ') === -1) {
            var el = document.getElementById(selector);
            return el ? [el] : [];
          }
          return (ctx || document).querySelectorAll(selector);
        } catch (e) { return []; }
      },
      updateHiddenInput: function(fieldName, value) {
        try {
          var el = document.querySelector('#field_' + fieldName + '_value, [name="' + fieldName + '"]');
        if (el) { el.value = value; }
        } catch (e) {}
      },
      getValue: function(el) {
        try { return (el && 'value' in el) ? el.value : ''; } catch (e) { return ''; }
      },
      setValue: function(target, value) {
        try {
          var el = (typeof target === 'string') ? document.querySelector(target) : target;
          if (el && 'value' in el) {
            el.value = value;
            try { el.dispatchEvent(new Event('input', { bubbles: true })); } catch (e) {}
          }
        } catch (e) {}
      },
      emitFieldChange: function(fieldName, type, value, oldValue) {
        try {
          var ev = new CustomEvent('fieldtype:change', { detail: { fieldName: fieldName, type: type, value: value, oldValue: oldValue } });
          document.dispatchEvent(ev);
        } catch (e) {}
      },
      // Create an observer utility that supports two signatures:
      // 1) createObserver(callback, options?) -> { observe(el), disconnect() }
      // 2) createObserver(element, callback, options?) -> returns observer handle
      createObserver: function(arg1, arg2, arg3) {
        var MutationObs = window.MutationObserver || window.WebKitMutationObserver || window.MozMutationObserver;
        function makeHandle(cb, opts) {
          var handle = { _obs: null };
          handle.observe = function(el) {
            try {
              if (MutationObs && el) {
                handle._obs = new MutationObs(function() { try { cb(el); } catch (e) {} });
                handle._obs.observe(el, Object.assign({ childList: true, subtree: true }, opts || {}));
              } else {
                // Fallback: periodic check
                var timer = setInterval(function(){ try { cb(el); } catch (e) {} }, 300);
                handle._obs = { disconnect: function(){ clearInterval(timer); } };
              }
            } catch (e) {}
            return handle;
          };
          handle.disconnect = function(){ try { if (handle._obs && handle._obs.disconnect) handle._obs.disconnect(); } catch (e) {} };
          return handle;
        }
        // Signature detection
        if (arg1 && arg1.nodeType === 1 && typeof arg2 === 'function') {
          // (element, callback, options)
          var h = makeHandle(arg2, arg3);
          return h.observe(arg1);
        } else if (typeof arg1 === 'function') {
          // (callback, options)
          return makeHandle(arg1, arg2);
        }
        // Unknown usage; return no-op handle
        return { observe: function(){ return this; }, disconnect: function(){} };
      },
      // Generic event binding helper. Accepts element or selector; returns unsubscribe.
      addEventListener: function(target, event, handler, options) {
        try {
          var el = (typeof target === 'string') ? document.querySelector(target) : target;
          if (!el || !el.addEventListener) return function(){};
          el.addEventListener(event, handler, options || false);
          return function() { try { el.removeEventListener(event, handler, options || false); } catch (e) {} };
        } catch (e) { return function(){}; }
      }
    };
  }

  // Configuration registry expected by some fieldtypes
  if (!window.HaruFieldtypeConfig) {
    window.HaruFieldtypeConfig = {
      _configs: {},
      register: function(fieldtype, cfg) {
        try {
          this._configs[fieldtype] = Object.assign({}, this._configs[fieldtype] || {}, cfg || {});
        } catch (e) {}
        return this._configs[fieldtype] || {};
      },
      getSelector: function(fieldtype, key, fieldName) {
        // Prefer registered selectors
        try {
          var cfg = this._configs[fieldtype];
          var sel = cfg && cfg.selectors ? cfg.selectors[key] : '';
          if (sel) { return String(sel).replace('{fieldName}', fieldName); }
        } catch (e) {}
        // Fallback maps for common fieldtypes
        var maps = {
          tags: {
            container: '#tags_field_' + fieldName,
            hiddenInput: '#field_' + fieldName + '_value',
            input: '#field_' + fieldName + '_input',
            chips: '.tags-chips',
            chip: '.tag-chip',
            removeButton: '.tag-remove'
          },
          relation: {
            container: '#relation_field_' + fieldName,
            hiddenInput: '#field_' + fieldName + '_value',
            list: '#relation_list_' + fieldName,
            controls: '.relation-controls',
            selectButton: '.select-relation-btn',
            removeAllButton: '.remove-all-relation-btn'
          }
        };
        return (maps[fieldtype] && maps[fieldtype][key]) ? maps[fieldtype][key] : '';
      },
      getSettings: function(fieldtype) {
        try { return (this._configs[fieldtype] && this._configs[fieldtype].settings) || {}; } catch (e) { return {}; }
      },
      getEvents: function(fieldtype) {
        try { return (this._configs[fieldtype] && this._configs[fieldtype].events) || {}; } catch (e) { return {}; }
      },
      // Return a single setting or a default value
      getSetting: function(fieldtype, key, defVal) {
        try {
          var settings = this.getSettings(fieldtype);
          return (settings && key in settings) ? settings[key] : defVal;
        } catch (e) { return defVal; }
      }
    };
  }

  // Performance helpers (no-ops sufficient for MPA)
  if (!window.HaruFieldtypePerformance) {
    window.HaruFieldtypePerformance = {
      batchDOMOperations: function(ops) {
        try { (ops || []).forEach(function(fn){ try { fn(); } catch (e) {} }); } catch (e) {}
      },
      queryDOM: function(selector, root) {
        try { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); } catch (e) { return []; }
      },
      cache: function() {},
      getCached: function() { return null; },
      registerLazyLoader: function(fieldtype, loader, options) {
        try {
          var selMap = {
            tags: '.tags-field-container',
            relation: '.relation-field',
            media: '.media-field',
            dynamicitems: '.dynamic-items-field',
            editor: '.editor-field'
          };
          var run = function() {
            var sel = selMap[fieldtype] || ('[data-fieldtype="' + fieldtype + '"]');
            var list = document.querySelectorAll(sel);
            if (list.length === 0) { try { loader(); } catch (e) {} return; }
            Array.prototype.forEach.call(list, function(el){ try { loader(el); } catch (e) {} });
          };
          if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', run, { once: true });
          } else {
            setTimeout(run, 0);
          }
        } catch (e) {}
      }
    };
  }
})();


