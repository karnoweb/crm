<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Namespace-aware scan of package source: parses use statements and qualified names.
 */
final class SourceScanner
{
    /**
     * @return list<string>
     */
    public static function phpFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return list<string>
     */
    public static function importedAndQualifiedNames(string $contents): array
    {
        $tokens = token_get_all($contents);
        $names = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (! is_array($token)) {
                continue;
            }

            if ($token[0] === T_USE) {
                foreach (self::parseUseStatement($tokens, $i) as $name) {
                    $names[] = $name;
                }

                continue;
            }

            if (in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                $names[] = ltrim($token[1], '\\');
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     *
     * @return list<string>
     */
    private static function parseUseStatement(array $tokens, int &$index): array
    {
        $buffer = '';
        $count = count($tokens);

        for ($index++; $index < $count; $index++) {
            $token = $tokens[$index];

            if ($token === ';') {
                break;
            }

            $buffer .= is_array($token) ? $token[1] : $token;
        }

        $buffer = trim($buffer);
        $buffer = preg_replace('/^(function|const)\s+/i', '', $buffer) ?? $buffer;

        if (str_contains($buffer, '{')) {
            [$prefix, $group] = array_pad(explode('{', $buffer, 2), 2, '');
            $prefix = trim($prefix, " \t\n\r\0\x0B\\");
            $inner = rtrim($group, '}');
            $names = [];

            foreach (explode(',', $inner) as $piece) {
                $piece = trim(explode(' as ', $piece, 2)[0]);
                if ($piece === '') {
                    continue;
                }
                $names[] = $prefix . '\\' . $piece;
            }

            return $names;
        }

        $name = trim(explode(' as ', $buffer, 2)[0]);

        return $name === '' ? [] : [$name];
    }
}
