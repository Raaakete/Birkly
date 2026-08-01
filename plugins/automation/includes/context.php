<?php
/**
 * Enrich automation execution context with useful template variables.
 */

/**
 * Absolute admin URL for editing an entry (empty when collection/id missing).
 */
function automation_entry_admin_url(string $collection, string $entryId): string
{
    $collection = trim($collection);
    $entryId = trim($entryId);
    if ($collection === '' || $entryId === '') {
        return '';
    }

    $params = ['collection' => $collection, 'entry' => $entryId];

    if (function_exists('birkly_hosting_admin_url')) {
        return (string) birkly_hosting_admin_url('entry-editor', $params);
    }

    if (function_exists('birkly_admin_url') && function_exists('birkly_cms_base_url')) {
        $path = birkly_admin_url('entry-editor', $params);
        $base = rtrim((string) birkly_cms_base_url(), '/');
        if ($base !== '' && !preg_match('#^https?://#i', $path)) {
            return $base . $path;
        }
        return $path;
    }

    if (function_exists('birkly_admin_url')) {
        return (string) birkly_admin_url('entry-editor', $params);
    }

    $query = http_build_query(array_merge(['page' => 'entry-editor'], $params));
    return '/admin/index.php?' . $query;
}

/**
 * Human-readable summary of what triggered this run.
 */
function automation_trigger_summary(?string $triggerType, array $context = []): string
{
    $collection = trim((string) ($context['collection'] ?? $context['entry']['collection'] ?? ''));
    $title = trim((string) ($context['entry']['title'] ?? ''));
    $inCollection = $collection !== '' ? ' in "' . $collection . '"' : '';

    switch ((string) $triggerType) {
        case 'entry_created':
            return 'New entry' . $inCollection . ($title !== '' ? ': ' . $title : '');
        case 'entry_updated':
            return 'Entry updated' . $inCollection . ($title !== '' ? ': ' . $title : '');
        case 'entry_deleted':
            return 'Entry deleted' . $inCollection . ($title !== '' ? ': ' . $title : '');
        case 'status_changed':
            $from = (string) ($context['from_status'] ?? $context['old_status'] ?? '');
            $to = (string) ($context['to_status'] ?? $context['new_status'] ?? '');
            $statusBit = ($from !== '' || $to !== '') ? " ($from → $to)" : '';
            return 'Status changed' . $inCollection . $statusBit . ($title !== '' ? ': ' . $title : '');
        case 'sub_entry_created':
            $sub = trim((string) ($context['sub_collection'] ?? ''));
            return 'New sub-entry' . ($sub !== '' ? " ($sub)" : '') . $inCollection;
        case 'sub_entry_updated':
            return 'Sub-entry updated' . $inCollection;
        case 'sub_entry_deleted':
            return 'Sub-entry deleted' . $inCollection;
        case 'webhook_inbound':
            $name = trim((string) ($context['webhook_name'] ?? $context['webhook_id'] ?? 'webhook'));
            return 'Webhook received: ' . $name;
        case 'scheduled':
            return 'Scheduled run';
        case 'plugin_event':
            $event = trim((string) ($context['plugin_event'] ?? 'plugin event'));
            return 'Plugin event: ' . $event;
        default:
            if ($triggerType) {
                return str_replace('_', ' ', (string) $triggerType) . $inCollection;
            }
            return $title !== '' ? $title : 'Automation ran';
    }
}

/**
 * Enrich context used by {{variable}} templates during execution.
 *
 * @param array<string, mixed> $context
 * @param array<string, mixed> $automation
 * @return array<string, mixed>
 */
