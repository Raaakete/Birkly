<h2>Templating Language</h2>
<p>This guide explains the Birkly template language in a simple, human-readable way. You can copy the examples as-is. No programming knowledge required.</p>

<h3>Key ideas</h3>
<ul>
  <li><strong>Single braces</strong>: Use <code>{ like this }</code> (never double braces).</li>
  <li><strong>Plain English</strong>: Templates read like sentences.</li>
  <li><strong>Safety</strong>: Text is safely displayed by default. Rich editor content shows with formatting automatically.</li>
</ul>

<h3>Basic variables</h3>
<p>Variables show values. Inside a blog post, for example:</p>
<pre><code><h1>{ title }</h1>
<p>By { author }</p>
<time>{ date "F j, Y" }</time></code></pre>
<ul>
  <li><code>{ title }</code> — The current entry's title</li>
  <li><code>{ date "F j, Y" }</code> — Show a date with a readable format (e.g., January 5, 2025)</li>
  <li><code>{ content }</code> — Rich content (from the editor) shown with formatting</li>
</ul>

<h3>Showing a list of entries</h3>
<p>Loop through entries of a collection:</p>
<pre><code>{for each entry in 'blog'}
  <article>
    <h2><a href="/project/post.html?slug={ slug }">{ title }</a></h2>
    <time>{ date "F j, Y" }</time>

    {if entry has tags}
      <div class="tags">
        {for each tag}
          <span class="tag">{ tag }</span>
        {endfor}
      </div>
    {endif}

    {if entry has excerpt}
      <p>{ excerpt }</p>
    {endif}
  </article>
{endfor}</code></pre>
<p>Notes:</p>
<ul>
  <li>Inside the loop, <code>{ title }</code> means the current entry's title.</li>
  <li><code>{for each tag}</code> automatically loops over the current entry’s tags.</li>
</ul>

<h3>Showing a single entry</h3>
<p>Load a specific entry (by slug) and then display it:</p>
<pre><code>{from 'blog' get entry 'how-to-use-birkly'}
<h1>{ title }</h1>
<time>{ date "F j, Y" }</time>
{ content }</code></pre>

<h3>Checking if something exists</h3>
<p>Use “has” with clear subjects:</p>
<pre><code>{if 'blog' has entries}
  <p>We have blog posts!</p>
{endif}

{if entry has tags}
  <p>This post has tags.</p>
{endif}

{if not entry has excerpt}
  <p>No excerpt yet.</p>
{endif}</code></pre>

<h3>Getting entries with options</h3>
<p>Fetch lists with simple options:</p>
<pre><code>{from 'blog' get entries limit=6 sort="date:desc"}
{for each entry in entries}
  <h2><a href="/demo-blog/post.html?slug={ slug }">{ title }</a></h2>
{endfor}</code></pre>

<h3>Media (images/files)</h3>
<p>Get a media file URL, optionally with basic sizing:</p>
<pre><code><img src="{ media 'birkly-logo.svg' }" alt="Birkly">
<img src="{ media 'hero.jpg' width=1200 height=600 crop="center" }" alt="Hero"></code></pre>

<h3>Includes (re-use partial templates)</h3>
<p>Include other small template files:</p>
<pre><code>{include 'header'}
{include 'post-card' with entry}</code></pre>

<h3>Links and URLs</h3>
<p>Build an internal link with parameters:</p>
<pre><code><a href="{ url '/project/post.html' slug=slug }">Read more</a></code></pre>

<h3>Counts</h3>
<pre><code>Total blog posts: { count of 'blog' }</code></pre>

<h3>Rules you don’t have to remember</h3>
<ul>
  <li><strong>Editor content is automatic</strong>: <code>{ content }</code> always shows with formatting.</li>
  <li><strong>Everything else is safe</strong>: Titles, excerpts, etc. are safely displayed.</li>
  <li><strong>Missing values</strong> simply show as empty (no errors).</li>
</ul>

<h3>Cheatsheet</h3>
<table>
  <tr><th>Do this</th><th>Explanation</th></tr>
  <tr><td><code>{ title }</code></td><td>Current entry’s title</td></tr>
  <tr><td><code>{ date "F j, Y" }</code></td><td>Format a date</td></tr>
  <tr><td><code>{ content }</code></td><td>Rich content (editor), formatting included</td></tr>
  <tr><td><code>{for each entry in 'blog'} … {endfor}</code></td><td>Loop entries of a collection</td></tr>
  <tr><td><code>{for each tag} … {endfor}</code></td><td>Loop tags of current entry</td></tr>
  <tr><td><code>{if entry has tags} … {endif}</code></td><td>Show if the current entry has tags</td></tr>
  <tr><td><code>{if 'blog' has entries} … {endif}</code></td><td>Show if a collection has entries</td></tr>
  <tr><td><code>{from 'blog' get entries}</code></td><td>Fetch entries into <code>entries</code></td></tr>
  <tr><td><code>{from 'blog' get entry 'slug'}</code></td><td>Fetch a single entry</td></tr>
  <tr><td><code>{ media 'file.jpg' }</code></td><td>Get a media URL</td></tr>
  <tr><td><code>{ url '/path' key=value }</code></td><td>Build a URL with parameters</td></tr>
</table>

<h3>Compatibility</h3>
<p>The old forms will keep working for a transition period, but we recommend using the new style above:</p>
<ul>
  <li><code>{{ title }}</code> → <code>{ title }</code></li>
  <li><code>{{ content|raw }}</code> → <code>{ content }</code></li>
  <li><code>{foreach tags as tag}</code> → <code>{for each tag}</code></li>
</ul>

<p>That’s it—you can now write templates that read like plain English, while staying safe and powerful.</p>

