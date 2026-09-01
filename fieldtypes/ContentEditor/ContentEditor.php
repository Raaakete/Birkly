<?php
namespace Birkly\FieldTypes\ContentEditor;

use Birkly\FieldTypes\BaseField;

class ContentEditor extends BaseField {
    /** Default bundled preview stylesheet (overridden per field via css_url). */
    public const DEFAULT_PREVIEW_CSS = '/fieldtypes/ContentEditor/preview/demo-preview.css';

    protected $config;

    /**
     * Normalize css_url to a project-relative path (e.g. css/style.css), or null when not project-local.
     */
    public static function normalizeProjectCssPath(string $cssUrl): ?string
    {
        require_once __DIR__ . '/../../core/content_editor_preview_css.php';
        return birkly_content_editor_normalize_preview_css_path($cssUrl);
    }

    /**
     * URL the editor should fetch for preview CSS (proxy for project files, direct for bundled/external).
     */
    public static function resolvePreviewCssFetchUrl(string $cssUrl): string
    {
        $trimmed = trim($cssUrl);
        if ($trimmed === '') {
            return '';
        }

        $projectPath = self::normalizeProjectCssPath($trimmed);
        if ($projectPath !== null) {
            require_once __DIR__ . '/../../core/content_editor_preview_css.php';
            return birkly_content_editor_preview_css_proxy_path($projectPath);
        }

        return $trimmed;
    }

    /**
     * Read a project CSS file, trying common path variants when the exact path is missing.
     *
     * @return array{path:string,content:string,size:int}|null
     */
    public static function readProjectPreviewCssFile(\ProjectSiteFileSystem $fs, string $path): ?array
    {
        require_once __DIR__ . '/../../core/content_editor_preview_css.php';
        return birkly_content_editor_read_preview_css_file($fs, $path);
    }
    
    public function __construct(array $config) {
        parent::__construct($config);
        // Store full config for access to field-specific settings
        $this->config = $config;
    }

    public function getType(): string {
        return 'content-editor';
    }

    public function getCategory(): string {
        return 'Content & Text';
    }

