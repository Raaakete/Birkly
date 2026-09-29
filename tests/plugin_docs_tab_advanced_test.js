/**
 * P104-M4 — Advanced Users collapses in the plugin Docs tab.
 */
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const file = path.join(__dirname, '../admin/js/plugin-docs-tab.js');
const context = {
    window: {},
    document: {
        readyState: 'complete',
        querySelectorAll() { return []; },
        createElement() {
            return { textContent: '', get innerHTML() { return this.textContent; } };
        },
    },
};
vm.createContext(context);
vm.runInContext(fs.readFileSync(file, 'utf8'), context);

const html = context.window.BirklyPluginDocs.renderMarkdown(
    '# Commerce\n\n## Summary\n\nSell a product.\n\n## Beginner\n\n1. Enable Commerce.\n2. Add a price.\n3. Place a test order.\n\n## Advanced Users\n\nWebhook field reference.\n'
);

function fail(message) {
    console.error('FAIL', message);
    process.exit(1);
}

if (!html.includes('<h2>Summary</h2>')) fail('Summary heading missing');
if (!html.includes('<h2>Beginner</h2>')) fail('Beginner heading missing');
if (html.includes('<h2>Advanced Users</h2>')) fail('Advanced Users is still an open heading');
if (!html.includes('<details class="plugin-docs-advanced"><summary>Advanced Users</summary>')) {
    fail('Advanced Users is not collapsed');
}
if (html.includes('plugin-docs-advanced" open')) fail('Advanced Users starts open');
const advancedAt = html.indexOf('plugin-docs-advanced');
const summaryAt = html.indexOf('<h2>Summary</h2>');
const beginnerAt = html.indexOf('<h2>Beginner</h2>');
if (!(summaryAt < advancedAt && beginnerAt < advancedAt)) fail('Summary or Beginner rendered after the deep section');
if (!html.includes('Webhook field reference.')) fail('Advanced body missing');

console.log('plugin docs advanced collapse: PASS');
