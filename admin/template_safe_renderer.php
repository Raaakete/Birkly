<?php
/**
 * Safe renderer for compiled admin templates (P2-M6-T1).
 * Validates compiler output then includes via a temp file (no eval()).
 */

class TemplateSafeRenderer
{
    /** Tokens forbidden inside PHP blocks of compiled templates. */
    private const FORBIDDEN_PHP = [
        'eval', 'exec', 'system', 'shell_exec', 'passthru', 'proc_open', 'popen',
        'require', 'include', 'require_once', 'include_once',
        'file_get_contents', 'file_put_contents', 'fopen', 'fwrite', 'unlink',
        'rmdir', 'mkdir', 'chmod', 'chown', 'chgrp', 'rename', 'copy',
        '$_GET', '$_POST', '$_SERVER', '$_ENV', '$_FILES', '$_COOKIE', '$_REQUEST',
        'GLOBALS', 'get_defined_vars', 'create_function', 'assert(', 'preg_replace',
        'call_user_func', 'call_user_func_array', 'Reflection', 'new ', 'function ',
        'class ', 'trait ', 'interface ', 'namespace ', 'use ', 'goto ', 'global ',
        '`',
    ];

    /**
     * @throws InvalidArgumentException when compiled output fails safety checks
     */
    public static function validate(string $compiled): void
    {
        if ($compiled === '') {
            return;
        }

        if (preg_match('/<\?(?!php[\s=]|=)/', $compiled)) {
            throw new InvalidArgumentException('Template contains disallowed PHP open tags');
        }

        foreach (self::extractPhpBlocks($compiled) as $php) {
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
        $lower = strtolower($php);

        foreach (self::FORBIDDEN_PHP as $token) {
            if (strpos($lower, strtolower($token)) !== false) {
                throw new InvalidArgumentException('Template contains disallowed construct: ' . $token);
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
    public static function render(string $compiled, object $context, array $data = []): string
    {
        self::validate($compiled);

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