    public function render(): string {
        $required = $this->required ? 'required' : '';
        $rawValue = $this->value ?? '';
        $valueAttr = htmlspecialchars($rawValue, ENT_QUOTES, 'UTF-8');
        $fieldId = 'field_' . $this->name;
        $rawCssUrl = $this->config['css_url'] ?? '';
        $cssUrl = htmlspecialchars($rawCssUrl, ENT_QUOTES, 'UTF-8');
        $cssFetchUrl = htmlspecialchars(self::resolvePreviewCssFetchUrl($rawCssUrl), ENT_QUOTES, 'UTF-8');
        $cssUrlAttr = $cssUrl ? ' data-css-url="' . $cssUrl . '"' : '';
        $cssFetchAttr = $cssFetchUrl ? ' data-css-fetch-url="' . $cssFetchUrl . '"' : '';
        $metadataEndpoint = htmlspecialchars('/fieldtypes/ContentEditor/link-metadata.php', ENT_QUOTES, 'UTF-8');
        $metadataAttr = ' data-link-metadata="' . $metadataEndpoint . '"';
        $tabVisualId = 'tab_visual_' . $this->name;
        $tabHtmlId = 'tab_html_' . $this->name;
        $panelHtmlId = 'panel_html_' . $this->name;
        
        return <<<HTML
        <div class="content-editor-wrapper" data-field-name="{$this->name}"{$cssUrlAttr}{$cssFetchAttr}{$metadataAttr}>
            <div class="content-editor-mode-tabs" role="tablist" aria-label="Editor mode for {$this->label}">
                <button type="button" class="content-editor-mode-tab is-active" id="{$tabVisualId}" data-mode="visual" role="tab" aria-selected="true" aria-controls="editor_{$this->name}" tabindex="0">Visual</button>
                <button type="button" class="content-editor-mode-tab" id="{$tabHtmlId}" data-mode="html" role="tab" aria-selected="false" aria-controls="{$panelHtmlId}" tabindex="-1">HTML</button>
            </div>
            <div class="content-editor-toolbar" id="toolbar_{$this->name}">
                <!-- Text Styles Group -->
                <div class="toolbar-group" data-group="text-styles">
                    <button type="button" class="toolbar-btn" data-command="bold" title="Bold - Make text bold (Ctrl+B)">
                        <strong>B</strong>
                    </button>
                    <button type="button" class="toolbar-btn" data-command="italic" title="Italic - Make text italic (Ctrl+I)">
                        <em>I</em>
                    </button>
                    <button type="button" class="toolbar-btn" data-command="underline" title="Underline - Underline text (Ctrl+U)">
                        <u>U</u>
                    </button>
                    <button type="button" class="toolbar-btn" data-command="strikethrough" title="Strikethrough - Strike through text (Ctrl+Shift+S)">
                        <s>S</s>
                    </button>
                </div>
                
                <div class="toolbar-separator"></div>
                
                <!-- Format Group -->
                <div class="toolbar-group" data-group="format">
                    <div class="toolbar-dropdown">
                        <button type="button" class="toolbar-btn dropdown-toggle" data-command="heading" title="Text Format - Change text format and heading level">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 4h8a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"></path>
                                <path d="M6 12h9"></path>
                                <path d="M6 20V4"></path>
                            </svg>
                            <span class="dropdown-arrow">▼</span>
                        </button>
                        <div class="dropdown-menu">
                            <button type="button" class="dropdown-item" data-command="formatBlock" data-value="p">
                                <span class="dropdown-item-label">Paragraph</span>
                            </button>
                            <button type="button" class="dropdown-item" data-command="formatBlock" data-value="h1">
                                <span class="dropdown-item-label">Heading 1</span>
                            </button>
                            <button type="button" class="dropdown-item" data-command="formatBlock" data-value="h2">
                                <span class="dropdown-item-label">Heading 2</span>
                            </button>
                            <button type="button" class="dropdown-item" data-command="formatBlock" data-value="h3">
                                <span class="dropdown-item-label">Heading 3</span>
                            </button>
                            <button type="button" class="dropdown-item" data-command="formatBlock" data-value="h4">
                                <span class="dropdown-item-label">Heading 4</span>
                            </button>
                            <button type="button" class="dropdown-item" data-command="formatBlock" data-value="h5">
                                <span class="dropdown-item-label">Heading 5</span>
                            </button>
                            <button type="button" class="dropdown-item" data-command="formatBlock" data-value="h6">
                                <span class="dropdown-item-label">Heading 6</span>
                            </button>
                        </div>
                    </div>
                    <button type="button" class="toolbar-btn" data-command="insertUnorderedList" title="Bullet List - Create a bulleted list (Ctrl+Shift+L)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <circle cx="5" cy="8" r="2.5"></circle>
                            <circle cx="5" cy="16" r="2.5"></circle>
                            <line x1="9" y1="8" x2="20" y2="8" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"></line>
                            <line x1="9" y1="16" x2="20" y2="16" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"></line>
                        </svg>
                    </button>
                    <button type="button" class="toolbar-btn" data-command="insertOrderedList" title="Numbered List - Create a numbered list (Ctrl+Shift+O)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <line x1="10" y1="8" x2="21" y2="8" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"></line>
                            <line x1="10" y1="16" x2="21" y2="16" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"></line>
                            <text x="5" y="11" font-size="14" font-weight="800" fill="currentColor" text-anchor="middle" dominant-baseline="middle" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">1</text>
                            <text x="5" y="19" font-size="14" font-weight="800" fill="currentColor" text-anchor="middle" dominant-baseline="middle" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">2</text>
                        </svg>
                    </button>
                    <button type="button" class="toolbar-btn" data-command="formatBlock" data-value="blockquote" title="Blockquote - Create a blockquote">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1z"></path>
                            <path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 6v1c0 1 0 1 1 1z"></path>
                        </svg>
                    </button>
                </div>
                
                <div class="toolbar-separator"></div>
                
                <!-- Alignment Group -->
                <div class="toolbar-group" data-group="alignment">
                    <div class="toolbar-dropdown">
                        <button type="button" class="toolbar-btn dropdown-toggle" data-command="align" title="Text Alignment - Align text left, center, right, or justify">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="3" y1="6" x2="15" y2="6"></line>
                                <line x1="3" y1="12" x2="21" y2="12"></line>
                                <line x1="3" y1="18" x2="15" y2="18"></line>
                                <line x1="18" y1="6" x2="21" y2="6"></line>
                                <line x1="18" y1="18" x2="21" y2="18"></line>
                            </svg>
                            <span class="dropdown-arrow">▼</span>
                        </button>
                        <div class="dropdown-menu">
                            <button type="button" class="dropdown-item" data-command="justifyLeft" data-value="left">
                                <span class="dropdown-item-label">Align Left</span>
                            </button>
                            <button type="button" class="dropdown-item" data-command="justifyCenter" data-value="center">
                                <span class="dropdown-item-label">Align Center</span>
                            </button>
                            <button type="button" class="dropdown-item" data-command="justifyRight" data-value="right">
                                <span class="dropdown-item-label">Align Right</span>
                            </button>
                            <button type="button" class="dropdown-item" data-command="justifyFull" data-value="justify">
                                <span class="dropdown-item-label">Justify</span>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="toolbar-separator"></div>
                
                <!-- Links & Code Group -->
                <div class="toolbar-group" data-group="links-code">
                    <button type="button" class="toolbar-btn" data-command="createLink" title="Insert Link - Add or edit a link (Ctrl+K)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                        </svg>
                    </button>
                    <button type="button" class="toolbar-btn" data-command="code" title="Inline Code - Format text as inline code">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10 8l-2 2 2 2M14 8l2 2-2 2"></path>
                            <path d="M16 4h2a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-2M8 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h2"></path>
                        </svg>
                    </button>
                    <button type="button" class="toolbar-btn" data-command="formatBlock" data-value="pre" title="Code Block - Insert a code block">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="4" y="4" width="16" height="16" rx="2"></rect>
                            <path d="M8 8h8M8 12h8M8 16h4"></path>
                        </svg>
                    </button>
                </div>
                
                <div class="toolbar-separator"></div>
                
                <!-- Colors Group -->
                <div class="toolbar-group" data-group="colors">
                    <div class="toolbar-dropdown">
                        <button type="button" class="toolbar-btn dropdown-toggle" data-command="textColor" title="Text & Background Colors - Change text and background colors">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path>
                            </svg>
                            <span class="dropdown-arrow">▼</span>
                        </button>
                        <div class="dropdown-menu color-picker-menu">
                            <div class="color-picker-content">
                                <div class="color-picker-section">
                                    <label>Text Color</label>
                                    <div class="color-picker-wrapper">
                                        <div class="color-preview" id="text-color-preview" style="background-color: #000000;"></div>
                                        <input type="text" class="color-hex-input" id="text-color-hex" placeholder="#000000" maxlength="7" value="#000000">
                                    </div>
                                    <div class="color-wheel-container" data-target="text-color">
                                        <canvas class="color-wheel" id="text-color-wheel" width="200" height="200"></canvas>
                                        <div class="color-wheel-marker" id="text-color-marker"></div>
                                    </div>
                                    <div class="color-brightness-container" data-target="text-color">
                                        <canvas class="color-brightness" id="text-color-brightness" width="200" height="20"></canvas>
                                        <div class="color-brightness-marker" id="text-color-brightness-marker"></div>
                                    </div>
                                </div>
                                <div class="color-picker-section">
                                    <label>Background Color</label>
                                    <div class="color-picker-wrapper">
                                        <div class="color-preview" id="bg-color-preview" style="background-color: #FFFFFF;"></div>
                                        <input type="text" class="color-hex-input" id="bg-color-hex" placeholder="#FFFFFF" maxlength="7" value="#FFFFFF">
                                    </div>
                                    <div class="color-wheel-container" data-target="bg-color">
                                        <canvas class="color-wheel" id="bg-color-wheel" width="200" height="200"></canvas>
                                        <div class="color-wheel-marker" id="bg-color-marker"></div>
                                    </div>
                                    <div class="color-brightness-container" data-target="bg-color">
                                        <canvas class="color-brightness" id="bg-color-brightness" width="200" height="20"></canvas>
                                        <div class="color-brightness-marker" id="bg-color-brightness-marker"></div>
                                    </div>
                                </div>
                                <div class="color-picker-actions">
                                    <button type="button" class="color-picker-btn color-picker-apply">Apply</button>
                                    <button type="button" class="color-picker-btn color-picker-remove">Remove</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="toolbar-separator"></div>
                
                <!-- Layout Group -->
                <div class="toolbar-group" data-group="layout">
                    <button type="button" class="toolbar-btn" data-command="insertTable" title="Insert Table - Add a table">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="1" ry="1"></rect>
                            <line x1="3" y1="9" x2="21" y2="9"></line>
                            <line x1="3" y1="15" x2="21" y2="15"></line>
                            <line x1="9" y1="3" x2="9" y2="21"></line>
                            <line x1="15" y1="3" x2="15" y2="21"></line>
                        </svg>
                    </button>
                    <button type="button" class="toolbar-btn" data-command="insertGrid" title="Insert Grid - Add a CSS grid layout">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7"></rect>
                            <rect x="14" y="3" width="7" height="7"></rect>
                            <rect x="3" y="14" width="7" height="7"></rect>
                            <rect x="14" y="14" width="7" height="7"></rect>
                        </svg>
                    </button>
                    <button type="button" class="toolbar-btn" data-command="insertToggle" title="Insert Toggle - Add accordion toggles">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                            <line x1="4" y1="5" x2="20" y2="5"></line>
                            <line x1="4" y1="19" x2="20" y2="19"></line>
                        </svg>
                    </button>
                    <button type="button" class="toolbar-btn" data-command="insertHorizontalRule" title="Horizontal Rule - Insert a horizontal divider">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="3" y1="12" x2="21" y2="12"></line>
                        </svg>
                    </button>
                </div>
                
                <div class="toolbar-separator"></div>
                
                <!-- Media Group -->
                <div class="toolbar-group" data-group="media">
                    <button type="button" class="toolbar-btn" data-command="insertImage" title="Insert Image - Add an image from media library or URL">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <polyline points="21 15 16 10 5 21"></polyline>
                        </svg>
                    </button>
                    <div class="toolbar-dropdown">
                        <button type="button" class="toolbar-btn dropdown-toggle" data-command="insertMedia" title="Insert Media - Add video, audio, maps, or other embeds">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <polygon points="9 9 9 15 15 12 9 9"></polygon>
                                <circle cx="17" cy="7" r="1.5"></circle>
                            </svg>
                            <span class="dropdown-arrow">▼</span>
                        </button>
                        <div class="dropdown-menu">
                            <button type="button" class="dropdown-item" data-command="insertVideo" data-value="video">
                                <span class="dropdown-item-label">Video</span>
                                <span class="dropdown-item-desc">Embed a video</span>
                            </button>
                            <button type="button" class="dropdown-item" data-command="insertAudio" data-value="audio">
                                <span class="dropdown-item-label">Audio</span>
                                <span class="dropdown-item-desc">Embed an audio player</span>
                            </button>
                            <button type="button" class="dropdown-item" data-command="insertMaps" data-value="maps">
                                <span class="dropdown-item-label">Maps</span>
                                <span class="dropdown-item-desc">Embed a map</span>
                            </button>
                            <button type="button" class="dropdown-item" data-command="insertLinkPreview" data-value="link-preview">
                                <span class="dropdown-item-label">Link Preview</span>
                                <span class="dropdown-item-desc">Rich link preview card</span>
                            </button>
                            <button type="button" class="dropdown-item" data-command="insertIframe" data-value="iframe">
                                <span class="dropdown-item-label">Iframe</span>
                                <span class="dropdown-item-desc">Embed an iframe</span>
                            </button>
                            <button type="button" class="dropdown-item" data-command="insertCustomEmbed" data-value="custom">
                                <span class="dropdown-item-label">Custom Embed</span>
                                <span class="dropdown-item-desc">Custom HTML embed</span>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="toolbar-separator"></div>
                
                <!-- Components Group -->
                <div class="toolbar-group" data-group="components">
                    <button type="button" class="toolbar-btn" data-command="insertComponent" title="Insert Component - Add a reusable component block">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                            <path d="M2 17l10 5 10-5"></path>
                            <path d="M2 12l10 5 10-5"></path>
                        </svg>
                    </button>
                </div>
                
                <div class="toolbar-separator"></div>
                
                <!-- Actions Group -->
                <div class="toolbar-group" data-group="actions">
                    <button type="button" class="toolbar-btn" data-command="clearFormatting" title="Clear Formatting - Remove all formatting from selected text">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 4L6 16"></path>
                            <path d="M6 4l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <!-- History & View Group (Right Side) -->
                <div class="toolbar-group toolbar-group-right" data-group="history-view">
                    <span class="content-editor-preview-badge" data-preview-state="idle" hidden aria-live="polite"></span>
                    <button type="button" class="toolbar-btn" data-command="undo" title="Undo - Undo last action (Ctrl+Z)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 7v6h6"></path>
                            <path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"></path>
                        </svg>
                    </button>
                    <button type="button" class="toolbar-btn" data-command="redo" title="Redo - Redo last undone action (Ctrl+Shift+Z)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 7v6h-6"></path>
                            <path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3L21 13"></path>
                        </svg>
                    </button>
                    <button type="button" class="toolbar-btn toolbar-btn-fullscreen" data-field-name="{$this->name}" title="Full Screen - Open editor in full screen mode (F11)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="content-editor-body">
            <div class="content-editor-area" 
                 id="editor_{$this->name}" 
                 role="tabpanel"
                 aria-labelledby="{$tabVisualId}"
                 contenteditable="true"
                 data-field-name="{$this->name}"></div>
            <div class="content-editor-html-panel is-mode-hidden" id="{$panelHtmlId}" role="tabpanel" aria-labelledby="{$tabHtmlId}" hidden>
                <div class="content-editor-html-gutter" aria-hidden="true"></div>
                <div class="content-editor-html-scroll">
                    <pre class="content-editor-html-highlight" aria-hidden="true"><code></code></pre>
                    <textarea class="content-editor-html-source" spellcheck="false" aria-label="HTML source for {$this->label}"></textarea>
                </div>
            </div>
            </div>
            <input type="hidden" name="{$this->name}" id="{$fieldId}" value="{$valueAttr}" {$required}>
        </div>
        HTML;
    }

