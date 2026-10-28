<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Support;

class ViteAliasArray
{
    private const ALIAS_ARRAY = '/^([ \t]*)alias:\s*\[(\s*\])?/m';

    public function isUsedIn(string $content): bool
    {
        return preg_match(self::ALIAS_ARRAY, $content) === 1;
    }

    public function add(string $content, string $alias, string $target): string
    {
        $updated = (string) preg_replace_callback(
            self::ALIAS_ARRAY,
            fn (array $matches): string => $this->entry($matches, $alias, $target),
            $content,
            limit: 1,
        );

        return $this->ensureFileUrlImport($updated);
    }

    /**
     * @param  array<int, string>  $matches
     */
    private function entry(array $matches, string $alias, string $target): string
    {
        $indent = $matches[1];
        $inner = sprintf('%s    ', $indent);

        $entry = implode("\n", [
            sprintf('%s{', $inner),
            sprintf("%s    find: '%s',", $inner, $alias),
            sprintf("%s    replacement: fileURLToPath(new URL('%s', import.meta.url)),", $inner, $target),
            sprintf('%s},', $inner),
        ]);

        if (($matches[2] ?? '') !== '') {
            return sprintf("%salias: [\n%s\n%s]", $indent, $entry, $indent);
        }

        return sprintf("%salias: [\n%s", $indent, $entry);
    }

    private function ensureFileUrlImport(string $content): string
    {
        $urlImport = '/import\s*\{([^}]*)\}\s*from\s*([\'"])((?:node:)?url)\2;?/';

        if (preg_match($urlImport, $content, $matches) !== 1) {
            return $this->insertAfterLastImport($content, "import { fileURLToPath } from 'node:url';");
        }

        if (preg_match('/\bfileURLToPath\b/', $matches[1]) === 1) {
            return $content;
        }

        return str_replace(
            $matches[0],
            sprintf('import { fileURLToPath, %s } from %s%s%s;', trim($matches[1]), $matches[2], $matches[3], $matches[2]),
            $content,
        );
    }

    private function insertAfterLastImport(string $content, string $import): string
    {
        if (preg_match_all('/^import\b[\s\S]*?[\'"][^\'"\n]+[\'"];?[ \t]*\n/m', $content, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return sprintf("%s\n%s", $import, $content);
        }

        $last = end($matches[0]);
        $offset = $last[1] + strlen($last[0]);

        return sprintf("%s%s\n%s", substr($content, 0, $offset), $import, substr($content, $offset));
    }
}
