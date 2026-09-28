<?php
$searchTypeToSearch = __('admin.search_type_to_search');
$searchNoResults = __('admin.search_no_results');
$searchPlaceholder = __('admin.search_collections_entries');
$searchCloseAria = __('admin.search_close_aria');
?>
<div class="search-overlay" id="search-overlay" style="display: none;" data-i18n-type-to-search="<?= htmlspecialchars($searchTypeToSearch, ENT_QUOTES, 'UTF-8') ?>" data-i18n-no-results="<?= htmlspecialchars($searchNoResults, ENT_QUOTES, 'UTF-8') ?>" data-i18n-placeholder="<?= htmlspecialchars($searchPlaceholder, ENT_QUOTES, 'UTF-8') ?>">
    <div class="search-overlay-backdrop" id="search-overlay-backdrop"></div>
    <div class="search-overlay-content" role="dialog" aria-modal="true" aria-labelledby="search-input">
        <div class="search-input-wrapper">
            <?= birkly_icon('search', ['class' => 'search-icon']) ?>
            <input
                type="text"
                id="search-input"
                class="search-input"
                placeholder="<?= htmlspecialchars($searchPlaceholder, ENT_QUOTES, 'UTF-8') ?>"
                autocomplete="off"
            />
            <button class="modal-close-btn" type="button" id="search-close-btn" aria-label="<?= htmlspecialchars($searchCloseAria, ENT_QUOTES, 'UTF-8') ?>">&times;</button>
        </div>
        <div class="search-results" id="search-results">
            <div class="search-empty-state">
                <p><?= htmlspecialchars($searchTypeToSearch, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
        <div class="search-footer">
            <span><kbd>↑</kbd><kbd>↓</kbd> <?= htmlspecialchars(__('admin.search_hint_move'), ENT_QUOTES, 'UTF-8') ?></span>
            <span><kbd>Enter</kbd> <?= htmlspecialchars(__('admin.search_hint_open'), ENT_QUOTES, 'UTF-8') ?></span>
            <span><kbd>Esc</kbd> <?= htmlspecialchars(__('admin.search_hint_close'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>
</div>
