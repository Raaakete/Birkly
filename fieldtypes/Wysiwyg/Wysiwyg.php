<?php
namespace HaruCMS\FieldTypes\Wysiwyg;

use HaruCMS\FieldTypes\BaseField;

class Wysiwyg extends BaseField {
    public function getType(): string {
        return 'wysiwyg';
    }

    public function render(): string {
        $value = htmlspecialchars($this->value ?? '', ENT_QUOTES | ENT_HTML5);
        $required = $this->required ? 'required' : '';
        
        // Comprehensive toolbar with more tools
        $toolbar = '<div class="wysiwyg-toolbar">
            <div class="toolbar-group">
                <button type="button" data-cmd="bold" title="Bold"><b>B</b></button>
                <button type="button" data-cmd="italic" title="Italic"><i>I</i></button>
                <button type="button" data-cmd="underline" title="Underline"><u>U</u></button>
                <button type="button" data-cmd="strikeThrough" title="Strikethrough"><s>S</s></button>
            </div>
            <div class="toolbar-group">
                <button type="button" data-cmd="justifyLeft" title="Align Left">⫷</button>
                <button type="button" data-cmd="justifyCenter" title="Align Center">⫸</button>
                <button type="button" data-cmd="justifyRight" title="Align Right">⫹</button>
                <button type="button" data-cmd="justifyFull" title="Justify">⫺</button>
            </div>
            <div class="toolbar-group">
                <button type="button" data-cmd="insertUnorderedList" title="Bullet List">•</button>
                <button type="button" data-cmd="insertOrderedList" title="Numbered List">1.</button>
                <button type="button" data-cmd="outdent" title="Decrease Indent">←</button>
                <button type="button" data-cmd="indent" title="Increase Indent">→</button>
            </div>
            <div class="toolbar-group">
                <button type="button" data-cmd="createLink" title="Insert Link">🔗</button>
                <button type="button" data-cmd="insertImage" title="Insert Image">🖼️</button>
                <button type="button" data-cmd="insertTable" title="Insert Table">⊞</button>
            </div>
            <div class="toolbar-group">
                <button type="button" data-cmd="formatBlock" data-value="h1" title="Heading 1">H1</button>
                <button type="button" data-cmd="formatBlock" data-value="h2" title="Heading 2">H2</button>
                <button type="button" data-cmd="formatBlock" data-value="h3" title="Heading 3">H3</button>
                <button type="button" data-cmd="formatBlock" data-value="p" title="Paragraph">P</button>
            </div>
            <div class="toolbar-group">
                <button type="button" data-cmd="removeFormat" title="Remove Formatting">Tx</button>
                <button type="button" data-cmd="undo" title="Undo">↶</button>
                <button type="button" data-cmd="redo" title="Redo">↷</button>
            </div>
        </div>';
        
        return <<<HTML
        <div class="wysiwyg-field" id="field_{$this->name}_wrapper">
            {$toolbar}
            <div class="wysiwyg-editor" id="field_{$this->name}" contenteditable="true" {$required} style="min-height:200px; border: 1px solid #ccc; padding: 10px; border-radius: 4px;">{$value}</div>
            <input type="hidden" name="{$this->name}" id="field_{$this->name}_value" value="{$value}">
        </div>
        HTML;
    }

    public function validate($value): array {
        $errors = [];
        if ($this->required && trim(strip_tags($value)) === '') {
            $errors[] = "{$this->label} is required";
        }
        return $errors;
    }

