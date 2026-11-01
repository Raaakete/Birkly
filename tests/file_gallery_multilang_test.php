<?php
/**
 * File and Gallery fieldtypes: multilanguage HTML id/name rewrite (entry editor).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/api/admin_templates.php';
require_once dirname(__DIR__) . '/fieldtypes/File/File.php';
require_once dirname(__DIR__) . '/fieldtypes/Gallery/Gallery.php';

$failures = 0;

function assert_true(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    echo $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
        if ($detail !== '') {
            echo '  ' . $detail . "\n";
        }
    }
}

function assert_contains(string $name, string $haystack, string $needle): void
{
    assert_true($name, str_contains($haystack, $needle), "expected substring: {$needle}");
}

function assert_not_contains(string $name, string $haystack, string $needle): void
{
    assert_true($name, !str_contains($haystack, $needle), "unexpected substring: {$needle}");
}

$fileField = new Birkly\FieldTypes\File\File([
    'name' => 'cover',
    'label' => 'Cover',
    'value' => '',
    'required' => false,
    'options' => ['accept' => 'image/*'],
]);
$fileHtml = $fileField->render();

assert_contains('file render container id', $fileHtml, 'id="field_cover"');
assert_contains('file render hidden value id', $fileHtml, 'id="field_cover_value"');
assert_contains('file render file input id', $fileHtml, 'id="file_input_cover"');

$enFileHtml = birkly_admin_multilang_field_html($fileHtml, 'cover', 'en');

assert_contains('file multilang container', $enFileHtml, 'id="field_en_cover"');
assert_contains('file multilang hidden value', $enFileHtml, 'id="field_en_cover_value"');
assert_contains('file multilang file input', $enFileHtml, 'id="file_input_en_cover"');
assert_contains('file multilang input name', $enFileHtml, 'name="en[cover]"');
assert_not_contains('file multilang leaves bare file_input', $enFileHtml, 'id="file_input_cover"');

$galleryField = new Birkly\FieldTypes\Gallery\Gallery([
    'name' => 'gallery',
    'label' => 'Gallery',
    'value' => [],
    'required' => false,
]);
$galleryHtml = $galleryField->render();

assert_contains('gallery render file input id', $galleryHtml, 'id="gallery_input_gallery"');

$deGalleryHtml = birkly_admin_multilang_field_html($galleryHtml, 'gallery', 'de');

assert_contains('gallery multilang container', $deGalleryHtml, 'id="field_de_gallery"');
assert_contains('gallery multilang gallery input', $deGalleryHtml, 'id="gallery_input_de_gallery"');
assert_not_contains('gallery multilang leaves bare gallery_input', $deGalleryHtml, 'id="gallery_input_gallery"');

exit($failures > 0 ? 1 : 0);
