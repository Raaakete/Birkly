<h2>Public API Forms</h2>
<p>Birkly allows you to create collections that accept submissions from external websites. This is perfect for contact forms, newsletter signups, feedback forms, and more.</p>

<h3>What is Entry Method?</h3>
<p>Each collection has an <strong>Entry Method</strong> that determines how entries can be created:</p>

<ul>
    <li><strong>Admin Only</strong> (default) - Only entries created through the admin panel are allowed</li>
    <li><strong>Public API</strong> - Allows entries to be submitted via external websites using an API key</li>
</ul>

<h3>Setting Up a Public API Collection</h3>

<ol>
    <li>Go to <strong>Content → Collections</strong> in the admin panel</li>
    <li>Click on the collection you want to make public (or create a new one)</li>
    <li>Go to the <strong>Settings</strong> tab</li>
    <li>Change <strong>Entry Method</strong> from "Admin Only" to "Public API"</li>
    <li>An API key will be automatically generated</li>
    <li>Configure the security settings:
        <ul>
            <li><strong>Auto-publish:</strong> Automatically publish submissions (or keep as drafts for moderation)</li>
            <li><strong>Rate Limit:</strong> Maximum submissions per hour (default: 10)</li>
            <li><strong>Notification Email:</strong> Email to receive notifications when submissions are received</li>
            <li><strong>CAPTCHA Provider:</strong> Optional CAPTCHA protection (reCAPTCHA v3 or hCaptcha)</li>
        </ul>
    </li>
    <li>Click <strong>Save Collection</strong></li>
</ol>

<h3>Using Public API Forms on Your Website</h3>

<p>There are two ways to create forms that submit to your Birkly collection:</p>

<h4>Method 1: Template-First Form (Recommended)</h4>

<p>Define your form HTML directly in your template using the <code>{entry for}</code> directive:</p>

<pre><code>&lt;div class="contact-form-wrapper"&gt;
{entry for 'contact'}
    &lt;div class="form-group"&gt;
        &lt;label for="name"&gt;Name &lt;span class="required"&gt;*&lt;/span&gt;&lt;/label&gt;
        &lt;input type="text" name="{name}" id="name" required /&gt;
    &lt;/div&gt;
    
    &lt;div class="form-group"&gt;
        &lt;label for="email"&gt;Email &lt;span class="required"&gt;*&lt;/span&gt;&lt;/label&gt;
        &lt;input type="email" name="{email}" id="email" required /&gt;
    &lt;/div&gt;
    
    &lt;div class="form-group"&gt;
        &lt;label for="message"&gt;Message &lt;span class="required"&gt;*&lt;/span&gt;&lt;/label&gt;
        &lt;textarea name="{message}" id="message" rows="5" required&gt;&lt;/textarea&gt;
    &lt;/div&gt;
    
    &lt;button type="submit"&gt;Send Message&lt;/button&gt;
{endentry}
&lt;/div&gt;

&lt;script src="/birkly-client.js" 
        data-cms-url="https://your-cms-domain.com"&gt;&lt;/script&gt;</code></pre>

<p><strong>How it works:</strong></p>
<ul>
    <li>The <code>{entry for 'contact'}</code> directive wraps your form HTML</li>
    <li>Field names use template variables like <code>{name}</code>, <code>{email}</code>, <code>{message}</code></li>
    <li>The client library automatically:
        <ul>
            <li>Fetches the collection schema and API key</li>
            <li>Wraps your HTML in a proper <code>&lt;form&gt;</code> tag</li>
            <li>Maps field names to the collection schema</li>
            <li>Handles form submission to the API</li>
            <li>Shows success/error messages</li>
        </ul>
    </li>
</ul>

<h4>Method 2: Auto-Generated Form</h4>

<p>Use the <code>{public_form}</code> directive to automatically generate a form based on your collection schema:</p>

<pre><code>&lt;div class="contact-form-wrapper"&gt;
    {public_form 'contact'}
&lt;/div&gt;

&lt;script src="/birkly-client.js" 
        data-cms-url="https://your-cms-domain.com"&gt;&lt;/script&gt;</code></pre>

<p><strong>How it works:</strong></p>
<ul>
    <li>The client library automatically generates a form based on your collection's field types</li>
    <li>All fields from your collection schema are included</li>
    <li>Form styling can be customized with CSS</li>
</ul>

<h3>Field Name Mapping</h3>

<p>When using <code>{entry for}</code>, field names in your HTML must match your collection's field slugs:</p>

<ul>
    <li>If your collection has a field with slug <code>email</code>, use <code>name="{email}"</code></li>
    <li>If your collection has a field with slug <code>full_name</code>, use <code>name="{full_name}"</code></li>
    <li>Field slugs are shown in the <strong>Templating</strong> tab of the collection editor</li>
</ul>

<h3>Complete Example: Contact Form</h3>

