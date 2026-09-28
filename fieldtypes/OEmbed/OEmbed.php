<?php
namespace Birkly\FieldTypes\OEmbed;

use Birkly\FieldTypes\BaseField;

class OEmbed extends BaseField {
    protected $placeholder;

    public function __construct(array $config) {
        parent::__construct($config);
        $this->placeholder = $config['placeholder'] ?? 'https://www.youtube.com/watch?v=...';
    }

    public function getType(): string {
        return 'oembed';
    }

    public function getCategory(): string {
        return 'Media';
    }

    public function getTypeLabel(): string {
        return 'Embed';
    }

    public function getIcon(): string {
        return 'play';
    }

    public function getDescription(): string {
        return 'A video or post embedded from a link';
    }

    public function render(): string {
        $required = $this->required ? 'required' : '';
        $placeholder = htmlspecialchars($this->placeholder, ENT_QUOTES, 'UTF-8');
        $value = (string)($this->value ?? '');
        $escapedValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $embedUrl = htmlspecialchars($this->resolveEmbedUrl($value) ?? '', ENT_QUOTES, 'UTF-8');
        $previewHidden = $embedUrl === '' ? 'hidden' : '';

        return <<<HTML
        <div class="oembed-field" data-oembed-field>
            <input type="url"
                name="{$this->name}"
                id="field_{$this->name}"
                value="{$escapedValue}"
                {$required}
                placeholder="{$placeholder}"
                class="input oembed-field-input"
            >
            <div class="oembed-field-preview" {$previewHidden}>
                <iframe
                    class="oembed-field-frame"
                    src="{$embedUrl}"
                    loading="lazy"
                    allowfullscreen
                    referrerpolicy="strict-origin-when-cross-origin"
                ></iframe>
            </div>
        </div>
        HTML;
    }

    public function validate($value): array {
        $errors = [];
        $value = trim((string)$value);

        if ($this->required && $value === '') {
            $errors[] = "{$this->label} is required";
            return $errors;
        }

        if ($value !== '' && $this->resolveEmbedUrl($value) === null) {
            $errors[] = "{$this->label} must be a valid YouTube or Vimeo URL";
        }

        return $errors;
    }

    public function getJavaScript(): string {
        return file_get_contents(__DIR__ . '/fieldtype.js');
    }

    public function getCSS(): string {
        return <<<'CSS'
        .oembed-field {
            display: grid;
            gap: var(--space-2, 0.5rem);
        }

        .oembed-field-preview {
            position: relative;
            width: 100%;
            aspect-ratio: 16 / 9;
            border: 1px solid var(--border-medium, #e5e7eb);
            border-radius: var(--radius-sm, 0.375rem);
            overflow: hidden;
            background: var(--bg-secondary, #f8fafc);
        }

        .oembed-field-frame {
            width: 100%;
            height: 100%;
            border: 0;
        }
        CSS;
    }

    public function getAdminConfig(): array {
        return [
            'type' => 'oembed',
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group,
            'placeholder' => $this->placeholder,
        ];
    }

    public function getSchema(): array {
        return [
            'type' => 'string',
            'format' => 'uri',
            'provider' => 'youtube|vimeo',
            'required' => $this->required,
        ];
    }

    public function getDefaultValue() {
        return '';
    }

    public function processValue($value) {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        return $this->resolveEmbedUrl($value) ?? $value;
    }

    public function processLoadedValue($value) {
        return (string)$value;
    }

    public function renderListCell($value, array $context = []): string {
        $embedUrl = (string)$value;
        if ($embedUrl === '') {
            return '<span class="ft-list-empty">–</span>';
        }

        $provider = strpos($embedUrl, 'vimeo.com') !== false ? 'Vimeo' : 'YouTube';
        return '<span class="ft-list-badge">' . htmlspecialchars($provider, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    public function getTableMinWidth(): string {
        return '120px';
    }

    public function getConfigUI($fieldIndex, $existingData = []): string {
        $placeholder = htmlspecialchars($existingData['placeholder'] ?? 'https://www.youtube.com/watch?v=...', ENT_QUOTES, 'UTF-8');

        return <<<HTML
            <div class="field-config-section">
                <label>Placeholder</label>
                <input type="text" name="fields[{$fieldIndex}][placeholder]" class="input" value="{$placeholder}" placeholder="https://www.youtube.com/watch?v=...">
            </div>
        HTML;
    }

    public function resolveEmbedUrlForTemplate(string $value): ?string
    {
        return $this->resolveEmbedUrl($value);
    }

    private function resolveEmbedUrl(string $value): ?string {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($value);
        $host = strtolower($parts['host'] ?? '');
        $path = trim($parts['path'] ?? '', '/');

        if (strpos($host, 'youtu.be') !== false) {
            $videoId = $this->sanitizeVideoId($path);
            return $videoId ? 'https://www.youtube.com/embed/' . $videoId : null;
        }

        if (strpos($host, 'youtube.com') !== false || strpos($host, 'youtube-nocookie.com') !== false) {
            if ($path !== '' && preg_match('#^(embed|shorts)/([^/]+)$#', $path, $matches)) {
                $videoId = $this->sanitizeVideoId($matches[2]);
                return $videoId ? 'https://www.youtube.com/embed/' . $videoId : null;
            }

            parse_str($parts['query'] ?? '', $query);
            $videoId = $this->sanitizeVideoId($query['v'] ?? '');
            return $videoId ? 'https://www.youtube.com/embed/' . $videoId : null;
        }

        if (strpos($host, 'vimeo.com') !== false) {
            if (preg_match('#(?:video/)?([0-9]+)$#', $path, $matches)) {
                return 'https://player.vimeo.com/video/' . $matches[1];
            }
        }

        return null;
    }

    private function sanitizeVideoId(string $videoId): string {
        $videoId = trim($videoId);
        return preg_match('/^[A-Za-z0-9_-]{6,20}$/', $videoId) ? $videoId : '';
    }
}
