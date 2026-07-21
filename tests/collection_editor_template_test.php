<?php
/**
 * Collection editor templating PHP blocks must parse (P6.4 hotfix regression).
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Collection editor template PHP tests\n\n";

$root = dirname(__DIR__);
$template = $root . '/admin/templates/collection-editor.html';
$content = file_get_contents($template);
test_assert('collection editor includes field template controls', strpos($content, 'field-template-select') !== false && strpos($content, 'save-field-template-btn') !== false);

$blocks = [
    'entry level example' => <<<'PHP'
$collectionSlug = 'products';
$exampleSlug = 'example-entry-slug';
$fields = [['name' => 'image', 'label' => 'Image', 'type' => 'media']];
$entryExample = "{from '" . $collectionSlug . "' get entry '" . $exampleSlug . "'}\n";
$entryExample .= "  <h1>{ title }</h1>\n";
if (!empty($fields)) {
    foreach ($fields as $field) {
        $fieldName = $field['name'] ?? '';
        $fieldLabel = $field['label'] ?? $fieldName;
        if ($field['type'] === 'media') {
            $entryExample .= "  <img src=\"{ " . $fieldName . " }\" alt=\"" . htmlspecialchars($fieldLabel) . "\">\n";
        }
    }
}
$entryExample .= "{endget}";
PHP,
    'sub-entry example' => <<<'PHP'
$collectionSlug = 'products';
$subCollectionSlug = 'variants';
$exampleEntrySlug = 'example-entry-slug';
$subCollectionData = ['schema' => ['fields' => [['name' => 'photo', 'label' => 'Photo', 'type' => 'media']]]];
$entryExample = "{from '" . $collectionSlug . "' get entry '" . $exampleEntrySlug . "'}\n";
$entryExample .= "  <h1>{ title }</h1>\n";
$entryExample .= "  {for each sub_entry in '" . $subCollectionSlug . "'}\n";
$entryExample .= "    <div class=\"sub-entry\">\n";
$subFields = $subCollectionData['schema']['fields'] ?? [];
if (!empty($subFields)) {
    foreach ($subFields as $field) {
        $fieldName = $field['name'] ?? '';
        $fieldLabel = $field['label'] ?? $fieldName;
        if ($field['type'] === 'media') {
            $entryExample .= "      <img src=\"{ " . $fieldName . " }\" alt=\"" . htmlspecialchars($fieldLabel) . "\">\n";
        }
    }
}
$entryExample .= "    </div>\n";
$entryExample .= "  {endfor}\n";
$entryExample .= "{endget}";
PHP,
];

foreach ($blocks as $name => $code) {
    $tmp = tempnam(sys_get_temp_dir(), 'ce-php-');
    file_put_contents($tmp, "<?php\n" . $code);
    $out = [];
    $exit = 0;
    exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $exit);
    @unlink($tmp);
    test_assert($name . ' PHP block parses', $exit === 0, implode("\n", $out));
}

exit(test_finish('collection_editor_template_test'));
