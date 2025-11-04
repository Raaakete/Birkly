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
        
        // Preserve entry block placeholders - extract and restore them after processing
        const entryBlockPlaceholders = [];
        const entryBlockRegex = /<div[^>]*class="haru-entry-block"[^>]*>.*?<\/div>/g;
        let match;
        while ((match = entryBlockRegex.exec(output)) !== null) {
            entryBlockPlaceholders.push({
                placeholder: match[0],
                index: match.index,
                uniqueId: 'ENTRY_BLOCK_PLACEHOLDER_' + entryBlockPlaceholders.length
            });
        }
        
        // Replace entry blocks with unique placeholders that won't be processed
        entryBlockPlaceholders.forEach((block, index) => {
            output = output.replace(block.placeholder, block.uniqueId);
        });
        
        // Handle {foreach array as item} loops (for arrays like tags)
        // We need to process these BEFORE the collection loops to avoid conflicts
        // Use a while loop to process all foreach loops
        let foreachProcessed = true;
        while (foreachProcessed) {
            foreachProcessed = false;
            const foreachRegex = /\{foreach\s+([^\s]+)\s+as\s+([^\s]+)\}/;
            const match = output.match(foreachRegex);
            if (match) {
                foreachProcessed = true;
                const fullMatch = match[0];
                const arrayName = match[1];
                const itemVar = match[2];
                const array = getNestedValue(data, arrayName) || [];
                
                if (!Array.isArray(array)) {
                    // Remove the entire loop (including endforeach)
                    const startPos = output.indexOf(fullMatch);
                    const endPos = output.indexOf('{endforeach', startPos);
                    if (endPos !== -1) {
                        output = output.substring(0, startPos) + output.substring(endPos + 11);
                    } else {
                        output = output.replace(fullMatch, '');
                    }
                    continue;
                }
                
                // Find the matching {endforeach}
                const startPos = output.indexOf(fullMatch);
                let pos = startPos + fullMatch.length;
                let depth = 1;
                let endPos = -1;
                
                while (pos < output.length && depth > 0) {
                    const nextForeach = output.indexOf('{foreach', pos);
                    const nextEndForeach = output.indexOf('{endforeach', pos);
                    
                    if (nextForeach !== -1 && nextForeach < nextEndForeach) {
                        depth++;
                        pos = nextForeach + 8;
                    } else if (nextEndForeach !== -1) {
                        depth--;
                        if (depth === 0) {
                            endPos = nextEndForeach;
                            break;
                        }
                        pos = nextEndForeach + 11;
                    } else {
                        break; // No matching endforeach
                    }
                }
                
                if (endPos === -1) {
                    // No matching endforeach, remove the loop start
                    output = output.replace(fullMatch, '');
                    continue;
                }
                
                const loopContent = output.substring(startPos + fullMatch.length, endPos);
                
                // Render each item
                const rendered = array.map(item => {
                    let itemOutput = loopContent;
                    
                    // Replace {{ itemVar }} with actual item value
                    itemOutput = itemOutput.replace(new RegExp(`\\{\\{\\s*${itemVar}\\s*\\}\\}`, 'g'), () => {
                        return typeof item === 'string' ? escapeHtml(item) : item;
                    });
                    
                    // Handle conditionals inside loop
                    itemOutput = renderConditionals(itemOutput, { [itemVar]: item }, itemVar);
                    
                    return itemOutput;
                }).join('');
                
                // Replace the entire loop (including endforeach) with rendered content
                output = output.substring(0, startPos) + rendered + output.substring(endPos + 11);
            }
        }
        
        // Handle {for each item in 'collection'} loops
        // Match: {for each entry in 'services'} or {for entry in 'services'}
        const loopRegex = /\{for\s+(?:each\s+)?([a-zA-Z0-9_]+)\s+in\s+['"]([^'"]+)['"]\}/g;
        output = output.replace(loopRegex, (match, itemVar, collectionName) => {
            const items = data[collectionName] || [];
            if (!Array.isArray(items)) return '';
            
            // Find the matching {endfor} or {endforeach}
            const loopContent = extractLoopContent(output, match);
            if (!loopContent) return '';
            
            // Render each item
            return items.map(item => {
                let itemOutput = loopContent;
                
                // Handle nested {for each tag} loops (without 'in' clause) - refers to item's property
                let nestedLoopProcessed = true;
                while (nestedLoopProcessed) {
                    nestedLoopProcessed = false;
                    const nestedLoopRegex = /\{for\s+each\s+([^\s}]+)\}/g;
                    const nestedMatch = nestedLoopRegex.exec(itemOutput);
                    if (nestedMatch) {
                        nestedLoopProcessed = true;
                        const fullMatch = nestedMatch[0];
                        const propName = nestedMatch[1];
                        
                        // Check if it's a property of the current item (e.g., tags)
                        const array = item[propName] || [];
                        if (!Array.isArray(array)) {
                            // Remove the entire loop
                            const startPos = itemOutput.indexOf(fullMatch);
                            const endPos = itemOutput.indexOf('{endfor', startPos);
                            if (endPos !== -1) {
                                itemOutput = itemOutput.substring(0, startPos) + itemOutput.substring(endPos + 7);
                            } else {
                                itemOutput = itemOutput.replace(fullMatch, '');
                            }
                            nestedLoopRegex.lastIndex = 0; // Reset regex
                            continue;
                        }
                        
                        // Find the matching {endfor}
                        const startPos = itemOutput.indexOf(fullMatch);
                        let pos = startPos + fullMatch.length;
                        let depth = 1;
                        let endPos = -1;
                        
                        while (pos < itemOutput.length && depth > 0) {
                            const nextFor = itemOutput.indexOf('{for', pos);
                            const nextEndFor = itemOutput.indexOf('{endfor', pos);
                            
                            if (nextFor !== -1 && nextFor < nextEndFor) {
                                depth++;
                                pos = nextFor + 4;
                            } else if (nextEndFor !== -1) {
                                depth--;
                                if (depth === 0) {
                                    endPos = nextEndFor;
                                    break;
                                }
                                pos = nextEndFor + 7;
                            } else {
                                break;
                            }
                        }
                        
                        if (endPos === -1) {
                            itemOutput = itemOutput.replace(fullMatch, '');
                            nestedLoopRegex.lastIndex = 0;
                            continue;
                        }
                        
                        const nestedLoopContent = itemOutput.substring(startPos + fullMatch.length, endPos);
                        
                        // Render each array item
                        const rendered = array.map(tagItem => {
                            let tagOutput = nestedLoopContent;
                            // Replace { tag } with actual tag value
                            tagOutput = tagOutput.replace(new RegExp(`\\{\\s*${propName}\\s*\\}`, 'g'), () => {
                                return typeof tagItem === 'string' ? escapeHtml(tagItem) : tagItem;
                            });
                            return tagOutput;
                        }).join('');
                        
                        // Replace the nested loop with rendered content
                        itemOutput = itemOutput.substring(0, startPos) + rendered + itemOutput.substring(endPos + 7);
                        nestedLoopRegex.lastIndex = 0; // Reset regex
                    }
                }
                
                // First, handle nested foreach loops (like {foreach entry.tags as tag})
                let foreachProcessed = true;
                while (foreachProcessed) {
                    foreachProcessed = false;
                    const foreachRegex = /\{foreach\s+([a-zA-Z0-9_\.]+)\s+as\s+([a-zA-Z0-9_]+)\}/;
                    const match = itemOutput.match(foreachRegex);
                    if (match) {
                        foreachProcessed = true;
                        const fullMatch = match[0];
                        const arrayPath = match[1];
                        const tagVar = match[2];
                        
                        // Check if it's a property of the current item (e.g., entry.tags)
                        if (arrayPath.startsWith(itemVar + '.')) {
                            const propName = arrayPath.substring(itemVar.length + 1);
                            const array = item[propName] || [];
                            if (!Array.isArray(array)) {
                                // Remove the entire loop
                                const startPos = itemOutput.indexOf(fullMatch);
                                const endPos = itemOutput.indexOf('{endforeach', startPos);
                                if (endPos !== -1) {
                                    itemOutput = itemOutput.substring(0, startPos) + itemOutput.substring(endPos + 11);
                                } else {
                                    itemOutput = itemOutput.replace(fullMatch, '');
                                }
                                continue;
                            }
                            
                            // Find the matching {endforeach}
                            const startPos = itemOutput.indexOf(fullMatch);
                            let pos = startPos + fullMatch.length;
                            let depth = 1;
                            let endPos = -1;
                            
                            while (pos < itemOutput.length && depth > 0) {
                                const nextForeach = itemOutput.indexOf('{foreach', pos);
                                const nextEndForeach = itemOutput.indexOf('{endforeach', pos);
                                
                                if (nextForeach !== -1 && nextForeach < nextEndForeach) {
                                    depth++;
                                    pos = nextForeach + 8;
                                } else if (nextEndForeach !== -1) {
                                    depth--;
                                    if (depth === 0) {
                                        endPos = nextEndForeach;
                                        break;
                                    }
                                    pos = nextEndForeach + 11;
                                } else {
                                    break;
                                }
                            }
                            
                            if (endPos === -1) {
                                itemOutput = itemOutput.replace(fullMatch, '');
                                continue;
                            }
                            
                            const foreachContent = itemOutput.substring(startPos + fullMatch.length, endPos);
                            
                            // Render each tag/item
                            const rendered = array.map(tagItem => {
                                let tagOutput = foreachContent;
                                // Replace {{ tagVar }} with actual tag value
                                tagOutput = tagOutput.replace(new RegExp(`\\{\\{\\s*${tagVar}\\s*\\}\\}`, 'g'), () => {
                                    return typeof tagItem === 'string' ? escapeHtml(tagItem) : tagItem;
                                });
                                return tagOutput;
                            }).join('');
                            
                            // Replace the foreach loop with rendered content
                            itemOutput = itemOutput.substring(0, startPos) + rendered + itemOutput.substring(endPos + 11);
                        } else {
                            // Not for this item, skip it
                            break;
                        }
                    }
                }
                
                // Replace {{ item.property }} with actual values
                // Fixed regex: variable and property names should not include pipe character
                itemOutput = itemOutput.replace(/\{\{\s*([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)(?:\s*\|\s*([a-zA-Z0-9_]+)(?:\s*"([^"]+)")?)?\s*\}\}/g, (varMatch, itemName, prop, filter, filterValue) => {
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
                
                // Support single-brace variables for current item: { title }, { slug }, etc.
                // Skip control structures like {if, {for, {endfor}
                // Handle variables with spaces: { title } -> title
                itemOutput = itemOutput.replace(/\{\s*([a-zA-Z0-9_]+)\s*\}/g, (m, name) => {
                    name = name.trim(); // Trim whitespace
                    const lowers = name.toLowerCase();
                    if (['if','for','endif','endfor','foreach','endforeach','from','get','has','include','media','url','count'].includes(lowers)) return m;
                    // Prefer current item property
                    if (item && Object.prototype.hasOwnProperty.call(item, name)) {
                        if (name === 'content') return (item[name] || ''); // auto-raw for editor content
                        return escapeHtml(item[name] || '');
                    }
                    // Fallback to outer data
                    if (data && Object.prototype.hasOwnProperty.call(data, name)) {
                        if (name === 'content') return (data[name] || '');
                        return escapeHtml(data[name] || '');
                    }
                    return '';
                });
                
                // Replace {{ variable }} (non-item variables)
                itemOutput = itemOutput.replace(/\{\{\s*([a-zA-Z0-9_]+)(?:\s*\|\s*([a-zA-Z0-9_]+)(?::\s*"([^"]+)")?)?\s*\}\}/g, (varMatch, varName, filter, filterValue) => {
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
                // Create a combined data object that includes the item with both its variable name and as 'entry'
                const loopData = {
                    ...data,
                    [itemVar]: item,
                    entry: item // Also available as 'entry' for conditionals like {if entry.status == 'published'}
                };
                itemOutput = renderConditionals(itemOutput, loopData, itemVar);
                
                return itemOutput;
            }).join('');
        });
        
        // NOTE: {entry for} blocks are already processed in processTemplates() BEFORE renderTemplate is called
        // They are replaced with placeholders in the DOM, so we don't need to process them here
        
        // Handle {public_form 'collection'} directive - replace with placeholder
        // We'll process these after template rendering is complete
        output = output.replace(/\{public_form\s+['"]([^'"]+)['"]\}/g, (match, collectionSlug) => {
            const placeholderId = 'haru-form-placeholder-' + Math.random().toString(36).substr(2, 9);
            // Store form info in data attribute for later processing
            return '<div id="' + placeholderId + '" data-haru-collection="' + escapeHtml(collectionSlug) + '" class="haru-form-placeholder">Loading form...</div>';
        });
        
        // Replace {{ variable }} outside loops
        output = output.replace(/\{\{\s*([a-zA-Z0-9_\.]+)(?:\s*\|\s*([a-zA-Z0-9_]+)(?::\s*"([^"]+)")?)?\s*\}\}/g, (match, varName, filter, filterValue) => {
            let value = getNestedValue(data, varName) || '';
            
            if (filter === 'date' && filterValue) {
                value = formatDate(value, filterValue);
            } else if (filter === 'raw') {
                return value;
            } else if (filter === 'escape' || !filter) {
                value = escapeHtml(value);
            }
            
            return value;
        });
        
        // Support single-brace variables outside loops: { content }, { site_title }, etc.
        // Handle variables with spaces: { site_title } -> site_title
        // IMPORTANT: Process this LAST, after all directives are processed
        // Use a while loop to track positions correctly
        let lastIndex = 0;
        let result = '';
        const varRegex = /\{\s*([a-zA-Z0-9_]+)\s*(?:"([^"]+)")?\s*\}/g;
        let varMatch;
        
        while ((varMatch = varRegex.exec(output)) !== null) {
            const matchStart = varMatch.index;
            const matchEnd = varMatch.index + varMatch[0].length;
            const varName = varMatch[1].trim();
            const arg = varMatch[2];
            
            // Add everything before this match
            result += output.substring(lastIndex, matchStart);
            
            const lowers = varName.toLowerCase();
            
            // Skip if it's a directive keyword (these should already be processed)
            const directiveKeywords = ['if','for','endif','endfor','foreach','endforeach','from','get','has','include','media','url','count','public_form','entry','endentry'];
            if (directiveKeywords.includes(lowers)) {
                // Check context around match to see if it's part of a directive
                const before = output.substring(Math.max(0, matchStart - 20), matchStart);
                const after = output.substring(matchEnd, Math.min(output.length, matchEnd + 20));
                
                // If it's part of a directive, keep it as-is
                if (after.match(/^\s+(for|each|in|has|exist|==|!=|<=|>=|>|<)/) || 
                    before.match(/(entry|for|if)\s+$/)) {
                    result += varMatch[0]; // Keep directive as-is
                } else {
                    // It's a standalone directive keyword, keep it
                    result += varMatch[0];
                }
            } else {
                // It's a regular variable - replace it
                let value = getNestedValue(data, varName) || '';
                if (varName === 'content' || /\.content$/.test(varName)) {
                    result += value; // auto-raw
                } else if (varName === 'date' && arg) {
                    result += formatDate(value, arg);
                } else {
                    result += escapeHtml(value);
                }
            }
            
            lastIndex = matchEnd;
        }
        
        // Add remaining content
        result += output.substring(lastIndex);
        output = result;
        
        // Handle conditionals
        output = renderConditionals(output, data);
        
        // Restore entry block placeholders
        entryBlockPlaceholders.forEach((block) => {
            output = output.replace(block.uniqueId, block.placeholder);
        });
        
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
        
        // Handle {if variable == 'value'} comparisons
        output = output.replace(/\{if\s+([^}]+)\s+==\s+['"]([^'"]+)['"]\}/g, (match, varPath, expectedValue) => {
            const value = getNestedValue(data, varPath);
            const stringValue = String(value || '');
            return stringValue === expectedValue ? '' : '<!--SKIP-->';
        });
        
        // Handle {if variable has property} (e.g., {if entry has tags})
        output = output.replace(/\{if\s+([^\s]+)\s+has\s+([^\s}]+)\}/g, (match, objPath, propName) => {
            const obj = getNestedValue(data, objPath);
            const hasProp = obj && typeof obj === 'object' && (obj[propName] !== undefined && obj[propName] !== null && obj[propName] !== '');
            return hasProp ? '' : '<!--SKIP-->';
        });
        
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
     * Fetch site settings from API
     */
    async function fetchSiteSettings() {
        try {
            const apiData = await fetchContent('get_site_settings', {});
            if (apiData && apiData.status === 'ok' && apiData.data.settings) {
                return apiData.data.settings;
            }
        } catch (error) {
            console.warn('HaruCMS: Could not fetch site settings:', error);
        }
        return {
            title: 'HaruCMS',
            name: 'HaruCMS',
            description: ''
        };
    }
    
    /**
     * Process all templates in the page
     */
    async function processTemplates() {
        // Fetch site settings first
        const siteSettings = await fetchSiteSettings();
        const siteTitle = siteSettings.name || siteSettings.title || 'HaruCMS';
        
        // Check for URL parameters that might indicate we need a specific entry
        urlParams = new URLSearchParams(window.location.search);
        const slug = urlParams.get('slug');
        let collection = urlParams.get('collection');
        
        // If no collection specified, detect from template or URL
        if (!collection) {
            collection = detectCollectionFromTemplate();
        }
        
        // IMPORTANT: For demo-website website, the collection is always 'blog', not 'demo-website'
        // The '/demo-website/' path is just the website folder, not the collection name
        if (window.location.pathname.includes('/demo-website/')) {
            // If collection was detected as 'demo-website', change it to 'blog'
            if (collection === 'demo-website') {
                collection = 'blog';
            }
            // If no collection detected, default to 'blog' for demo blog
            if (!collection) {
                collection = 'blog';
            }
        }
        
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
                        site_title: siteTitle,
                        current_year: new Date().getFullYear(),
                        title: entry.title || '',
                        content: entry.content || '',
                        slug: entry.slug || slug,
                        created_at: entry.created_at || '',
                        excerpt: entry.excerpt || '',
                        tags: entry.tags || [],
                        entry: entry, // Add entry object for conditionals
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
                } else {
                    // Entry not found - show error message and process any remaining templates
                    const main = document.querySelector('main');
                    if (main) {
                        main.innerHTML = `
                            <div style="text-align: center; padding: 3rem;">
                                <h1 style="font-size: 2rem; margin-bottom: 1rem;">Post Not Found</h1>
                                <p style="color: #6b7280; margin-bottom: 2rem;">The post you're looking for doesn't exist.</p>
                                <a href="/demo-website/index.html" style="color: #3b82f6; text-decoration: none; font-weight: 600;">← Back to Home</a>
                            </div>
                        `;
                    }
                    
                    // Process remaining templates (header, footer) with default data
                    const defaultData = {
                        site_title: siteTitle,
                        current_year: new Date().getFullYear()
                    };
                    
                    // Process header and footer templates
                    const walker = document.createTreeWalker(
                        document.body,
                        NodeFilter.SHOW_TEXT,
                        null,
                        false
                    );
                    
                    const templates = [];
                    let node;
                    
                    while (node = walker.nextNode()) {
                        if (node.nodeValue && /\{[a-zA-Z0-9_\.\s'"]+\}/.test(node.nodeValue)) {
                            const parent = node.parentElement;
                            if (!parent || parent.dataset.cmsProcessed) continue;
                            
                            parent.dataset.cmsProcessed = 'true';
                            templates.push({
                                element: parent,
                                template: parent.innerHTML
                            });
                        }
                    }
                    
                    // Render templates with default data
                    for (let template of templates) {
                        try {
                            const rendered = renderTemplate(template.template, defaultData);
                            template.element.innerHTML = rendered;
                        } catch (error) {
                            console.error('HaruCMS: Error processing template:', error);
                        }
                    }
                    
                    return;
                }
            } catch (error) {
                console.error('HaruCMS: Could not fetch entry by slug:', error);
                console.error('HaruCMS: Error details:', {
                    slug: slug,
                    collection: collection,
                    error: error.message || error
                });
                
                // Show error message and process remaining templates
                const main = document.querySelector('main');
                if (main) {
                    main.innerHTML = `
                        <div style="text-align: center; padding: 3rem;">
                            <h1 style="font-size: 2rem; margin-bottom: 1rem;">Error Loading Post</h1>
                            <p style="color: #6b7280; margin-bottom: 2rem;">Could not load the post. Please try again later.</p>
                            <a href="/demo-website/index.html" style="color: #3b82f6; text-decoration: none; font-weight: 600;">← Back to Home</a>
                        </div>
                    `;
                }
                
                // Process remaining templates (header, footer) with default data
                const defaultData = {
                    site_title: siteTitle,
                    current_year: new Date().getFullYear()
                };
                
                const walker = document.createTreeWalker(
                    document.body,
                    NodeFilter.SHOW_TEXT,
                    null,
                    false
                );
                
                const templates = [];
                let node;
                
                while (node = walker.nextNode()) {
                    if (node.nodeValue && /\{[a-zA-Z0-9_\.\s'"]+\}/.test(node.nodeValue)) {
                        const parent = node.parentElement;
                        if (!parent || parent.dataset.cmsProcessed) continue;
                        
                        parent.dataset.cmsProcessed = 'true';
                        templates.push({
                            element: parent,
                            template: parent.innerHTML
                        });
                    }
                }
                
                // Render templates with default data
                for (let template of templates) {
                    try {
                        const rendered = renderTemplate(template.template, defaultData);
                        template.element.innerHTML = rendered;
                    } catch (error) {
                        console.error('HaruCMS: Error processing template:', error);
                    }
                }
                
                return;
            }
        }
        
        // STEP 1: Process {entry for 'collection'} blocks FIRST before template detection
        // This prevents the form HTML inside from being detected as separate templates
        const entryBlocks = document.querySelectorAll('*');
        for (const el of entryBlocks) {
            const innerHTML = el.innerHTML || '';
            // Check if this element contains {entry for} directive
            if (innerHTML.includes('{entry for') && innerHTML.includes('{endentry}')) {
                const entryForRegex = /\{entry\s+for\s+['"]([^'"]+)['"]\}/;
                const entryForMatch = innerHTML.match(entryForRegex);
                if (entryForMatch) {
                    const collectionSlug = entryForMatch[1];
                    const startPos = innerHTML.indexOf(entryForMatch[0]);
                    const afterStart = innerHTML.substring(startPos + entryForMatch[0].length);
                    const endPos = afterStart.indexOf('{endentry}');
                    
                    if (endPos !== -1) {
                        const formContent = afterStart.substring(0, endPos);
                        const placeholderId = 'haru-entry-block-' + Math.random().toString(36).substr(2, 9);
                        const placeholder = '<div id="' + placeholderId + '" data-haru-collection="' + escapeHtml(collectionSlug) + '" data-haru-content="' + escapeHtml(formContent) + '" class="haru-entry-block" data-haru-preserved="false">Loading form...</div>';
                        
                        // Replace the entire {entry for}...{endentry} block with placeholder
                        const beforeBlock = innerHTML.substring(0, startPos);
                        const afterBlock = innerHTML.substring(startPos + entryForMatch[0].length + endPos + 9);
                        el.innerHTML = beforeBlock + placeholder + afterBlock;
                        
                        // Mark this element as processed to avoid detecting it again
                        el.dataset.cmsProcessed = 'true';
                        console.log('HaruCMS: Processed {entry for} block for collection:', collectionSlug);
                    }
                }
            }
        }
        
        // STEP 2: Normal template processing for lists/loops
        // Find ALL elements with template syntax (including in attributes)
        // We need to check both text nodes AND attribute values
        const templates = [];
        
        // Method 1: Check text nodes (for content)
        const walker = document.createTreeWalker(
            document.body,
            NodeFilter.SHOW_TEXT,
            null,
            false
        );
        
        let node;
        while (node = walker.nextNode()) {
            const nodeValue = node.nodeValue || '';
            // Check for template syntax - look for { or {{ followed by alphanumeric
            // Skip if inside .haru-entry-block (already processed)
            const parent = node.parentElement;
            if (parent && parent.classList && parent.classList.contains('haru-entry-block')) {
                continue; // Skip - already processed as entry block
            }
            
            if (nodeValue && (/\{[a-zA-Z0-9_]/ || /\{\{/).test(nodeValue)) {
                // Found template syntax in text (single or double braces)
                if (!parent || parent.dataset.cmsProcessed) continue;
                
                // Skip if parent contains an entry block (it will be processed separately)
                if (parent.querySelector('.haru-entry-block')) {
                    console.log('HaruCMS: Skipping template with entry block:', parent.tagName, parent.className);
                    continue;
                }
                
                parent.dataset.cmsProcessed = 'true';
                templates.push({
                    element: parent,
                    originalHtml: parent.innerHTML,
                    template: parent.innerHTML
                });
                console.log('HaruCMS: Found template in element:', parent.tagName, parent.className, 'Content:', nodeValue.substring(0, 50));
            }
        }
        
        console.log('HaruCMS: Found', templates.length, 'templates to process');
        
        // If no templates found, try a different approach - check all elements
        if (templates.length === 0) {
            console.warn('HaruCMS: No templates found via text nodes, trying alternative detection...');
            const allElementsAlt = document.body.querySelectorAll('*');
            for (const el of allElementsAlt) {
                if (el.dataset.cmsProcessed || el.classList.contains('haru-entry-block')) continue;
                
                const innerHTML = el.innerHTML || '';
                if (innerHTML && (/\{[a-zA-Z0-9_]/ || /\{\{/).test(innerHTML)) {
                    el.dataset.cmsProcessed = 'true';
                    templates.push({
                        element: el,
                        originalHtml: innerHTML,
                        template: innerHTML
                    });
                    // Check if this element contains entry blocks for logging
                    if (el.querySelector('.haru-entry-block')) {
                        console.log('HaruCMS: Found template via alternative detection:', el.tagName, el.className, '(contains entry block)');
                    } else {
                        console.log('HaruCMS: Found template via alternative detection:', el.tagName, el.className);
                    }
                }
            }
            console.log('HaruCMS: Found', templates.length, 'templates via alternative detection');
        }
        
        // Method 2: Check attributes (for href, src, etc.)
        // Find all elements that might have template syntax in attributes
        const allElements = document.body.querySelectorAll('*');
        for (let el of allElements) {
            if (el.dataset.cmsProcessed) continue;
            
            // Check all attributes for template syntax
            for (let attr of el.attributes) {
                if (/\{[a-zA-Z0-9_\.\s'"]+\}/.test(attr.value)) {
                    // Found template syntax in attribute
                    // Add parent element to templates list
                    let parent = el.parentElement;
                    if (!parent) parent = el;
                    if (!parent.dataset.cmsProcessed) {
                        parent.dataset.cmsProcessed = 'true';
                        templates.push({
                            element: parent,
                            originalHtml: parent.innerHTML,
                            template: parent.innerHTML
                        });
                    }
                    break; // Found template syntax, no need to check other attributes
                }
            }
        }
        
        // Process each template
        for (let template of templates) {
            try {
                console.log('HaruCMS: Processing template:', template.template.substring(0, 100));
                
                // Check if this template contains entry blocks - if so, preserve them in DOM
                // IMPORTANT: Only preserve entry blocks if they're DIRECT children of this template element
                // Don't preserve them if they're nested deeper (they'll be preserved by their parent template)
                const entryBlocksInDOM = template.element.querySelectorAll('.haru-entry-block');
                const preservedBlocks = [];
                
                // Filter to only direct children (not nested deeper)
                const directEntryBlocks = Array.from(entryBlocksInDOM).filter(block => {
                    // Check if this block is a direct child of template.element
                    return block.parentElement === template.element;
                });
                
                // Also check if the template HTML string contains entry block placeholders
                const hasEntryBlockInHTML = template.template.includes('haru-entry-block');
                
                if (directEntryBlocks.length > 0) {
                    console.log('HaruCMS: Found', directEntryBlocks.length, 'direct entry blocks in DOM, preserving them');
                    directEntryBlocks.forEach((block, index) => {
                        // Clone the element so we can restore it after innerHTML replacement
                        const clonedElement = block.cloneNode(true);
                        // Store the actual element reference and its position
                        preservedBlocks.push({
                            originalElement: block,
                            clonedElement: clonedElement,
                            html: block.outerHTML,
                            parent: block.parentElement,
                            nextSibling: block.nextSibling,
                            collectionSlug: block.dataset.haruCollection
                        });
                        console.log('HaruCMS: Preserved entry block', index, 'for collection:', block.dataset.haruCollection);
                    });
                } else if (hasEntryBlockInHTML && entryBlocksInDOM.length === 0) {
                    // Only extract from HTML if there are NO entry blocks in DOM at all
                    // (meaning they're in the HTML but not yet in DOM, or were removed)
                    // Entry block is in the HTML string but not in DOM - this shouldn't happen
                    // but if it does, we need to extract it from the HTML and create a DOM element
                    console.warn('HaruCMS: Template HTML contains entry block placeholder, but not found in DOM');
                    console.warn('HaruCMS: Attempting to extract entry block from HTML string');
                    
                    // Try to extract the entry block HTML from template.template
                    // Try multiple regex patterns to match the entry block
                    console.log('HaruCMS: Template HTML length:', template.template.length, 'Contains haru-entry-block:', template.template.includes('haru-entry-block'));
                    
                    let entryBlockMatch = template.template.match(/<div[^>]*class="haru-entry-block"[^>]*>.*?<\/div>/s);
                    if (!entryBlockMatch) {
                        // Try matching with class attribute in different position
                        entryBlockMatch = template.template.match(/<div[^>]*class\s*=\s*"haru-entry-block"[^>]*>.*?<\/div>/s);
                    }
                    if (!entryBlockMatch) {
                        // Try matching by ID pattern
                        entryBlockMatch = template.template.match(/<div[^>]*id="[^"]*haru-entry-block[^"]*"[^>]*>.*?<\/div>/s);
                    }
                    if (!entryBlockMatch) {
                        // Try matching with more flexible pattern
                        entryBlockMatch = template.template.match(/<div[^>]*\s+class\s*=\s*["']haru-entry-block["'][^>]*>[\s\S]*?<\/div>/);
                    }
                    
                    if (entryBlockMatch) {
                        const entryBlockHTML = entryBlockMatch[0];
                        console.log('HaruCMS: Found entry block HTML, length:', entryBlockHTML.length);
                        console.log('HaruCMS: Entry block HTML (first 500 chars):', entryBlockHTML.substring(0, 500));
                        
                        // Extract collection slug directly from HTML string to avoid parsing issues
                        const collectionSlugMatch = entryBlockHTML.match(/data-haru-collection\s*=\s*["']([^"']+)["']/);
                        const collectionSlug = collectionSlugMatch ? collectionSlugMatch[1] : null;
                        
                        if (collectionSlug) {
                            console.log('HaruCMS: Extracted collection slug from HTML:', collectionSlug);
                            // Create a proper entry block element from the HTML
                            const tempDiv = document.createElement('div');
                            // Use the exact HTML we found, but ensure it's properly formatted
                            tempDiv.innerHTML = entryBlockHTML;
                            let extractedBlock = tempDiv.firstElementChild;
                            
                            // If parsing failed, create a new element with the correct attributes
                            if (!extractedBlock || !extractedBlock.classList || !extractedBlock.classList.contains('haru-entry-block')) {
                                console.log('HaruCMS: HTML parsing failed, creating element manually');
                                extractedBlock = document.createElement('div');
                                extractedBlock.className = 'haru-entry-block';
                                extractedBlock.id = entryBlockHTML.match(/id\s*=\s*["']([^"']+)["']/)?.[1] || 'haru-entry-block-' + Math.random().toString(36).substr(2, 9);
                                extractedBlock.dataset.haruCollection = collectionSlug;
                                
                                // Extract data-haru-content from raw HTML string
                                // The content is HTML-encoded in the attribute, we need to extract it from the raw HTML
                                // The browser's parser may truncate attribute values, so extract directly from the string
                                let extractedContent = null;
                                
                                // Find the start of the data-haru-content attribute
                                const attrStart = entryBlockHTML.indexOf('data-haru-content="');
                                if (attrStart !== -1) {
                                    let contentStart = attrStart + 19; // Length of 'data-haru-content="'
                                    let i = contentStart;
                                    
                                    // Find the closing quote by character-by-character parsing
                                    // The content is HTML-encoded, so we need to find the closing quote
                                    // that comes AFTER the content, not within it
                                    // Strategy: Look for the closing quote that's followed by whitespace and then 'class="haru-entry-block"'
                                    // or the closing quote that's followed by '>'
                                    
                                    // First, find the position of 'class="haru-entry-block"' to know where the attribute should end
                                    const classAttrPos = entryBlockHTML.indexOf(' class="haru-entry-block"', attrStart);
                                    const closingTagPos = entryBlockHTML.indexOf('>', attrStart);
                                    
                                    // The closing quote should be before the class attribute or closing tag
                                    const maxPos = (classAttrPos !== -1 && closingTagPos !== -1) 
                                        ? Math.min(classAttrPos, closingTagPos) 
                                        : (classAttrPos !== -1 ? classAttrPos : closingTagPos);
                                    
                                    if (maxPos === -1) {
                                        console.warn('HaruCMS: Could not find class attribute or closing tag to determine attribute end');
                                        // Fallback: Search backwards from the end
                                        let searchEnd = entryBlockHTML.length - 1;
                                        while (searchEnd > contentStart) {
                                            if (entryBlockHTML[searchEnd] === '"') {
                                                // Check if this quote is followed by whitespace or 'class='
                                                let afterQuote = searchEnd + 1;
                                                while (afterQuote < entryBlockHTML.length && /\s/.test(entryBlockHTML[afterQuote])) {
                                                    afterQuote++;
                                                }
                                                if (entryBlockHTML.substring(afterQuote, afterQuote + 6) === 'class=' || 
                                                    entryBlockHTML.substring(afterQuote, afterQuote + 1) === '>') {
                                                    extractedContent = entryBlockHTML.substring(contentStart, searchEnd);
                                                    break;
                                                }
                                            }
                                            searchEnd--;
                                        }
                                    } else {
                                        // Search backwards from maxPos to find the closing quote
                                        // We need to find the quote that's immediately followed by whitespace and then 'class='
                                        let searchEnd = maxPos - 1;
                                        while (searchEnd > contentStart) {
                                            if (entryBlockHTML[searchEnd] === '"') {
                                                // Check if this is not part of an entity
                                                // Look backwards to see if it's &quot;
                                                if (searchEnd >= 6 && entryBlockHTML.substring(searchEnd - 6, searchEnd) === '&quot;') {
                                                    searchEnd--;
                                                    continue;
                                                }
                                                // Check if it's escaped
                                                if (searchEnd > contentStart && entryBlockHTML[searchEnd - 1] === '\\') {
                                                    searchEnd--;
                                                    continue;
                                                }
                                                // Verify this is the closing quote by checking what comes after it
                                                let afterQuote = searchEnd + 1;
                                                // Skip whitespace after the quote
                                                while (afterQuote < entryBlockHTML.length && /\s/.test(entryBlockHTML[afterQuote])) {
                                                    afterQuote++;
                                                }
                                                // Check if after whitespace we have 'class=' or '>'
                                                // The closing quote should be followed by whitespace and then 'class="haru-entry-block"'
                                                const afterWhitespace = entryBlockHTML.substring(afterQuote, afterQuote + 25);
                                                if (entryBlockHTML.substring(afterQuote, afterQuote + 6) === 'class=' || 
                                                    entryBlockHTML[afterQuote] === '>') {
                                                    // Verify this is really the closing quote by checking if we're near the class attribute
                                                    // The closing quote should be very close to 'class="haru-entry-block"'
                                                    const distanceToClass = classAttrPos - searchEnd;
                                                    if (distanceToClass > 0 && distanceToClass < 50) {
                                                        // This is the closing quote!
                                                        extractedContent = entryBlockHTML.substring(contentStart, searchEnd);
                                                        
                                                        // Aggressively remove any trailing malformed patterns
                                                        // The content might end with: button&gt;="" "="" data-haru-preserved="false
                                                        // We need to remove everything from the first quote that's followed by = or data-haru
                                                        
                                                        // First, check if content ends with data-haru-preserved and remove it
                                                        const dataHaruIndex = extractedContent.indexOf('data-haru-preserved');
                                                        if (dataHaruIndex !== -1) {
                                                            // Find the quote before data-haru-preserved
                                                            let quoteBeforeDataHaru = dataHaruIndex - 1;
                                                            while (quoteBeforeDataHaru > 0 && extractedContent[quoteBeforeDataHaru] !== '"') {
                                                                quoteBeforeDataHaru--;
                                                            }
                                                            if (quoteBeforeDataHaru > 0 && extractedContent[quoteBeforeDataHaru] === '"') {
                                                                // Check what's before this quote - if it's = or whitespace, trim from there
                                                                if (quoteBeforeDataHaru > 0 && (extractedContent[quoteBeforeDataHaru - 1] === '=' || /\s/.test(extractedContent[quoteBeforeDataHaru - 1]))) {
                                                                    // Trim from the quote before data-haru
                                                                    extractedContent = extractedContent.substring(0, quoteBeforeDataHaru);
                                                                    // Also trim any trailing = or whitespace
                                                                    extractedContent = extractedContent.replace(/\s*=\s*$/, '');
                                                                } else {
                                                                    // Just trim from data-haru-preserved
                                                                    extractedContent = extractedContent.substring(0, dataHaruIndex);
                                                                }
                                                            } else {
                                                                // Just trim from data-haru-preserved
                                                                extractedContent = extractedContent.substring(0, dataHaruIndex);
                                                            }
                                                        }
                                                        
                                                        // Remove any trailing malformed patterns
                                                        extractedContent = extractedContent.replace(/\s*=""?\s*$/, '');
                                                        extractedContent = extractedContent.replace(/\s*=\s*""?\s*$/, '');
                                                        extractedContent = extractedContent.replace(/\s*"\s*$/, '');
                                                        
                                                        break;
                                                    }
                                                }
                                            }
                                            searchEnd--;
                                        }
                                    }
                                    
                                    if (extractedContent) {
                                        // Check if the extracted content includes extra characters at the end
                                        // The last chars show: button&gt;="" "="" data-haru-preserved="false
                                        // This means we're including part of the next attribute
                                        // We need to trim it properly
                                        // The content should end before the closing quote, not include it
                                        // But we already extracted it correctly, so the issue must be elsewhere
                                        console.log('HaruCMS: Extracted content from raw HTML string, length:', extractedContent.length);
                                        console.log('HaruCMS: First 200 chars:', extractedContent.substring(0, 200));
                                        console.log('HaruCMS: Last 100 chars:', extractedContent.substring(Math.max(0, extractedContent.length - 100)));
                                        console.log('HaruCMS: Content ends with:', extractedContent.substring(Math.max(0, extractedContent.length - 20)));
                                    } else {
                                        console.warn('HaruCMS: Could not find closing quote for data-haru-content attribute. contentStart:', contentStart, 'HTML length:', entryBlockHTML.length);
                                        console.warn('HaruCMS: HTML around attribute:', entryBlockHTML.substring(Math.max(0, attrStart - 50), Math.min(entryBlockHTML.length, attrStart + 200)));
                                    }
                                } else {
                                    console.warn('HaruCMS: Could not find data-haru-content attribute in HTML string');
                                }
                                
                                if (extractedContent) {
                                    extractedBlock.dataset.haruContent = extractedContent;
                                    console.log('HaruCMS: Final extracted content length:', extractedContent.length);
                                } else {
                                    console.warn('HaruCMS: Could not extract data-haru-content from entry block HTML');
                                }
                                
                                extractedBlock.textContent = 'Loading form...';
                            }
                            
                            if (extractedBlock && extractedBlock.dataset && extractedBlock.dataset.haruCollection) {
                                // Store the extracted block
                                preservedBlocks.push({
                                    originalElement: null, // No original element in DOM
                                    clonedElement: extractedBlock.cloneNode(true),
                                    html: entryBlockHTML,
                                    parent: null,
                                    nextSibling: null,
                                    collectionSlug: extractedBlock.dataset.haruCollection
                                });
                                console.log('HaruCMS: Extracted entry block from HTML for collection:', extractedBlock.dataset.haruCollection);
                            } else {
                                console.warn('HaruCMS: Could not create valid entry block element');
                            }
                        } else {
                            console.warn('HaruCMS: Could not extract collection slug from entry block HTML');
                        }
                    } else {
                        console.warn('HaruCMS: Could not match entry block in template HTML');
                    }
                }
                
                // Extract collection names from template
                // Match: {for each entry in 'services'} or {for entry in 'services'}
                const collectionMatches = template.template.match(/\{for\s+(?:each\s+)?[a-zA-Z0-9_]+\s+in\s+['"]([^'"]+)['"]\}/g);
                
                if (collectionMatches) {
                    const collections = [];
                    collectionMatches.forEach(match => {
                        const collectionName = match.match(/['"]([^'"]+)['"]/)[1];
                        if (collections.indexOf(collectionName) === -1) {
                            collections.push(collectionName);
                        }
                    });
                    
                    console.log('HaruCMS: Found collections:', collections);
                    
                    // Fetch data for all collections
                    const data = {
                        site_title: siteTitle,
                        current_year: new Date().getFullYear()
                    };
                    
                    for (let collectionName of collections) {
                        console.log('HaruCMS: Fetching entries for collection:', collectionName);
                        const apiData = await fetchContent('list_entries', { collection: collectionName });
                        if (apiData && apiData.status === 'ok' && apiData.data.entries) {
                            data[collectionName] = apiData.data.entries;
                            console.log('HaruCMS: Fetched', apiData.data.entries.length, 'entries for', collectionName);
                        } else {
                            console.warn('HaruCMS: Failed to fetch entries for', collectionName, apiData);
                            data[collectionName] = [];
                        }
                    }
                    
                    // Render template
                    let rendered = renderTemplate(template.template, data);
                    
                    // Restore entry blocks BEFORE setting innerHTML
                    if (preservedBlocks.length > 0) {
                        console.log('HaruCMS: Restoring', preservedBlocks.length, 'entry blocks in rendered HTML before setting innerHTML');
                        
                        // Replace all placeholders in the rendered HTML string BEFORE setting innerHTML
                        preservedBlocks.forEach((block, index) => {
                            const collectionSlug = block.collectionSlug || (block.originalElement ? block.originalElement.dataset.haruCollection : null);
                            if (!collectionSlug) {
                                console.warn('HaruCMS: Entry block', index, 'has no collection slug');
                                return;
                            }
                            
                            // Clone the element
                            const restoredElement = block.clonedElement.cloneNode(true);
                            restoredElement.setAttribute('data-haru-preserved', 'true');
                            
                            // Try to find the placeholder in the rendered HTML and replace it
                            const placeholderIdMatch = block.html.match(/id="([^"]*haru-entry-block[^"]*)"/);
                            if (placeholderIdMatch) {
                                const placeholderId = placeholderIdMatch[1];
                                // Try to find placeholder by ID in rendered HTML
                                const placeholderRegex = new RegExp('<div[^>]*id="' + placeholderId.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '"[^>]*>.*?<\/div>', 's');
                                if (rendered.match(placeholderRegex)) {
                                    // Replace placeholder with actual block
                                    rendered = rendered.replace(placeholderRegex, restoredElement.outerHTML);
                                    console.log('HaruCMS: Entry block', index, 'replaced in rendered HTML for collection:', collectionSlug);
                                    return;
                                }
                            }
                            
                            // Fallback: Try to find any placeholder and replace it
                            const placeholderFullRegex = /<div[^>]*class="haru-entry-block"[^>]*>.*?<\/div>/s;
                            if (rendered.match(placeholderFullRegex)) {
                                rendered = rendered.replace(placeholderFullRegex, restoredElement.outerHTML);
                                console.log('HaruCMS: Entry block', index, 'replaced in rendered HTML (fallback) for collection:', collectionSlug);
                                return;
                            }
                            
                            console.warn('HaruCMS: Entry block', index, 'placeholder not found in rendered HTML for collection:', collectionSlug);
                        });
                    }
                    
                    // Set innerHTML only once after all placeholders are replaced
                    template.element.innerHTML = rendered;
                    
                    // Verify entry blocks exist in DOM after setting innerHTML
                    if (preservedBlocks.length > 0) {
                        preservedBlocks.forEach((block, index) => {
                            const collectionSlug = block.collectionSlug || (block.originalElement ? block.originalElement.dataset.haruCollection : null);
                            if (!collectionSlug) return;
                            
                            const existingBlock = template.element.querySelector('.haru-entry-block[data-haru-collection="' + collectionSlug + '"]');
                            if (existingBlock) {
                                console.log('HaruCMS: Entry block', index, 'verified in DOM for collection:', collectionSlug);
                            } else {
                                console.warn('HaruCMS: Entry block', index, 'NOT found in DOM after restoration for collection:', collectionSlug);
                                // Last resort: Insert into DOM
                                const restoredElement = block.clonedElement.cloneNode(true);
                                restoredElement.setAttribute('data-haru-preserved', 'true');
                                
                                const container = template.element;
                                if (block.nextSibling && block.nextSibling.parentElement === container) {
                                    container.insertBefore(restoredElement, block.nextSibling);
                                } else {
                                    container.appendChild(restoredElement);
                                }
                                console.log('HaruCMS: Entry block', index, 'inserted into DOM as fallback');
                            }
                        });
                    }
                    console.log('HaruCMS: Template rendered successfully');
                } else {
                    // No collections, just render variables
                    const data = {
                        site_title: siteTitle,
                        current_year: new Date().getFullYear()
                    };
                    console.log('HaruCMS: Rendering template without collections');
                    let rendered = renderTemplate(template.template, data);
                    
                    // Restore entry blocks BEFORE setting innerHTML
                    if (preservedBlocks.length > 0) {
                        console.log('HaruCMS: Restoring', preservedBlocks.length, 'entry blocks in rendered HTML before setting innerHTML');
                        
                        // Replace all placeholders in the rendered HTML string BEFORE setting innerHTML
                        preservedBlocks.forEach((block, index) => {
                            const collectionSlug = block.collectionSlug || (block.originalElement ? block.originalElement.dataset.haruCollection : null);
                            if (!collectionSlug) {
                                console.warn('HaruCMS: Entry block', index, 'has no collection slug');
                                return;
                            }
                            
                            // Clone the element
                            const restoredElement = block.clonedElement.cloneNode(true);
                            restoredElement.setAttribute('data-haru-preserved', 'true');
                            
                            // Try to find the placeholder in the rendered HTML and replace it
                            const placeholderIdMatch = block.html.match(/id="([^"]*haru-entry-block[^"]*)"/);
                            if (placeholderIdMatch) {
                                const placeholderId = placeholderIdMatch[1];
                                // Try to find placeholder by ID in rendered HTML
                                const placeholderRegex = new RegExp('<div[^>]*id="' + placeholderId.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '"[^>]*>.*?<\/div>', 's');
                                if (rendered.match(placeholderRegex)) {
                                    // Replace placeholder with actual block
                                    rendered = rendered.replace(placeholderRegex, restoredElement.outerHTML);
                                    console.log('HaruCMS: Entry block', index, 'replaced in rendered HTML for collection:', collectionSlug);
                                    return;
                                }
                            }
                            
                            // Fallback: Try to find any placeholder and replace it
                            const placeholderFullRegex = /<div[^>]*class="haru-entry-block"[^>]*>.*?<\/div>/s;
                            if (rendered.match(placeholderFullRegex)) {
                                rendered = rendered.replace(placeholderFullRegex, restoredElement.outerHTML);
                                console.log('HaruCMS: Entry block', index, 'replaced in rendered HTML (fallback) for collection:', collectionSlug);
                                return;
                            }
                            
                            console.warn('HaruCMS: Entry block', index, 'placeholder not found in rendered HTML for collection:', collectionSlug);
                        });
                    }
                    
                    // Set innerHTML only once after all placeholders are replaced
                    template.element.innerHTML = rendered;
                    
                    // Verify entry blocks exist in DOM after setting innerHTML
                    if (preservedBlocks.length > 0) {
                        preservedBlocks.forEach((block, index) => {
                            const collectionSlug = block.collectionSlug || (block.originalElement ? block.originalElement.dataset.haruCollection : null);
                            if (!collectionSlug) return;
                            
                            const existingBlock = template.element.querySelector('.haru-entry-block[data-haru-collection="' + collectionSlug + '"]');
                            if (existingBlock) {
                                console.log('HaruCMS: Entry block', index, 'verified in DOM for collection:', collectionSlug);
                            } else {
                                console.warn('HaruCMS: Entry block', index, 'NOT found in DOM after restoration for collection:', collectionSlug);
                                // Last resort: Insert into DOM
                                const restoredElement = block.clonedElement.cloneNode(true);
                                restoredElement.setAttribute('data-haru-preserved', 'true');
                                
                                const container = template.element;
                                if (block.nextSibling && block.nextSibling.parentElement === container) {
                                    container.insertBefore(restoredElement, block.nextSibling);
                                } else {
                                    container.appendChild(restoredElement);
                                }
                                console.log('HaruCMS: Entry block', index, 'inserted into DOM as fallback');
                            }
                        });
                    }
                    console.log('HaruCMS: Template rendered successfully');
                }
            } catch (error) {
                console.error('HaruCMS: Error processing template:', error);
                console.error('HaruCMS: Error stack:', error.stack);
            }
        }
    }
    
    // Store urlParams for use in detectCollectionFromTemplate
    let urlParams = null;
    
    /**
     * Detect collection name from template or URL
     * IMPORTANT: Ignore /demo-website/ path - that's just the website folder, not a collection
     */
    function detectCollectionFromTemplate() {
        // Try to extract from template
        const bodyText = document.body.innerHTML;
        const match = bodyText.match(/\{for\s+(?:each\s+)?[^\s]+\s+in\s+['"]([^'"]+)['"]\}/);
        if (match && match[1] !== 'demo-website') {
            return match[1];
        }
        
        // Try to extract from URL path (but ignore demo-website prefix)
        const path = window.location.pathname;
        let cleanPath = path.replace(/^\/demo-website/, '');
        
        const pathMatch = cleanPath.match(/\/([^\/]+)\/(?:post|entry|article)/i);
        if (pathMatch && pathMatch[1] !== 'demo-website') {
            return pathMatch[1];
        }
        
        // Check if we're on a post/entry/article page
        const filename = cleanPath.split('/').pop() || '';
        if (filename.includes('post') || filename.includes('entry') || filename.includes('article')) {
            // Default to 'blog' for post pages
            return 'blog';
        }
        
        // Common defaults based on path (but exclude demo-website)
        if (cleanPath.includes('/blog') || cleanPath.includes('blog')) return 'blog';
        if (cleanPath.includes('/post')) return 'blog';
        
        // If we're on a demo-website page, default to 'blog' collection
        if (path.includes('/demo-website/')) {
            return 'blog';
        }
        
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
        try {
            console.log('HaruCMS: Fetching entry by slug', { collection, slug });
            const data = await fetchContent('list_entries', { collection: collection });
            
            if (!data) {
                console.warn('HaruCMS: No data returned from API');
                return null;
            }
            
            if (data.status !== 'ok') {
                console.warn('HaruCMS: API returned error:', data.message || data.status);
                return null;
            }
            
            if (!data.data || !data.data.entries) {
                console.warn('HaruCMS: No entries in API response');
                return null;
            }
            
            // Normalize slug for comparison (handle both formats)
            const normalizedSlug = slug.toLowerCase().trim();
            
            console.log('HaruCMS: Searching for slug', normalizedSlug, 'in', data.data.entries.length, 'entries');
            console.log('HaruCMS: Available slugs:', data.data.entries.map(e => e.slug).filter(Boolean));
            
            const entry = data.data.entries.find(entry => {
                const entrySlug = (entry.slug || '').toLowerCase().trim();
                const match = entrySlug === normalizedSlug;
                if (match) {
                    console.log('HaruCMS: Found entry:', entry.title || entry.id);
                }
                return match;
            });
            
            if (entry) {
                return entry;
            }
            
            // If not found, log for debugging
            console.warn('HaruCMS: Entry not found', {
                collection: collection,
                slug: slug,
                normalizedSlug: normalizedSlug,
                availableSlugs: data.data.entries.map(e => e.slug).filter(Boolean),
                entriesCount: data.data.entries.length,
                allEntries: data.data.entries.map(e => ({ id: e.id, title: e.title, slug: e.slug, status: e.status }))
            });
        } catch (error) {
            console.error('HaruCMS: Error fetching entry by slug:', error);
            console.error('HaruCMS: Error stack:', error.stack);
        }
        return null;
    }
    
    /**
     * Fetch collection schema and API key
     */
    async function fetchCollectionInfo(collectionSlug) {
        try {
            // Try to get collection info from a new API endpoint
            const response = await fetch(HaruCMS.cmsUrl + '/api/public.php?action=get_collection_info&collection=' + encodeURIComponent(collectionSlug));
            const data = await response.json();
            if (data && data.status === 'ok' && data.data) {
                return data.data;
            }
        } catch (error) {
            console.warn('HaruCMS: Could not fetch collection info:', error);
        }
        return null;
    }
    
    /**
     * Render public form based on collection
     */
    async function renderPublicForm(collectionSlug) {
        const collectionInfo = await fetchCollectionInfo(collectionSlug);
        if (!collectionInfo || !collectionInfo.schema || !collectionInfo.api_key) {
            return '<p style="color: red;">Error: Collection not found or Public API not enabled.</p>';
        }
        
        let formHtml = '<form class="haru-form" data-haru-collection="' + escapeHtml(collectionSlug) + '" data-haru-api-key="' + escapeHtml(collectionInfo.api_key) + '">\n';
        
        const fields = collectionInfo.schema.fields || [];
        for (const field of fields) {
            const fieldName = field.name || '';
            const fieldLabel = field.label || fieldName;
            const fieldType = field.type || 'text';
            const required = field.required || false;
            
            // Skip status field (handled automatically)
            if (fieldName === 'status') continue;
            
            formHtml += '  <div class="form-group">\n';
            formHtml += '    <label for="' + escapeHtml(fieldName) + '">' + escapeHtml(fieldLabel);
            if (required) {
                formHtml += ' <span class="required">*</span>';
            }
            formHtml += '</label>\n';
            
            if (fieldType === 'textarea' || fieldType === 'editor') {
                formHtml += '    <textarea name="' + escapeHtml(fieldName) + '" id="' + escapeHtml(fieldName) + '" class="form-control"';
                if (required) formHtml += ' required';
                formHtml += ' rows="5"></textarea>\n';
            } else if (fieldType === 'select') {
                formHtml += '    <select name="' + escapeHtml(fieldName) + '" id="' + escapeHtml(fieldName) + '" class="form-control"';
                if (required) formHtml += ' required';
                formHtml += '>\n';
                formHtml += '      <option value="">Select...</option>\n';
                const options = field.options || [];
                for (const option of options) {
                    const optLabel = option.label || option.value || '';
                    const optValue = option.value || optLabel;
                    formHtml += '      <option value="' + escapeHtml(optValue) + '">' + escapeHtml(optLabel) + '</option>\n';
                }
                formHtml += '    </select>\n';
            } else if (fieldType === 'boolean') {
                formHtml += '    <label class="checkbox-label">\n';
                formHtml += '      <input type="checkbox" name="' + escapeHtml(fieldName) + '" id="' + escapeHtml(fieldName) + '" value="1">\n';
                formHtml += '      <span>' + escapeHtml(fieldLabel) + '</span>\n';
                formHtml += '    </label>\n';
            } else {
                const inputType = (fieldType === 'email') ? 'email' : (fieldType === 'number') ? 'number' : 'text';
                formHtml += '    <input type="' + inputType + '" name="' + escapeHtml(fieldName) + '" id="' + escapeHtml(fieldName) + '" class="form-control"';
                if (required) formHtml += ' required';
                formHtml += '>\n';
            }
            formHtml += '  </div>\n';
        }
        
        // Add honeypot field
        formHtml += '  <div style="display: none;">\n';
        formHtml += '    <input type="text" name="_honeypot" tabindex="-1" autocomplete="off">\n';
        formHtml += '  </div>\n';
        
        // Add submit button
        formHtml += '  <button type="submit" class="btn btn-primary">Submit</button>\n';
        formHtml += '</form>\n';
        
        return formHtml;
    }
    
    /**
     * Setup auto-form submission for HaruCMS forms
     */
    function setupFormSubmission() {
        const forms = document.querySelectorAll('form[data-haru-collection]');
        for (const form of forms) {
            if (form.dataset.haruFormInitialized) continue;
            form.dataset.haruFormInitialized = 'true';
            
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const form = this;
                const collectionSlug = form.dataset.haruCollection;
                const apiKey = form.dataset.haruApiKey;
                
                if (!collectionSlug || !apiKey) {
                    alert('Error: Form configuration missing. Please check the form setup.');
                    return;
                }
                
                const submitBtn = form.querySelector('button[type=submit]');
                const originalText = submitBtn ? submitBtn.textContent : 'Submit';
                
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Sending...';
                }
                
                const formData = new FormData(form);
                const data = {};
                for (const [key, value] of formData.entries()) {
                    data[key] = value;
                }
                
                try {
                    const apiUrl = HaruCMS.cmsUrl + '/api/public/collections.php?collection=' + encodeURIComponent(collectionSlug);
                    const response = await fetch(apiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-API-Key': apiKey
                        },
                        body: JSON.stringify(data)
                    });
                    
                    const result = await response.json();
                    
                    if (result.status === 'ok') {
                        form.reset();
                        alert('Thank you! Your message has been received. We\'ll get back to you soon.');
                    } else {
                        alert('Error: ' + (result.message || 'Something went wrong'));
                    }
                } catch (error) {
                    console.error('HaruCMS: Form submission error:', error);
                    alert('Error submitting form. Please try again.');
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;
                    }
                }
            });
        }
    }
    
    /**
     * Initialize HaruCMS
     */
    async function init() {
        if (HaruCMS.initialized) return;
        
        HaruCMS.initialized = true;
        
        console.log('HaruCMS: Initializing...');
        
        // Detect API endpoint first
        await detectApiEndpoint();
        console.log('HaruCMS: API endpoint detected:', HaruCMS.apiEndpoint);
        
        // Process templates immediately (don't wait for DOMContentLoaded)
        console.log('HaruCMS: Processing templates...');
        await processTemplates();
        console.log('HaruCMS: Templates processed');
        
        // Process {entry for 'collection'} blocks (wait a bit for DOM to update)
        // Use setTimeout to ensure DOM is fully updated after template processing
        setTimeout(() => {
            let entryBlocks = document.querySelectorAll('.haru-entry-block');
            console.log('HaruCMS: Found', entryBlocks.length, 'entry blocks to process');
            
            if (entryBlocks.length === 0) {
                console.warn('HaruCMS: No entry blocks found. They may have been removed during template processing.');
                // Try to find them by data attribute instead
                const byDataAttr = document.querySelectorAll('[data-haru-collection]');
                console.log('HaruCMS: Found', byDataAttr.length, 'elements with data-haru-collection attribute');
                for (const el of byDataAttr) {
                    if (el.dataset.haruCollection && !el.classList.contains('haru-entry-block')) {
                        console.log('HaruCMS: Found element with collection but missing class:', el.id, el.dataset.haruCollection);
                        // Add the class and process it
                        el.classList.add('haru-entry-block');
                    }
                }
                // Re-query after adding classes
                entryBlocks = document.querySelectorAll('.haru-entry-block');
                console.log('HaruCMS: Now found', entryBlocks.length, 'entry blocks after adding classes');
                
                if (entryBlocks.length === 0) {
                    return;
                }
            }
            
            for (const block of entryBlocks) {
                const collectionSlug = block.dataset.haruCollection;
                let formContent = block.dataset.haruContent;
                console.log('HaruCMS: Processing entry block for collection:', collectionSlug, 'Content length:', formContent ? formContent.length : 0);
                
                if (collectionSlug && formContent) {
                    // Decode HTML-encoded content
                    // The content is stored as HTML-encoded (e.g., &lt;div&gt; becomes <div>)
                    // We need to decode HTML entities properly
                    // Use a temporary element to decode HTML entities
                    const tempDiv = document.createElement('div');
                    // Decode HTML entities - this handles all HTML entities including numeric ones
                    tempDiv.innerHTML = formContent;
                    // Get the text content (which will have HTML entities decoded)
                    // But we need the actual HTML, so we need to decode entities manually
                    // Actually, if we set innerHTML and then get innerHTML, it should decode entities
                    // But we need to be careful - let's use a different approach
                    // Create a textarea to decode HTML entities properly
                    const textarea = document.createElement('textarea');
                    textarea.innerHTML = formContent;
                    formContent = textarea.value;
                    
                    // Clean up any malformed HTML patterns that might have been included
                    // The extraction might include extra characters like =" or ="" or the next attribute
                    // Remove patterns like ="" or =" that appear in decoded content
                    formContent = formContent.replace(/\s*="">""\s*/g, '">');
                    formContent = formContent.replace(/\s*="">"\s*/g, '">');
                    formContent = formContent.replace(/\s*="">\s*/g, '">');
                    formContent = formContent.replace(/\s*="\s*$/gm, '');
                    formContent = formContent.replace(/\s*=""\s*$/gm, '');
                    
                    // Remove any trailing content that looks like attributes (data-haru-preserved, etc.)
                    // This should have been removed during extraction, but clean it up here too
                    formContent = formContent.replace(/\s*"\s*data-haru-preserved.*$/s, '');
                    formContent = formContent.replace(/\s*data-haru-preserved.*$/s, '');
                    
                    // Also remove any remaining malformed quote patterns at the very end
                    formContent = formContent.replace(/\s*"\s*=\s*"\s*$/s, '');
                    formContent = formContent.replace(/\s*=\s*"\s*$/s, '');
                    
                    console.log('HaruCMS: Decoded form content, length:', formContent.length);
                    console.log('HaruCMS: First 200 chars:', formContent.substring(0, 200));
                    console.log('HaruCMS: Last 100 chars:', formContent.substring(Math.max(0, formContent.length - 100)));
                    
                    fetchCollectionInfo(collectionSlug).then(collectionInfo => {
                        console.log('HaruCMS: Fetched collection info for:', collectionSlug, collectionInfo ? 'OK' : 'FAILED');
                        if (!collectionInfo || !collectionInfo.schema || !collectionInfo.api_key) {
                            block.innerHTML = '<p style="color: red;">Error: Collection not found or Public API not enabled.</p>';
                            return;
                        }
                        
                        // Create a data object with field names as variables
                        const fieldData = {};
                        const fields = collectionInfo.schema.fields || [];
                        for (const field of fields) {
                            // Make field names available as template variables
                            fieldData[field.name] = field.name; // Use field name as both key and initial value
                        }
                        
                        console.log('HaruCMS: Rendering form content with', fields.length, 'fields');
                        
                        // Render the form content with field data
                        const renderedContent = renderTemplate(formContent, fieldData);
                        
                        console.log('HaruCMS: Form content rendered, length:', renderedContent.length);
                        
                        // Wrap in form with auto-submission attributes
                        const formHtml = '<form class="haru-form" data-haru-collection="' + escapeHtml(collectionSlug) + '" data-haru-api-key="' + escapeHtml(collectionInfo.api_key) + '">\n' +
                            renderedContent + '\n' +
                            '</form>';
                        
                        // Replace the block with the form
                        const parent = block.parentElement;
                        if (parent) {
                            // Create a temporary container to parse the form HTML
                            const tempDiv = document.createElement('div');
                            tempDiv.innerHTML = formHtml;
                            const formElement = tempDiv.firstElementChild;
                            
                            // Replace the block with the form element
                            parent.replaceChild(formElement, block);
                            console.log('HaruCMS: Form HTML inserted via replaceChild, setting up submission');
                            
                            // Setup form submission after a brief delay to ensure DOM is updated
                            setTimeout(() => {
                                setupFormSubmission();
                            }, 50);
                        } else {
                            // Fallback to outerHTML if no parent
                            block.outerHTML = formHtml;
                            console.log('HaruCMS: Form HTML inserted via outerHTML, setting up submission');
                            setTimeout(() => {
                                setupFormSubmission();
                            }, 50);
                        }
                    }).catch(error => {
                        console.error('HaruCMS: Error rendering entry form:', error);
                        block.innerHTML = '<p style="color: red;">Error loading form. Please try again later.</p>';
                    });
                } else {
                    console.warn('HaruCMS: Entry block missing collection slug or form content:', { collectionSlug, hasContent: !!formContent });
                }
            }
        }, 100);
        
        // Process {public_form} placeholders after templates are rendered
        const formPlaceholders = document.querySelectorAll('.haru-form-placeholder');
        for (const placeholder of formPlaceholders) {
            const collectionSlug = placeholder.dataset.haruCollection;
            if (collectionSlug) {
                renderPublicForm(collectionSlug).then(formHtml => {
                    placeholder.outerHTML = formHtml;
                    setupFormSubmission();
                }).catch(error => {
                    console.error('HaruCMS: Error rendering form:', error);
                    placeholder.innerHTML = '<p style="color: red;">Error loading form. Please try again later.</p>';
                });
            }
        }
        
        // Setup form auto-submission after templates are processed
        setupFormSubmission();
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
    
    // Auto-initialize - start immediately (don't wait for DOMContentLoaded for inline scripts)
    // But check if DOM is ready first
    if (document.readyState === 'loading') {
        // Wait for DOM to be ready before processing templates
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // DOM already ready, initialize immediately
        init();
    }
})();
