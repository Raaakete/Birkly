<?php
/**
 * Backward-compatibility stub — use birkly_bootstrap_active_plugin() instead (P82-M7).
 */
require_once __DIR__ . '/../plugin_availability.php';

function automation_plugin_active(): bool
{
    return birkly_bootstrap_active_plugin('automation');
}

function automation_bootstrap(): bool
{
    return automation_plugin_active();
}
