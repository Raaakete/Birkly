#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Validate project / site HTML for phantom Birkly template syntax (P45-M7).
 *
 * Flags:
 *   - {entry 'x'.'y'}…{endentry}  (NOT {entry for 'contact'} which is valid forms)
 *   - truncate / strip_tags used as filters inside {…} tags
 *
 * Usage:
 *   php scripts/validate-project-templates.php [path ...]
 *   php scripts/validate-project-templates.php --self-test
 *
 * Exit codes: 0 = clean, 1 = findings (or self-test failure), 2 = usage error
 */

function birkly_validator_usage(): void
{
    fwrite(STDERR, "Usage: php scripts/validate-project-templates.php [--self-test] [path ...]\n");
    fwrite(STDERR, "  Paths may be files or directories (scans *.html, *.htm, *.php, *.md).\n");
}

/**
 * @return list<array{file:string,line:int,rule:string,snippet:string}>
 */
function birkly_validate_template_text(string $text, string $fileLabel): array
{
    $findings = [];
    $lines = preg_split("/\r\n|\n|\r/", $text);
    if (!is_array($lines)) {
        return $findings;
    }

    $openEntryPhantom = null; // line of unmatched {entry 'x'.'y'}

    foreach ($lines as $idx => $line) {
        $lineNo = $idx + 1;

        // Phantom entry block open: {entry 'collection'.'slug'} — not {entry for '…'}
        if (preg_match("/\\{entry\\s+'[^']+'\\.'[^']+'\\}/i", $line, $m)) {
            $findings[] = [
                'file' => $fileLabel,
                'line' => $lineNo,
                'rule' => 'phantom-entry-block',
                'snippet' => trim($m[0]),
            ];
            $openEntryPhantom = $lineNo;
        }

        if (preg_match('/\{endentry\}/i', $line, $m)) {
            $findings[] = [
                'file' => $fileLabel,
                'line' => $lineNo,
                'rule' => 'phantom-endentry',
                'snippet' => '{endentry}',
            ];
            $openEntryPhantom = null;
        }

        // Filters inside template tags only
        if (preg_match_all('/\{([^{}]+)\}/', $line, $tagMatches)) {
            foreach ($tagMatches[1] as $inner) {
                $innerTrim = trim($inner);

                // Valid forms marker — do not flag
                if (preg_match("/^entry\\s+for\\s+'/i", $innerTrim)) {
                    continue;
                }

                if (preg_match('/\btruncate\b/i', $innerTrim)) {
                    $findings[] = [
                        'file' => $fileLabel,
                        'line' => $lineNo,
                        'rule' => 'phantom-truncate-filter',
                        'snippet' => '{' . $innerTrim . '}',
                    ];
                }

                if (preg_match('/\bstrip_tags\b/i', $innerTrim)) {
                    $findings[] = [
                        'file' => $fileLabel,
                        'line' => $lineNo,
                        'rule' => 'phantom-strip_tags-filter',
                        'snippet' => '{' . $innerTrim . '}',
                    ];
                }
            }
        }
    }

    // Suppress unused-var notice if open left dangling — already flagged open tag.
    unset($openEntryPhantom);

    return $findings;
}

/**
 * @return list<string>
 */
function birkly_validator_collect_files(array $paths): array
{
    $files = [];
    $exts = ['html', 'htm', 'php', 'md', 'txt'];

    foreach ($paths as $path) {
        if (is_file($path)) {
            $files[] = $path;
            continue;
        }
        if (!is_dir($path)) {
            fwrite(STDERR, "Not found: {$path}\n");
            continue;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            /** @var SplFileInfo $fileInfo */
            if (!$fileInfo->isFile()) {
                continue;
            }
            $ext = strtolower((string) $fileInfo->getExtension());
            if (!in_array($ext, $exts, true)) {
                continue;
            }
            $files[] = $fileInfo->getPathname();
        }
    }

    sort($files);
    return array_values(array_unique($files));
}

