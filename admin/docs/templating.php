<h2>Templating Language</h2>
<p>HaruCMS uses a simple templating language to display your content on your website. This guide will teach you everything you need to know.</p>

<h3>What is Templating?</h3>
<p>A <strong>template</strong> is an HTML file with special placeholders that get filled in with your actual content. Instead of hardcoding content, you write templates that automatically pull content from your CMS.</p>

<div class="example-box">
    <h4>Simple Example:</h4>
    <p>Instead of writing:</p>
    <pre><code>&lt;h1&gt;My Blog Post Title&lt;/h1&gt;
&lt;p&gt;My blog post content...&lt;/p&gt;</code></pre>
    
    <p>You write:</p>
    <pre><code>&lt;h1&gt;{{ title }}&lt;/h1&gt;
&lt;p&gt;{{ content|raw }}&lt;/p&gt;</code></pre>
    
    <p>And HaruCMS automatically fills in the actual title and content!</p>
</div>

<h3>Displaying Variables</h3>
<p>The most basic template syntax is displaying a variable:</p>

<pre><code>{{ variable_name }}</code></pre>

<p>This displays the value of a variable. For example:</p>
<ul>
    <li><code>{{ title }}</code> → Displays the title</li>
    <li><code>{{ content }}</code> → Displays the content</li>
    <li><code>{{ site_title }}</code> → Displays your site's title</li>
</ul>

<h3>Accessing Entry Properties</h3>
<p>When working with entries, use dot notation to access properties:</p>

<pre><code>{{ entry.title }}
{{ entry.content }}
{{ entry.author }}
{{ entry.created_at }}</code></pre>

<h3>Filters</h3>
<p>Filters modify how values are displayed. Use the pipe character (<code>|</code>) to apply filters:</p>

<h4>Raw Filter</h4>
<p>Use the <strong>raw</strong> filter to display HTML without escaping:</p>

<pre><code>{{ content|raw }}</code></pre>

<p><strong>Important:</strong> Always use <code>|raw</code> for Editor field content so formatting (bold, lists, etc.) displays correctly!</p>

<h4>Date Filter</h4>
<p>Format dates using the date filter:</p>

<pre><code>{{ created_at|date:"F j, Y" }}</code></pre>

<p>This displays dates in a readable format. Example:</p>
<ul>
    <li><code>{{ created_at|date:"F j, Y" }}</code> → "January 15, 2025"</li>
    <li><code>{{ created_at|date:"m/d/Y" }}</code> → "01/15/2025"</li>
</ul>

<h3>Conditionals</h3>
<p>Show or hide content based on conditions:</p>

<h4>If Something Exists</h4>
<pre><code>{if tags exist}
    &lt;div class="tags"&gt;Tags available!&lt;/div&gt;
{endif}</code></pre>

<p>Only shows the content if the "tags" field has a value.</p>

<h4>If Entry Property Exists</h4>
<pre><code>{if entry.excerpt exist}
    &lt;p&gt;{{ entry.excerpt }}&lt;/p&gt;
{endif}</code></pre>

<h3>Loops - Displaying Lists</h3>
<p>Loops let you display multiple entries or items.</p>

<h4>Displaying Entries from a Collection</h4>
<p>To show all entries from a collection (like all blog posts):</p>

<pre><code>{for each entry in 'blog'}
    &lt;article&gt;
        &lt;h2&gt;{{ entry.title }}&lt;/h2&gt;
        &lt;p&gt;{{ entry.excerpt }}&lt;/p&gt;
        &lt;a href="/post.html?slug={{ entry.slug }}"&gt;Read More&lt;/a&gt;
    &lt;/article&gt;
{endfor}</code></pre>

<p>This loops through all entries in the "blog" collection and displays each one.</p>

<h4>Displaying Tags</h4>
<p>To display a list of tags:</p>

<pre><code>{foreach entry.tags as tag}
    &lt;span class="tag"&gt;{{ tag }}&lt;/span&gt;
{endforeach}</code></pre>

<p>This loops through the tags array and displays each tag.</p>

<h3>Complete Example</h3>
<p>Here's a complete blog post template:</p>

<pre><code>&lt;!DOCTYPE html&gt;
&lt;html&gt;
&lt;head&gt;
    &lt;title&gt;{{ title }} - {{ site_title }}&lt;/title&gt;
&lt;/head&gt;
&lt;body&gt;
    &lt;article&gt;
        &lt;h1&gt;{{ title }}&lt;/h1&gt;
        
        &lt;div class="meta"&gt;
            &lt;time&gt;{{ created_at|date:"F j, Y" }}&lt;/time&gt;
            
            {if tags exist}
            &lt;div class="tags"&gt;
                {foreach tags as tag}
                &lt;span&gt;{{ tag }}&lt;/span&gt;
                {endforeach}
            &lt;/div&gt;
            {endif}
        &lt;/div&gt;
        
        &lt;div class="content"&gt;
            {{ content|raw }}
        &lt;/div&gt;
    &lt;/article&gt;
&lt;/body&gt;
&lt;/html&gt;</code></pre>

<h3>Common Patterns</h3>

<h4>Display Entry List</h4>
<pre><code>{for each entry in 'blog'}
    &lt;article&gt;
        &lt;h2&gt;&lt;a href="/post.html?slug={{ entry.slug }}"&gt;{{ entry.title }}&lt;/a&gt;&lt;/h2&gt;
        &lt;time&gt;{{ entry.created_at|date:"F j, Y" }}&lt;/time&gt;
        
        {if entry.excerpt exist}
        &lt;p&gt;{{ entry.excerpt }}&lt;/p&gt;
        {endif}
        
        &lt;a href="/post.html?slug={{ entry.slug }}"&gt;Read More →&lt;/a&gt;
    &lt;/article&gt;
{endfor}</code></pre>

<h4>Conditional Content</h4>
<pre><code>{if entry.featured_image exist}
    &lt;img src="{{ entry.featured_image }}" alt="{{ entry.title }}"&gt;
{endif}</code></pre>

<h3>Tips and Best Practices</h3>
<ul>
    <li><strong>Always use <code>|raw</code> for Editor fields:</strong> This ensures formatting displays correctly</li>
    <li><strong>Use conditionals:</strong> Check if fields exist before displaying them</li>
    <li><strong>Organize your templates:</strong> Keep templates simple and readable</li>
    <li><strong>Test thoroughly:</strong> Check your templates with different content</li>
</ul>

<p>Now you're ready to create your website templates! Continue to the <strong>Website Integration</strong> section to learn how to connect your templates to HaruCMS.</p>

