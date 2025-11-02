<h2>Client Library</h2>
<p>The HaruCMS client library is a JavaScript file that automatically connects your website to your CMS and handles all the heavy lifting for you.</p>

<h3>What Does It Do?</h3>
<p>The client library automatically:</p>
<ul>
    <li>Detects template syntax in your HTML</li>
    <li>Connects to your CMS API</li>
    <li>Fetches content based on URL parameters</li>
    <li>Renders templates with your actual content</li>
    <li>Handles errors gracefully</li>
</ul>

<h3>How to Include It</h3>
<p>Add the script tag just before <code>&lt;/body&gt;</code>:</p>

<pre><code>&lt;script src="/harucms-client.js" 
        data-cms-url="http://localhost:8080"&gt;&lt;/script&gt;</code></pre>

<h3>Configuration Attributes</h3>
<p>You can configure the library using data attributes on the script tag:</p>

<table>
    <thead>
        <tr>
            <th>Attribute</th>
            <th>Description</th>
            <th>Example</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><code>data-cms-url</code></td>
            <td>The URL of your CMS</td>
            <td><code>data-cms-url="http://localhost:8080"</code></td>
        </tr>
        <tr>
            <td><code>data-collection</code></td>
            <td>Specify which collection to use (optional - auto-detected)</td>
            <td><code>data-collection="blog"</code></td>
        </tr>
    </tbody>
</table>

<h3>Automatic Features</h3>
<p>The client library handles several things automatically:</p>

<h4>1. Template Detection</h4>
<p>It scans your HTML for template syntax and processes it:</p>
<ul>
    <li><code>{{ variable }}</code> - Variables</li>
    <li><code>{for each entry in 'collection'}</code> - Loops</li>
    <li><code>{if condition exist}</code> - Conditionals</li>
</ul>

<h4>2. Collection Detection</h4>
<p>The library automatically determines which collection to use by:</p>
<ol>
    <li>Checking the <code>data-collection</code> attribute</li>
    <li>Looking for template syntax that specifies a collection</li>
    <li>Analyzing the URL path</li>
    <li>Using common defaults (like "blog" for blog-related URLs)</li>
</ol>

<h4>3. Entry Loading</h4>
<p>For single entry pages (with <code>?slug=...</code> in the URL):</p>
<ul>
    <li>Detects the slug parameter</li>
    <li>Fetches the specific entry</li>
    <li>Populates variables like <code>{{ title }}</code>, <code>{{ content }}</code></li>
</ul>

<h4>4. Collection Lists</h4>
<p>For list pages:</p>
<ul>
    <li>Detects collection loops in templates</li>
    <li>Fetches all entries from that collection</li>
    <li>Renders the loop content for each entry</li>
</ul>

<h3>Available Variables</h3>
<p>The client library makes these variables available:</p>

<h4>Global Variables</h4>
<ul>
    <li><code>{{ site_title }}</code> - Your website's title</li>
    <li><code>{{ current_year }}</code> - Current year (for copyright notices)</li>
</ul>

<h4>Entry Variables (when viewing a single entry)</h4>
<ul>
    <li><code>{{ title }}</code> - Entry title</li>
    <li><code>{{ content }}</code> - Entry content (use with <code>|raw</code>)</li>
    <li><code>{{ slug }}</code> - Entry slug</li>
    <li><code>{{ created_at }}</code> - Creation date</li>
    <li><code>{{ excerpt }}</code> - Entry excerpt</li>
    <li><code>{{ tags }}</code> - Tags array (use with foreach)</li>
    <li>Plus any custom fields you've defined!</li>
</ul>

<h4>Loop Variables (when in a loop)</h4>
<ul>
    <li><code>{{ entry.title }}</code> - Title of current entry</li>
    <li><code>{{ entry.content }}</code> - Content of current entry</li>
    <li><code>{{ entry.slug }}</code> - Slug of current entry</li>
    <li>Any other entry properties using dot notation</li>
</ul>

<h3>Best Practices</h3>
<ul>
    <li><strong>Include the script at the end:</strong> Put it just before <code>&lt;/body&gt;</code> for better performance</li>
    <li><strong>Use explicit collections:</strong> Add <code>data-collection</code> when possible for clarity</li>
    <li><strong>Handle loading states:</strong> Consider showing a loading message while content loads</li>
    <li><strong>Test thoroughly:</strong> Test with different content and edge cases</li>
</ul>

<h3>Debugging</h3>
<p>The client library logs helpful information to the browser console:</p>
<ul>
    <li>Open Developer Tools (F12)</li>
    <li>Go to the Console tab</li>
    <li>Look for messages starting with "HaruCMS:"</li>
</ul>

<p>Common console messages:</p>
<ul>
    <li><code>HaruCMS: Fetching entry by slug</code> - Loading a specific entry</li>
    <li><code>HaruCMS: Processing templates</code> - Rendering templates</li>
    <li><code>HaruCMS: Error loading post</code> - Something went wrong (check the details)</li>
</ul>

<h3>That's It!</h3>
<p>The client library is designed to be simple and automatic. Just add the script tag, write your templates, and your content will appear!</p>

<p>If you have questions, check the <strong>Templating Language</strong> section for syntax help, or the <strong>Website Integration</strong> section for connection setup.</p>

