<?php
/**
 * HaruCMS Natural Template Parser
 * 
 * Parses natural templating syntax and renders it with data
 */

class TemplateParser {
    private $data = [];
    
    public function __construct($data = []) {
        $this->data = $data;
    }
    
    public function setData($data) {
        $this->data = $data;
    }
    
    public function render($template) {
        // Parse natural templating syntax
        $parsed = $this->parseNaturalSyntax($template);
        
        // Render with data
        return $this->renderWithData($parsed, $this->data);
    }
    
    private function parseNaturalSyntax($template) {
        $parsed = $template;
        
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
        
        // Handle {if condition} ... {endif} syntax
        $parsed = preg_replace_callback(
            '/\{if\s+([^}]+)\s+exist\}/',
            function($matches) {
                $condition = $matches[1];
                return '<?php if (!empty($' . $condition . ')): ?>';
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
        $parsed = preg_replace_callback(
            '/\{if\s+([^}]+)\s*==\s*[\'"]([^\'"]+)[\'"]\}/',
            function($matches) {
                $condition = $matches[1];
                $value = $matches[2];
                // Convert dot notation to array access properly
                $parts = explode('.', $condition);
                $condition = '$' . $parts[0];
                for ($i = 1; $i < count($parts); $i++) {
                    $condition .= '[\'' . $parts[$i] . '\']';
                }
                return '<?php if ((' . $condition . ' ?? \'\') === \'' . $value . '\'): ?>';
            },
            $parsed
        );
        
        // Handle {else} syntax
        $parsed = str_replace('{else}', '<?php else: ?>', $parsed);
        
        // Handle {endif} syntax
        $parsed = str_replace('{endif}', '<?php endif; ?>', $parsed);
        
        // Handle {/if} syntax (alternative closing)
        $parsed = str_replace('{/if}', '<?php endif; ?>', $parsed);
        
        // Handle {for item in array} ... {endfor} syntax
        $parsed = preg_replace_callback(
            '/\{for\s+([^\s]+)\s+in\s+([^}]+)\}/',
            function($matches) {
                $item = $matches[1];
                $array = $matches[2];
                return '<?php foreach ($' . $array . ' as $' . $item . '): ?>';
            },
            $parsed
        );
        
        $parsed = str_replace('{endfor}', '<?php endforeach; ?>', $parsed);
        
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
    
    private function renderWithData($parsed, $data) {
        // Extract variables from data
        extract($data);
        
        // Start output buffering
        ob_start();
        
        // Evaluate the parsed template
        eval('?>' . $parsed . '<?php');
        
        // Get the output
        $output = ob_get_clean();
        
        return $output;
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
    
    $template = file_get_contents($templatePath);
    return render_template($template, $data);
}
?>
