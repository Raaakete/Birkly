// cms-template.js - Advanced client-side CMS templating engine for HaruCMS
(function() {
  // Config
  const API_BASE = '/api/public.php';

  // Utility: fetch JSON from API
  function fetchAPI(params) {
    const url = API_BASE + '?' + Object.entries(params).map(([k,v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`).join('&');
    return fetch(url).then(res => res.json());
  }

  // Utility: escape HTML
  function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, function(tag) {
      const chars = {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}; return chars[tag] || tag;
    });
  }

  // Utility: date formatting (very basic, can be extended)
  function formatDate(dateStr, format) {
    const d = new Date(dateStr);
    if (isNaN(d)) return dateStr;
    // Simple replacements
    return format
      .replace(/YYYY/g, d.getFullYear())
      .replace(/MM/g, String(d.getMonth()+1).padStart(2,'0'))
      .replace(/DD/g, String(d.getDate()).padStart(2,'0'))
      .replace(/HH/g, String(d.getHours()).padStart(2,'0'))
      .replace(/mm/g, String(d.getMinutes()).padStart(2,'0'))
      .replace(/ss/g, String(d.getSeconds()).padStart(2,'0'));
  }

  // Utility: apply filters to a value
  function applyFilters(val, filters) {
    let out = val;
    for (const f of filters) {
      if (f.name === 'escape') out = escapeHtml(out);
      else if (f.name === 'raw') ; // do nothing
      else if (f.name === 'default') out = (out == null || out === '') ? f.arg : out;
      else if (f.name === 'date') out = formatDate(out, f.arg || 'YYYY-MM-DD');
      // Add more filters here as needed
    }
    return out;
  }

  // Utility: parse filter string (e.g. "escape|date:YYYY-MM-DD|default:Untitled")
  function parseFilters(str) {
    return str.split('|').map(f => {
      const [name, arg] = f.split(':');
      return { name: name.trim(), arg: arg ? arg.trim().replace(/^"|"$/g, '') : undefined };
    }).filter(f => f.name);
  }

  // Utility: filter entries array
  function filterEntries(entries, filterStr) {
    if (!filterStr) return entries;
    // Support AND/OR/NOT, e.g. "image AND status=published AND views>100"
    // Only basic parsing for now
    let filters = filterStr.split(/\s+AND\s+/i);
    return entries.filter(entry => {
      return filters.every(f => {
        f = f.trim();
        if (!f) return true;
        // NOT
        if (f.startsWith('!')) return !entry[f.slice(1)];
        // field=value
        let m = f.match(/^([\w-]+)(!?=|:|>|<)(.+)$/);
        if (m) {
          let field = m[1], op = m[2], val = m[3].trim();
          if (op === '=' || op === ':') return String(entry[field]) === val;
          if (op === '!=') return String(entry[field]) !== val;
          if (op === '>') return Number(entry[field]) > Number(val);
          if (op === '<') return Number(entry[field]) < Number(val);
        }
        // field (must be non-empty)
        return !!entry[f];
      });
    });
  }

  // Utility: sort entries array
  function sortEntries(entries, sortStr) {
    if (!sortStr) return entries;
    let sorts = sortStr.split(',').map(s => s.trim()).filter(Boolean);
    let arr = [...entries];
    for (let i = sorts.length - 1; i >= 0; i--) {
      let s = sorts[i];
      // first:field:value
      let m1 = s.match(/^first:([\w-]+):(.+)$/);
      if (m1) {
        let field = m1[1], val = m1[2];
        arr.sort((a, b) => (String(b[field]) === val ? 1 : 0) - (String(a[field]) === val ? 1 : 0));
        continue;
      }
      // last:field:value
      let m2 = s.match(/^last:([\w-]+):(.+)$/);
      if (m2) {
        let field = m2[1], val = m2[2];
        arr.sort((a, b) => (String(a[field]) === val ? 1 : 0) - (String(b[field]) === val ? 1 : 0));
        continue;
      }
      // random
      if (s === 'random') {
        arr.sort(() => Math.random() - 0.5);
        continue;
      }
      // field:asc/desc
      let m3 = s.match(/^([\w-]+):(asc|desc)$/);
      if (m3) {
        let field = m3[1], dir = m3[2];
        arr.sort((a, b) => {
          if (a[field] == null) return 1;
          if (b[field] == null) return -1;
          if (a[field] === b[field]) return 0;
          return (a[field] > b[field] ? 1 : -1) * (dir === 'asc' ? 1 : -1);
        });
        continue;
      }
      // Shortcuts
      if (s === 'newest-first') arr.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
      if (s === 'oldest-first') arr.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
    }
    return arr;
  }

  // Utility: get single entry field from a collection
  async function getSingleField(collection, entry, field) {
    // Try by id, then by slug
    let res = await fetchAPI({ action: 'list_entries', collection });
    if (res.status !== 'ok' || !res.data || !Array.isArray(res.data.entries)) return '';
    let found = res.data.entries.find(e => e.id === entry || e.slug === entry);
    return found ? (found[field] || '') : '';
  }

  // Main: scan for curly-brace templates and render them
  function processTemplates() {
    // Find all elements with curly-brace template blocks
    document.querySelectorAll('body, body *').forEach(el => {
      if (!el.innerHTML.match(/{for each entry in '([\w-]+)'/)) return;
      let html = el.innerHTML;
      // Find all {for each entry in 'collection' ...}...{/for} blocks (with options)
      const forRegex = /{\s*for each entry in '([\w-]+)'([^}]*)}(.*?)\{\s*\/for\s*}/gs;
      let match;
      const promises = [];
      while ((match = forRegex.exec(html))) {
        const [block, collection, optsRaw, inner] = match;
        // Parse options (sort, filter, limit, offset)
        let opts = {};
        if (optsRaw) {
          optsRaw.replace(/([\w-]+)\s*=\s*"([^"]*)"/g, (match, key, value) => { opts[key] = value; });
          optsRaw.replace(/([\w-]+)\s*=\s*(\d+)/g, (match, key, value) => { opts[key] = value; });
        }
        // Fetch entries for this collection
        const p = fetchAPI({action:'list_entries', collection}).then(data => {
          let entries = (data.data && data.data.entries) ? data.data.entries : [];
          // Filtering
          entries = filterEntries(entries, opts.filter);
          // Sorting
          entries = sortEntries(entries, opts.sort);
          // Offset/Limit
          if (opts.offset) entries = entries.slice(Number(opts.offset));
          if (opts.limit) entries = entries.slice(0, Number(opts.limit));
          // Render each entry
          let rendered = entries.map((entry, idx) => {
            let out = inner;
            // {index}
            out = out.replace(/{\s*index\s*}/g, idx+1);
            // {field|filter1|filter2:arg}
            out = out.replace(/{\s*(\w+)(\|[^}]+)?\s*}/g, (_, field, filters) => {
              let val = entry[field] || '';
              if (filters) val = applyFilters(val, parseFilters(filters.slice(1)));
              else val = escapeHtml(val);
              return val;
            });
            // {if field}...{else}...{/if}
            out = out.replace(/\{\s*if\s+(\w+)\s*\}(.*?)((?:\{\s*else\s*\}(.*?))?)\{\s*\/if\s*\}/gs, (_, field, yes, _, no) => {
              return entry[field] ? yes : (no || '');
            });
            return out;
          }).join('');
          html = html.replace(block, rendered);
        });
        promises.push(p);
      }
      // {from 'collection' get 'entry, field'}
      const getRegex = /{\s*from\s+'([\w-]+)'\s+get\s+'([\w-]+)\s*,\s*([\w-]+)'\s*}/g;
      let getMatches = [...html.matchAll(getRegex)];
      for (const m of getMatches) {
        const [block, collection, entry, field] = m;
        const p = getSingleField(collection, entry, field).then(val => {
          html = html.replace(block, escapeHtml(val));
        });
        promises.push(p);
      }
      Promise.all(promises).then(() => {
        el.innerHTML = html;
      });
    });
  }

  // Run on DOMContentLoaded
  document.addEventListener('DOMContentLoaded', processTemplates);

  // HaruForm: AJAX form helper for public submissions
  window.HaruForm = function(formSelectorOrId, opts) {
    const form = typeof formSelectorOrId === 'string' ? document.getElementById(formSelectorOrId) || document.querySelector(formSelectorOrId) : formSelectorOrId;
    if (!form) return;
    opts = opts || {};
    // Fetch CSRF token if needed
    const csrfInput = form.querySelector('input[name="_csrf"]');
    if (csrfInput && !csrfInput.value) {
      fetch('/api/csrf.php').then(r=>r.json()).then(d=>{csrfInput.value=d.token;});
    }
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(form);
      fetch('/api/public_submit.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'success') {
          if (opts.onSuccess) opts.onSuccess(data);
          else alert('Thank you! Your submission was received.');
          form.reset();
        } else {
          if (opts.onError) opts.onError(data);
          else alert(data.message || 'Submission failed.');
        }
      })
      .catch(err => {
        if (opts.onError) opts.onError({status:'error',message:'Submission failed.'});
        else alert('Submission failed.');
      });
    });
  };

  // --- Documentation (for devs, not rendered) ---
  /*
  HaruCMS Templating Language Features:
  -------------------------------------
  1. Loops:
     {for each entry in 'collection' sort="created_at:desc" filter="image AND status=published" limit=5 offset=2}
       ...
     {/for}
     - sort: field:asc/desc, first:field:value, last:field:value, random, newest-first, oldest-first
     - filter: field, field=value, field!=value, field>value, field<value, !field, AND/OR
     - limit, offset: numbers
  2. Conditionals:
     {if field}...{else}...{/if}
  3. Index:
     {index} inside a for block
  4. Filters:
     {field|escape|date:YYYY-MM-DD|default:Untitled}
     - escape, raw, date, default
  5. Single Data Fetch:
     {from 'collection' get 'entry, field'}
  */
})(); 