    public function validate($value): array {
        $errors = [];
        if ($this->required && empty(trim(strip_tags($value)))) {
            $errors[] = "{$this->label} is required";
        }
        return $errors;
    }

    public function getJavaScript(): string {
        $parts = [];
        // Load in correct order: core utilities first, then commands, then editor
        $paths = [
            // Core utilities
            __DIR__ . '/core/dom-utils.js',
            __DIR__ . '/core/sync-manager.js',
            __DIR__ . '/core/html-source-editor.js',
            __DIR__ . '/core/preview-css.js',
            __DIR__ . '/core/component-registry.js',
            __DIR__ . '/core/toolbar-manager.js',
            __DIR__ . '/core/keyboard-handler.js',
            __DIR__ . '/core/history-manager.js',
            __DIR__ . '/core/color-picker.js',
            __DIR__ . '/core/event-manager.js',
            // Selection utilities
            __DIR__ . '/selection.js',
            // Command system (modular Phase 2)
            __DIR__ . '/commands/inline-format.js',
            __DIR__ . '/commands/block-format.js',
            // Block system (P3-M12) — after block-format (manager delegates to it)
            __DIR__ . '/blocks/block-registry.js',
            __DIR__ . '/blocks/block-manager.js',
            // Layout studio (P5.5-M1)
            __DIR__ . '/layout/layout-block.js',
            __DIR__ . '/layout/layout-inspector.js',
            __DIR__ . '/layout/grid-layout.js',
            __DIR__ . '/layout/resize-controller.js',
            __DIR__ . '/layout/layout-manager.js',
            __DIR__ . '/layout/table-layout.js',
            __DIR__ . '/commands/base-command.js',
            __DIR__ . '/commands/registry.js',
            __DIR__ . '/commands/bootstrap.js',
            // Layout commands (P5.5-M4) — before legacy commands.js
            __DIR__ . '/commands/layout/table.js',
            __DIR__ . '/commands/layout/grid.js',
            __DIR__ . '/commands.js', // Legacy commands (backward compatibility)
            // Main editor
            __DIR__ . '/editor.js',
        ];
        foreach ($paths as $p) {
            if (file_exists($p)) {
                $parts[] = file_get_contents($p);
            }
        }
        return implode("\n\n", $parts);
    }

