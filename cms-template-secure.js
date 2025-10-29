// cms-template-secure.js - Secure client-side CMS templating engine for HaruCMS
(function() {
  // Config - automatically detect domain and use secure API
  const SECURE_API_BASE = '/api/secure_public.php';
  const FALLBACK_API_BASE = '/api/public.php'; // For development/testing
  
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
  let apiBase = SECURE_API_BASE;
  let licenseInfo = null;

  // Check if we're in development mode (localhost)
  function isDevelopment() {
    return window.location.hostname === 'localhost' || 
           window.location.hostname === '127.0.0.1' ||
           window.location.hostname === '::1' ||
           window.location.hostname === '0.0.0.0' ||
           window.location.hostname.includes('.local') ||
           window.location.hostname.includes('.test') ||
           window.location.hostname.includes('.dev') ||
           /^localhost:\d+$/.test(window.location.hostname);
  }

  // Initialize API base URL
  function initApiBase() {
    if (isDevelopment()) {
      apiBase = FALLBACK_API_BASE;
      console.log('Development mode: Using fallback API');
    } else {
      apiBase = SECURE_API_BASE;
      console.log('Production mode: Using secure API');
    }
  }

  // Utility: fetch JSON from API with error handling
  function fetchAPI(params) {
    const url = apiBase + '?' + Object.entries(params).map(([k,v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`).join('&');
    
    return fetch(url, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    .then(res => {
      if (!res.ok) {
        if (res.status === 403) {
          throw new Error('Domain not licensed. Please contact support to register your domain.');
        }
        throw new Error(`API Error: ${res.status} ${res.statusText}`);
      }
      return res.json();
    })
    .catch(error => {
      console.error('API Error:', error);
      
      // If secure API fails and we're not in development, show license error
      if (apiBase === SECURE_API_BASE && !isDevelopment()) {
        showLicenseError(error.message);
        throw error;
      }
      
      throw error;
    });
  }

  // Show license error message
  function showLicenseError(message) {
    const errorDiv = document.createElement('div');
    errorDiv.className = 'cms-license-error';
    errorDiv.innerHTML = `
      <div style="
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        background: #f44336;
        color: white;
        padding: 1rem;
        text-align: center;
        z-index: 9999;
        font-family: Arial, sans-serif;
      ">
        <strong>License Error:</strong> ${message}
        <br>
        <small>Please contact support to register your domain for HaruCMS access.</small>
      </div>
    `;
    document.body.appendChild(errorDiv);
  }

  // Check license status
  function checkLicense() {
    if (apiBase === FALLBACK_API_BASE) {
      return Promise.resolve(); // Skip license check in development
    }
    
    return fetchAPI({ action: 'license_info' })
      .then(data => {
        if (data.status === 'ok') {
          licenseInfo = data.data;
          console.log('License validated for domain:', licenseInfo.domain);
        }
      })
      .catch(error => {
        console.error('License check failed:', error);
        // Don't throw error for local development
        if (!isDevelopment()) {
          throw error;
        }
      });
  }

  // Utility: escape HTML
  function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, function(tag) {
      const chars = {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}; 
      return chars[tag] || tag;
    });
  }

  // Utility: date formatting
  function formatDate(dateStr, format) {
    const d = new Date(dateStr);
    if (isNaN(d)) return dateStr;
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
      else if (f.name === 'limit') {
        const limit = parseInt(f.arg) || 100;
        if (typeof out === 'string' && out.length > limit) {
          out = out.substring(0, limit) + '...';
        }
      }
    }
    return out;
  }

  // Utility: parse filter string
  function parseFilters(str) {
    return str.split('|').map(f => {
      const [name, arg] = f.split(':');
      return { name: name.trim(), arg: arg ? arg.trim().replace(/^"|"$/g, '') : undefined };
    }).filter(f => f.name);
  }

  // Process templates in the current page
  function processTemplates() {
    const templates = document.querySelectorAll('[data-cms-template]');
    templates.forEach(template => {
      const templateType = template.getAttribute('data-cms-template');
      processTemplate(template, templateType);
    });
  }

  // Process a single template
  function processTemplate(template, type) {
    const content = template.innerHTML;
    const processedContent = processTemplateContent(content, type);
    template.innerHTML = processedContent;
  }

  // Process template content
  function processTemplateContent(content, type) {
    // Handle for loops: {for each entry in 'collection' sort="field:direction" limit=N}
    content = content.replace(/\{for each (\w+) in ['"`]([^'"`]+)['"`]([^}]*)\}([\s\S]*?)\{\/for\}/g, 
      function(match, varName, collection, options, loopContent) {
        return processForLoop(varName, collection, options, loopContent);
      }
    );

    // Handle variables: {variable|filter1:arg1|filter2:arg2}
    content = content.replace(/\{([^}]+)\}/g, function(match, variable) {
      return processVariable(variable);
    });

    return content;
  }

  // Process for loops
  function processForLoop(varName, collection, options, loopContent) {
    // Parse options (sort, limit, etc.)
    const sortMatch = options.match(/sort="([^"]+)"/);
    const limitMatch = options.match(/limit=(\d+)/);
    
    const sort = sortMatch ? sortMatch[1] : null;
    const limit = limitMatch ? parseInt(limitMatch[1]) : null;

    // Fetch collection data
    return fetchAPI({ action: 'list_entries', collection: collection })
      .then(data => {
        if (data.status !== 'ok') {
          console.error('Failed to fetch collection:', collection);
          return '';
        }

        let entries = data.data.entries || [];

        // Apply sorting
        if (sort) {
          const [field, direction] = sort.split(':');
          entries.sort((a, b) => {
            let aVal = a[field] || '';
            let bVal = b[field] || '';
            
            if (direction === 'desc') {
              [aVal, bVal] = [bVal, aVal];
            }
            
            if (typeof aVal === 'string') return aVal.localeCompare(bVal);
            return aVal - bVal;
          });
        }

        // Apply limit
        if (limit) {
          entries = entries.slice(0, limit);
        }

        // Process each entry
        return entries.map(entry => {
          let processed = loopContent;
          processed = processed.replace(new RegExp(`\\{${varName}\\.([^}]+)\\}`, 'g'), 
            function(match, field) {
              const filters = parseFilters(field);
              const fieldName = filters.shift().name;
              const value = entry[fieldName] || '';
              return applyFilters(value, filters);
            }
          );
          return processed;
        }).join('');
      })
      .catch(error => {
        console.error('Error processing for loop:', error);
        return '';
      });
  }

  // Process variables
  function processVariable(variable) {
    const filters = parseFilters(variable);
    const varName = filters.shift().name;
    
    // Handle special variables
    if (varName === 'current_url') {
      return applyFilters(window.location.pathname, filters);
    }
    if (varName === 'domain') {
      return applyFilters(window.location.hostname, filters);
    }
    if (varName === 'license_info' && licenseInfo) {
      return applyFilters(JSON.stringify(licenseInfo), filters);
    }
    
    // Handle entry variables (would need to be set in context)
    if (window.currentEntry && window.currentEntry[varName] !== undefined) {
      return applyFilters(window.currentEntry[varName], filters);
    }
    
    return applyFilters('', filters);
  }

  // Initialize the CMS template engine
  function init() {
    initApiBase();
    initRoutes();
    
    // Check license first
    checkLicense()
      .then(() => {
        processTemplates();
      })
      .catch(error => {
        console.error('CMS initialization failed:', error);
        if (apiBase === SECURE_API_BASE) {
          showLicenseError('Failed to validate license. Please contact support.');
        }
      });
  }

  // Run on DOMContentLoaded
  document.addEventListener('DOMContentLoaded', init);

  // HaruForm: AJAX form helper for public submissions (secure version)
  window.HaruForm = function(formSelectorOrId, opts) {
    const form = typeof formSelectorOrId === 'string' ? 
      document.getElementById(formSelectorOrId) || document.querySelector(formSelectorOrId) : 
      formSelectorOrId;
    
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

  // Expose API base for external use
  window.HARUCMS_API_BASE = apiBase;
  window.HARUCMS_LICENSE_INFO = licenseInfo;

  // Secure form submission functions
  function submitForm(collection, formData, config = {}) {
    if (apiBase === FALLBACK_API_BASE) {
      console.warn('Form submission not available in development mode');
      return Promise.reject(new Error('Form submission requires domain registration'));
    }
    
    return fetch(apiBase + '/secure_forms.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({
        action: 'submit_form',
        collection: collection,
        data: formData,
        config: config
      })
    })
    .then(response => {
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      return response.json();
    })
    .then(data => {
      if (data.status === 'ok') {
        console.log('Form submitted successfully:', data.data);
        return data.data;
      } else {
        throw new Error(data.message || 'Form submission failed');
      }
    });
  }

  function getFormConfig(collection) {
    if (apiBase === FALLBACK_API_BASE) {
      console.warn('Form config not available in development mode');
      return Promise.reject(new Error('Form configuration requires domain registration'));
    }
    
    return fetch(apiBase + '/secure_forms.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({
        action: 'get_form_config',
        collection: collection
      })
    })
    .then(response => {
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      return response.json();
    })
    .then(data => {
      if (data.status === 'ok') {
        return data.data;
      } else {
        throw new Error(data.message || 'Failed to get form configuration');
      }
    });
  }

  function handleForm(formElement, collection, options = {}) {
    const form = typeof formElement === 'string' ? document.querySelector(formElement) : formElement;
    if (!form) {
      console.error('Form element not found');
      return;
    }
    
    // Get form configuration
    getFormConfig(collection)
      .then(config => {
        // Add validation attributes based on schema
        Object.keys(config.schema).forEach(fieldName => {
          const field = form.querySelector(`[name="${fieldName}"]`);
          if (field) {
            const schema = config.schema[fieldName];
            
            if (schema.required) {
              field.setAttribute('required', 'required');
            }
            
            if (schema.type === 'email') {
              field.setAttribute('type', 'email');
            }
            
            if (schema.max_length) {
              field.setAttribute('maxlength', schema.max_length);
            }
            
            if (schema.min_length) {
              field.setAttribute('minlength', schema.min_length);
            }
          }
        });
        
        // Handle form submission
        form.addEventListener('submit', function(e) {
          e.preventDefault();
          
          const formData = new FormData(form);
          const data = {};
          
          // Convert FormData to object
          formData.forEach((value, key) => {
            if (key !== '_honeypot') { // Skip honeypot field
              data[key] = value;
            }
          });
          
          // Add honeypot check
          const honeypot = form.querySelector('[name="_honeypot"]');
          if (honeypot && honeypot.value) {
            console.warn('Honeypot field filled - possible spam');
            return;
          }
          
          // Show loading state
          const submitBtn = form.querySelector('[type="submit"]');
          const originalText = submitBtn.textContent;
          submitBtn.disabled = true;
          submitBtn.textContent = 'Submitting...';
          
          // Submit form
          submitForm(collection, data, options)
            .then(result => {
              // Success
              if (options.onSuccess) {
                options.onSuccess(result);
              } else {
                alert('Form submitted successfully!');
                form.reset();
              }
            })
            .catch(error => {
              // Error
              console.error('Form submission error:', error);
              if (options.onError) {
                options.onError(error);
              } else {
                alert('Error submitting form: ' + error.message);
              }
            })
            .finally(() => {
              // Reset button
              submitBtn.disabled = false;
              submitBtn.textContent = originalText;
            });
        });
      })
      .catch(error => {
        console.error('Failed to get form configuration:', error);
      });
  }

  // Expose secure form functions globally
  window.HaruSecureForm = {
    submitForm,
    getFormConfig,
    handleForm
  };
})(); 