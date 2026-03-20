<h2>Collections</h2>
<p>Collections are the foundation of organizing your content in Birkly. They group similar content together and define what information each piece of content should have.</p>

<h3>What is a Collection?</h3>
<p>A <strong>collection</strong> is a type of content. It's like creating a template that defines:</p>
<ul>
    <li>What information you'll collect for each entry</li>
    <li>What fields (inputs) each entry should have</li>
    <li>How the content will be organized</li>
</ul>

<h3>Common Collection Examples</h3>
<div class="example-box">
    <h4>Blog Collection</h4>
    <p>Fields might include:</p>
    <ul>
        <li>Title (Text)</li>
        <li>Content (Editor - for rich text)</li>
        <li>Excerpt (Textarea)</li>
        <li>Author (Text)</li>
        <li>Published Date (Date)</li>
        <li>Tags (Tags)</li>
        <li>Featured Image (Media)</li>
    </ul>
</div>

<div class="example-box">
    <h4>Products Collection</h4>
    <p>Fields might include:</p>
    <ul>
        <li>Product Name (Text)</li>
        <li>Description (Editor)</li>
        <li>Price (Number)</li>
        <li>Product Images (Gallery)</li>
        <li>Category (Select)</li>
        <li>In Stock (Checkbox)</li>
    </ul>
</div>

<h3>Creating a Collection</h3>
<p>To create a new collection:</p>
<ol>
    <li>Go to <strong>Content</strong> in the admin sidebar</li>
    <li>Click <strong>"Create Collection"</strong> or <strong>"New Collection"</strong></li>
    <li>Enter a name (e.g., "Blog Posts", "Products")</li>
    <li>Add fields by clicking <strong>"Add Field"</strong></li>
    <li>For each field, specify:
        <ul>
            <li>Field name (e.g., "title", "content")</li>
            <li>Field type (Text, Editor, Media, etc.)</li>
            <li>Whether it's required</li>
        </ul>
    </li>
    <li>Go to the <strong>Settings</strong> tab to configure:
        <ul>
            <li><strong>Entry Method:</strong> Choose "Admin Only" (default) or "Public API" (for forms)</li>
            <li>If "Public API" is selected, configure security settings (rate limit, auto-publish, CAPTCHA, etc.)</li>
        </ul>
    </li>
    <li>Click <strong>"Save Collection"</strong></li>
</ol>

<h3>Entry Method</h3>
<p>Each collection has an <strong>Entry Method</strong> that determines how entries can be created:</p>
<ul>
    <li><strong>Admin Only</strong> (default) - Only entries created through the admin panel are allowed. This is the standard method for most content.</li>
    <li><strong>Public API</strong> - Allows entries to be submitted via external websites using an API key. Perfect for contact forms, newsletter signups, feedback forms, and other user-submitted content.</li>
</ul>

<p>When you select "Public API":</p>
<ul>
    <li>An API key is automatically generated</li>
    <li>You can configure security settings (rate limit, auto-publish, CAPTCHA, etc.)</li>
    <li>You can use the <code>{entry for}</code> directive in your website templates to create forms</li>
    <li>All submissions are saved as entries in your collection</li>
</ul>

<p>For more information on using Public API forms, see the <strong>Public API Forms</strong> documentation.</p>

<h3>Field Types Explained</h3>
<p>Each field type serves a specific purpose:</p>

<table>
    <thead>
        <tr>
            <th>Field Type</th>
            <th>Best For</th>
            <th>Example</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>Text</strong></td>
            <td>Short, single-line text</td>
            <td>Titles, names, labels</td>
        </tr>
        <tr>
            <td><strong>Textarea</strong></td>
            <td>Longer text without formatting</td>
            <td>Descriptions, summaries</td>
        </tr>
        <tr>
            <td><strong>Editor</strong></td>
            <td>Formatted text with styling</td>
            <td>Blog post content, articles</td>
        </tr>
        <tr>
            <td><strong>Media</strong></td>
            <td>Single image or file</td>
            <td>Featured images, documents</td>
        </tr>
        <tr>
            <td><strong>Gallery</strong></td>
            <td>Multiple images</td>
            <td>Product galleries, portfolios</td>
        </tr>
        <tr>
            <td><strong>Date</strong></td>
            <td>Date selection</td>
            <td>Publish dates, events</td>
        </tr>
        <tr>
            <td><strong>Tags</strong></td>
            <td>Multiple keywords</td>
            <td>Categories, topics, labels</td>
        </tr>
        <tr>
            <td><strong>Number</strong></td>
            <td>Numeric values</td>
            <td>Prices, quantities, ratings</td>
        </tr>
        <tr>
            <td><strong>Checkbox</strong></td>
            <td>Yes/No options</td>
            <td>Published, featured, active</td>
        </tr>
    </tbody>
</table>

<h3>Editing a Collection</h3>
<p>You can always edit a collection after creating it:</p>
<ul>
    <li>Add new fields</li>
    <li>Remove fields (be careful - this deletes that data from all entries!)</li>
    <li>Change field types (may require data migration)</li>
    <li>Rename the collection</li>
</ul>

<h3>Best Practices</h3>
<ul>
    <li><strong>Plan ahead:</strong> Think about what information you'll need before creating fields</li>
    <li><strong>Use clear names:</strong> Field names like "title" or "content" are better than "field1"</li>
    <li><strong>Choose the right type:</strong> Use Editor for formatted content, Textarea for plain text</li>
    <li><strong>Make important fields required:</strong> This ensures you don't forget critical information</li>
</ul>

<p>Once you've created a collection, you're ready to add entries! Continue to the <strong>Entries</strong> section to learn how.</p>

