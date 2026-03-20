/**
 * Birkly Client - Loops Module
 * Handles {for each entry in 'collection'} loop processing
 */

(function(Birkly) {
    'use strict';
    
    if (!Birkly) Birkly = {};
    if (!Birkly.utils) {
        console.error('Birkly: utils module must be loaded before loops module');
        return;
    }
    if (!Birkly.conditionals) {
        console.error('Birkly: conditionals module must be loaded before loops module');
        return;
    }
    if (!Birkly.dateFormatter) {
        console.error('Birkly: dateFormatter module must be loaded before loops module');
        return;
    }
    
    const getNestedValue = Birkly.utils.getNestedValue;
    const escapeHtml = Birkly.utils.escapeHtml;
    const formatDate = Birkly.dateFormatter.formatDate;
    const formatValueForOutput = Birkly.dateFormatter.formatValueForOutput;
    const renderConditionals = Birkly.conditionals.renderConditionals.bind(Birkly.conditionals);
    
    /**
     * Loops namespace
     */
    Birkly.loops = {
        /**
         * Extract loop content between {for ...} and {endfor}
         * @param {string} template - Template string
         * @param {string} startMatch - The {for ...} match string
         * @returns {string|null} The loop content or null if not found
         */
        extractLoopContent: function(template, startMatch) {
            const startPos = template.indexOf(startMatch);
            if (startPos === -1) {
                console.warn('Birkly: extractLoopContent - startMatch not found:', startMatch);
                return null;
            }
            
            const afterStart = template.substring(startPos + startMatch.length);
            
            // Use a simpler approach: find all {for} and {endfor} markers and track depth
            const markers = [];
            
            // Find all {for...} directives
            const forRegex = /\{for\s+(?:each\s+)?[^}]+\}/g;
            let forMatch;
            while ((forMatch = forRegex.exec(afterStart)) !== null) {
                markers.push({ type: 'for', pos: forMatch.index, end: forMatch.index + forMatch[0].length });
            }
            
            // Find all {endfor} and {endforeach} - handle optional whitespace
            const endforRegex = /\{\s*endfor\s*\}/g;
            let endforMatch;
            while ((endforMatch = endforRegex.exec(afterStart)) !== null) {
                markers.push({ type: 'endfor', pos: endforMatch.index, end: endforMatch.index + endforMatch[0].length });
            }
            
            const endforeachRegex = /\{\s*endforeach\s*\}/g;
            let endforeachMatch;
            while ((endforeachMatch = endforeachRegex.exec(afterStart)) !== null) {
                markers.push({ type: 'endfor', pos: endforeachMatch.index, end: endforeachMatch.index + endforeachMatch[0].length });
            }
            
            // Sort markers by position
            markers.sort((a, b) => a.pos - b.pos);
            
            // Process markers to find matching {endfor}
            let currentDepth = 1;
            let lastPos = 0;
            let content = '';
            
            for (const marker of markers) {
                // Add content before this marker
                if (marker.pos > lastPos) {
                    content += afterStart.substring(lastPos, marker.pos);
                }
                
                if (marker.type === 'for') {
                    // Found a nested {for} - include it and increment depth
                    content += afterStart.substring(marker.pos, marker.end);
                    currentDepth++;
                    lastPos = marker.end;
                } else if (marker.type === 'endfor') {
                    // Found an {endfor} - decrement depth
                    currentDepth--;
                    lastPos = marker.end;
                    if (currentDepth === 0) {
                        // Found the matching {endfor} - return content
                        return content;
                    }
                }
            }
            
            // If we get here, we didn't find a matching {endfor}
            // Try a simpler fallback: find the first {endfor} if depth tracking failed
            if (currentDepth > 0 && markers.length === 0) {
                // No markers found at all - try direct search for {endfor}
                const directEndfor = afterStart.search(/\{\s*endfor\s*\}/);
                if (directEndfor !== -1) {
                    return afterStart.substring(0, directEndfor);
                }
            }
            
            // Additional fallback: if we're looking for a nested loop (indicated by 'item' variable)
            // and no {endfor} was found, try to find it by looking for the closing </span> tag
            if (markers.length === 0 && startMatch.includes('item')) {
                // Look for </span> which should close the <span class="tag"> tag
                const spanClose = afterStart.indexOf('</span>');
                if (spanClose !== -1) {
                    // Include the </span> tag and everything up to it
                    const endPos = spanClose + 7; // Length of '</span>'
                    return afterStart.substring(0, endPos);
                }
            }
            
            // Add any remaining content
            if (lastPos < afterStart.length) {
                content += afterStart.substring(lastPos);
            }
            
            return null;
        },
        
        /**
         * Process nested loops in template
         * @param {string} template - Template string
         * @param {Object} data - Data object
         * @param {number} depth - Current recursion depth
         * @returns {string} Template with loops processed
         */
        processNestedLoops: function(template, data, depth = 0) {
            // Only process if there are actually loops in the template
            if (!template.includes('{for each') && !template.includes('{for ')) {
                return template;
            }
            
            // Limit recursion depth to prevent infinite loops
            if (depth > 2) {
                console.warn('Birkly: processNestedLoops reached max depth', depth, ', stopping');
                return template;
            }
            
            // Process loops one at a time
            const loopRegex = /\{for\s+(?:each\s+)?([a-zA-Z0-9_]+)\s+in\s+(?:['"]([^'"]+)['"]|([a-zA-Z0-9_\.]+))\}/;
            const match = template.match(loopRegex);
            if (!match) return template; // No more loops to process
            
            const itemVar = match[1];
            const quotedName = match[2];
            const unquotedPath = match[3];
            const collectionName = quotedName || unquotedPath || '';
            
            // Resolve the array to loop over
            let resolved = null;
            if (unquotedPath) {
                // Dot notation path (e.g., entry.tags)
                resolved = getNestedValue(data, unquotedPath);
            } else if (quotedName) {
                // Quoted name: could be a collection OR a field on current entry
                // When inside a loop, entry fields are spread into data, so check top-level first
                // Then check data.entry, then fallback to collection
                if (data[quotedName] !== undefined && data[quotedName] !== null) {
                    // Check top-level data first (for when entry fields are spread)
                    resolved = data[quotedName];
                } else if (data.entry && data.entry[quotedName] !== undefined && data.entry[quotedName] !== null) {
                    // Check if it's a field on the current entry
                    resolved = data.entry[quotedName];
                } else {
                    // Fallback: treat as collection name
                    resolved = data[quotedName] || [];
                }
            } else {
                resolved = data[collectionName] || [];
            }
            
            // Ensure resolved is an array
            const items = Array.isArray(resolved) ? resolved : [];
            
            const loopContent = this.extractLoopContent(template, match[0]);
            if (!loopContent) {
                // Couldn't find endfor - remove the loop directive to prevent it from appearing in output
                console.warn('Birkly: processNestedLoops - failed to extract loop content for:', match[0]);
                const startPos = template.indexOf(match[0]);
                // Try to find the next {endfor} or {endforeach} after the loop start
                const nextEndFor = template.indexOf('{endfor', startPos);
                const nextEndForEach = template.indexOf('{endforeach', startPos);
                let endPos = -1;
                if (nextEndFor !== -1 && (nextEndForEach === -1 || nextEndFor < nextEndForEach)) {
                    endPos = template.indexOf('}', nextEndFor) + 1;
                } else if (nextEndForEach !== -1) {
                    endPos = template.indexOf('}', nextEndForEach) + 1;
                }
                if (endPos !== -1) {
                    return template.substring(0, startPos) + template.substring(endPos);
                }
                // If we can't find endfor, just remove the loop start directive
                return template.substring(0, startPos) + template.substring(startPos + match[0].length);
            }
            
            if (!Array.isArray(items) || items.length === 0) {
                // Remove the loop if no items
                const startPos = template.indexOf(match[0]);
                const endPos = startPos + match[0].length + loopContent.length + 7;
                return template.substring(0, startPos) + template.substring(endPos);
            }
            
            const output = items.map(item => {
                const itemData = { ...data, [itemVar]: item };
                let itemOutput = loopContent;
                
                // Handle simple unquoted variables first: {item}
                itemOutput = itemOutput.replace(/\{\s*([a-zA-Z0-9_]+)\s*\}/g, (m, varName) => {
                    // Skip if it's a directive keyword
                    const directiveKeywords = ['if','for','endif','endfor','foreach','endforeach','from','get','has','include','media','url','count','endget','else','elseif'];
                    if (directiveKeywords.includes(varName.toLowerCase())) return m;
                    
                    // If it's the loop variable (e.g., 'item'), output the current item
                    if (varName === itemVar) {
                        if (typeof item === 'string') return escapeHtml(item);
                        if (item && typeof item === 'object') {
                            const v = item.value ?? item.name ?? item.id ?? item.label ?? item;
                            return typeof v === 'string' ? escapeHtml(v) : String(v);
                        }
                        return escapeHtml(String(item));
                    }
                    
                    // Otherwise, check if it's in the data context
                    if (itemData[varName] !== undefined) {
                        const value = itemData[varName];
                        // Check if it's a date field and apply default formatting
                        const isDateField = Birkly.dateFormatter.isDateField(varName);
                        if (isDateField) {
                            return formatValueForOutput(value, varName);
                        }
                        return escapeHtml(String(value));
                    }
                    
                    return m; // Don't replace if not found
                });
                
                // Handle quoted fields: {'field'} or {'field'.0.'subfield'}
                itemOutput = itemOutput.replace(/\{\s*((?:['"][^'"]+['"]|\.?\d+\.?)+)\s*\}/g, (m, pathStr) => {
                    const directiveKeywords = ['if','for','endif','endfor','foreach','endforeach','from','get','has','include','media','url','count','endget'];
                    const firstPartMatch = pathStr.match(/^['"]([^'"]+)['"]/);
                    if (firstPartMatch && directiveKeywords.includes(firstPartMatch[1].toLowerCase())) return m;
                    
                    const pathParts = [];
                    const pathMatch = pathStr.matchAll(/['"]([^'"]+)['"]|(\d+)/g);
                    for (const pm of pathMatch) {
                        if (pm[1]) pathParts.push(pm[1]);
                        else if (pm[2]) pathParts.push(pm[2]);
                    }
                    
                    if (pathParts.length === 0) return m;
                    
                    const value = getNestedValue(itemData, pathParts.join('.'));
                    if (value === null || value === undefined) return '';
                    if (pathParts.includes('content') || pathParts[pathParts.length - 1] === 'content') {
                        return String(value);
                    }
                    return escapeHtml(String(value));
                });
                
                // Replace variables with format filters and fallback operators
                itemOutput = itemOutput.replace(/\{\s*([a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)*(?:\.[0-9]+(?:\.[a-zA-Z0-9_]+)*)?)\s*(?:format\s+"([^"]+)")?(?:\|\|\s*([^}]+))?\s*\}/g, (m, varPath, formatFilter, fallback) => {
                    const rawPath = String(varPath || '').trim();
                    const lowers = rawPath.toLowerCase();
                    const directiveKeywords = ['if','for','endif','endfor','foreach','endforeach','from','get','has','include','media','url','count','public_form','entry','endentry','else'];
                    if (directiveKeywords.includes(lowers)) return m;
                    
                    let value = getNestedValue(itemData, rawPath);
                    
                    if (rawPath.endsWith('.length')) {
                        const basePath = rawPath.slice(0, -7);
                        const baseValue = getNestedValue(itemData, basePath);
                        if (Array.isArray(baseValue)) {
                            value = baseValue.length;
                        } else if (baseValue && typeof baseValue === 'object' && 'length' in baseValue) {
                            value = baseValue.length;
                        } else {
                            value = null;
                        }
                    }
                    
                    if ((value === null || value === undefined || value === '') && fallback) {
                        const fallbackTrimmed = fallback.trim();
                        if (fallbackTrimmed === '0' || /^\d+$/.test(fallbackTrimmed)) {
                            value = parseInt(fallbackTrimmed, 10);
                        } else {
                            const fallbackValue = getNestedValue(itemData, fallbackTrimmed);
                            value = fallbackValue !== null && fallbackValue !== undefined ? fallbackValue : fallbackTrimmed;
                        }
                    }
                    
                    if (value === null || value === undefined) return '';
                    
                    // Apply date formatting if format filter is present
                    if (formatFilter) {
                        const isDateField = Birkly.dateFormatter.isDateField(rawPath);
                        if (isDateField) {
                            return escapeHtml(formatDate(String(value), formatFilter));
                        }
                    }
                    
                    // Apply default date formatting for date fields
                    const lastSegment = rawPath.split('.').pop();
                    if (lastSegment === 'content' || /\.content$/.test(rawPath)) {
                        return String(value);
                    }
                    
                    // Use formatValueForOutput for date fields (applies default YYYY-MM-DD)
                    return formatValueForOutput(value, rawPath);
                });
                
                // Process conditionals
                itemOutput = renderConditionals(itemOutput, itemData);
                
                // Recursively process deeper nested loops (limit depth)
                if (depth < 2 && (itemOutput.includes('{for each') || itemOutput.includes('{for '))) {
                    itemOutput = this.processNestedLoops(itemOutput, itemData, depth + 1);
                }
                
                return itemOutput;
            }.bind(this)).join('');
            
            // Replace the loop with its output
            const startPos = template.indexOf(match[0]);
            // Find the actual {endfor} or {endforeach} position to ensure accurate replacement
            let endPos = -1;
            const searchStart = startPos + match[0].length + loopContent.length;
            const endforPos = template.indexOf('{endfor}', searchStart);
            const endforeachPos = template.indexOf('{endforeach}', searchStart);
            
            if (endforPos !== -1 && (endforeachPos === -1 || endforPos < endforeachPos)) {
                endPos = endforPos + 8; // {endfor} is 8 chars
            } else if (endforeachPos !== -1) {
                endPos = endforeachPos + 12; // {endforeach} is 12 chars
            } else {
                // Fallback to old calculation if we can't find endfor
                endPos = startPos + match[0].length + loopContent.length + 7;
            }
            
            const result = template.substring(0, startPos) + output + template.substring(endPos);
            
            // Process any remaining nested loops in the result (but limit depth)
            if (depth === 0 && (result.includes('{for each') || result.includes('{for '))) {
                // Only process if there are actually unprocessed loop directives
                const hasUnprocessedLoops = /\{for\s+(?:each\s+)?[a-zA-Z0-9_]+\s+in\s+/.test(result);
                if (hasUnprocessedLoops) {
                    return this.processNestedLoops(result, data, depth + 1);
                }
            }
            
            return result;
        }
    };
    
})(window.Birkly || (window.Birkly = {}));