    public function getCSS(): string {
        $css = '';
        $cssPath = __DIR__ . '/editor.css';
        if (file_exists($cssPath)) {
            $css = file_get_contents($cssPath);
        }
        return $css;
    }

    public function getAdminConfig(): array {
        return [
            'type' => 'content-editor',
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group,
            'css_url' => $this->config['css_url'] ?? '',
        ];
    }

    public function getSchema(): array {
        return [
            'type' => 'text',
            'required' => $this->required,
        ];
    }

    public function getDefaultValue() {
        return '';
    }

    public function processValue($value) {
        return $this->normalizeHTML($this->stripLayoutEditorMetadata($value));
    }

    /**
     * Remove editor-only layout metadata from stored HTML (P5.5-M1 / D4).
     */
    private function stripLayoutEditorMetadata(string $html): string {
        if ($html === '') {
            return '';
        }

        $html = preg_replace('/<span[^>]*data-ce-insert-marker="true"[^>]*>\s*<\/span>/i', '', $html);
        $html = preg_replace('/<div[^>]*class="[^"]*content-editor-layout-handles[^"]*"[^>]*>[\s\S]*?<\/div>/i', '', $html);
        $html = preg_replace('/<div[^>]*class="[^"]*content-editor-grid-controls[^"]*"[^>]*>[\s\S]*?<\/div>/i', '', $html);
        $html = preg_replace('/<div[^>]*class="[^"]*content-editor-table-controls[^"]*"[^>]*>[\s\S]*?<\/div>/i', '', $html);
        $html = preg_replace('/\s*data-ce-[a-z0-9-]+="[^"]*"/i', '', $html);
        $html = preg_replace('/\s*data-editable-table="[^"]*"/i', '', $html);
        $html = preg_replace('/\s*data-block-type="[^"]*"/i', '', $html);
        $html = preg_replace('/\s*contenteditable="(?:true|false)"/i', '', $html);

        // P8-M5 — drop multi-cell selection highlight class; keep native colspan/rowspan.
        $html = preg_replace('/content-editor-table-cell-selected\s*/i', '', $html);
        $html = preg_replace('/\s*class="\s*"/i', '', $html);

        return $html;
    }

