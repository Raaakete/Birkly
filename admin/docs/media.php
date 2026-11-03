<h2>Media</h2>
<p>The Media Library is where you store and organize all the files (images, PDFs, videos, etc.) that you use in your content.</p>

<h3>What is the Media Library?</h3>
<p>The <strong>Media Library</strong> is a central location for all your uploaded files. Instead of storing files in random places, the Media Library:</p>
<ul>
    <li>Keeps all your files organized in one place</li>
    <li>Lets you easily find and reuse files</li>
    <li>Provides metadata (file size, upload date, etc.)</li>
    <li>Allows you to insert files into your content entries</li>
</ul>

<h3>Uploading Files</h3>
<p>To upload a file:</p>
<ol>
    <li>Go to <strong>Media</strong> in the sidebar</li>
    <li>Click <strong>"Upload File"</strong> or drag and drop files</li>
    <li>Select the file(s) you want to upload</li>
    <li>Wait for the upload to complete</li>
</ol>

<p><strong>Supported file types:</strong> Images (JPG, PNG, GIF, SVG), PDFs, documents, videos, and more!</p>

<h3>Using Media in Entries</h3>
<p>Once uploaded, you can use media files in your content entries:</p>

<ol>
    <li>When editing an entry, find a <strong>Media</strong> or <strong>Gallery</strong> field</li>
    <li>Click the field to open the Media Library</li>
    <li>Select the file(s) you want to use</li>
    <li>The files are automatically inserted into your entry</li>
</ol>

<h3>Organizing Your Media</h3>
<p>As your Media Library grows, organization becomes important:</p>

<ul>
    <li><strong>Use descriptive filenames:</strong> "product-image-red.jpg" is better than "img123.jpg"</li>
    <li><strong>Upload when needed:</strong> Upload files when you create entries, or upload in advance</li>
    <li><strong>Delete unused files:</strong> Remove files you no longer need to keep things tidy</li>
</ul>

<h3>Best Practices</h3>
<ul>
    <li><strong>Optimize images:</strong> Compress images before uploading for faster website loading</li>
    <li><strong>Use appropriate formats:</strong> JPG for photos, PNG for graphics with transparency</li>
    <li><strong>Name files clearly:</strong> Descriptive names help you find files later</li>
    <li><strong>Keep file sizes reasonable:</strong> Large files slow down your website</li>
</ul>

<h3>Editing and Deleting Media</h3>
<p>Click a file to open the detail sheet. From there you can:</p>
<ul>
    <li><strong>Download:</strong> Save the original file to your computer</li>
    <li><strong>Replace file:</strong> Upload a new master file while keeping the same media ID (entries and URLs that reference the ID stay valid)</li>
    <li><strong>Alt text and metadata:</strong> Edit display name, alt text, and folder</li>
    <li><strong>Delete:</strong> Remove files you no longer need (be careful — this removes them from entries too)</li>
</ul>

<h3>Crop and focal point (images)</h3>
<p>For raster images, the preview includes a framing editor:</p>
<ul>
    <li><strong>Focal point:</strong> Click the image to set where cropping should center (used in templates with <code>{media_img}</code>)</li>
    <li><strong>Crop region:</strong> Drag the box to move the crop; drag the corner handle to resize. Percent fields stay in sync with the overlay</li>
    <li><strong>Save details:</strong> Persists focal point and crop metadata on the master file (non-destructive — the original pixels are unchanged)</li>
    <li><strong>Create web variant:</strong> Writes an optimized derivative (WebP when supported) for faster pages; reference in templates with <code>{media_img('field', alt, 'web')}</code></li>
    <li><strong>Bake crop to file:</strong> After saving a partial crop, exports a cropped derivative file (<code>variants.crop</code>) you can use where a fixed crop asset is needed</li>
</ul>
<p>SVG and non-image files do not show crop or variant actions. Variant generation requires PHP GD on the server.</p>

<p>Now that you know how to manage media, you're ready to create rich, visual content!</p>

