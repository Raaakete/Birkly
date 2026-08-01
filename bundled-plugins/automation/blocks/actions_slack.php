<?php
/**
 * Slack/Teams Automation Actions for Birkly
 * 
 * Defines Slack and Microsoft Teams webhook actions.
 */

require_once __DIR__ . '/../registry.php';
require_once __DIR__ . '/../executor.php';
require_once __DIR__ . '/../security.php';

// Slack Webhook Action
register_automation_block('action', 'slack', [
    'name' => 'Send Slack Message',
    'description' => 'Send a message to a Slack channel via webhook',
    'category' => 'Communication',
    'schema' => [
        'webhook_url' => [
            'type' => 'url',
            'label' => 'Slack Webhook URL',
            'required' => true
        ],
        'channel' => [
            'type' => 'string',
            'label' => 'Channel (optional)',
            'description' => 'Override default channel (e.g., #general)'
        ],
        'username' => [
            'type' => 'string',
            'label' => 'Username (optional)',
            'description' => 'Override default bot name'
        ],
        'message' => [
            'type' => 'textarea',
            'label' => 'Message',
            'required' => true
        ],
        'attachments' => [
            'type' => 'textarea',
            'label' => 'Attachments (JSON, optional)',
            'description' => 'Slack attachments JSON array'
        ]
    ],
    'handler' => function($config, $context) {
        $webhook_url = render_automation_template($config['webhook_url'] ?? '', $context);
        $channel = render_automation_template($config['channel'] ?? '', $context);
        $username = render_automation_template($config['username'] ?? '', $context);
        $message = render_automation_template($config['message'] ?? '', $context);
        $attachments_json = render_automation_template($config['attachments'] ?? '[]', $context);
        
        if (empty($webhook_url) || empty($message)) {
            throw new Exception('Webhook URL and message are required');
        }
        $url_check = automation_validate_outbound_url($webhook_url);
        if (!$url_check['ok']) {
            throw new Exception('Slack webhook URL rejected: ' . ($url_check['error'] ?? 'invalid URL'));
        }
        
        // Parse attachments
        $attachments = json_decode($attachments_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $attachments = [];
        }
        
        // Build Slack payload
        $payload = [
            'text' => $message
        ];
        
        if ($channel) {
            $payload['channel'] = $channel;
        }
        
        if ($username) {
            $payload['username'] = $username;
        }
        
        if (!empty($attachments)) {
            $payload['attachments'] = $attachments;
        }
        
        // Send to Slack
        $ch = curl_init($webhook_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
            curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('Slack request failed: ' . $error);
        }
        
        if ($http_code !== 200) {
            throw new Exception("Slack returned HTTP $http_code: $response");
        }
        
        return ['success' => true, 'http_code' => $http_code];
    }
]);

// Microsoft Teams Webhook Action
register_automation_block('action', 'teams', [
    'name' => 'Send Teams Message',
    'description' => 'Send a message to a Microsoft Teams channel via webhook',
    'category' => 'Communication',
    'schema' => [
        'webhook_url' => [
            'type' => 'url',
            'label' => 'Teams Webhook URL',
            'required' => true
        ],
        'title' => [
            'type' => 'string',
            'label' => 'Title',
            'required' => true
        ],
        'message' => [
            'type' => 'textarea',
            'label' => 'Message',
            'required' => true
        ],
        'theme_color' => [
            'type' => 'string',
            'label' => 'Theme Color (hex)',
            'description' => 'Optional theme color (e.g., #FF0000)'
        ]
    ],
    'handler' => function($config, $context) {
        $webhook_url = render_automation_template($config['webhook_url'] ?? '', $context);
        $title = render_automation_template($config['title'] ?? '', $context);
        $message = render_automation_template($config['message'] ?? '', $context);
        $theme_color = render_automation_template($config['theme_color'] ?? '', $context);
        
        if (empty($webhook_url) || empty($title) || empty($message)) {
            throw new Exception('Webhook URL, title, and message are required');
        }
        $url_check = automation_validate_outbound_url($webhook_url);
        if (!$url_check['ok']) {
            throw new Exception('Teams webhook URL rejected: ' . ($url_check['error'] ?? 'invalid URL'));
        }
        
        // Build Teams payload (MessageCard format)
        $payload = [
            '@type' => 'MessageCard',
            '@context' => 'https://schema.org/extensions',
            'summary' => $title,
            'themeColor' => $theme_color ?: '0078D4',
            'title' => $title,
            'text' => $message
        ];
        
        // Send to Teams
        $ch = curl_init($webhook_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
            curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('Teams request failed: ' . $error);
        }
        
        // Teams returns 200 on success
        if ($http_code !== 200) {
            throw new Exception("Teams returned HTTP $http_code: $response");
        }
        
        return ['success' => true, 'http_code' => $http_code];
    }
]);
