// cms-template.js - Advanced client-side CMS templating engine for HaruCMS with dynamic routing
(function() {
  // Config
  const API_BASE = '/api/public.php';
  
  // Route configuration - will be set dynamically based on current path
  let ROUTES = {};
  
  // Initialize routes based on current path
  function initRoutes() {
    const currentPath = window.location.pathname;
    const isSubdirectory = currentPath.includes('/testwebsite') || currentPath.includes('/test');
    const basePath = isSubdirectory ? currentPath.replace(/\/[^\/]*$/, '') : '';
    
    ROUTES = {
      [basePath + '/']: { template: 'home', title: 'Home' },
      [basePath + '/blog']: { template: 'blog-list', title: 'Blog' },
      [basePath + '/blog/:slug']: { template: 'blog-post', title: 'Blog Post' },
      [basePath + '/page/:slug']: { template: 'page', title: 'Page' }
    };
  }

  // Current route state
  let currentRoute = null;
  let currentParams = {};

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

  // Utility: get single entry by slug
  async function getEntryBySlug(collection, slug) {
    let res = await fetchAPI({ action: 'list_entries', collection });
    if (res.status !== 'ok' || !res.data || !Array.isArray(res.data.entries)) return null;
    return res.data.entries.find(e => e.slug === slug) || null;
  }

  // Router: parse URL and find matching route
  function parseRoute(pathname) {
    const path = pathname || window.location.pathname;
    
    for (const [pattern, route] of Object.entries(ROUTES)) {
      const params = {};
      let match = true;
      
      // Convert route pattern to regex
      let regexPattern = pattern
        .replace(/:[^\/]+/g, '([^\/]+)') // Replace :param with capture group
        .replace(/\//g, '\\/'); // Escape slashes
      
      const regex = new RegExp(`^${regexPattern}$`);
      const matches = path.match(regex);
      
      if (matches) {
        // Extract parameters
        const paramNames = (pattern.match(/:[^\/]+/g) || []).map(p => p.slice(1));
        paramNames.forEach((name, index) => {
          params[name] = matches[index + 1];
        });
        
        return { route, params, path };
      }
    }
    
    return null;
  }

  // Router: handle navigation
  function navigate(path) {
    window.history.pushState({}, '', path);
    handleRoute();
  }

  // Router: handle route changes
  async function handleRoute() {
    const routeInfo = parseRoute();
    
    if (!routeInfo) {
      // 404 - not found
      document.body.innerHTML = '<h1>404 - Page Not Found</h1><p><a href="/">← Back to Home</a></p>';
      return;
    }
    
    currentRoute = routeInfo.route;
    currentParams = routeInfo.params;
    
    // Update page title
    document.title = currentRoute.title;
    
    // Handle different route types
    switch (currentRoute.template) {
      case 'home':
        await renderHome();
        break;
      case 'blog-list':
        await renderBlogList();
        break;
      case 'blog-post':
        await renderBlogPost(currentParams.slug);
        break;
      case 'page':
        await renderPage(currentParams.slug);
        break;
      default:
        document.body.innerHTML = '<h1>Template not found</h1><p><a href="/">← Back to Home</a></p>';
    }
    
    // Process any templates in the rendered content
    processTemplates();
  }

  // Render home page
  async function renderHome() {
    try {
      // Fetch latest blog entries from API
      const response = await fetchAPI({ action: 'list_entries', collection: 'blog' });
      
      let html = `
        <div class="container">
          <h1>Welcome to Our Website</h1>
          <p>This is the home page with dynamic content from HaruCMS.</p>
      `;
      
      if (response.status === 'ok' && response.data && response.data.entries) {
        const entries = response.data.entries;
        
        // Sort by date descending and take first 3
        entries.sort((a, b) => new Date(b.date || b.created_at) - new Date(a.date || a.created_at));
        const latestEntries = entries.slice(0, 3);
        
        html += '<h2>Latest Blog Posts</h2>';
        
        latestEntries.forEach(entry => {
          const date = formatDate(entry.date || entry.created_at, 'YYYY-MM-DD');
          const excerpt = (entry.content || '').substring(0, 100) + '...';
          const slug = entry.slug || entry.id;
          
          html += `
            <div class="entry">
              <h3><a href="./blog/${slug}">${escapeHtml(entry.title || 'Untitled')}</a></h3>
              <div class="entry-date">${date}</div>
              <div class="entry-excerpt">${escapeHtml(excerpt)}</div>
            </div>
          `;
        });
      } else {
        html += '<h2>Latest Blog Posts</h2><p>No blog posts found.</p>';
      }
      
      html += '</div>';
      document.body.innerHTML = html;
      
    } catch (error) {
      console.error('Error loading home page:', error);
      document.body.innerHTML = `
        <div class="container">
          <h1>Welcome to Our Website</h1>
          <p>This is the home page with dynamic content from HaruCMS.</p>
          <h2>Latest Blog Posts</h2>
          <p>Error loading blog posts.</p>
        </div>
      `;
    }
  }

  // Render blog list
  async function renderBlogList() {
    try {
      // Fetch blog entries from API
      const response = await fetchAPI({ action: 'list_entries', collection: 'blog' });
      
      if (response.status !== 'ok' || !response.data || !response.data.entries) {
        document.body.innerHTML = '<div class="container"><h1>Blog</h1><p>No blog posts found.</p></div>';
        return;
      }
      
      const entries = response.data.entries;
      
      // Sort by date descending
      entries.sort((a, b) => new Date(b.date || b.created_at) - new Date(a.date || a.created_at));
      
      let html = `
        <div class="container">
          <h1>Blog</h1>
          <p>Found ${entries.length} blog posts</p>
      `;
      
      entries.forEach(entry => {
        const date = formatDate(entry.date || entry.created_at, 'YYYY-MM-DD');
        const excerpt = (entry.content || '').substring(0, 150) + '...';
        const slug = entry.slug || entry.id;
        
        html += `
          <div class="entry">
            <h2><a href="./blog/${slug}">${escapeHtml(entry.title || 'Untitled')}</a></h2>
            <div class="entry-date">${date}</div>
            <div class="entry-excerpt">${escapeHtml(excerpt)}</div>
            ${entry.featured ? '<span class="text-muted">★ Featured</span>' : ''}
          </div>
        `;
      });
      
      html += '</div>';
      document.body.innerHTML = html;
      
    } catch (error) {
      console.error('Error loading blog posts:', error);
      document.body.innerHTML = '<div class="container"><h1>Blog</h1><p>Error loading blog posts.</p></div>';
    }
  }

  // Render single blog post
  async function renderBlogPost(slug) {
    try {
      const entry = await getEntryBySlug('blog', slug);
      
      if (!entry) {
        document.body.innerHTML = `
          <div class="container">
            <h1>Blog post not found</h1>
            <p>The blog post "${escapeHtml(slug)}" could not be found.</p>
            <p><a href="./blog">← Back to Blog</a></p>
          </div>
        `;
        return;
      }
      
      const date = formatDate(entry.date || entry.created_at, 'YYYY-MM-DD');
      
      const html = `
        <div class="container">
          <h1>${escapeHtml(entry.title || 'Untitled')}</h1>
          <div class="entry-date">${date}</div>
          <div class="entry-content">${entry.content || ''}</div>
          ${entry.featured ? '<span class="text-muted">★ Featured Post</span>' : ''}
          <p><a href="./blog">← Back to Blog</a></p>
        </div>
      `;
      
      document.body.innerHTML = html;
      
    } catch (error) {
      console.error('Error loading blog post:', error);
      document.body.innerHTML = `
        <div class="container">
          <h1>Error</h1>
          <p>Error loading blog post.</p>
          <p><a href="./blog">← Back to Blog</a></p>
        </div>
      `;
    }
  }

  // Render single page
  async function renderPage(slug) {
    const entry = await getEntryBySlug('pages', slug);
    
    if (!entry) {
      document.body.innerHTML = '<h1>Page not found</h1>';
      return;
    }
    
    const template = `
      <div class="container">
        <h1>{title}</h1>
        <div class="page-content">{content}</div>
      </div>
    `;
    
    // Replace template variables
    let html = template
      .replace(/{title}/g, entry.title || '')
      .replace(/{content}/g, entry.content || '');
    
    document.body.innerHTML = html;
  }

  // Main: scan for curly-brace templates and render them
  function processTemplates() {
    // Get URL parameters for dynamic replacement
    const urlParams = new URLSearchParams(window.location.search);
    
    // First pass: replace all URL parameter placeholders globally
    if (urlParams.entries().next().done === false) {
      document.querySelectorAll('body, body *').forEach(el => {
        let html = el.innerHTML;
        let hasPlaceholders = false;
        
        // Replace URL parameter placeholders
        for (const [key, value] of urlParams.entries()) {
          const placeholder = key.toUpperCase() + '_PLACEHOLDER';
          if (html.includes(placeholder)) {
            html = html.replace(new RegExp(placeholder, 'g'), value);
            hasPlaceholders = true;
            console.log(`Replaced ${placeholder} with ${value}`);
          }
        }
        
        if (hasPlaceholders) {
          el.innerHTML = html;
        }
      });
    }
    
    // Second pass: process templates
    document.querySelectorAll('body, body *').forEach(el => {
      let html = el.innerHTML;
      
      let hasTemplates = html.match(/{for each entry in '([\w-]+)'/) || 
                        html.match(/{from '([\w-]+)' get '([\w-]+)'/) ||
                        html.match(/{get entry from '([\w-]+)' where '([\w-]+)' = '([^']+)'/);
      
      if (!hasTemplates) return;
      
      const promises = [];
      
      // Find all {for each entry in 'collection' ...}...{/for} blocks (with options)
      const forRegex = /{\s*for each entry in '([\w-]+)'([^}]*)}(.*?)\{\s*\/for\s*}/gs;
      let match;
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
            out = out.replace(/\{\s*if\s+(\w+)\s*\}(.*?)((?:\{\s*else\s*\}(.*?))?)\{\s*\/if\s*\}/gs, (_, field, yes, _unused, no) => {
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
      
      // NEW: {get entry from 'collection' where 'field' = 'value'}...{/get}
      const getEntryRegex = /{\s*get entry from '([\w-]+)' where '([\w-]+)'\s*=\s*'([^']+)'\s*}(.*?)\{\s*\/get\s*}/gs;
      while ((match = getEntryRegex.exec(html))) {
        const [block, collection, field, value, inner] = match;
        
        // Check if this is a URL parameter placeholder
        let actualValue = value;
        if (value === 'URL_SLUG_PARAM' || value === 'URL_ID_PARAM' || value === 'URL_TITLE_PARAM') {
          const paramName = value.replace('URL_', '').replace('_PARAM', '').toLowerCase();
          const urlParams = new URLSearchParams(window.location.search);
          actualValue = urlParams.get(paramName) || value;
        }
        
        // NEW: Handle dynamic URL parameters automatically
        // If the value looks like a URL parameter reference, get it from the URL
        if (value.startsWith('$') && value.endsWith('$')) {
          const paramName = value.slice(1, -1); // Remove $ symbols
          const urlParams = new URLSearchParams(window.location.search);
          actualValue = urlParams.get(paramName) || value;
        }
        
        const p = getEntryBySlug(collection, actualValue).then(entry => {
          if (!entry) {
            // Try to find by the specified field instead of slug
            return fetchAPI({action:'list_entries', collection}).then(data => {
              let entries = (data.data && data.data.entries) ? data.data.entries : [];
              return entries.find(e => String(e[field]) === actualValue) || null;
            });
          }
          return entry;
        }).then(entry => {
          if (!entry) {
            html = html.replace(block, '<div class="error">Entry not found</div>');
            return;
          }
          
          let rendered = inner;
          // {field|filter1|filter2:arg}
          rendered = rendered.replace(/{\s*(\w+)(\|[^}]+)?\s*}/g, (_, field, filters) => {
            let val = entry[field] || '';
            if (filters) val = applyFilters(val, parseFilters(filters.slice(1)));
            else val = escapeHtml(val);
            return val;
          });
          // {if field}...{else}...{/if}
          rendered = rendered.replace(/\{\s*if\s+(\w+)\s*\}(.*?)((?:\{\s*else\s*\}(.*?))?)\{\s*\/if\s*\}/gs, (_, field, yes, _unused, no) => {
            return entry[field] ? yes : (no || '');
          });
          
          html = html.replace(block, rendered);
        });
        promises.push(p);
      }
      
      Promise.all(promises).then(() => {
        el.innerHTML = html;
      });
    });
  }

  // Initialize routing
  function initRouting() {
    // Initialize routes based on current path
    initRoutes();
    
    // Handle browser back/forward
    window.addEventListener('popstate', handleRoute);
    
    // Handle initial route
    handleRoute();
    
    // Intercept clicks on internal links
    document.addEventListener('click', (e) => {
      const link = e.target.closest('a');
      if (link && link.href.startsWith(window.location.origin)) {
        e.preventDefault();
        navigate(link.pathname);
      }
    });
  }

  // Run on DOMContentLoaded
  document.addEventListener('DOMContentLoaded', () => {
    // Always replace URL parameter placeholders first
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.entries().next().done === false) {
      document.querySelectorAll('body, body *').forEach(el => {
        let html = el.innerHTML;
        let hasPlaceholders = false;
        
        // Replace URL parameter placeholders
        for (const [key, value] of urlParams.entries()) {
          const placeholder = key.toUpperCase() + '_PLACEHOLDER';
          if (html.includes(placeholder)) {
            html = html.replace(new RegExp(placeholder, 'g'), value);
            hasPlaceholders = true;
          }
        }
        
        if (hasPlaceholders) {
          el.innerHTML = html;
        }
      });
    }
    
    // Check if we're in routing mode (has #routing in URL or specific setup)
    const routingMode = urlParams.get('routing') === 'true' || window.location.hash === '#routing';
    
    if (routingMode) {
      // Initialize dynamic routing
      initRouting();
    } else {
      // Legacy mode: just process templates
      processTemplates();
    }
  });

  // Expose navigation function globally
  window.HaruCMS = {
    navigate,
    getEntryBySlug,
    fetchAPI,
    processTemplates
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
  
  Dynamic Routing:
  ---------------
  Enable routing mode by adding ?routing=true to URL or #routing to hash.
  Routes: /, /blog, /blog/:slug, /page/:slug
  Use window.HaruCMS.navigate('/blog/my-post') for programmatic navigation.
  */
})(); 