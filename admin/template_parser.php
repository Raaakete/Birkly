<?php
/**
 * HaruCMS Natural Template Parser
 * 
 * Parses natural templating syntax and renders it with data
 */

require_once __DIR__ . '/../core/birkly_paths.php';

class TemplateParser {
    private $data = [];
    
    public function __construct($data = []) {
        $this->data = $data;
    }
    
    public function setData($data) {
        $this->data = $data;
    }
    
    public function render($template) {
        [$masked, $trustedPhpBlocks] = $this->maskExistingPhpBlocks($template);
        $parsed = $this->parseNaturalSyntax($masked);
        $parsed = $this->unmaskPhpBlocks($parsed, $trustedPhpBlocks);

        return $this->renderWithData($parsed, $this->data, array_values($trustedPhpBlocks));
    }

    /**
     * Preserve developer-authored PHP in admin templates; only parser output is validated.
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private function maskExistingPhpBlocks(string $template): array
    {
        $blocks = [];
        $masked = preg_replace_callback(
            '/<\?(?:php\s|=)(.*?)(?:\?>)/s',
            function ($matches) use (&$blocks) {
                $key = '___BIRKLY_TRUSTED_PHP_' . count($blocks) . '___';
                $blocks[$key] = $matches[0];
                return $key;
            },
            $template
        );

        return [$masked, $blocks];
    }

  /**
     * @param array<string, string> $blocks
     */
    private function unmaskPhpBlocks(string $parsed, array $blocks): string
    {
        foreach ($blocks as $key => $block) {
            $parsed = str_replace($key, $block, $parsed);
        }
        return $parsed;
    }
    
    private function parseNaturalSyntax($template) {
        $parsed = $template;
        
        // FIRST: Process function-like directives BEFORE simple variables
        // Handle {translate 'key'} or {__ 'key'} syntax for i18n (must be before simple variable handler)
        $parsed = preg_replace_callback(
            '/\{(?:translate|__)\s+[\'"]([^\'"]+)[\'"]\}/',
            function($matches) {
                $key = $matches[1];
                return '<?= __(\'' . addslashes($key) . '\') ?>';
            },
            $parsed
        );
        
        // Handle {admin_url 'page'} with optional query params: collection='collection.slug'
        $parsed = preg_replace_callback(
            '/\{admin_url\s+[\'"]([^\'"]+)[\'"]([^}]*)\}/',
            function($matches) {
                $page = $matches[1];
                $attrs = trim($matches[2]);
                if ($attrs === '') {
                    return '<?= admin_url(\'' . addslashes($page) . '\') ?>';
                }

                $params = [];
                if (preg_match_all('/(\w+)=[\'"]([^\'"]+)[\'"]/', $attrs, $attrMatches, PREG_SET_ORDER)) {
                    foreach ($attrMatches as $am) {
                        $key = $am[1];
                        $val = $am[2];
                        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z0-9_]+)+$/', $val)) {
                            $parts = explode('.', $val);
                            $php = '$' . array_shift($parts);
                            foreach ($parts as $p) { $php .= "['" . $p . "']"; }
                            $params[] = "'" . addslashes($key) . "' => (" . $php . " ?? '')";
                        } else {
                            $params[] = "'" . addslashes($key) . "' => '" . addslashes($val) . "'";
                        }
                    }
                }

