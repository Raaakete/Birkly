<?php
/**
 * MediaModal action wiring — Cancel/Upload must reach overlay delegated handlers.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$js = (string) file_get_contents($root . '/admin/js/media-modal.js');
$footer = (string) file_get_contents($root . '/admin/partials/layout_footer.php');

$failures = 0;
$assert = static function (bool $cond, string $msg) use (&$failures): void {
    if ($cond) {
        echo "ok - {$msg}\n";
        return;
    }
    echo "FAIL - {$msg}\n";
    $failures++;
};

$assert(strpos($js, "e.stopPropagation()") === false, 'MediaModal does not stopPropagation on inner dialog clicks');
$assert(strpos($js, "data-media-modal-action") !== false, 'MediaModal uses data-media-modal-action buttons');
$assert(strpos($js, 'window.BirklyFeedback') !== false, 'Upload feedback uses window.BirklyFeedback');
$assert(strpos($js, 'global.BirklyFeedback') === false, 'Upload handler does not reference undefined global');
$assert(strpos($footer, 'media-modal.js?v=20260803-1') !== false, 'layout_footer cache-busts fixed media-modal.js');

if ($failures > 0) {
    fwrite(STDERR, "media_modal_actions_test: {$failures} failure(s)\n");
    exit(1);
}

echo "media_modal_actions_test passed\n";
