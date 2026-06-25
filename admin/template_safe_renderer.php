<?php
/**
 * Safe renderer for compiled admin templates (P2-M6-T1).
 * Validates compiler output then includes via a temp file (no eval()).
 */

class TemplateSafeRenderer
{
    /**
     * @param string[] $trustedPhpBlocks Original template PHP blocks (developer-authored, not parser output)
     * @throws InvalidArgumentException when compiled output fails safety checks
     */
    public static function validate(string $compiled, array $trustedPhpBlocks = []): void
    {
        if ($compiled === '') {
            return;
        }

        $check = $compiled;
        foreach ($trustedPhpBlocks as $block) {
            if ($block !== '') {
                $check = str_replace($block, '', $check);
            }
        }

        if (preg_match('/<\?(?!php[\s=]|=)/', $check)) {
            throw new InvalidArgumentException('Template contains disallowed PHP open tags');
        }

        foreach (self::extractPhpBlocks($check) as $php) {
            self::validatePhpBlock($php);
        }
    }

  /**
     * @return string[]
     */
    private static function extractPhpBlocks(string $compiled): array
    {
        $blocks = [];
        if (preg_match_all('/<\?(?:php\s|=)(.*?)(?:\?>)/s', $compiled, $matches)) {
            $blocks = $matches[1];
        }
        return $blocks;
    }

    private static function validatePhpBlock(string $php): void
    {
        $patterns = [
            '/\beval\s*\(/i',
            '/\b(exec|system|shell_exec|passthru|proc_open|popen)\s*\(/i',
            '/\b(require|include)(_once)?\s+/i',
            '/\b(file_get_contents|file_put_contents|fopen|fwrite|unlink)\s*\(/i',
            '/\b(rmdir|mkdir|chmod|chown|chgrp|rename|copy)\s*\(/i',
            '/\$_(GET|POST|SERVER|ENV|FILES|COOKIE|REQUEST)\b/i',
            '/\bGLOBALS\b/',
            '/\bget_defined_vars\s*\(/i',
            '/\bcreate_function\s*\(/i',
            '/\bassert\s*\(/i',
            '/\bpreg_replace\s*\(/i',
            '/\bcall_user_func(_array)?\s*\(/i',
            '/\bReflection\b/',
            '/\bnew\s+[A-Za-z_]/',
            '/\bfunction\s+[A-Za-z_]/',
            '/\b(class|trait|interface)\s+[A-Za-z_]/',
            '/\bnamespace\s+[A-Za-z_]/',
            '/\buse\s+[A-Za-z_]/',
            '/\bgoto\s+[A-Za-z_]/',
            '/\bglobal\s+\$/',
            '/`/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $php)) {
                throw new InvalidArgumentException('Template contains disallowed construct');
            }
        }

        if (preg_match('/->/', $php) && !preg_match('/\$this->renderFieldtype\s*\(/', $php)) {
            throw new InvalidArgumentException('Template contains disallowed object access');
        }

        if (preg_match('/::/', $php)) {
            throw new InvalidArgumentException('Template contains disallowed static access');
        }
    }

    /**
     * Render validated compiled template with $this bound to $context.
     *
     * @throws Exception on render failure
     */
    public static function render(string $compiled, object $context, array $data = [], array $trustedPhpBlocks = []): string
    {
        self::validate($compiled, $trustedPhpBlocks);

        $tmp = tempnam(sys_get_temp_dir(), 'birkly_tpl_');
        if ($tmp === false) {
            throw new RuntimeException('Failed to create temporary template file');
        }

        $written = file_put_contents($tmp, $compiled);
        if ($written === false) {
            @unlink($tmp);
            throw new RuntimeException('Failed to write temporary template file');
        }

        ob_start();
        $oldErrorReporting = error_reporting(E_ALL);
        $oldDisplayErrors = ini_get('display_errors');
        ini_set('display_errors', '0');

        try {
            $render = function () use ($tmp, $data) {
                extract($data);
                include $tmp;
            };
            $render = \Closure::bind($render, $context, get_class($context));
            $render();
        } catch (Throwable $e) {
            ob_end_clean();
            error_reporting($oldErrorReporting);
            ini_set('display_errors', $oldDisplayErrors);
            @unlink($tmp);
            throw new Exception('Template rendering error: ' . $e->getMessage() . ' at line ' . $e->getLine(), 0, $e);
        }

        error_reporting($oldErrorReporting);
        ini_set('display_errors', $oldDisplayErrors);
        @unlink($tmp);

        $output = ob_get_clean();
        if ($output === false) {
            throw new Exception('Template rendering failed: Output buffering error');
        }

        return $output;
    }
}