                if (empty($params)) {
                    return '<?= admin_url(\'' . addslashes($page) . '\') ?>';
                }
                return '<?= admin_url(\'' . addslashes($page) . '\', [' . implode(', ', $params) . ']) ?>';
            },
            $parsed
        );
        
        // Handle {birkly_url '/path'}
        $parsed = preg_replace_callback(
            '/\{birkly_url\s+[\'"]([^\'"]+)[\'"]\}/',
            function ($matches) {
                return '<?= birkly_url(\'' . addslashes($matches[1]) . '\') ?>';
            },
            $parsed
        );
        
        // Handle {admin_url 'page'} with query string parameters appended (e.g., {admin_url 'entry-editor'}?collection={collection.slug})
        // This pattern matches: {admin_url 'page'}?param={variable}
        $parsed = preg_replace_callback(
            '/\{admin_url\s+[\'"]([^\'"]+)[\'"]\}\?([a-zA-Z_]+)=\{([a-zA-Z0-9_\.]+)\}/',
            function($matches) {
                $page = $matches[1];
                $paramName = $matches[2];
                $varPath = $matches[3];
                // Build PHP variable from dot notation
                $parts = explode('.', $varPath);
                $php = '$' . array_shift($parts);
                foreach ($parts as $p) { $php .= "['" . $p . "']"; }
                return '<?= admin_url(\'' . addslashes($page) . '\', [\'' . addslashes($paramName) . '\' => ' . $php . ' ?? \'\']) ?>';
            },
            $parsed
        );
        
        // Handle {include_script 'filename.js'} syntax (must be before simple variable handler)
        $parsed = preg_replace_callback(
            '/\{include_script\s+[\'"]([^\'"]+)[\'"]?\}/',
            function($matches) {
                $filename = $matches[1];
                // Ensure .js extension
                if (!preg_match('/\.js$/', $filename)) {
                    $filename .= '.js';
                }
                $jsPath = '/admin/js/' . $filename;
                return '<?php
                    if (!function_exists(\'birkly_build_info\')) {
                        require_once __DIR__ . \'/../core/build_info.php\';
                    }
                    $__jsVer = (string) (birkly_build_info()[\'git_commit\'] ?? \'\');
                    $__jsUrl = birkly_url(\'' . addslashes($jsPath) . '\') . ($__jsVer !== \'\' ? \'?v=\' . rawurlencode($__jsVer) : \'\');
                    echo \'<script src="\' . htmlspecialchars($__jsUrl) . \'"></script>\';
                ?>';
            },
            $parsed
        );
        
        // Handle {include_style 'filename.css'} syntax (must be before simple variable handler)
        $parsed = preg_replace_callback(
            '/\{include_style\s+[\'"]([^\'"]+)[\'"]?\}/',
            function($matches) {
                $filename = $matches[1];
                // Ensure .css extension
                if (!preg_match('/\.css$/', $filename)) {
                    $filename .= '.css';
                }
                $cssPath = '/admin/css/' . $filename;
                return '<?= \'<link rel="stylesheet" href="\' . htmlspecialchars(birkly_url(\'' . addslashes($cssPath) . '\')) . \'">\' ?>';
            },
            $parsed
        );
        
        // Handle {count variable} syntax (must be before simple variable handler)
        $parsed = preg_replace_callback(
            '/\{count\s+([a-zA-Z0-9_\.]+)\}/',
            function($matches) {
                $var = trim($matches[1]);
                $parts = explode('.', $var);
                $php = '$' . array_shift($parts);
                foreach ($parts as $p) { $php .= "['" . $p . "']"; }
                return '<?= is_array(' . $php . ') ? count(' . $php . ') : 0 ?>';
            },
            $parsed
        );

        // Handle {json variable} or {json variable.property} syntax for JSON encoding (must be before simple variable handler)
        $parsed = preg_replace_callback(
            '/\{json\s+([a-zA-Z0-9_\.]+)\}/',
            function($matches) {
                $var = trim($matches[1]);
                // Build PHP variable from dot notation: a.b.c -> $a['b']['c']
                $parts = explode('.', $var);
                $php = '$' . array_shift($parts);
                foreach ($parts as $p) { $php .= "['" . $p . "']"; }
                return '<?= json_encode(' . $php . ' ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>';
            },
            $parsed
        );
        
        // THEN: Support single-brace variables {variable} directly (new syntax)
        // Skip control keywords, and render variables with safe defaults.
        // Rule: editor fields like "content" are raw; everything else escaped.
        $parsed = preg_replace_callback(
            '/\{\s*([a-zA-Z0-9_\.]+)\s*\}/',
            function($matches) {
                $var = trim($matches[1]);
                // Skip if it's a control keyword token (beginning of a block)
                $controlStarts = ['if', 'else', 'endif', 'for', 'foreach', 'endfor', 'endforeach', 'from', 'get', 'has', 'exist', 'render', 'fieldtype', 'include', 'media', 'url', 'count', 'json', 'translate', '__', 'admin_url'];
                foreach ($controlStarts as $kw) {
                    if (strpos($var, $kw) === 0) {
                        return '{' . $var . '}';
                    }
                }
                // Build PHP variable from dot notation: a.b.c -> $a['b']['c']
                $parts = explode('.', $var);
                $php = '$' . array_shift($parts);
                foreach ($parts as $p) { $php .= "['" . $p . "']"; }
                // Auto-raw for editor content fields named exactly 'content' or ending with '.content'
                $isContent = ($var === 'content' || preg_match('/\.content$/', $var));
                if ($isContent) {
                    return '<?= ' . $php . ' ?? "" ?>';
                }
                return '<?= htmlspecialchars(' . $php . ' ?? "", ENT_QUOTES, "UTF-8") ?>';
            },
            $parsed
        );

        // Support {{ variable }} with dot-notation and optional filters (| raw, | date:"format")
        // Example: {{ entry.title }} or {{ field.html | raw }} or {{ entry.created_at|date:"F j, Y" }}
        $parsed = preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_\.]+)\s*(\|\s*([a-z]+)(?::\s*"([^"]+)")?)?\s*\}\}/',
            function($matches) {
                $path = $matches[1];
                $filterType = isset($matches[3]) ? trim($matches[3]) : '';
                $filterValue = isset($matches[4]) ? $matches[4] : '';
                
                $parts = explode('.', $path);
                $php = '$' . array_shift($parts);
                foreach ($parts as $p) { $php .= "['" . $p . "']"; }
                
                // Apply filters
                if ($filterType === 'raw') {
                    return '<?= ' . $php . ' ?? "" ?>';
                } elseif ($filterType === 'date' && !empty($filterValue)) {
                    // Date filter: {{ entry.created_at|date:"F j, Y" }}
                    $format = addslashes($filterValue);
                    return '<?= date("' . $format . '", strtotime(' . $php . ' ?? "")) ?>';
                }
                
                // Default: escape for HTML
                return '<?= htmlspecialchars(' . $php . ' ?? "", ENT_QUOTES, "UTF-8") ?>';
            },
            $parsed
        );
        
        // Handle {from object get property} syntax
        $parsed = preg_replace_callback(
            '/\{from\s+([^\s}]+)\s+get\s+([^}]+)\}/',
            function($matches) {
                $object = $matches[1];
                $property = $matches[2];
                return '<?= htmlspecialchars($' . $object . '[\'' . $property . '\'] ?? \'\') ?>';
            },
            $parsed
        );
        
        // Handle {if condition exist} ... {endif} syntax
        // Support dot-notation: {if fieldtype_assets.js_files exist}
        // Note: "exist" checks should be separate from comparison operators
        // Use nested if statements: {if var exist} {if var.property == value} ... {endif} {endif}
        $parsed = preg_replace_callback(
            '/\{if\s+([^}]+)\s+exist\}/',
            function($matches) {
                $path = trim($matches[1]);
                // Build PHP variable from dot-notation: a.b.c -> $a['b']['c']
                $parts = explode('.', $path);
                $base = '$' . array_shift($parts);
                
                // If no dot notation, just check the base variable
                if (empty($parts)) {
                    // For simple variables like "entry", check if it exists and is not null/empty
                    // For arrays, consider them "existing" if they're set (even if empty, as they might have default values)
                    return '<?php if (isset(' . $base . ') && (' . $base . ' !== null)): ?>';
                }
                
                // For dot notation, build nested checks
                $current = $base;
                $issetChecks = ['isset(' . $base . ')'];
                foreach ($parts as $p) {
                    $current .= "['" . $p . "']";
                    $issetChecks[] = 'isset(' . $current . ')';
                }
                $issetExpr = implode(' && ', $issetChecks);
                return '<?php if (' . $issetExpr . ' && !empty(' . $current . ')): ?>';
            },
            $parsed
        );
        
        $parsed = preg_replace_callback(
            '/\{if\s+([^}]+)\s+has\s+([^}]+)\}/',
            function($matches) {
                $object = $matches[1];
                $property = $matches[2];
                return '<?php if (isset($' . $object . '[\'' . $property . '\']) && !empty($' . $object . '[\'' . $property . '\'])): ?>';
            },
            $parsed
        );
        
        $parsed = preg_replace_callback(
            '/\{if\s+([^}]+)\s+equals\s+"([^"]+)"\}/',
            function($matches) {
                $object = $matches[1];
                $value = $matches[2];
                return '<?php if (($' . $object . ' ?? \'\') === \'' . $value . '\'): ?>';
            },
            $parsed
        );
        
        // Handle {if object.property == 'value'} syntax
        // Also handle multiple conditions with || (OR) and && (AND)
        // Only match if the pattern contains == or != (comparison operators)
        // This must come after "exist" checks to avoid conflicts
        $parsed = preg_replace_callback(
            '/\{if\s+([^}]+)\}/',
            function($matches) {
                $fullCondition = trim($matches[1]);
                
                // Skip if this is an "exist" check (should already be handled)
                if (preg_match('/\s+exist\s*$/', $fullCondition) || preg_match('/\s+has\s+/', $fullCondition)) {
                    return '{if ' . $fullCondition . '}';
                }
                
                // Check if this contains == or != comparisons
                if (strpos($fullCondition, '==') === false && strpos($fullCondition, '!=') === false) {
                    // Not a comparison condition - handle as boolean check
                    // Simple variable check: {if item.completed} means check if truthy
                    $parts = explode('.', $fullCondition);
                    $phpVar = '$' . array_shift($parts);
                    foreach ($parts as $p) {
                        $phpVar .= "['" . $p . "']";
                    }
                    return '<?php if (' . $phpVar . ' ?? false): ?>';
                }
                
                // Split by || and && to handle multiple conditions
                // Use a regex that captures the operators as separate elements
                $matches = preg_split('/(\s*\|\|\s*|\s*&&\s*)/', $fullCondition, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                $operators = [];
                $parts = [];
                
                // Extract operators and conditions
                foreach ($matches as $item) {
                    $trimmed = trim($item);
                    if ($trimmed === '||' || $trimmed === '&&') {
                        $operators[] = $trimmed;
                    } else if (!empty($trimmed)) {
                        $parts[] = trim($item);
                    }
                }
                
                // Convert each part to PHP code
                $phpConditions = [];
                foreach ($parts as $part) {
                    $part = trim($part);

                    // Helper: convert identifiers/dot-paths to PHP vars (lang.code -> $lang['code'])
                    $toPhpVar = function(string $expr): string {
                        $expr = trim($expr);
                        if ($expr === '') return "''";
                        // literals
                        if (preg_match('/^(true|false|null)$/i', $expr)) return strtolower($expr);
                        if (preg_match('/^\d+(\.\d+)?$/', $expr)) return $expr;
                        // already quoted string
                        if ((str_starts_with($expr, "'") && str_ends_with($expr, "'")) || (str_starts_with($expr, "\"") && str_ends_with($expr, "\""))) {
                            return $expr;
                        }
                        // dot-notation var
                        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z0-9_]+)+$/', $expr)) {
                            $parts = explode('.', $expr);
                            $php = '$' . array_shift($parts);
                            foreach ($parts as $p) { $php .= "['" . $p . "']"; }
                            return '(' . $php . ' ?? null)';
                        }
                        // simple identifier
                        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $expr)) {
                            return '($' . $expr . ' ?? null)';
                        }
                        return $expr; // fallback (already PHP-ish)
                    };
                    
                    // Handle boolean comparisons: == true or == false
                    if (preg_match('/^(.+?)\s*==\s*(true|false)$/i', $part, $subMatches)) {
                        $condition = trim($subMatches[1]);
                        $boolValue = strtolower(trim($subMatches[2])) === 'true';
                        
                        // Convert dot notation to array access
                        $conditionParts = explode('.', $condition);
                        $phpVar = '$' . $conditionParts[0];
                        for ($i = 1; $i < count($conditionParts); $i++) {
                            $phpVar .= '[\'' . $conditionParts[$i] . '\']';
                        }
                        
                        $phpConditions[] = '(' . $phpVar . ' ?? false) === ' . ($boolValue ? 'true' : 'false');
                    }
                    // Handle != with boolean values
                    else if (preg_match('/^(.+?)\s*!=\s*(true|false)$/i', $part, $subMatches)) {
                        $condition = trim($subMatches[1]);
                        $boolValue = strtolower(trim($subMatches[2])) === 'true';
                        
                        // Convert dot notation to array access
                        $conditionParts = explode('.', $condition);
                        $phpVar = '$' . $conditionParts[0];
                        for ($i = 1; $i < count($conditionParts); $i++) {
                            $phpVar .= '[\'' . $conditionParts[$i] . '\']';
                        }
                        
                        $phpConditions[] = '(' . $phpVar . ' ?? false) !== ' . ($boolValue ? 'true' : 'false');
                    }
                    // Parse string comparisons: entry.status == 'value'
                    else if (preg_match('/^(.+?)\s*==\s*[\'"]([^\'"]+)[\'"]$/', $part, $subMatches)) {
                        $condition = trim($subMatches[1]);
                        $value = $subMatches[2];
                        
                        // Convert dot notation to array access
                        $conditionParts = explode('.', $condition);
                        $phpVar = '$' . $conditionParts[0];
                        for ($i = 1; $i < count($conditionParts); $i++) {
                            $phpVar .= '[\'' . $conditionParts[$i] . '\']';
                        }
                        
                        // Escape the value for PHP
                        $valueEscaped = addslashes($value);
                        $phpConditions[] = '(' . $phpVar . ' ?? \'\') === \'' . $valueEscaped . '\'';
                    }
                    // Handle != with string values
                    else if (preg_match('/^(.+?)\s*!=\s*[\'"]([^\'"]+)[\'"]$/', $part, $subMatches)) {
                        $condition = trim($subMatches[1]);
                        $value = $subMatches[2];
                        
                        // Convert dot notation to array access
                        $conditionParts = explode('.', $condition);
                        $phpVar = '$' . $conditionParts[0];
                        for ($i = 1; $i < count($conditionParts); $i++) {
                            $phpVar .= '[\'' . $conditionParts[$i] . '\']';
                        }
                        
                        // Escape the value for PHP
                        $valueEscaped = addslashes($value);
                        $phpConditions[] = '(' . $phpVar . ' ?? \'\') !== \'' . $valueEscaped . '\'';
                    } else {
                        // Handle var-to-var comparisons like: lang.code == current_language
                        if (preg_match('/^(.+?)\s*(==|!=)\s*(.+)$/', $part, $m)) {
                            $left = $toPhpVar($m[1]);
                            $op = $m[2];
                            $right = $toPhpVar($m[3]);
                            $phpConditions[] = $left . ' ' . $op . ' ' . $right;
                        } else {
                            // Fallback: treat as raw condition (might be handled by other patterns)
                            $phpConditions[] = $part;
                        }
                    }
                }
                
                // Reconstruct with operators
                $result = $phpConditions[0];
                for ($i = 0; $i < count($operators) && isset($phpConditions[$i + 1]); $i++) {
                    $result .= ' ' . $operators[$i] . ' ' . $phpConditions[$i + 1];
                }
                
                return '<?php if (' . $result . '): ?>';
            },
            $parsed
        );
        
        // Handle {else} syntax
        $parsed = str_replace('{else}', '<?php else: ?>', $parsed);
        
        // Handle {endif} syntax
        $parsed = str_replace('{endif}', '<?php endif; ?>', $parsed);
        
        // Handle {/if} syntax (alternative closing)
        $parsed = str_replace('{/if}', '<?php endif; ?>', $parsed);
        
        // Handle {for item in array} or {for each item in array} ... {endfor} syntax
        // Support dot-notation: {for src in fieldtype_assets.js_files}
        // Also support: {for each entry in 'blog'} with quoted collection names
        $parsed = preg_replace_callback(
            '/\{for\s+(?:each\s+)?([^\s]+)\s+in\s+([^}]+)\}/',
            function($matches) {
                $item = trim($matches[1]); // e.g., "entry"
                $arrayPath = trim($matches[2]); // e.g., "'blog'" or "blog" or "fieldtype_assets.js_files"
                
                // Check if it's a quoted string (collection name like 'blog' or "blog")
                if (preg_match("/^['\"]([^'\"]+)['\"]/", $arrayPath, $quoteMatches)) {
                    $collectionName = $quoteMatches[1];
                    // Use the collection name directly as a variable (e.g., $blog)
                    $phpArray = '$' . $collectionName;
                } else {
                    // Convert dot-notation to array access: a.b.c -> $a['b']['c']
                    $parts = explode('.', $arrayPath);
                    $phpArray = '$' . array_shift($parts);
                    foreach ($parts as $p) {
                        $phpArray .= "['" . $p . "']";
                    }
                }
                return '<?php foreach (' . $phpArray . ' ?? [] as $' . $item . '): ?>';
            },
            $parsed
        );
        
        // Handle {foreach array as item} ... {endforeach} syntax (alternative)
        $parsed = preg_replace_callback(
            '/\{foreach\s+([^\s]+)\s+as\s+([^\s}]+)\}/',
            function($matches) {
                $arrayPath = trim($matches[1]);
                $item = trim($matches[2]);
                // Convert dot-notation to array access: a.b.c -> $a['b']['c']
                $parts = explode('.', $arrayPath);
                $phpArray = '$' . array_shift($parts);
                foreach ($parts as $p) {
                    $phpArray .= "['" . $p . "']";
                }
                return '<?php foreach (' . $phpArray . ' ?? [] as $' . $item . '): ?>';
            },
            $parsed
        );
        
        $parsed = str_replace('{endfor}', '<?php endforeach; ?>', $parsed);
        $parsed = str_replace('{endforeach}', '<?php endforeach; ?>', $parsed);
        
        // Handle {render fieldtype "name"} syntax
        $parsed = preg_replace_callback(
            '/\{render\s+fieldtype\s+"([^"]+)"\}/',
            function($matches) {
                $fieldtypeName = $matches[1];
                return '<?= $this->renderFieldtype("' . $fieldtypeName . '", $field) ?>';
            },
            $parsed
        );
        
        return $parsed;
    }
    
    private function renderWithData($parsed, $data, array $trustedPhpBlocks = []) {
        require_once __DIR__ . '/template_safe_renderer.php';

        return TemplateSafeRenderer::render($parsed, $this, $data, $trustedPhpBlocks);
    }
    
    private function renderFieldtype($fieldtypeName, $field) {
        // This would call the fieldtype API
        // For now, return a placeholder
        return '<div class="fieldtype-placeholder">Fieldtype: ' . htmlspecialchars($fieldtypeName) . '</div>';
    }
}

/**
 * Helper function to render a template with data
 */
function render_template($template, $data = []) {
    $parser = new TemplateParser($data);
    return $parser->render($template);
}

/**
 * Helper function to render a template file with data
 */
function render_template_file($templatePath, $data = []) {
    if (!file_exists($templatePath)) {
        return '<div class="error">Template not found: ' . htmlspecialchars($templatePath) . '</div>';
    }
    
    // Define __TEMPLATE_DIR__ constant for use in templates (for component includes)
    $templateDir = dirname($templatePath);
    if (!defined('__TEMPLATE_DIR__')) {
        define('__TEMPLATE_DIR__', $templateDir);
    }
    
    $template = file_get_contents($templatePath);
    // Strip UTF-8 BOM so <?php-first detection in admin/index.php works on all editors
    if (str_starts_with($template, "\xEF\xBB\xBF")) {
        $template = substr($template, 3);
    }
    return render_template($template, $data);
}
?>
