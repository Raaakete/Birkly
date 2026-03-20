<h2>Advanced JavaScript API</h2>
<p>For developers who prefer to use custom JavaScript instead of the templating language, Birkly provides a direct JavaScript API to fetch and manipulate content.</p>

<h3>Why Use the JavaScript API?</h3>
<p>You might want to use the JavaScript API if you:</p>
<ul>
    <li>Prefer writing custom JavaScript over templating syntax</li>
    <li>Need more control over how content is rendered</li>
    <li>Want to integrate with frameworks like React, Vue, or Angular</li>
    <li>Need to perform complex data manipulation before rendering</li>
    <li>Want to build dynamic, interactive interfaces</li>
</ul>

<h3>API Endpoint</h3>
<p>The public API endpoint is available at:</p>
<pre><code>https://your-cms-domain.com/api/public.php</code></pre>

<h3>Available Actions</h3>

<h4>1. List Collections</h4>
<pre><code>const response = await fetch('https://your-cms-domain.com/api/public.php?action=list_collections');
const data = await response.json();
// Returns: { status: 'ok', data: { collections: [...] } }</code></pre>

<h4>2. List Entries in a Collection</h4>
<pre><code>const response = await fetch('https://your-cms-domain.com/api/public.php?action=list_entries&collection=blog');
const data = await response.json();
// Returns: { status: 'ok', data: { entries: [...] } }</code></pre>

<h4>3. Get a Single Entry</h4>
<pre><code>const response = await fetch('https://your-cms-domain.com/api/public.php?action=get_entry&collection=blog&slug=my-post-slug');
const data = await response.json();
// Returns: { status: 'ok', data: { entry: {...} } }</code></pre>

<h4>4. Get Site Settings</h4>
<pre><code>const response = await fetch('https://your-cms-domain.com/api/public.php?action=get_site_settings');
const data = await response.json();
// Returns: { status: 'ok', data: { settings: {...} } }</code></pre>

<h4>5. Get Collection Info (for Public API forms)</h4>
<pre><code>const response = await fetch('https://your-cms-domain.com/api/public.php?action=get_collection_info&collection=contact');
const data = await response.json();
// Returns: { status: 'ok', data: { schema: {...}, api_key: '...' } }</code></pre>

<h3>Complete Example: Fetching and Rendering Blog Posts</h3>

