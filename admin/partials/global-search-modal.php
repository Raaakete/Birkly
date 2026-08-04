<div class="search-overlay" id="search-overlay" style="display: none;" data-i18n-type-to-search="Type to search…" data-i18n-no-results="No results found" data-i18n-placeholder="Search collections and entries…">
    <div class="search-overlay-backdrop" id="search-overlay-backdrop"></div>
    <div class="search-overlay-content" role="dialog" aria-modal="true" aria-labelledby="search-input">
        <div class="search-input-wrapper">
            <?= birkly_icon('magnifying-glass', ['size' => 20, 'class' => 'search-icon']) ?>
            <input
                type="text"
                id="search-input"
                class="search-input"
                placeholder="Search collections and entries..."
                autocomplete="off"
            />
            <button class="modal-close-btn" type="button" id="search-close-btn" aria-label="Close search">&times;</button>
        </div>
        <div class="search-results" id="search-results">
            <div class="search-empty-state">
                <p>Type to search...</p>
            </div>
        </div>
        <div class="search-footer">
            <kbd>↑</kbd><kbd>↓</kbd> Navigate • <kbd>Enter</kbd> Open • <kbd>Esc</kbd> Close
        </div>
    </div>
</div>