    public function processLoadedValue($value) {
        // Return value as-is, even if empty
        return $value ?? '';
    }

    public function renderListCell($value, array $context = []): string {
        // Strip HTML tags and truncate
        $text = trim(strip_tags((string)$value));
        if ($text === '') {
            return '<span class="ft-list-empty">–</span>';
        }
        
        // Truncate with "..."
        $max = $context['truncate'] ?? 60;
        if (mb_strlen($text) > $max) {
            $text = mb_substr($text, 0, $max - 3) . '...';
        }
        
        return '<span class="ft-list-text">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    public function getTableMinWidth(): string {
        return '320px'; // ContentEditor fields need more space for rich content preview
    }

    public function getConfigUI($fieldIndex, $existingData = []): string {
        $cssUrl = htmlspecialchars($existingData['css_url'] ?? '', ENT_QUOTES, 'UTF-8');
        $defaultCss = self::DEFAULT_PREVIEW_CSS;
        
        return <<<HTML
            <div class="field-config-section">
                <label>CSS Preview URL</label>
                <input type="text" name="fields[{$fieldIndex}][css_url]" class="input" value="{$cssUrl}" placeholder="{$defaultCss}" autocomplete="off" spellcheck="false">
                <small class="form-text">Preview stylesheet for this field in the entry editor (not saved in entry HTML). Use a project path such as <code>/project/css/style.css</code> or <code>css/style.css</code>, the bundled <code>{$defaultCss}</code>, or a full <code>https://…</code> URL for external sites.</small>
            </div>
        HTML;
    }

