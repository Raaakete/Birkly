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
$stickySaveBarRightHtml = $stickySaveBarRightHtml ?? '';
?>
<div class="main-actions-bar main-actions-bar--sticky">
    <div class="main-actions-left">
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
    <div class="main-actions-right">
        <?php if ($stickySaveBarRightHtml): ?>
            <div class="main-actions-tools">
                <?= $stickySaveBarRightHtml ?>
            </div>
        <?php endif; ?>
        <?php if ($stickySaveBarMessageId): ?>
            <div id="<?= htmlspecialchars($stickySaveBarMessageId) ?>" class="<?= htmlspecialchars(trim('main-actions-message ' . $stickySaveBarMessageClass)) ?>" role="status" aria-live="polite" hidden></div>
        <?php endif; ?>
        <?php if ($stickySaveBarStatusHtml): ?>
            <?= $stickySaveBarStatusHtml ?>
        <?php endif; ?>
    </div>
</div>