    public function getJavaScript(): string {
        return <<<'JS'
        // Wysiwyg Editor JavaScript
        function initWysiwygEditor(editorId) {
            const editor = document.getElementById(editorId);
            const toolbar = editor.parentElement.querySelector('.wysiwyg-toolbar');
            const hiddenInput = document.getElementById(editorId + '_value');
            
            if (!editor || !toolbar) return;
            
            // Initialize toolbar functionality
            toolbar.addEventListener('click', function(e) {
                if (e.target.tagName === 'BUTTON') {
                    e.preventDefault();
                    const cmd = e.target.getAttribute('data-cmd');
                    const value = e.target.getAttribute('data-value');
                    
                    if (cmd === 'createLink') {
                        const url = prompt('Enter URL:');
                        if (url) {
                            document.execCommand(cmd, false, url);
                        }
                    } else if (cmd === 'insertImage') {
                        const url = prompt('Enter image URL:');
                        if (url) {
                            document.execCommand('insertImage', false, url);
                        }
                    } else if (cmd === 'insertTable') {
                        const rows = prompt('Enter number of rows:', '3');
                        const cols = prompt('Enter number of columns:', '3');
                        if (rows && cols) {
                            insertTable(editor, parseInt(rows), parseInt(cols));
                        }
                    } else if (cmd === 'formatBlock') {
                        document.execCommand(cmd, false, value);
                    } else {
                        document.execCommand(cmd, false, null);
                    }
                    
                    updateHiddenInput();
                    editor.focus();
                }
            });
            
            // Update hidden input when content changes
            editor.addEventListener('input', updateHiddenInput);
            editor.addEventListener('keyup', updateHiddenInput);
            editor.addEventListener('paste', updateHiddenInput);
            
            function updateHiddenInput() {
                if (hiddenInput) {
                    hiddenInput.value = editor.innerHTML;
                }
            }
            
            // Initialize content
            updateHiddenInput();
        }
        
        function insertTable(editor, rows, cols) {
            let table = '<table border="1" style="border-collapse: collapse; width: 100%;">';
            for (let i = 0; i < rows; i++) {
                table += '<tr>';
                for (let j = 0; j < cols; j++) {
                    table += '<td style="padding: 8px; border: 1px solid #ccc;">&nbsp;</td>';
                }
                table += '</tr>';
            }
            table += '</table>';
            
            document.execCommand('insertHTML', false, table);
        }
        
        // Make function globally available
        window.initWysiwygEditor = initWysiwygEditor;
        
        // Initialize all wysiwyg editors on page load
        document.addEventListener('DOMContentLoaded', function() {
            const editors = document.querySelectorAll('.wysiwyg-editor');
            editors.forEach(function(editor) {
                initWysiwygEditor(editor.id);
            });
        });
        JS;
    }

    public function getCSS(): string {
        return <<<'CSS'
        .wysiwyg-field {
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .wysiwyg-toolbar {
            background: #f8f9fa;
            border-bottom: 1px solid #ddd;
            padding: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            align-items: center;
        }
        
        .toolbar-group {
            display: flex;
            gap: 2px;
            border-right: 1px solid #ddd;
            padding-right: 8px;
            margin-right: 8px;
        }
        
        .toolbar-group:last-child {
            border-right: none;
            padding-right: 0;
            margin-right: 0;
        }
        
        .wysiwyg-toolbar button {
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 3px;
            padding: 6px 8px;
            cursor: pointer;
            font-size: 12px;
            min-width: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .wysiwyg-toolbar button:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }
        
        .wysiwyg-toolbar button:active {
            background: #dee2e6;
            transform: translateY(1px);
        }
        
        .wysiwyg-toolbar button[data-cmd="bold"] { font-weight: bold; }
        .wysiwyg-toolbar button[data-cmd="italic"] { font-style: italic; }
        .wysiwyg-toolbar button[data-cmd="underline"] { text-decoration: underline; }
        .wysiwyg-toolbar button[data-cmd="strikeThrough"] { text-decoration: line-through; }
        
        .wysiwyg-editor {
            min-height: 200px;
            padding: 12px;
            border: none;
            outline: none;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 14px;
            line-height: 1.5;
            color: #333;
        }
        
        .wysiwyg-editor:focus {
            background: #fff;
        }
        
        .wysiwyg-editor h1, .wysiwyg-editor h2, .wysiwyg-editor h3 {
            margin: 16px 0 8px 0;
            font-weight: 600;
        }
        
        .wysiwyg-editor h1 { font-size: 24px; }
        .wysiwyg-editor h2 { font-size: 20px; }
        .wysiwyg-editor h3 { font-size: 18px; }
        
        .wysiwyg-editor p {
            margin: 0 0 12px 0;
        }
        
        .wysiwyg-editor ul, .wysiwyg-editor ol {
            margin: 8px 0;
            padding-left: 24px;
        }
        
        .wysiwyg-editor table {
            border-collapse: collapse;
            width: 100%;
            margin: 8px 0;
        }
        
        .wysiwyg-editor table td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        
        .wysiwyg-editor img {
            max-width: 100%;
            height: auto;
        }
        
        .wysiwyg-editor a {
            color: #007bff;
            text-decoration: underline;
        }
        CSS;
    }

    public function getAdminConfig(): array {
        return [
            'type' => 'wysiwyg',
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group
        ];
    }

    public function getSchema(): array {
        return [
            'type' => 'string',
            'required' => $this->required
        ];
    }

    public function getDefaultValue() {
        return '';
    }

    public function processValue($value) {
        // Optionally sanitize HTML here
        return $value;
    }

    public function processLoadedValue($value) {
        return $value;
    }
} 