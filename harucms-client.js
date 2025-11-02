/**
 * HaruCMS Client Library
 * 
 * Automatically connects external websites to HaruCMS
 * Handles domain authentication, API calls, and template rendering
 * 
 * Usage:
 *   <script src="https://your-cms-domain.com/harucms-client.js"></script>
 */

(function() {
    'use strict';
    
    // Configuration
    const HaruCMS = {
        // Auto-detect CMS URL from script src
        cmsUrl: (function() {
            const scripts = document.getElementsByTagName('script');
            for (let script of scripts) {
                if (script.src && script.src.includes('harucms-client.js')) {
                    const url = new URL(script.src);
                    return url.origin;
                }
            }
            // Fallback: try to detect from data attribute
            const script = document.querySelector('script[data-cms-url]');
            if (script) {
                return script.getAttribute('data-cms-url');
            }
            // Last resort: current origin
            return window.location.origin;
        })(),
        
        // Current domain
        domain: window.location.hostname,
        
        // Is localhost?
        isLocalhost: (function() {
            const hostname = window.location.hostname.toLowerCase();
            return hostname === 'localhost' || 
                   hostname === '127.0.0.1' ||
                   hostname === '::1' ||
                   hostname === '0.0.0.0' ||
                   hostname.includes('.local') ||
                   hostname.includes('.test') ||
                   hostname.includes('.dev');
        })(),
        
        // API endpoints
        publicApi: '/api/public.php',
        secureApi: '/api/secure_public.php',
        
        // Current API endpoint (will be determined)
        apiEndpoint: null,
        
        // CSRF token cache
        csrfToken: null,
        
        // Initialization flag
        initialized: false
    };
    
    /**
     * Detect which API endpoint to use
     */
    async function detectApiEndpoint() {
        if (HaruCMS.isLocalhost) {
            // Localhost always uses public API
            HaruCMS.apiEndpoint = HaruCMS.cmsUrl + HaruCMS.publicApi;
            return HaruCMS.apiEndpoint;
        }
        
        // For production, try secure API first
        // If it fails with domain not licensed, show error
        HaruCMS.apiEndpoint = HaruCMS.cmsUrl + HaruCMS.secureApi;
        return HaruCMS.apiEndpoint;
    }
    
    /**
     * Get CSRF token from CMS
     */
    async function getCSRFToken() {
        if (HaruCMS.csrfToken) {
            return HaruCMS.csrfToken;
        }
        
        try {
            const response = await fetch(HaruCMS.cmsUrl + '/api/csrf.php');
            const data = await response.json();
            
            if (data.status === 'ok' && data.data.csrf_token) {
                HaruCMS.csrfToken = data.data.csrf_token;
                return HaruCMS.csrfToken;
            }
        } catch (error) {
            console.warn('HaruCMS: Could not fetch CSRF token:', error);
        }
        
        return null;
    }
    
    /**
     * Fetch content from CMS API
     */
    async function fetchContent(action, params = {}) {
        if (!HaruCMS.apiEndpoint) {
            await detectApiEndpoint();
        }
        
        const queryParams = new URLSearchParams({
            action: action,
            ...params
        });
        
        const url = `${HaruCMS.apiEndpoint}?${queryParams.toString()}`;
        
        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `API request failed: ${response.status}`);
            }
            
            const data = await response.json();
            
            // Handle domain not licensed error
            if (data.status === 'error' && data.code === 'DOMAIN_NOT_LICENSED') {
                showDomainError(data.message);
                return null;
            }
            
            return data;
        } catch (error) {
            console.error('HaruCMS API Error:', error);
            
            // If secure API fails and not localhost, try public API as fallback
            if (!HaruCMS.isLocalhost && HaruCMS.apiEndpoint.includes('secure_public')) {
                console.warn('HaruCMS: Secure API failed, trying public API...');
                HaruCMS.apiEndpoint = HaruCMS.cmsUrl + HaruCMS.publicApi;
                return fetchContent(action, params);
            }
            
            return null;
        }
    }
    
    /**
     * Submit data to CMS (for forms)
     */
    async function submitData(action, data = {}) {
        if (!HaruCMS.apiEndpoint) {
            await detectApiEndpoint();
        }
        
        // Use secure forms API for writes
        const apiUrl = HaruCMS.cmsUrl + '/api/secure_forms.php';
        
        // Get CSRF token
        const csrfToken = await getCSRFToken();
        
        const payload = {
            action: action,
            ...data,
            _csrf: csrfToken
        };
        
        try {
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            });
            
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `Submission failed: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('HaruCMS Submit Error:', error);
            return {
                status: 'error',
                message: error.message
            };
        }
    }
    
    /**
     * Show error message when domain is not registered
     */
    function showDomainError(message) {
        const errorDiv = document.createElement('div');
        errorDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #f44336;
            color: white;
            padding: 16px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 10000;
            max-width: 400px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 14px;
        `;
        errorDiv.innerHTML = `
            <strong>HaruCMS Domain Error</strong><br>
            ${message}<br>
            <small style="opacity: 0.9;">Please register your domain in the HaruCMS admin panel.</small>
        `;
        document.body.appendChild(errorDiv);
        
        // Remove after 10 seconds
        setTimeout(() => {
            errorDiv.remove();
        }, 10000);
    }
    
    /**
     * Parse template syntax and render with data
     */
    function renderTemplate(template, data) {
        let output = template;
        
        // Handle {for each item in 'collection'} loops
        const loopRegex = /\{for\s+(?:each\s+)?([^\s]+)\s+in\s+['"]([^'"]+)['"]\}/g;
        output = output.replace(loopRegex, (match, itemVar, collectionName) => {
            const items = data[collectionName] || [];
            if (!Array.isArray(items)) return '';
            
            // Find the matching {endfor} or {endforeach}
            const loopContent = extractLoopContent(output, match);
            if (!loopContent) return '';
            
            // Render each item
            return items.map(item => {
                let itemOutput = loopContent;
                
                // Replace {{ item.property }} with actual values
                itemOutput = itemOutput.replace(/\{\{\s*([^\s}]+)\.([^\s}]+)(?:\s*\|\s*([^\s}]+)(?::\s*"([^"]+)")?)?\s*\}\}/g, (varMatch, itemName, prop, filter, filterValue) => {
                    if (itemName !== itemVar) return varMatch;
                    
                    let value = item[prop] || '';
                    
                    // Apply filters
                    if (filter === 'date' && filterValue) {
                        value = formatDate(value, filterValue);
                    } else if (filter === 'raw') {
                        return value; // Don't escape
                    } else if (filter === 'escape' || !filter) {
                        value = escapeHtml(value);
                    }
                    
                    return value;
                });
                
                // Replace {{ variable }} (non-item variables)
                itemOutput = itemOutput.replace(/\{\{\s*([^.\s}]+)(?:\s*\|\s*([^\s}]+)(?::\s*"([^"]+)")?)?\s*\}\}/g, (varMatch, varName, filter, filterValue) => {
                    // Skip if it contains a dot (it's an item property)
                    if (varMatch.includes('.')) return varMatch;
                    
                    let value = data[varName] || '';
                    
                    if (filter === 'date' && filterValue) {
                        value = formatDate(value, filterValue);
                    } else if (filter === 'raw') {
                        return value;
                    } else if (filter === 'escape' || !filter) {
                        value = escapeHtml(value);
                    }
                    
                    return value;
                });
                
                // Handle conditionals inside loop
                itemOutput = renderConditionals(itemOutput, item, itemVar);
                
                return itemOutput;
            }).join('');
        });
        
        // Replace {{ variable }} outside loops
        output = output.replace(/\{\{\s*([^.\s}]+)(?:\s*\|\s*([^\s}]+)(?::\s*"([^"]+)")?)?\s*\}\}/g, (match, varName, filter, filterValue) => {
            let value = data[varName] || '';
            
            if (filter === 'date' && filterValue) {
                value = formatDate(value, filterValue);
            } else if (filter === 'raw') {
                return value;
            } else if (filter === 'escape' || !filter) {
                value = escapeHtml(value);
            }
            
            return value;
        });
        
        // Handle conditionals
        output = renderConditionals(output, data);
        
        return output;
    }
    
    /**
     * Extract loop content between {for} and {endfor}
     */
    function extractLoopContent(template, startMatch) {
        const startPos = template.indexOf(startMatch);
        if (startPos === -1) return null;
        
        const afterStart = template.substring(startPos + startMatch.length);
        let depth = 1;
        let pos = 0;
        let content = '';
        
        while (pos < afterStart.length && depth > 0) {
            const nextFor = afterStart.indexOf('{for', pos);
            const nextEndFor = afterStart.indexOf('{endfor', pos);
            const nextEndForEach = afterStart.indexOf('{endforeach', pos);
            
            let nextEnd = -1;
            if (nextEndFor !== -1 && (nextEndForEach === -1 || nextEndFor < nextEndForEach)) {
                nextEnd = nextEndFor;
            } else if (nextEndForEach !== -1) {
                nextEnd = nextEndForEach;
            }
            
            if (nextEnd !== -1 && (nextFor === -1 || nextEnd < nextFor)) {
                content += afterStart.substring(pos, nextEnd);
                depth--;
                if (depth === 0) {
                    return content;
                }
                pos = nextEnd + (afterStart.indexOf('{endfor', pos) !== -1 ? 7 : 11);
            } else if (nextFor !== -1) {
                content += afterStart.substring(pos, nextFor);
                depth++;
                pos = nextFor + 4;
            } else {
                break;
            }
        }
        
        return null;
    }
    
    /**
     * Render conditionals {if} ... {endif}
     */
    function renderConditionals(template, data, itemPrefix = '') {
        let output = template;
        
        // Handle {if variable exist}
        output = output.replace(/\{if\s+([^}]+)\s+exist\}/g, (match, varPath) => {
            const value = getNestedValue(data, varPath);
            return value !== null && value !== undefined && value !== '' ? '' : '<!--SKIP-->';
        });
        
        // Handle {endif}
        output = output.replace(/\{endif\}/g, '<!--ENDIF-->');
        output = output.replace(/\{endfor\}/g, '');
        output = output.replace(/\{endforeach\}/g, '');
        
        // Remove content between <!--SKIP--> and <!--ENDIF-->
        output = output.replace(/<!--SKIP-->[\s\S]*?<!--ENDIF-->/g, '');
        
        return output;
    }
    
    /**
     * Get nested value from object (e.g., "entry.title")
     */
    function getNestedValue(obj, path) {
        const parts = path.split('.');
        let value = obj;
        for (let part of parts) {
            if (value && typeof value === 'object') {
                value = value[part];
            } else {
                return null;
            }
        }
        return value;
    }
    
    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        if (text == null) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Format date
     */
    function formatDate(dateString, format) {
        if (!dateString) return '';
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return dateString;
        
        // Simple date formatting (can be enhanced)
        const map = {
            'Y': date.getFullYear(),
            'm': String(date.getMonth() + 1).padStart(2, '0'),
            'd': String(date.getDate()).padStart(2, '0'),
            'F': date.toLocaleString('en-US', { month: 'long' }),
            'j': date.getDate(),
            'y': String(date.getFullYear()).slice(-2)
        };
        
        return format.replace(/([YmjFdy])/g, (match) => map[match] || match);
    }
    
    /**
     * Process all templates in the page
     */
    async function processTemplates() {
        // Check for URL parameters that might indicate we need a specific entry
        urlParams = new URLSearchParams(window.location.search);
        const slug = urlParams.get('slug');
        let collection = urlParams.get('collection') || detectCollectionFromTemplate();
        
        // If no collection detected but we have a slug, default to 'blog'
        if (slug && !collection) {
            collection = 'blog';
        }
        
        // If we have a slug parameter, fetch that specific entry first
        if (slug && collection) {
            try {
                const entry = await getEntryBySlug(collection, slug);
                if (entry) {
                    // Prepare data with the specific entry
                    const data = {
                        site_title: document.title || 'My Website',
                        current_year: new Date().getFullYear(),
                        title: entry.title || '',
                        content: entry.content || '',
                        slug: entry.slug || slug,
                        created_at: entry.created_at || '',
                        excerpt: entry.excerpt || '',
                        tags: entry.tags || [],
                        ...entry // Spread all entry properties for direct access
                    };
                    
                    // Find and process templates
                    const walker = document.createTreeWalker(
                        document.body,
                        NodeFilter.SHOW_TEXT,
                        null,
                        false
                    );
                    
                    const templates = [];
                    let node;
                    
                    while (node = walker.nextNode()) {
                        if (node.nodeValue && /\{\{|\{for|\{if/.test(node.nodeValue)) {
                            const parent = node.parentElement;
                            if (!parent || parent.dataset.cmsProcessed) continue;
                            
                            parent.dataset.cmsProcessed = 'true';
                            templates.push({
                                element: parent,
                                originalHtml: parent.innerHTML,
                                template: parent.innerHTML
                            });
                        }
                    }
                    
                    // Render each template with the entry data
                    for (let template of templates) {
                        try {
                            const rendered = renderTemplate(template.template, data);
                            template.element.innerHTML = rendered;
                            // Update page title if title variable exists
                            if (entry.title && template.template.includes('{{ title }}')) {
                                document.title = entry.title + (document.title.includes(' - ') ? document.title.substring(document.title.indexOf(' - ')) : '');
                            }
                        } catch (error) {
                            console.error('HaruCMS: Error processing template:', error);
                        }
                    }
                    
                    return; // Done processing single entry
                }
            } catch (error) {
                console.warn('HaruCMS: Could not fetch entry by slug:', error);
                // Fall through to normal template processing
            }
        }
        
        // Normal template processing for lists/loops
        const walker = document.createTreeWalker(
            document.body,
            NodeFilter.SHOW_TEXT,
            null,
            false
        );
        
        const templates = [];
        let node;
        
        while (node = walker.nextNode()) {
            if (node.nodeValue && /\{\{|\{for|\{if/.test(node.nodeValue)) {
                // Found template syntax
                const parent = node.parentElement;
                if (!parent || parent.dataset.cmsProcessed) continue;
                
                parent.dataset.cmsProcessed = 'true';
                templates.push({
                    element: parent,
                    originalHtml: parent.innerHTML,
                    template: parent.innerHTML
                });
            }
        }
        
        // Process each template
        for (let template of templates) {
            try {
                // Extract collection names from template
                const collectionMatches = template.template.match(/\{for\s+(?:each\s+)?[^\s]+\s+in\s+['"]([^'"]+)['"]\}/g);
                
                if (collectionMatches) {
                    const collections = [];
                    collectionMatches.forEach(match => {
                        const collectionName = match.match(/['"]([^'"]+)['"]/)[1];
                        if (collections.indexOf(collectionName) === -1) {
                            collections.push(collectionName);
                        }
                    });
                    
                    // Fetch data for all collections
                    const data = {
                        site_title: document.title || 'My Website',
                        current_year: new Date().getFullYear()
                    };
                    
                    for (let collectionName of collections) {
                        const apiData = await fetchContent('list_entries', { collection: collectionName });
                        if (apiData && apiData.status === 'ok' && apiData.data.entries) {
                            data[collectionName] = apiData.data.entries;
                        } else {
                            data[collectionName] = [];
                        }
                    }
                    
                    // Render template
                    const rendered = renderTemplate(template.template, data);
                    template.element.innerHTML = rendered;
                } else {
                    // No collections, just render variables
                    const data = {
                        site_title: document.title || 'My Website',
                        current_year: new Date().getFullYear()
                    };
                    const rendered = renderTemplate(template.template, data);
                    template.element.innerHTML = rendered;
                }
            } catch (error) {
                console.error('HaruCMS: Error processing template:', error);
            }
        }
    }
    
    // Store urlParams for use in detectCollectionFromTemplate
    let urlParams = null;
    
    /**
     * Detect collection name from template or URL
     */
    function detectCollectionFromTemplate() {
        // Try to extract from template
        const bodyText = document.body.innerHTML;
        const match = bodyText.match(/\{for\s+(?:each\s+)?[^\s]+\s+in\s+['"]([^'"]+)['"]\}/);
        if (match) {
            return match[1];
        }
        
        // Try to extract from URL path
        const path = window.location.pathname;
        const pathMatch = path.match(/\/([^\/]+)\/(?:post|entry|article)/i);
        if (pathMatch) {
            return pathMatch[1];
        }
        
        // Check if we're on a post/entry/article page
        const filename = path.split('/').pop() || '';
        if (filename.includes('post') || filename.includes('entry') || filename.includes('article')) {
            // Default to 'blog' for post pages, or check if there's a collection hint in the URL
            if (path.includes('/blog')) return 'blog';
            // Common default for post pages
            return 'blog';
        }
        
        // Common defaults based on path
        if (path.includes('/blog') || path.includes('blog')) return 'blog';
        if (path.includes('/post')) return 'blog';
        
        // Last resort: if we're on a page with slug parameter, default to 'blog'
        if (urlParams && urlParams.get('slug')) {
            return 'blog';
        }
        
        return null;
    }
    
    /**
     * Get entry by slug (helper function)
     */
    async function getEntryBySlug(collection, slug) {
        const data = await fetchContent('list_entries', { collection: collection });
        if (data && data.status === 'ok' && data.data.entries) {
            return data.data.entries.find(entry => entry.slug === slug);
        }
        return null;
    }
    
    /**
     * Initialize HaruCMS
     */
    async function init() {
        if (HaruCMS.initialized) return;
        
        HaruCMS.initialized = true;
        
        // Detect API endpoint
        await detectApiEndpoint();
        
        // Process templates when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', processTemplates);
        } else {
            processTemplates();
        }
    }
    
    /**
     * Public API
     */
    window.HaruCMS = {
        /**
         * Initialize (called automatically, but can be called manually)
         */
        init: init,
        
        /**
         * Fetch content from CMS
         */
        fetch: fetchContent,
        
        /**
         * Submit data to CMS (for forms)
         */
        submit: submitData,
        
        /**
         * Get entry by slug
         */
        async getEntryBySlug(collection, slug) {
            const data = await fetchContent('list_entries', { collection: collection });
            if (data && data.status === 'ok' && data.data.entries) {
                return data.data.entries.find(entry => entry.slug === slug);
            }
            return null;
        },
        
        /**
         * Render template manually
         */
        render: renderTemplate,
        
        /**
         * Process all templates in page
         */
        processTemplates: processTemplates,
        
        /**
         * Get current configuration
         */
        getConfig: () => ({
            cmsUrl: HaruCMS.cmsUrl,
            domain: HaruCMS.domain,
            isLocalhost: HaruCMS.isLocalhost,
            apiEndpoint: HaruCMS.apiEndpoint
        })
    };
    
    // Auto-initialize
    init();
})();
