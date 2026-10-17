<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\DocLinks;

/**
 * The fragment anchors a Markdown file exposes on GitHub: one slug per ATX (`## Title`) or setext heading
 * (GitHub / github-slugger rules: lower case, punctuation removed, spaces to `-`, `-1`, `-2` … for repeated
 * headings) plus explicit HTML anchors (`<a id="…">`, `<a name="…">`, any tag's `id="…"`) and heading
 * attributes (`## Title {#custom-id}`, Pandoc / kramdown / MkDocs; the slug is then built from `Title`).
 * Headings and anchors inside fenced code blocks are ignored.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class MarkdownAnchors
{
    private const ATX = '/^ {0,3}#{1,6}(?:[ \t]+(.*))?$/';

    private const SETEXT = '/^ {0,3}(?:=+|-+)[ \t]*$/';

    private const NOT_PARAGRAPH = '/^\s*(?:$|#|>|\||[-*+][ \t]|\d+[.)][ \t]|```|~~~|<)/';

    private const HTML_TAG = '/<[a-z][^>]*>/i';

    private const HTML_ANCHOR = '/\s(?:id|name)\s*=\s*["\']([^"\']+)["\']/i';

    /**
     * @return array<string, true> anchors, lower-cased (GitHub matches fragments case-insensitively)
     */
    public static function fromMarkdown(string $markdown): array
    {
        $anchors = [];
        $counts = [];
        $inFence = false;
        $previous = '';
        foreach (preg_split('/\r\n|\n|\r/', $markdown) ?: [] as $line) {
            if (1 === preg_match('/^\s*(```|~~~)/', $line)) {
                $inFence = !$inFence;
                $previous = '';
                continue;
            }
            if ($inFence) {
                continue;
            }
            $heading = null;
            if (1 === preg_match(self::ATX, $line, $m)) {
                $heading = (string) preg_replace('/(?:^|[ \t]+)#+[ \t]*$/', '', $m[1] ?? '');
            } elseif ('' !== trim($previous) && 1 === preg_match(self::SETEXT, $line) && 1 !== preg_match(self::NOT_PARAGRAPH, $previous)) {
                $heading = $previous;
            }
            if (null !== $heading && 1 === preg_match('/[ \t]*\{#([^}\s]+)\}[ \t]*$/', $heading, $attribute)) {
                // Heading attribute (Pandoc / kramdown / MkDocs attr_list): `## Title {#custom-id}`.
                $anchors[self::lower($attribute[1])] = true;
                $heading = substr($heading, 0, -\strlen($attribute[0]));
            }
            if (null !== $heading) {
                // github-slugger: repeated slugs get -1, -2 … (skipping slugs already taken).
                $slug = self::slug($heading);
                $unique = $slug;
                while (isset($counts[$unique])) {
                    $unique = $slug . '-' . ++$counts[$slug];
                }
                $counts[$unique] = 0;
                $anchors[$unique] = true;
            }
            preg_match_all(self::HTML_TAG, $line, $tags);
            foreach ($tags[0] as $tag) {
                preg_match_all(self::HTML_ANCHOR, $tag, $ids);
                foreach ($ids[1] as $id) {
                    $anchors[self::lower($id)] = true;
                }
            }
            $previous = null !== $heading ? '' : $line;
        }

        return $anchors;
    }

    /**
     * GitHub heading slug of the heading's text (inline Markdown and HTML reduced to their text first).
     */
    public static function slug(string $heading): string
    {
        $text = (string) preg_replace('/!?\[([^\]]*)\]\([^)]*\)/', '$1', $heading);
        $text = (string) preg_replace('/<[^>]+>/', '', $text);
        $text = html_entity_decode(str_replace('`', '', trim($text)), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/[^\p{L}\p{M}\p{N}\p{Pc}\- ]/u', '', self::lower($text));

        return str_replace(' ', '-', $text);
    }

    private static function lower(string $text): string
    {
        return \function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    }
}
