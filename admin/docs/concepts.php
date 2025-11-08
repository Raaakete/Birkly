<h2>Key Concepts</h2>
<p>Understanding these core concepts will help you make the most of HaruCMS.</p>

<h3>Collections</h3>
<p>A <strong>collection</strong> is like a folder that organizes similar content together. Think of it as a content type.</p>

<div class="example-box">
    <h4>Example Collections:</h4>
    <ul>
        <li><strong>Blog Posts:</strong> All your blog articles</li>
        <li><strong>Products:</strong> All your product listings</li>
        <li><strong>Team Members:</strong> Information about your staff</li>
        <li><strong>Testimonials:</strong> Customer reviews</li>
        <li><strong>Contact Submissions:</strong> Form submissions from your website</li>
    </ul>
</div>

<p>Each collection has an <strong>Entry Method</strong> that determines how entries can be created:</p>
<ul>
    <li><strong>Admin Only</strong> (default) - Only entries created through the admin panel are allowed</li>
    <li><strong>Public API</strong> - Allows entries to be submitted via external websites using an API key (perfect for contact forms, newsletter signups, etc.)</li>
</ul>

<p>Each collection has a <strong>schema</strong> that defines what fields (information) each entry in that collection will have. For example, a Blog Post collection might have fields like:</p>
<ul>
    <li>Title</li>
    <li>Content</li>
    <li>Author</li>
    <li>Published Date</li>
    <li>Tags</li>
</ul>

<h3>Entries</h3>
<p>An <strong>entry</strong> is a single piece of content within a collection. If a collection is like a folder, entries are the files inside it.</p>

<div class="example-box">
    <h4>Example:</h4>
    <p>If "Blog Posts" is your collection, then:</p>
    <ul>
        <li>"10 Tips for Better Web Design" is an entry</li>
        <li>"Getting Started with HaruCMS" is another entry</li>
        <li>Each entry has its own title, content, and other fields</li>
    </ul>
</div>

<h3>Fields</h3>
<p><strong>Fields</strong> are the individual pieces of information that make up an entry. Think of them as form fields that you fill out.</p>

<p>Different field types serve different purposes:</p>
<ul>
    <li><strong>Text:</strong> Short text like titles or names</li>
    <li><strong>Textarea:</strong> Longer text like descriptions</li>
    <li><strong>Editor:</strong> Rich text with formatting (bold, italic, lists, etc.)</li>
    <li><strong>Media:</strong> Upload images or files</li>
    <li><strong>Date:</strong> Date and time selection</li>
    <li><strong>Tags:</strong> Multiple keywords or tags</li>
</ul>

<h3>Media</h3>
<p>The <strong>Media Library</strong> is where all your uploaded files (images, PDFs, etc.) are stored and organized. You can upload files here and then use them in your content entries.</p>

<h3>How They Work Together</h3>
<p>Here's a simple flow:</p>
<ol>
    <li>Create a <strong>Collection</strong> (e.g., "Blog Posts")</li>
    <li>Define <strong>Fields</strong> for that collection (Title, Content, Author, etc.)</li>
    <li>Upload <strong>Media</strong> files (like a featured image)</li>
    <li>Create <strong>Entries</strong> and fill in the fields</li>
    <li>Display entries on your website using the templating language</li>
</ol>

<p>The next sections will show you exactly how to do each of these steps!</p>

