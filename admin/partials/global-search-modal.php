<div class="search-overlay" id="search-overlay" style="display: none;">
    <div class="search-overlay-backdrop" id="search-overlay-backdrop"></div>
    <div class="search-overlay-content" role="dialog" aria-modal="true" aria-labelledby="search-input">
        <div class="search-input-wrapper">
            <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="7"></circle>
                <line x1="16.5" y1="16.5" x2="21" y2="21"></line>
            </svg>
            <input
                type="text"
                id="search-input"
                class="search-input"
                placeholder="Search collections and entries..."
                autocomplete="off"
            />
            <button class="search-close" type="button" id="search-close-btn" aria-label="Close search">×</button>
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
