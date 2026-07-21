<?php
$stickySaveBarForm = $stickySaveBarForm ?? null;
$stickySaveBarSaveLabel = $stickySaveBarSaveLabel ?? __('admin.save');
$stickySaveBarCancelLabel = $stickySaveBarCancelLabel ?? __('admin.cancel');
$stickySaveBarCancelUrl = $stickySaveBarCancelUrl ?? null;
$stickySaveBarMessageId = $stickySaveBarMessageId ?? null;
$stickySaveBarMessageClass = trim((string) ($stickySaveBarMessageClass ?? ''));
$stickySaveBarStatusHtml = $stickySaveBarStatusHtml ?? '';
$stickySaveBarSaveId = $stickySaveBarSaveId ?? null;
$stickySaveBarSaveClass = trim((string) ($stickySaveBarSaveClass ?? 'btn btn-primary'));
$stickySaveBarExtraActionHtml = $stickySaveBarExtraActionHtml ?? '';
?>
<div class="sticky-save-bar">
    <div class="sticky-save-bar__content">
        <div class="sticky-save-bar__meta">
            <?php if ($stickySaveBarMessageId): ?>
                <div id="<?= htmlspecialchars($stickySaveBarMessageId) ?>" class="<?= htmlspecialchars(trim('sticky-save-bar__message ' . $stickySaveBarMessageClass)) ?>" style="display: none;" role="status" aria-live="polite"></div>
            <?php endif; ?>
            <?php if ($stickySaveBarStatusHtml): ?>
                <?= $stickySaveBarStatusHtml ?>
            <?php endif; ?>
        </div>
        <div class="sticky-save-bar__actions">
            <?php if ($stickySaveBarCancelUrl): ?>
                <a href="<?= htmlspecialchars($stickySaveBarCancelUrl) ?>" class="btn btn-secondary">
                    <?= htmlspecialchars($stickySaveBarCancelLabel) ?>
                </a>
            <?php endif; ?>
            <?php if ($stickySaveBarExtraActionHtml): ?>
                <?= $stickySaveBarExtraActionHtml ?>
            <?php endif; ?>
            <button
                type="<?= $stickySaveBarForm ? 'submit' : 'button' ?>"
                class="<?= htmlspecialchars($stickySaveBarSaveClass) ?>"
                <?php if ($stickySaveBarSaveId): ?>id="<?= htmlspecialchars($stickySaveBarSaveId) ?>"<?php endif; ?>
                <?php if ($stickySaveBarForm): ?>form="<?= htmlspecialchars($stickySaveBarForm) ?>"<?php endif; ?>
            >
                <?= htmlspecialchars($stickySaveBarSaveLabel) ?>
            </button>
        </div>
    </div>
</div>
