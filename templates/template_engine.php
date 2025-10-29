<?php
// Minimal PHP Templating Engine for HaruCMS
class TemplateEngine {
    public static function render($template, $vars = []) {
        // Variable interpolation: {{ var }}
        $template = preg_replace_callback('/{{\s*(\w+)\s*}}/', function($m) use ($vars) {
            return isset($vars[$m[1]]) ? htmlspecialchars($vars[$m[1]]) : '';
        }, $template);
        // For loops: {% for item in items %} ... {% endfor %}
        $template = preg_replace_callback('/{%\s*for\s+(\w+)\s+in\s+(\w+)\s*%}([\s\S]*?){%\s*endfor\s*%}/', function($m) use ($vars) {
            $out = '';
            $arr = $vars[$m[2]] ?? [];
            foreach ($arr as $item) {
                $out .= self::render($m[3], array_merge($vars, [$m[1]=>$item]));
            }
            return $out;
        }, $template);
        // If conditionals: {% if var %} ... {% endif %}
        $template = preg_replace_callback('/{%\s*if\s+(\w+)\s*%}([\s\S]*?){%\s*endif\s*%}/', function($m) use ($vars) {
            return !empty($vars[$m[1]]) ? $m[2] : '';
        }, $template);
        // Add entry block: {add entry to 'collection'} ... {/add}
        $template = preg_replace_callback('/{add entry to ["\']([\w-]+)["\']}([\s\S]*?){\/add}/', function($m) use ($vars) {
            $collection = $m[1];
            $block = $m[2];
            // Load schema for the collection
            $schemaPath = __DIR__ . "/../content/collections/$collection/schema.json";
            $schema = file_exists($schemaPath) ? json_decode(file_get_contents($schemaPath), true) : null;
            $formSchema = $schema ? $schema['fields'] : [];
            // Make $formSchema available for fieldtype helpers (not used in this minimal engine, but for future extension)
            $formVars = $vars;
            $formVars['formSchema'] = $formSchema;
            // Replace {{field}} with value if present in $vars, else empty
            $block = preg_replace_callback('/{{\s*(\w+)\s*}}/', function($mm) use ($vars) {
                return isset($vars[$mm[1]]) ? htmlspecialchars($vars[$mm[1]]) : '';
            }, $block);
            // Fieldtype helpers: {input name="fieldname"}, {textarea name="fieldname"}, etc.
            $block = preg_replace_callback('/{(input|textarea|select|checkbox|number|date|datetime|media|relation)\s+name=["\'](\w+)["\']}/', function($mm) use ($formSchema, $vars) {
                $type = $mm[1];
                $name = $mm[2];
                $field = null;
                foreach ($formSchema as $f) {
                    if ($f['name'] === $name) { $field = $f; break; }
                }
                if (!$field) return '';
                $value = isset($vars[$name]) ? htmlspecialchars($vars[$name]) : '';
                // Render input based on type
                switch ($type) {
                    case 'input':
                        return '<input type="text" name="'.$name.'" value="'.$value.'" class="input"'.($field['required']?' required':'').' placeholder="'.htmlspecialchars($field['placeholder']??'').'">';
                    case 'textarea':
                        return '<textarea name="'.$name.'" class="input"'.($field['required']?' required':'').' placeholder="'.htmlspecialchars($field['placeholder']??'').'">'.$value.'</textarea>';
                    case 'select':
                        $opts = '';
                        foreach (($field['options']??[]) as $opt) {
                            $sel = $value===$opt['value']?' selected':'';
                            $opts .= '<option value="'.htmlspecialchars($opt['value']).'"'.$sel.'>'.htmlspecialchars($opt['label']).'</option>';
                        }
                        return '<select name="'.$name.'" class="input"'.($field['required']?' required':'').'>'.$opts.'</select>';
                    case 'checkbox':
                        return '<input type="checkbox" name="'.$name.'" value="1"'.($value?' checked':'').'> ';
                    case 'number':
                        return '<input type="number" name="'.$name.'" value="'.$value.'" class="input"'.($field['required']?' required':'').'> ';
                    case 'date':
                        return '<input type="date" name="'.$name.'" value="'.$value.'" class="input"'.($field['required']?' required':'').'> ';
                    case 'datetime':
                        return '<input type="datetime-local" name="'.$name.'" value="'.$value.'" class="input"'.($field['required']?' required':'').'> ';
                    case 'media':
                        return '<input type="file" name="'.$name.'" class="input">';
                    case 'relation':
                        return '<input type="text" name="'.$name.'" value="'.$value.'" class="input">'; // Placeholder, can be improved
                }
                return '';
            }, $block);
            return $block;
        }, $template);
        return $template;
    }
}

// --- Usage Example ---
// Fetch entries from the public API (replace with your collection ID or slug)
$apiUrl = 'http://localhost:8001/api/public.php?action=list_entries&collection=col-01-blog';
$data = json_decode(file_get_contents($apiUrl), true);
$entries = $data['data']['entries'] ?? [];
$template = <<<HTML
<h1>Blog Entries</h1>
<ul>
{% for entry in entries %}
  <li>
    <strong>{{ entry.title }}</strong><br>
    <small>{{ entry.created_at }}</small><br>
    <div>{{ entry.content }}</div>
  </li>
{% endfor %}
</ul>
HTML;
echo TemplateEngine::render($template, ['entries'=>$entries]); 