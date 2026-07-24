#!/usr/bin/env php
<?php
/**
 * Reset Onboarding Script
 * 
 * This script helps reset the onboarding state for testing purposes.
 * It removes all entries and onboarding progress while keeping collections.
 * 
 * Usage: php scripts/reset-onboarding.php [options]
 * Options:
 *   --keep-entries    Keep entry files (only reset onboarding progress)
 *   --help            Show this help message
 */

$options = getopt('', ['keep-entries', 'help']);

if (isset($options['help'])) {
    echo "Reset Onboarding Script\n";
    echo "Usage: php scripts/reset-onboarding.php [options]\n\n";
    echo "Options:\n";
    echo "  --keep-entries    Keep entry files (only reset onboarding progress)\n";
    echo "  --help            Show this help message\n\n";
    echo "This script will:\n";
    echo "  - Remove all entry files (unless --keep-entries is used)\n";
    echo "  - Remove all onboarding progress files\n";
    echo "  - Keep collections and schemas intact\n";
    echo "  - Keep demo content packs intact\n\n";
    exit(0);
}

require_once dirname(__DIR__) . '/core/system_rebuild.php';

$baseDir = dirname(__DIR__);
$collectionsPath = "$baseDir/content/collections";
$usersPath = "$baseDir/users";

echo "🔄 Resetting onboarding state...\n\n";

$entryCount = 0;
$onboardingCount = 0;

if (!isset($options['keep-entries'])) {
    echo "📝 Removing starter site and entry files...\n";
    $rebuild = birkly_rebuild_system($baseDir);
    if (empty($rebuild['success'])) {
        fwrite(STDERR, "Error: " . ($rebuild['message'] ?? 'rebuild failed') . "\n");
        exit(1);
    }
    $entryCount = (int) ($rebuild['entries_removed'] ?? 0);
    $onboardingCount = (int) ($rebuild['onboarding_files_removed'] ?? 0);
    echo "   ✓ Removed $entryCount entry files\n";
    echo "   ✓ Removed $onboardingCount onboarding files\n";
} else {
    echo "📝 Keeping entry files (--keep-entries specified)\n";
    echo "📊 Removing onboarding progress...\n";
    $onboardingCount = birkly_remove_onboarding_progress_files($usersPath);
    echo "   ✓ Removed $onboardingCount onboarding files\n";
}

// Step 3: Summary
echo "\n✅ Onboarding state reset complete!\n\n";
echo "What was removed:\n";
if (!isset($options['keep-entries'])) {
    echo "  - All entry files\n";
}
echo "  - All onboarding progress files\n\n";
echo "What was kept:\n";
echo "  - Collection schemas and metadata\n";
echo "  - Demo content packs\n";
echo "  - Demo templates\n";
echo "  - User accounts\n\n";
echo "You can now:\n";
echo "  1. Log in to the admin panel\n";
echo "  2. You should be redirected to the onboarding wizard\n";
echo "  3. Start testing the onboarding flow\n\n";