<pre><code>&lt;!DOCTYPE html&gt;
&lt;html lang="en"&gt;
&lt;head&gt;
    &lt;meta charset="UTF-8"&gt;
    &lt;meta name="viewport" content="width=device-width, initial-scale=1.0"&gt;
    &lt;title&gt;My Blog&lt;/title&gt;
    &lt;style&gt;
        body { font-family: system-ui, sans-serif; max-width: 800px; margin: 0 auto; padding: 2rem; }
        .post { margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 1px solid #e5e7eb; }
        .post h2 { margin-top: 0; }
        .post-meta { color: #6b7280; font-size: 0.875rem; }
        .loading { text-align: center; padding: 2rem; }
        .error { color: #ef4444; padding: 1rem; background: #fef2f2; border-radius: 4px; }
    &lt;/style&gt;
&lt;/head&gt;
&lt;body&gt;
    &lt;h1&gt;My Blog&lt;/h1&gt;
    &lt;div id="blog-posts"&gt;
        &lt;div class="loading"&gt;Loading posts...&lt;/div&gt;
    &lt;/div&gt;

    &lt;script&gt;
        const CMS_URL = 'https://your-cms-domain.com';
        
        async function fetchBlogPosts() {
            try {
                const response = await fetch(`${CMS_URL}/api/public.php?action=list_entries&collection=blog`);
                const data = await response.json();
                
                if (data.status === 'ok' && data.data.entries) {
                    renderPosts(data.data.entries);
                } else {
                    showError('Failed to load blog posts');
                }
            } catch (error) {
                console.error('Error fetching blog posts:', error);
                showError('Error loading blog posts. Please try again later.');
            }
        }
        
        function renderPosts(posts) {
            const container = document.getElementById('blog-posts');
            
            if (posts.length === 0) {
                container.innerHTML = '&lt;p&gt;No posts found.&lt;/p&gt;';
                return;
            }
            
            container.innerHTML = posts.map(post => `
                &lt;article class="post"&gt;
                    &lt;h2&gt;&lt;a href="/post.html?slug=${post.slug}"&gt;${escapeHtml(post.title)}&lt;/a&gt;&lt;/h2&gt;
                    &lt;div class="post-meta"&gt;
                        &lt;time&gt;${formatDate(post.created_at)}&lt;/time&gt;
                        ${post.tags && post.tags.length > 0 ? `
                            &lt;div class="tags"&gt;
                                ${post.tags.map(tag => `&lt;span class="tag"&gt;${escapeHtml(tag)}&lt;/span&gt;`).join('')}
                            &lt;/div&gt;
                        ` : ''}
                    &lt;/div&gt;
                    ${post.excerpt ? `&lt;p&gt;${escapeHtml(post.excerpt)}&lt;/p&gt;` : ''}
                &lt;/article&gt;
            `).join('');
        }
        
        function showError(message) {
            const container = document.getElementById('blog-posts');
            container.innerHTML = `&lt;div class="error"&gt;${escapeHtml(message)}&lt;/div&gt;`;
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        }
        
        // Load posts when page loads
        fetchBlogPosts();
    &lt;/script&gt;
&lt;/body&gt;
&lt;/html&gt;</code></pre>

<h3>Example: Single Post Page</h3>

<pre><code>&lt;!DOCTYPE html&gt;
&lt;html lang="en"&gt;
&lt;head&gt;
    &lt;meta charset="UTF-8"&gt;
    &lt;meta name="viewport" content="width=device-width, initial-scale=1.0"&gt;
    &lt;title&gt;Loading...&lt;/title&gt;
&lt;/head&gt;
&lt;body&gt;
    &lt;div id="post-content"&gt;
        &lt;div class="loading"&gt;Loading post...&lt;/div&gt;
    &lt;/div&gt;

    &lt;script&gt;
        const CMS_URL = 'https://your-cms-domain.com';
        
        // Get slug from URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        const slug = urlParams.get('slug');
        
        if (!slug) {
            document.getElementById('post-content').innerHTML = '&lt;p&gt;Post not found.&lt;/p&gt;';
        } else {
            fetchPost('blog', slug);
        }
        
        async function fetchPost(collection, slug) {
            try {
                const response = await fetch(`${CMS_URL}/api/public.php?action=get_entry&collection=${collection}&slug=${slug}`);
                const data = await response.json();
                
                if (data.status === 'ok' && data.data.entry) {
                    renderPost(data.data.entry);
                } else {
                    document.getElementById('post-content').innerHTML = '&lt;p&gt;Post not found.&lt;/p&gt;';
                }
            } catch (error) {
                console.error('Error fetching post:', error);
                document.getElementById('post-content').innerHTML = '&lt;p&gt;Error loading post.&lt;/p&gt;';
            }
        }
        
        function renderPost(post) {
            // Update page title
            document.title = `${post.title} - My Blog`;
            
            // Render post content
            document.getElementById('post-content').innerHTML = `
                &lt;article&gt;
                    &lt;h1&gt;${escapeHtml(post.title)}&lt;/h1&gt;
                    &lt;div class="post-meta"&gt;
                        &lt;time&gt;${formatDate(post.created_at)}&lt;/time&gt;
                    &lt;/div&gt;
                    &lt;div class="post-content"&gt;
                        ${post.content || ''}
                    &lt;/div&gt;
                &lt;/article&gt;
            `;
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        }
    &lt;/script&gt;
&lt;/body&gt;
&lt;/html&gt;</code></pre>

<h3>Example: Submitting to Public API Collection</h3>

<pre><code>&lt;!DOCTYPE html&gt;
&lt;html lang="en"&gt;
&lt;head&gt;
    &lt;meta charset="UTF-8"&gt;
    &lt;meta name="viewport" content="width=device-width, initial-scale=1.0"&gt;
    &lt;title&gt;Contact Us&lt;/title&gt;
&lt;/head&gt;
&lt;body&gt;
    &lt;h1&gt;Contact Us&lt;/h1&gt;
    
    &lt;form id="contact-form"&gt;
        &lt;div&gt;
            &lt;label for="name"&gt;Name *&lt;/label&gt;
            &lt;input type="text" id="name" name="name" required&gt;
        &lt;/div&gt;
        
        &lt;div&gt;
            &lt;label for="email"&gt;Email *&lt;/label&gt;
            &lt;input type="email" id="email" name="email" required&gt;
        &lt;/div&gt;
        
        &lt;div&gt;
            &lt;label for="message"&gt;Message *&lt;/label&gt;
            &lt;textarea id="message" name="message" required&gt;&lt;/textarea&gt;
        &lt;/div&gt;
        
        &lt;button type="submit"&gt;Send Message&lt;/button&gt;
    &lt;/form&gt;
    
    &lt;div id="form-message"&gt;&lt;/div&gt;

    &lt;script&gt;
        const CMS_URL = 'https://your-cms-domain.com';
        const COLLECTION = 'contact';
        const API_KEY = 'your-api-key-here'; // Get this from collection settings
        
        // First, fetch collection info to get the API key
        async function getCollectionInfo() {
            try {
                const response = await fetch(`${CMS_URL}/api/public.php?action=get_collection_info&collection=${COLLECTION}`);
                const data = await response.json();
                
                if (data.status === 'ok' && data.data.api_key) {
                    return data.data.api_key;
                }
            } catch (error) {
                console.error('Error fetching collection info:', error);
            }
            return null;
        }
        
        document.getElementById('contact-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const formObject = {};
            formData.forEach((value, key) => {
                formObject[key] = value;
            });
            
            // Get API key (you can also hardcode it if you prefer)
            const apiKey = await getCollectionInfo() || API_KEY;
            
            try {
                const response = await fetch(`${CMS_URL}/api/public/collections.php?collection=${COLLECTION}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-API-Key': apiKey
                    },
                    body: JSON.stringify(formObject)
                });
                
                const data = await response.json();
                
                if (data.status === 'ok') {
                    document.getElementById('form-message').innerHTML = 
                        '&lt;div style="color: green; padding: 1rem; background: #f0fdf4; border-radius: 4px;"&gt;' +
                        'Message sent successfully! Thank you.' +
                        '&lt;/div&gt;';
                    e.target.reset();
                } else {
                    document.getElementById('form-message').innerHTML = 
                        '&lt;div style="color: red; padding: 1rem; background: #fef2f2; border-radius: 4px;"&gt;' +
                        `Error: ${data.message || 'Failed to send message'}` +
                        '&lt;/div&gt;';
                }
            } catch (error) {
                console.error('Error submitting form:', error);
                document.getElementById('form-message').innerHTML = 
                    '&lt;div style="color: red; padding: 1rem; background: #fef2f2; border-radius: 4px;"&gt;' +
                    'Error sending message. Please try again later.' +
                    '&lt;/div&gt;';
            }
        });
    &lt;/script&gt;
&lt;/body&gt;
&lt;/html&gt;</code></pre>

<h3>Example: React Integration</h3>

<pre><code>import React, { useState, useEffect } from 'react';

const CMS_URL = 'https://your-cms-domain.com';

function BlogPosts() {
    const [posts, setPosts] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    
    useEffect(() => {
        fetchBlogPosts();
    }, []);
    
    async function fetchBlogPosts() {
        try {
            const response = await fetch(`${CMS_URL}/api/public.php?action=list_entries&collection=blog`);
            const data = await response.json();
            
            if (data.status === 'ok' && data.data.entries) {
                setPosts(data.data.entries);
            } else {
                setError('Failed to load blog posts');
            }
        } catch (err) {
            setError('Error loading blog posts');
            console.error(err);
        } finally {
            setLoading(false);
        }
    }
    
    if (loading) return &lt;div&gt;Loading...&lt;/div&gt;;
    if (error) return &lt;div&gt;Error: {error}&lt;/div&gt;;
    
    return (
        &lt;div&gt;
            {posts.map(post => (
                &lt;article key={post.id || post.slug}&gt;
                    &lt;h2&gt;{post.title}&lt;/h2&gt;
                    &lt;time&gt;{new Date(post.created_at).toLocaleDateString()}&lt;/time&gt;
                    {post.excerpt && &lt;p&gt;{post.excerpt}&lt;/p&gt;}
                &lt;/article&gt;
            ))}
        &lt;/div&gt;
    );
}

export default BlogPosts;</code></pre>

<h3>Error Handling</h3>

<p>Always handle errors gracefully:</p>

<pre><code>async function fetchContent(collection) {
    try {
        const response = await fetch(`${CMS_URL}/api/public.php?action=list_entries&collection=${collection}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.status === 'ok') {
            return data.data.entries;
        } else {
            throw new Error(data.message || 'Unknown error');
        }
    } catch (error) {
        console.error('Error fetching content:', error);
        // Show user-friendly error message
        return [];
    }
}</code></pre>

<h3>Domain Authentication</h3>

<p>For security, your website's domain must be registered in Birkly:</p>

<ol>
    <li>Go to <strong>Settings → Domains</strong> in the admin panel</li>
    <li>Click <strong>"Register Domain"</strong></li>
    <li>Enter your domain name (e.g., "example.com")</li>
    <li>Set permissions (Read Only or Read & Write)</li>
    <li>Click <strong>"Register"</strong></li>
</ol>

<p><strong>Note:</strong> For local development (localhost), domains are automatically allowed.</p>

<h3>Best Practices</h3>

<ul>
    <li><strong>Cache responses:</strong> Cache API responses to reduce server load</li>
    <li><strong>Handle loading states:</strong> Show loading indicators while fetching data</li>
    <li><strong>Error handling:</strong> Always handle errors gracefully</li>
    <li><strong>Rate limiting:</strong> Be mindful of API rate limits</li>
    <li><strong>HTTPS:</strong> Always use HTTPS in production</li>
    <li><strong>Sanitize output:</strong> Always escape HTML when rendering user content</li>
</ul>

<h3>API Response Format</h3>

<p>All API responses follow this format:</p>

<pre><code>{
    "status": "ok" | "error",
    "message": "Optional message",
    "data": {
        // Response data here
    }
}</code></pre>

<h3>Next Steps</h3>

<p>For more information, see:</p>
<ul>
    <li><strong>Public API Forms</strong> - Using the templating language for forms</li>
    <li><strong>Website Integration</strong> - Basic integration guide</li>
    <li><strong>Templating Language</strong> - Template syntax reference</li>
</ul>

