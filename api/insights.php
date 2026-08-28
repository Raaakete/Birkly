<?php
/**
 * Visitor metrics API (ad-blocker-friendly alias for public analytics collection).
 *
 * Public clients should call this path instead of analytics.php for track +
 * public_config. Admin/settings continue to use analytics.php.
 */
require __DIR__ . '/analytics.php';
