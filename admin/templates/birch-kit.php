<?php
/**
 * Birch v5 component kit (P96-M7). Developer mode only.
 * Every component from the Components board, in the current theme and density.
 */
if (!isset($__birklyScriptNonceAttr) || $__birklyScriptNonceAttr === '') {
    require_once dirname(__DIR__) . '/../core/csp.php';
    $__birklyScriptNonceAttr = birkly_csp_nonce_attr();
}
$i = static fn (string $name, array $attrs = []): string => birkly_icon($name, $attrs);
?>
<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumbs">
            <a href="<?= htmlspecialchars(admin_url('settings', ['tab' => 'system'])) ?>">Settings</a>
            <span class="separator">›</span>
            <span class="current">Birch kit</span>
        </div>
    </div>
    <div class="page-header-actions"></div>
</div>

<div class="birch-kit" data-birch>
    <div class="page-bar">
        <div class="segmented well" role="group" aria-label="Theme" data-kit-theme>
            <button type="button" data-value="light" aria-pressed="true"><?= $i('sun') ?>Light</button>
            <button type="button" data-value="dark" aria-pressed="false"><?= $i('moon') ?>Dark</button>
        </div>
        <div class="segmented well" role="group" aria-label="Density" data-kit-density>
            <button type="button" data-value="compact" aria-pressed="false">Compact</button>
            <button type="button" data-value="default" aria-pressed="true">Comfortable</button>
            <button type="button" data-value="large" aria-pressed="false">Large</button>
        </div>
        <div class="page-bar-tools">
            <p class="kit-note">Preview only — theme and density return to your saved choice on reload.</p>
        </div>
    </div>

    <!-- Decisions for Keven -->
    <section class="kit-section">
        <h2 class="kit-heading">Your call · pick one of each</h2>
        <div class="kit-decisions">
            <div class="frame">
                <h3 class="dialog-title">Automation editor header</h3>
                <p class="dialog-text">One mint button per view (default) or both mint.</p>
                <div class="kit-choice">
                    <div class="kit-option">
                        <span class="field-label">A · One mint (board rule)</span>
                        <div class="kit-row">
                            <button type="button" class="button is-small"><?= $i('flask') ?>Test</button>
                            <button type="button" class="button is-small"><?= $i('play') ?>Run live…</button>
                            <button type="button" class="button is-main is-small"><?= $i('save') ?>Save</button>
                        </div>
                    </div>
                    <div class="kit-option">
                        <span class="field-label">B · Both mint (concept)</span>
                        <div class="kit-row">
                            <button type="button" class="button is-small"><?= $i('flask') ?>Test</button>
                            <button type="button" class="button is-main is-small"><?= $i('play') ?>Run live…</button>
                            <button type="button" class="button is-main is-small"><?= $i('save') ?>Save</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="frame">
                <h3 class="dialog-title">Action inside a warning</h3>
                <p class="dialog-text">Secondary buttons inside signals (default) or mint.</p>
                <div class="kit-choice">
                    <div class="kit-option">
                        <span class="field-label">A · Secondary</span>
                        <div class="signal"><?= $i('alert') ?><div class="signal-body"><p class="signal-title">No licence</p><p class="signal-text">Checkout runs in test mode.</p></div><button type="button" class="button is-small">View plans</button></div>
                    </div>
                    <div class="kit-option">
                        <span class="field-label">B · Mint (concept)</span>
                        <div class="signal"><?= $i('alert') ?><div class="signal-body"><p class="signal-title">No licence</p><p class="signal-text">Checkout runs in test mode.</p></div><button type="button" class="button is-main is-small">View plans</button></div>
                    </div>
                </div>
            </div>

            <div class="frame">
                <h3 class="dialog-title">Money for single-currency stores</h3>
                <p class="dialog-text">Always exact. Symbol or ISO code when only one currency is in use.</p>
                <div class="kit-choice">
                    <div class="kit-option">
                        <span class="field-label">A · Symbol</span>
                        <div class="metric slab"><span class="metric-label"><?= $i('bag') ?>Revenue</span><span class="metric-value">€630.00</span><span class="metric-meta">20 orders</span></div>
                    </div>
                    <div class="kit-option">
                        <span class="field-label">B · ISO code</span>
                        <div class="metric slab"><span class="metric-label"><?= $i('bag') ?>Revenue</span><span class="metric-value">EUR 630.00</span><span class="metric-meta">20 orders</span></div>
                    </div>
                </div>
                <p class="kit-note">Ambiguous symbols ($, kr) and mixed currencies always show the code: USD 15.00.</p>
            </div>
        </div>
    </section>

    <section class="kit-section">
        <h2 class="kit-heading">Buttons</h2>
        <div class="kit-row">
            <button type="button" class="button is-main"><?= $i('plus') ?>New entry</button>
            <button type="button" class="button">Preview<?= $i('eye') ?></button>
            <button type="button" class="button is-quiet">Customize</button>
            <button type="button" class="button is-danger"><?= $i('trash') ?>Delete</button>
        </div>
        <div class="kit-row">
            <button type="button" class="button is-main is-small">Review</button>
            <button type="button" class="button is-small">Fix</button>
            <button type="button" class="button is-quiet is-small">Cancel</button>
            <button type="button" class="icon-button" aria-label="More"><?= $i('dots') ?></button>
            <button type="button" class="icon-button" aria-label="Edit"><?= $i('pencil') ?></button>
        </div>
        <div class="kit-row">
            <button type="button" class="button is-main is-hover">Hover</button>
            <button type="button" class="button is-main" disabled>Disabled</button>
            <button type="button" class="button is-busy"><?= $i('loader') ?>Saving…</button>
        </div>
    </section>

    <section class="kit-section">
        <h2 class="kit-heading">Fields</h2>
        <div class="kit-grid">
            <div class="field-group">
                <label class="field-label" for="kit-name">Collection name</label>
                <input class="input" id="kit-name" value="Blog">
                <span class="field-help">Shown in the sidebar and lists.</span>
            </div>
            <div class="field-group">
                <label class="field-label" for="kit-slug">Address</label>
                <div class="input-group"><span class="prefix well">/blog/</span><input class="input is-mono" id="kit-slug" value="hello-world"></div>
                <span class="field-help">Generated from the title.</span>
            </div>
            <div class="field-group">
                <label class="field-label" for="kit-method">Entry method</label>
                <span class="select-box"><select id="kit-method"><option>Admin only</option><option>Public API</option></select><?= $i('chev-down') ?></span>
                <span class="field-help">Who can create entries.</span>
            </div>
            <div class="field-group">
                <label class="field-label" for="kit-title">Title</label>
                <input class="input is-invalid" id="kit-title" placeholder="Enter title" aria-invalid="true" aria-describedby="kit-title-error">
                <span class="field-error" id="kit-title-error">A title is required.</span>
            </div>
        </div>
    </section>

    <section class="kit-section">
        <h2 class="kit-heading">Selection and tabs</h2>
        <div class="kit-row">
            <label class="choice"><input type="checkbox" class="check">Off</label>
            <label class="choice"><input type="checkbox" class="check" checked>On</label>
            <label class="choice"><input type="checkbox" class="check" data-kit-indeterminate>Some</label>
            <label class="choice"><input type="radio" class="radio" name="kit-r" checked>One</label>
            <label class="choice"><input type="radio" class="radio" name="kit-r">Two</label>
            <span class="choice"><button type="button" class="switch" role="switch" aria-checked="true" aria-label="On"><span><?= $i('x') ?></span><span><?= $i('check') ?></span></button>On</span>
            <span class="choice"><button type="button" class="switch" role="switch" aria-checked="false" aria-label="Off"><span><?= $i('x') ?></span><span><?= $i('check') ?></span></button>Off</span>
        </div>
        <div class="kit-row">
            <div class="segmented well" role="group" aria-label="View">
                <button type="button" aria-pressed="true"><?= $i('grid') ?>Cards</button>
                <button type="button" aria-pressed="false"><?= $i('list') ?>List</button>
            </div>
            <div class="segmented well" role="group" aria-label="Status filter">
                <button type="button" aria-pressed="true">All <span class="count is-accent">10</span></button>
                <button type="button" aria-pressed="false">Drafts <span class="count">2</span></button>
            </div>
        </div>
        <div class="tabs" role="tablist">
            <button type="button" role="tab" aria-selected="true">Needs attention <span class="count is-warning">4</span></button>
            <button type="button" role="tab" aria-selected="false">Activity</button>
            <button type="button" role="tab" aria-selected="false">Recent entries</button>
        </div>
    </section>

    <section class="kit-section">
        <h2 class="kit-heading">Chips, badges and avatars</h2>
        <div class="kit-row">
            <span class="status is-published">Published</span>
            <span class="status">Draft</span>
            <span class="status is-scheduled">Scheduled</span>
            <span class="status is-archived">Archived</span>
            <span class="status is-warning is-plain">Warning</span>
            <span class="status is-failed is-plain">Failed</span>
            <span class="status is-info is-plain">Leads</span>
            <span class="lang well">EN</span>
            <span class="missing dashed">DE</span>
        </div>
        <div class="kit-row">
            <span class="count well">4</span>
            <span class="count is-warning">4</span>
            <span class="chip well">+5 sub-entries</span>
            <span class="kbd">⌘K</span>
            <span class="avatar">K</span>
            <span class="avatar is-accent">KB</span>
            <span class="avatar is-ink">BC</span>
        </div>
    </section>

    <section class="kit-section">
        <h2 class="kit-heading">Alerts</h2>
        <div class="kit-stack kit-narrow">
            <div class="signal"><?= $i('lock') ?><div class="signal-body"><p class="signal-title">Homepage can’t read “Blog”</p><p class="signal-text">Public read is off for this collection.</p></div><button type="button" class="button is-small">Fix</button></div>
            <div class="signal is-info"><?= $i('sparkle') ?><div class="signal-body"><p class="signal-title">AI action awaiting approval</p><p class="signal-text">1 change is waiting for your decision.</p></div><button type="button" class="button is-small">Review</button></div>
            <div class="signal is-success"><?= $i('check-circle') ?><div class="signal-body"><p class="signal-title">Entry published</p><p class="signal-text">It is now live on all 4 domains.</p></div></div>
        </div>
    </section>

    <section class="kit-section">
        <h2 class="kit-heading">Metric bar · up to four slots</h2>
        <div class="metrics">
            <div class="metric"><span class="metric-label"><?= $i('check-circle') ?>Published</span><span class="metric-value">341 <small>/ 425</small></span><span class="metric-meta">80% of all entries</span><div class="meter" data-value="80"><span></span></div></div>
            <div class="metric"><span class="metric-label"><?= $i('eye') ?>Page views</span><span class="metric-value">885</span><span class="metric-meta">across 4 domains</span></div>
            <div class="metric"><span class="metric-label"><?= $i('bag') ?>Revenue</span><span class="metric-value">€770.00</span><span class="metric-meta">25 orders</span></div>
            <div class="metric"><span class="metric-label"><?= $i('pencil') ?>Drafts</span><span class="metric-value">84</span><span class="metric-meta">waiting to be published</span></div>
            <button type="button" class="icon-button metrics-customize" aria-label="Customize metrics"><?= $i('sliders') ?></button>
        </div>
    </section>

    <div class="kit-grid">
        <section class="kit-section">
            <h2 class="kit-heading">Ranked bars</h2>
            <div class="slab kit-pad">
                <ul class="ranked">
                    <li><span class="ranked-label">/club.html</span><span class="ranked-value">165</span><div class="meter" data-value="43"><span></span></div></li>
                    <li><span class="ranked-label">/</span><span class="ranked-value">107</span><div class="meter" data-value="28"><span></span></div></li>
                    <li><span class="ranked-label">Not detected</span><span class="ranked-value">385</span><div class="meter is-muted" data-value="100"><span></span></div></li>
                </ul>
            </div>
        </section>

        <section class="kit-section">
            <h2 class="kit-heading">Rows and timeline</h2>
            <div class="row"><span class="avatar">C</span><div class="row-body"><span class="row-title">Casen</span><span class="row-meta">Alpha Waitlist · 1d ago</span></div><span class="status is-published">Published</span></div>
            <div class="row"><span class="row-icon"><?= $i('layers') ?></span><div class="row-body"><span class="row-title">Club posts</span><span class="row-meta">7 entries</span></div></div>
            <div class="row"><span class="row-icon is-info"><?= $i('sparkle') ?></span><div class="row-body"><span class="row-title">AI action awaiting approval</span><span class="row-meta">1 change proposed by an AI agent.</span></div><button type="button" class="button is-small">Review</button></div>
            <ul class="timeline">
                <li><span class="row-icon is-success"><?= $i('bag') ?></span><div class="row-body"><span class="row-title">Order paid — €15.00</span><span class="row-meta"><code>cord_c27a…d10e</code> · 1h ago</span></div></li>
                <li><span class="row-icon is-warning"><?= $i('lock') ?></span><div class="row-body"><span class="row-title">Live site blocked from “Blog” <span class="count is-warning">×5</span></span><span class="row-meta">Public read is off · 22h ago</span></div></li>
            </ul>
        </section>
    </div>

    <section class="kit-section">
        <h2 class="kit-heading">Collection cards · default, hover, empty</h2>
        <div class="card-grid kit-narrow">
            <a class="collection-card" href="#"><span class="collection-card-title">Blog</span><span class="collection-card-text">Articles for the marketing site.</span><span class="collection-card-foot"><strong>10</strong> entries</span></a>
            <a class="collection-card is-hover" href="#"><span class="collection-card-title">Roadmap Features</span><span class="collection-card-text">What is coming next.</span><span class="collection-card-foot"><strong>10</strong> entries <span class="chip well">+5 sub-entries</span></span><span class="icon-button" aria-hidden="true"><?= $i('dots') ?></span></a>
            <a class="collection-card is-empty" href="#"><span class="collection-card-title">Club deals</span><span class="collection-card-text">Member offers.</span><span class="collection-card-foot"><strong>0</strong> entries</span></a>
        </div>
    </section>

    <section class="kit-section">
        <h2 class="kit-heading">Data table · row, selected row, bulk bar</h2>
        <div class="bulk-bar" role="toolbar" aria-label="Bulk actions">
            <span class="bulk-bar-count">2 selected</span>
            <button type="button" class="button is-small"><?= $i('check-circle') ?>Publish</button>
            <button type="button" class="button is-small">Unpublish</button>
            <button type="button" class="button is-danger is-small"><?= $i('trash') ?>Delete</button>
            <button type="button" class="button is-quiet is-small"><?= $i('x') ?>Clear selection</button>
        </div>
        <div class="table kit-table" role="table">
            <div class="table-head" role="row"><span><input type="checkbox" class="check" data-kit-indeterminate aria-label="Select all"></span><span>Title</span><span>Status</span><span>Languages</span></div>
            <div class="table-row is-selected" role="row" aria-selected="true"><span><input type="checkbox" class="check" checked aria-label="Select"></span><span class="row-body"><span class="row-title">Hello, world — why Birkly exists</span><span class="row-meta">The story behind a CMS built for makers</span></span><span><span class="status is-published">Published</span></span><span class="kit-row"><span class="lang well">EN</span><span class="lang well">DE</span></span></div>
            <div class="table-row" role="row"><span><input type="checkbox" class="check" aria-label="Select"></span><span class="row-body"><span class="row-title">Commerce for content people</span><span class="row-meta">Selling without leaving your editor</span></span><span><span class="status">Draft</span></span><span class="kit-row"><span class="lang well">EN</span><span class="missing dashed">DE</span></span></div>
        </div>
    </section>

    <section class="kit-section">
        <h2 class="kit-heading">Field cards · builder</h2>
        <div class="kit-narrow">
            <div class="field-def"><span class="grip"><?= $i('grip') ?></span><span class="row-icon"><?= $i('type') ?></span><div class="row-body"><span class="row-title">Title</span><span class="row-meta">Single line of text</span></div><div class="field-def-tools"><span class="chip well">Short text</span><button type="button" class="switch" role="switch" aria-checked="true" aria-label="Required"><span><?= $i('x') ?></span><span><?= $i('check') ?></span></button>Required<button type="button" class="icon-button" aria-label="Field menu"><?= $i('dots') ?></button></div></div>
            <div class="field-def"><span class="grip"><?= $i('grip') ?></span><span class="row-icon"><?= $i('media') ?></span><div class="row-body"><span class="row-title">Cover image</span><span class="row-meta">One image from the library</span></div><div class="field-def-tools"><span class="chip well">Media</span><button type="button" class="switch" role="switch" aria-checked="false" aria-label="Required"><span><?= $i('x') ?></span><span><?= $i('check') ?></span></button>Required<button type="button" class="icon-button" aria-label="Field menu"><?= $i('dots') ?></button></div></div>
            <div class="add-row dashed"><?= $i('plus') ?> Add another field</div>
        </div>
    </section>

    <div class="kit-grid">
        <section class="kit-section">
            <h2 class="kit-heading">Menu</h2>
            <div class="menu kit-menu">
                <div class="menu-head"><span class="avatar is-accent">KB</span><div class="row-body"><span class="row-title">Keven Bayrak</span><span class="row-meta">Administrator</span></div></div>
                <button type="button" class="menu-item"><?= $i('user') ?>Profile &amp; security</button>
                <button type="button" class="menu-item is-hover"><?= $i('keyboard') ?>Keyboard shortcuts<span class="kbd">?</span></button>
                <div class="menu-rule" role="separator"></div>
                <div class="menu-item"><?= $i('code') ?>Developer mode<button type="button" class="switch" role="switch" aria-checked="true" aria-label="Developer mode"><span><?= $i('x') ?></span><span><?= $i('check') ?></span></button></div>
                <div class="menu-rule" role="separator"></div>
                <button type="button" class="menu-item is-danger"><?= $i('log-out') ?>Sign out</button>
            </div>
        </section>

        <section class="kit-section">
            <h2 class="kit-heading">Empty state</h2>
            <div class="empty">
                <span class="empty-icon"><?= $i('inbox') ?></span>
                <p class="empty-title">No entries yet</p>
                <p class="empty-text">Create the first entry and it will appear here, ready to publish.</p>
                <button type="button" class="button is-main is-small"><?= $i('plus') ?>New entry</button>
            </div>
        </section>
    </div>

    <section class="kit-section">
        <h2 class="kit-heading">Dialog (float)</h2>
        <div class="dialog kit-dialog" role="dialog" aria-labelledby="kit-dialog-title">
            <div class="dialog-head">
                <div><h3 class="dialog-title" id="kit-dialog-title">Refund ORD-20260920-0001</h3><p class="dialog-text">Paid €15.00 with Stripe · nothing refunded yet</p></div>
                <button type="button" class="icon-button" aria-label="Close"><?= $i('x') ?></button>
            </div>
            <div class="dialog-body">
                <div class="setting-row kit-line"><span class="row-title">Support Birkly</span><strong class="mono">€15.00</strong></div>
                <div class="setting-row kit-line"><div class="field-group"><label class="field-label" for="kit-amount">Refund amount</label><div class="input-group"><span class="prefix well">EUR</span><input class="input" id="kit-amount" value="15.00"></div></div></div>
                <div class="summary-note dashed">Refund <strong>€15.00</strong> to the original payment method.</div>
            </div>
            <div class="dialog-foot"><button type="button" class="button is-quiet">Cancel</button><button type="button" class="button is-main">Refund €15.00</button></div>
        </div>
    </section>

    <section class="kit-section">
        <h2 class="kit-heading">Tooltip, toast and loading</h2>
        <div class="kit-row">
            <span class="tooltip" role="tooltip">Search everything <span class="kbd">⌘K</span></span>
            <span class="toast" role="status"><?= $i('check-circle') ?>Entry published<button type="button" class="toast-action">Undo</button></span>
        </div>
        <div class="kit-stack kit-skeletons">
            <span class="skeleton" data-width="60"></span>
            <span class="skeleton"></span>
            <span class="skeleton" data-width="40"></span>
        </div>
    </section>
</div>
<script src="<?= htmlspecialchars(birkly_url('/admin/js/birch-kit.js')) ?>"<?= $__birklyScriptNonceAttr ?>></script>
