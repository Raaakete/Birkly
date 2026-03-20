/**
 * Birkly Template Engine
 * Handles template inheritance, includes, and dynamic content loading
 */

window.Birkly = window.Birkly || {};

Birkly.TemplateEngine = {
    // Template cache
    templates: {},
    
    // Template inheritance stack
    inheritanceStack: [],
    
    /**
     * Load a template
     */
    async loadTemplate(templateName) {
        if (this.templates[templateName]) {
            return this.templates[templateName];
        }
        
        try {
            const response = await fetch(`/templates/${templateName}`);
            if (!response.ok) {
                throw new Error(`Template not found: ${templateName}`);
            }
            
            const template = await response.text();
            this.templates[templateName] = template;
            return template;
        } catch (error) {
            console.error(`Failed to load template: ${templateName}`, error);
            throw error;
        }
    },
    
    /**
     * Render a template with data
     */
    async render(templateName, data = {}) {
        try {
            let template = await this.loadTemplate(templateName);
            
            // Handle template inheritance
            if (template.includes('{{extends')) {
                template = await this.handleInheritance(template, data);
            }
            
            // Handle includes
            template = await this.handleIncludes(template, data);
            
            // Handle blocks
            template = await this.handleBlocks(template, data);
            
            // Replace variables
            template = this.replaceVariables(template, data);
            
            return template;
        } catch (error) {
            console.error(`Failed to render template: ${templateName}`, error);
            throw error;
        }
    },
    
    /**
     * Handle template inheritance
     */
    async handleInheritance(template, data) {
        const extendsMatch = template.match(/\{\{extends\s+['"]([^'"]+)['"]\}\}/);
        if (!extendsMatch) {
            return template;
        }
        
        const parentTemplate = extendsMatch[1];
        const parentContent = await this.loadTemplate(parentTemplate);
        
        // Extract blocks from current template
        const blocks = this.extractBlocks(template);
        
        // Replace blocks in parent template
        let result = parentContent;
        for (const [blockName, blockContent] of Object.entries(blocks)) {
            const blockRegex = new RegExp(`\\{\\{block\\s+['"]${blockName}['"]\\}\\}([\\s\\S]*?)\\{\\{/block\\}\\}`, 'g');
            result = result.replace(blockRegex, `{{block "${blockName}"}}${blockContent}{{/block}}`);
        }
        
        return result;
    },
    
    /**
     * Handle template includes
     */
    async handleIncludes(template, data) {
        const includeRegex = /\{\{include\s+['"]([^'"]+)['"]\s*(?:with\s+([^}]+))?\}\}/g;
        
        let result = template;
        let match;
        
        while ((match = includeRegex.exec(template)) !== null) {
            const includeTemplate = match[1];
            const includeData = match[2] ? this.parseIncludeData(match[2], data) : data;
            
            try {
                const includedContent = await this.render(includeTemplate, includeData);
                result = result.replace(match[0], includedContent);
            } catch (error) {
                console.error(`Failed to include template: ${includeTemplate}`, error);
                result = result.replace(match[0], `<!-- Include failed: ${includeTemplate} -->`);
            }
        }
        
        return result;
    },
    
    /**
     * Handle template blocks
     */
    async handleBlocks(template, data) {
        const blockRegex = /\{\{block\s+['"]([^'"]+)['"]\}\}([\s\S]*?)\{\{\/block\}\}/g;
        
        let result = template;
        let match;
        
        while ((match = blockRegex.exec(template)) !== null) {
            const blockName = match[1];
            const blockContent = match[2];
            
            // Replace variables in block content
            const processedContent = this.replaceVariables(blockContent, data);
            result = result.replace(match[0], processedContent);
        }
        
        return result;
    },
    
    /**
     * Extract blocks from template
     */
    extractBlocks(template) {
        const blocks = {};
        const blockRegex = /\{\{block\s+['"]([^'"]+)['"]\}\}([\s\S]*?)\{\{\/block\}\}/g;
        
        let match;
        while ((match = blockRegex.exec(template)) !== null) {
            const blockName = match[1];
            const blockContent = match[2];
            blocks[blockName] = blockContent;
        }
        
        return blocks;
    },
    
    /**
     * Parse include data
     */
    parseIncludeData(dataString, parentData) {
        const data = {};
        const pairs = dataString.split(',').map(pair => pair.trim());
        
        for (const pair of pairs) {
            const [key, value] = pair.split('=').map(s => s.trim());
            if (key && value) {
                // Remove quotes if present
                const cleanValue = value.replace(/^['"]|['"]$/g, '');
                data[key] = parentData[cleanValue] || cleanValue;
            }
        }
        
        return data;
    },
    
    /**
     * Replace variables in template
     */
    replaceVariables(template, data) {
        // Replace simple variables: {{variable}}
        template = template.replace(/\{\{([^}]+)\}\}/g, (match, variable) => {
            const keys = variable.trim().split('.');
            let value = data;
            
            for (const key of keys) {
                if (value && typeof value === 'object' && key in value) {
                    value = value[key];
                } else {
                    value = '';
                    break;
                }
            }
            
            return value !== undefined && value !== null ? value : '';
        });
        
        // Handle conditional blocks: {{if condition}}...{{/if}}
        template = this.handleConditionals(template, data);
        
        // Handle loops: {{for item in items}}...{{/for}}
        template = this.handleLoops(template, data);
        
        return template;
    },
    
    /**
     * Handle conditional blocks
     */
    handleConditionals(template, data) {
        const conditionalRegex = /\{\{if\s+([^}]+)\}\}([\s\S]*?)(?:\{\{else\}\}([\s\S]*?))?\{\{\/if\}\}/g;
        
        return template.replace(conditionalRegex, (match, condition, trueContent, falseContent) => {
            const keys = condition.trim().split('.');
            let value = data;
            
            for (const key of keys) {
                if (value && typeof value === 'object' && key in value) {
                    value = value[key];
                } else {
                    value = '';
                    break;
                }
            }
            
            const isTruthy = value && value !== 'false' && value !== '0' && value !== '';
            return isTruthy ? trueContent : (falseContent || '');
        });
    },
    
    /**
     * Handle loop blocks
     */
    handleLoops(template, data) {
        const loopRegex = /\{\{for\s+(\w+)\s+in\s+([^}]+)\}\}([\s\S]*?)\{\{\/for\}\}/g;
        
        return template.replace(loopRegex, (match, itemName, arrayName, loopContent) => {
            const keys = arrayName.trim().split('.');
            let array = data;
            
            for (const key of keys) {
                if (array && typeof array === 'object' && key in array) {
                    array = array[key];
                } else {
                    array = [];
                    break;
                }
            }
            
            if (!Array.isArray(array)) {
                return '';
            }
            
            return array.map(item => {
                const loopData = { ...data, [itemName]: item };
                return this.replaceVariables(loopContent, loopData);
            }).join('');
        });
    },
    
    /**
     * Inject content into a base template
     */
    injectIntoBase(baseTemplate, content, customStyles = '', customScripts = '') {
        // Inject custom styles
        if (customStyles) {
            baseTemplate = baseTemplate.replace(
                '/* Custom styles will be injected here by templates */',
                customStyles
            );
        }
        
        // Inject content
        baseTemplate = baseTemplate.replace(
            '<div id="content-placeholder">',
            `<div id="content-placeholder">${content}`
        );
        
        // Inject custom scripts
        if (customScripts) {
            baseTemplate = baseTemplate.replace(
                '// Custom scripts will be injected here by templates',
                customScripts
            );
        }
        
        return baseTemplate;
    }
};

// Utility functions for templates
Birkly.utils = {
    /**
     * Format date
     */
    formatDate(dateString, format = 'long') {
        const date = new Date(dateString);
        
        if (format === 'short') {
            return date.toLocaleDateString();
        } else if (format === 'long') {
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        } else if (format === 'relative') {
            const now = new Date();
            const diff = now - date;
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            
            if (days === 0) return 'Today';
            if (days === 1) return 'Yesterday';
            if (days < 7) return `${days} days ago`;
            if (days < 30) return `${Math.floor(days / 7)} weeks ago`;
            if (days < 365) return `${Math.floor(days / 30)} months ago`;
            return `${Math.floor(days / 365)} years ago`;
        }
        
        return date.toLocaleDateString();
    },
    
    /**
     * Truncate text
     */
    truncate(text, length = 150, suffix = '...') {
        if (text.length <= length) return text;
        return text.substring(0, length) + suffix;
    },
    
    /**
     * Generate slug from text
     */
    slugify(text) {
        return text
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    },
    
    /**
     * Escape HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}; 