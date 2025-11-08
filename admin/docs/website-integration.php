<h2>Website Integration</h2>
<p>This section explains how to connect your website to HaruCMS so it can display your content.</p>

<h3>Overview</h3>
<p>HaruCMS uses a <strong>client library</strong> that automatically connects your website to the CMS. You don't need to write complex code - just add one script tag to your HTML!</p>

<h3>The Simple Way</h3>
<p>To connect your website to HaruCMS:</p>

<ol>
    <li>Add the HaruCMS client library script to your HTML file</li>
    <li>Use the templating language in your HTML</li>
    <li>That's it! Your content will automatically load</li>
</ol>

<h3>Adding the Client Library</h3>
<p>Add this script tag before the closing <code>&lt;/body&gt;</code> tag in your HTML:</p>

<pre><code>&lt;script src="https://your-cms-domain.com/harucms-client.js" 
        data-cms-url="https://your-cms-domain.com"&gt;&lt;/script&gt;</code></pre>

<p><strong>For local development:</strong></p>
<pre><code>&lt;script src="/harucms-client.js" 
        data-cms-url="http://localhost:8080"&gt;&lt;/script&gt;</code></pre>

<p>The client library automatically:</p>
<ul>
    <li>Connects to your CMS</li>
    <li>Fetches your content</li>
    <li>Renders templates</li>
    <li>Handles routing</li>
</ul>

<h3>Collection Detection</h3>
<p>The client library automatically detects which collection to use based on:</p>

<ol>
    <li><strong>URL path:</strong> If your URL is <code>/blog/post.html</code>, it looks for a "blog" collection</li>
    <li><strong>Template syntax:</strong> If your template has <code>{for each entry in 'blog'}</code>, it uses "blog"</li>
    <li><strong>Data attribute:</strong> You can specify a collection manually (see below)</li>
</ol>

<h3>Manual Collection Specification</h3>
<p>If you want to explicitly specify which collection to use, add the <code>data-collection</code> attribute:</p>

<pre><code>&lt;script src="/harucms-client.js" 
        data-cms-url="http://localhost:8080"
        data-collection="blog"&gt;&lt;/script&gt;</code></pre>

<h3>How It Works</h3>
<p>Here's what happens when someone visits your website:</p>

<ol>
    <li>Your HTML page loads</li>
    <li>The client library script runs</li>
    <li>It scans your HTML for template syntax (<code>{{ }}</code>, <code>{for}</code>, etc.)</li>
    <li>It connects to your CMS API</li>
    <li>It fetches the necessary content</li>
    <li>It replaces template placeholders with actual content</li>
    <li>The page displays with your content!</li>
</ol>

<h3>Complete Example</h3>
<p>Here's a complete example of an HTML file connected to HaruCMS:</p>

<pre><code>&lt;!DOCTYPE html&gt;
&lt;html lang="en"&gt;
&lt;head&gt;
    &lt;meta charset="UTF-8"&gt;
    &lt;title&gt;My Blog&lt;/title&gt;
    &lt;link rel="stylesheet" href="styles.css"&gt;
&lt;/head&gt;
&lt;body&gt;
    &lt;header&gt;
        &lt;h1&gt;My Website&lt;/h1&gt;
        &lt;nav&gt;
            &lt;a href="/"&gt;Home&lt;/a&gt;
            &lt;a href="/about.html"&gt;About&lt;/a&gt;
        &lt;/nav&gt;
    &lt;/header&gt;
    
    &lt;main&gt;
        {for each entry in 'blog'}
        &lt;article class="post-card"&gt;
            &lt;h2&gt;
                &lt;a href="/post.html?slug={{ entry.slug }}"&gt;
                    {{ entry.title }}
                &lt;/a&gt;
            &lt;/h2&gt;
            
            &lt;div class="meta"&gt;
                &lt;time&gt;{{ entry.created_at|date:"F j, Y" }}&lt;/time&gt;
                
                {if entry.tags exist}
                &lt;div class="tags"&gt;
                    {foreach entry.tags as tag}
                    &lt;span class="tag"&gt;{{ tag }}&lt;/span&gt;
                    {endforeach}
                &lt;/div&gt;
                {endif}
            &lt;/div&gt;
            
            {if entry.excerpt exist}
            &lt;p class="excerpt"&gt;{{ entry.excerpt }}&lt;/p&gt;
            {endif}
            
            &lt;a href="/post.html?slug={{ entry.slug }}" class="read-more"&gt;
                Read More →
            &lt;/a&gt;
        &lt;/article&gt;
        {endfor}
    &lt;/main&gt;
    
    &lt;footer&gt;
        &lt;p&gt;&copy; My Website {{ current_year }}&lt;/p&gt;
    &lt;/footer&gt;
    
    &lt;!-- HaruCMS Client Library --&gt;
    &lt;script src="/harucms-client.js" 
            data-cms-url="http://localhost:8080"&gt;&lt;/script&gt;
&lt;/body&gt;
&lt;/html&gt;</code></pre>

<h3>Single Entry Pages</h3>
<p>For displaying a single entry (like a blog post detail page), use URL parameters:</p>

<pre><code>&lt;!-- In your index.html --&gt;
&lt;a href="/post.html?slug={{ entry.slug }}"&gt;Read More&lt;/a&gt;

&lt;!-- In your post.html --&gt;
&lt;article&gt;
    &lt;h1&gt;{{ title }}&lt;/h1&gt;
    &lt;div class="content"&gt;{{ content|raw }}&lt;/div&gt;
&lt;/article&gt;

&lt;script src="/harucms-client.js" 
        data-cms-url="http://localhost:8080"
        data-collection="blog"&gt;&lt;/script&gt;</code></pre>

<p>The client library automatically:</p>
<ul>
    <li>Detects the <code>?slug=...</code> parameter</li>
    <li>Fetches the specific entry with that slug</li>
    <li>Populates the template with that entry's data</li>
</ul>

<h3>Domain Registration</h3>
<p>For security, you need to register your website's domain in HaruCMS:</p>

<ol>
    <li>Go to <strong>Settings → Domains</strong> in the admin panel</li>
    <li>Click <strong>"Register Domain"</strong></li>
    <li>Enter your domain name (e.g., "example.com")</li>
    <li>Set permissions (Read Only or Read & Write)</li>
    <li>Click <strong>"Register"</strong></li>
</ol>

<p><strong>Note:</strong> For local development (localhost), domains are automatically allowed.</p>

<h3>Security Best Practices</h3>
<ul>
    <li><strong>Register domains:</strong> Only registered domains can access your CMS content</li>
    <li><strong>Use HTTPS:</strong> Always use HTTPS in production for secure communication</li>
    <li><strong>Set permissions:</strong> Use "Read Only" for public websites unless you need write access</li>
</ul>

<h3>Troubleshooting</h3>
<p>If content isn't displaying:</p>
<ol>
    <li>Check that the script tag is present and correct</li>
    <li>Verify the CMS URL is correct</li>
    <li>Ensure your domain is registered in Settings → Domains</li>
    <li>Check the browser console for any error messages</li>
    <li>Verify your collection name matches the template syntax</li>
</ol>

<p>Congratulations! You now know how to integrate HaruCMS with your website!</p>