function birkly_validator_print_findings(array $findings): void
{
    foreach ($findings as $f) {
        echo $f['file'] . ':' . $f['line'] . ' [' . $f['rule'] . '] ' . $f['snippet'] . PHP_EOL;
    }
}

function birkly_validator_self_test(): int
{
    $failures = 0;
    $assert = static function (string $name, bool $ok) use (&$failures): void {
        echo ($ok ? 'PASS' : 'FAIL') . ' ' . $name . PHP_EOL;
        if (!$ok) {
            $failures++;
        }
    };

    $bad = "{entry 'site_elements'.'header'}\n{title}\n{endentry}\n";
    $badFindings = birkly_validate_template_text($bad, 'fixture-bad.html');
    $assert('flags phantom entry block', (bool) array_filter($badFindings, static fn($f) => $f['rule'] === 'phantom-entry-block'));
    $assert('flags endentry', (bool) array_filter($badFindings, static fn($f) => $f['rule'] === 'phantom-endentry'));

    $forms = "{entry for 'contact'}\n<form></form>\n";
    $formsFindings = birkly_validate_template_text($forms, 'fixture-forms.html');
    $assert('does not flag entry for forms', count($formsFindings) === 0);

    $filters = "{title truncate 80}\n{body strip_tags}\n";
    $filterFindings = birkly_validate_template_text($filters, 'fixture-filters.html');
    $assert('flags truncate filter', (bool) array_filter($filterFindings, static fn($f) => $f['rule'] === 'phantom-truncate-filter'));
    $assert('flags strip_tags filter', (bool) array_filter($filterFindings, static fn($f) => $f['rule'] === 'phantom-strip_tags-filter'));

    $good = "{from 'site_elements' get entry 'header'}{title}{endget}\n{created_at format \"Y-m-d\"}\n";
    $goodFindings = birkly_validate_template_text($good, 'fixture-good.html');
    $assert('allows canonical from/get and format', count($goodFindings) === 0);

    // Temp dir scan path
    $dir = sys_get_temp_dir() . '/birkly-tpl-val-' . uniqid('', true);
    mkdir($dir);
    file_put_contents($dir . '/bad.html', "{entry 'blog'.'post'}{endentry}\n");
    file_put_contents($dir . '/ok.html', "{for each entry in 'blog'}{title}{endfor}\n");
    $files = birkly_validator_collect_files([$dir]);
    $all = [];
    foreach ($files as $file) {
        $text = (string) file_get_contents($file);
        foreach (birkly_validate_template_text($text, $file) as $finding) {
            $all[] = $finding;
        }
    }
    $assert('directory scan finds phantom in bad.html', count($all) >= 2);
    // cleanup
    @unlink($dir . '/bad.html');
    @unlink($dir . '/ok.html');
    @rmdir($dir);

    echo 'self-test: ' . ($failures === 0 ? 'PASS' : 'FAIL') . PHP_EOL;
    return $failures === 0 ? 0 : 1;
}

// --- CLI ---
$args = array_slice($argv, 1);
if (count($args) === 0) {
    birkly_validator_usage();
    exit(2);
}

if (in_array('--self-test', $args, true)) {
    exit(birkly_validator_self_test());
}

$paths = array_values(array_filter($args, static fn($a) => $a !== '--self-test'));
if (count($paths) === 0) {
    birkly_validator_usage();
    exit(2);
}

$files = birkly_validator_collect_files($paths);
if (count($files) === 0) {
    fwrite(STDERR, "No template files found.\n");
    exit(2);
}

$allFindings = [];
foreach ($files as $file) {
    $text = @file_get_contents($file);
    if ($text === false) {
        fwrite(STDERR, "Unreadable: {$file}\n");
        continue;
    }
    foreach (birkly_validate_template_text($text, $file) as $finding) {
        $allFindings[] = $finding;
    }
}

birkly_validator_print_findings($allFindings);

if (count($allFindings) > 0) {
    fwrite(STDERR, count($allFindings) . " finding(s)\n");
    exit(1);
}

echo "OK — no phantom template syntax in " . count($files) . " file(s)\n";
exit(0);