    /**
     * Normalize HTML: remove empty tags, normalize whitespace, etc.
     */
    private function normalizeHTML($html): string {
        if (empty($html)) {
            return '';
        }

        // Remove empty formatting tags
        $html = preg_replace('/<(b|strong|i|em|u|s|del|span)[^>]*>\s*<\/\1>/i', '', $html);
        
        // Remove redundant nested tags
        $html = preg_replace('/<(b|strong)><\1[^>]*>(.*?)<\/\1><\/\1>/i', '<$1>$2</$1>', $html);
        $html = preg_replace('/<(i|em)><\1[^>]*>(.*?)<\/\1><\/\1>/i', '<$1>$2</$1>', $html);
        
        // Remove empty paragraphs
        $html = preg_replace('/<p[^>]*>\s*<\/p>/i', '', $html);
        
        // Normalize whitespace (multiple spaces to single, but preserve line breaks in pre/code)
        $html = preg_replace('/[ \t]+/', ' ', $html);
        $html = preg_replace('/\n\s*\n/', "\n", $html);
        
        // Only wrap in paragraph if there's actual content
        $trimmed = trim($html);
        if (empty($trimmed)) {
            return '';
        }
        
        // Ensure content starts with a block element only if needed
        if (!preg_match('/^<(p|div|h[1-6]|ul|ol|blockquote|pre|table|hr)/i', $trimmed)) {
            $html = '<p>' . $html . '</p>';
        }
        
        return trim($html);
    }
}