function automation_enrich_execution_context(array $context, array $automation = [], ?string $triggerType = null): array
{
    if (function_exists('enrich_automation_context')) {
        $context = enrich_automation_context($context);
    }

    $triggerType = $triggerType
        ?? ($context['trigger_type'] ?? null)
        ?? ($automation['trigger']['type'] ?? null);

    $entry = is_array($context['entry'] ?? null) ? $context['entry'] : [];
    $collection = trim((string) ($context['collection'] ?? $entry['collection'] ?? ''));
    $entryId = trim((string) ($entry['id'] ?? ''));

    $automationName = trim((string) ($automation['name'] ?? $context['automation_name'] ?? ''));
    $automationId = trim((string) ($automation['id'] ?? $context['automation_id'] ?? ''));

    $adminUrl = automation_entry_admin_url($collection, $entryId);
    $summary = automation_trigger_summary(is_string($triggerType) ? $triggerType : null, array_merge($context, [
        'collection' => $collection,
        'entry' => $entry,
    ]));

    $context['automation'] = array_merge(
        is_array($context['automation'] ?? null) ? $context['automation'] : [],
        array_filter([
            'id' => $automationId,
            'name' => $automationName,
        ], static fn($v) => $v !== '')
    );
    $context['automation_name'] = $automationName !== '' ? $automationName : ($context['automation_name'] ?? '');
    $context['automation_id'] = $automationId !== '' ? $automationId : ($context['automation_id'] ?? '');

    if ($triggerType) {
        $context['trigger_type'] = $triggerType;
        $block = function_exists('get_automation_block') ? get_automation_block('trigger', $triggerType) : null;
        $context['trigger_label'] = is_array($block) ? (string) ($block['name'] ?? $triggerType) : (string) $triggerType;
    }
    $context['trigger_summary'] = $summary;

    if ($collection !== '' && empty($context['collection'])) {
        $context['collection'] = $collection;
    }

    if ($adminUrl !== '') {
        $context['entry_admin_url'] = $adminUrl;
        // Alias used in docs / templates
        if (empty($context['entry_url'])) {
            $context['entry_url'] = $adminUrl;
        }
    }

    if (empty($context['now'])) {
        $context['now'] = date('Y-m-d H:i:s');
    }
    if (empty($context['timestamp'])) {
        $context['timestamp'] = date('c');
    }

    return $context;
}

/**
 * Default Send Email subject/body templates for the editor.
 *
 * @return array{subject: string, body: string}
 */
function automation_default_email_templates(): array
{
    return [
        'subject' => '{{automation.name}}',
        'body' => "{{trigger_summary}}\n\n"
            . "Title: {{entry.title}}\n"
            . "Collection: {{collection}}\n"
            . "Status: {{entry.status}}\n\n"
            . "Open in Birkly:\n{{entry_admin_url}}",
    ];
}

/**
 * Build HTML email body with optional CTA button to the entry.
 */
function automation_format_email_html(string $bodyText, array $context = []): string
{
    $escaped = nl2br(htmlspecialchars($bodyText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

    // Turn bare absolute URLs into links (after escaping, restore known URL from context).
    $adminUrl = trim((string) ($context['entry_admin_url'] ?? $context['entry_url'] ?? ''));
    if ($adminUrl !== '' && str_contains($bodyText, $adminUrl)) {
        $safeUrl = htmlspecialchars($adminUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escaped = str_replace($safeUrl, '<a href="' . $safeUrl . '">' . $safeUrl . '</a>', $escaped);
    } else {
        $escaped = preg_replace(
            '#(https?://[^\s<]+)#i',
            '<a href="$1">$1</a>',
            $escaped
        ) ?? $escaped;
    }

    $button = '';
    if ($adminUrl !== '') {
        $safeUrl = htmlspecialchars($adminUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        // Avoid duplicating the CTA if the body already focuses on the link.
        $alreadyLinked = str_contains($bodyText, $adminUrl);
        $button = '<p style="margin:24px 0 8px;">'
            . '<a href="' . $safeUrl . '" style="display:inline-block;background:#1f4b3a;color:#ffffff;'
            . 'text-decoration:none;padding:10px 16px;border-radius:6px;font-weight:600;">'
            . 'Open entry in Birkly</a></p>';
        if ($alreadyLinked) {
            // Keep button anyway — clearer than a raw URL alone.
        }
    }

    return '<div style="font-family:system-ui,-apple-system,Segoe UI,sans-serif;line-height:1.5;color:#1a1a1a;">'
        . '<div>' . $escaped . '</div>'
        . $button
        . '</div>';
}