<pre><code>&lt;!DOCTYPE html&gt;
&lt;html lang="en"&gt;
&lt;head&gt;
    &lt;meta charset="UTF-8"&gt;
    &lt;meta name="viewport" content="width=device-width, initial-scale=1.0"&gt;
    &lt;title&gt;Contact Us&lt;/title&gt;
    &lt;style&gt;
        .contact-form-wrapper {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background: #f9fafb;
            border-radius: 8px;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        .required {
            color: #ef4444;
        }
        input[type="text"],
        input[type="email"],
        textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 1rem;
        }
        button[type="submit"] {
            background: #3b82f6;
            color: white;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
        }
        button[type="submit"]:hover {
            background: #2563eb;
        }
    &lt;/style&gt;
&lt;/head&gt;
&lt;body&gt;
    &lt;h1&gt;Contact Us&lt;/h1&gt;
    
    &lt;div class="contact-form-wrapper"&gt;
        {entry for 'contact'}
            &lt;div class="form-group"&gt;
                &lt;label for="name"&gt;Name &lt;span class="required"&gt;*&lt;/span&gt;&lt;/label&gt;
                &lt;input type="text" name="{name}" id="name" required /&gt;
            &lt;/div&gt;
            
            &lt;div class="form-group"&gt;
                &lt;label for="email"&gt;Email &lt;span class="required"&gt;*&lt;/span&gt;&lt;/label&gt;
                &lt;input type="email" name="{email}" id="email" required /&gt;
            &lt;/div&gt;
            
            &lt;div class="form-group"&gt;
                &lt;label for="subject"&gt;Subject&lt;/label&gt;
                &lt;input type="text" name="{subject}" id="subject" /&gt;
            &lt;/div&gt;
            
            &lt;div class="form-group"&gt;
                &lt;label for="message"&gt;Message &lt;span class="required"&gt;*&lt;/span&gt;&lt;/label&gt;
                &lt;textarea name="{message}" id="message" rows="5" required&gt;&lt;/textarea&gt;
            &lt;/div&gt;
            
            &lt;button type="submit"&gt;Send Message&lt;/button&gt;
        {endentry}
    &lt;/div&gt;
    
    &lt;script src="/birkly-client.js" 
            data-cms-url="https://your-cms-domain.com"&gt;&lt;/script&gt;
&lt;/body&gt;
&lt;/html&gt;</code></pre>

<h3>Security Features</h3>

<p>Public API forms include several security features:</p>

<ul>
    <li><strong>API Key Authentication:</strong> Each collection has a unique API key</li>
    <li><strong>Rate Limiting:</strong> Prevents spam by limiting submissions per hour</li>
    <li><strong>Honeypot:</strong> Hidden field to catch bots</li>
    <li><strong>CAPTCHA:</strong> Optional human verification (reCAPTCHA v3 or hCaptcha)</li>
    <li><strong>Field Validation:</strong> Validates all submissions against your collection schema</li>
    <li><strong>Auto-publish Control:</strong> Choose whether submissions are published immediately or saved as drafts</li>
</ul>

<h3>Viewing Submissions</h3>

<p>All submissions are saved as entries in your collection:</p>

<ol>
    <li>Go to <strong>Content → Collections</strong></li>
    <li>Click on your collection (e.g., "Contact")</li>
    <li>View all submitted entries in the entries list</li>
    <li>If auto-publish is disabled, entries will be saved as drafts for moderation</li>
</ol>

<h3>Troubleshooting</h3>

<p><strong>Form not submitting:</strong></p>
<ul>
    <li>Check that the collection's Entry Method is set to "Public API"</li>
    <li>Verify the API key is correct (check in collection settings)</li>
    <li>Check browser console for error messages</li>
    <li>Ensure your domain is registered in Settings → Domains</li>
</ul>

<p><strong>Field names not working:</strong></p>
<ul>
    <li>Field names must match your collection's field slugs exactly</li>
    <li>Check the <strong>Templating</strong> tab in the collection editor for correct field slugs</li>
    <li>Use template syntax: <code>name="{field_slug}"</code> not <code>name="field_slug"</code></li>
</ul>

<p><strong>Rate limit errors:</strong></p>
<ul>
    <li>Increase the rate limit in collection settings if needed</li>
    <li>Check that you're not submitting too frequently during testing</li>
</ul>

<h3>Best Practices</h3>

<ul>
    <li><strong>Use descriptive field slugs:</strong> Make field slugs clear (e.g., <code>email</code>, <code>full_name</code>)</li>
    <li><strong>Set appropriate rate limits:</strong> Balance security with usability</li>
    <li><strong>Enable auto-publish for simple forms:</strong> Contact forms can usually be auto-published</li>
    <li><strong>Use moderation for important submissions:</strong> Keep auto-publish off for critical forms</li>
    <li><strong>Test thoroughly:</strong> Test your form with different inputs before going live</li>
    <li><strong>Monitor submissions:</strong> Check your collection regularly for new submissions</li>
</ul>

<p>For more advanced usage, see the <strong>Advanced JavaScript API</strong> section.</p>

