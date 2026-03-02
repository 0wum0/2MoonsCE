<?php

declare(strict_types=1);

/**
 *	SmartMoons / 2Moons Community Edition (2MoonsCE)
 * 
 *	Based on the original 2Moons project:
 *	
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 1.8.0
 * @link https://github.com/jkroepke/2Moons
 *  2Moons 
 *   by Jan-Otto Kröpke 2009-2016
 *
 * Modernization, PHP 8.3/8.4 compatibility, Twig Migration (Smarty removed)
 * Refactoring and feature extensions:
 * @copyright 2024-2026 Florian Engelhardt (0wum0)
 * @link https://github.com/0wum0/2MoonsCE
 * @eMail info.browsergame@gmail.com
 * 
 * Licensed under the MIT License.
 * See LICENSE for details.
 * @visit http://makeit.uno/
 */
class BBCode
{
    private Database $db;

    /** Block-level tags emitted by this parser that must not receive nl2br. */
    private const BLOCK_TAGS = [
        'ul', 'ol', 'li',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'pre', 'div', 'hr',
    ];

    public function __construct()
    {
        $this->db = Database::get();
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Convert BBCode to safe HTML.
     *
     * Pipeline:
     *   1. html_entity_decode  – undo any double-encoding stored in DB
     *   2. htmlspecialchars    – escape all raw HTML from the user
     *   3. extract [code] blocks (protect content from further parsing)
     *   4. parse all other BBCode tags
     *   5. restore [code] blocks
     *   6. selective nl2br     – only on text nodes, not inside block elements
     */
    public function parse(string $text, bool $allowHTML = false): string
    {
        // 1. Normalise entities stored in DB (e.g. &amp; -> &, &quot; -> ")
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 1b. Strip legacy HTML stored in DB by old code (e.g. <span class="admin">,
        //     <br />, <strong>, <span class="mod"> etc.).
        //     Convert <br> variants to newlines so line breaks are preserved,
        //     then strip all remaining HTML tags.
        if (!$allowHTML) {
            $text = preg_replace('/<br\s*\/?>/i', "\n", $text) ?? $text;
            $text = strip_tags($text);
        }

        // 2. Escape all user HTML so no raw tags survive
        if (!$allowHTML) {
            $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        // 3. Extract code blocks before any other parsing
        $codePlaceholders = [];
        $text = $this->extractCodeBlocks($text, $codePlaceholders);

        // 4. Parse remaining BBCode (order matters)
        $text = $this->parseMentions($text);
        $text = $this->parseBasicTags($text);
        $text = $this->parseHr($text);
        $text = $this->parseLinks($text);
        $text = $this->parseImages($text);
        $text = $this->parseQuotes($text);
        $text = $this->parseLists($text);
        $text = $this->parseTables($text);
        $text = $this->parseColors($text);
        $text = $this->parseSize($text);

        // 5. Restore code blocks
        $text = $this->restoreCodeBlocks($text, $codePlaceholders);

        // 6. nl2br only on text outside block elements
        $text = $this->selectiveNl2br($text);

        return $text;
    }

    /**
     * Extract @mention user IDs from raw (un-parsed) text.
     */
    public function extractMentions(string $text): array
    {
        preg_match_all('/@([a-zA-Z0-9_-]+)/', $text, $matches);
        $userIds = [];
        foreach (array_unique($matches[1]) as $username) {
            $user = $this->db->selectSingle(
                "SELECT id FROM %%USERS%% WHERE username = :name LIMIT 1",
                [':name' => $username]
            );
            if ($user) {
                $userIds[] = (int)$user['id'];
            }
        }
        return $userIds;
    }

    public static function getHelpText(): string
    {
        return '[b]Bold[/b] [i]Italic[/i] [u]Underline[/u] [s]Strike[/s]
[url=link]Text[/url] [img]url[/img]
[quote=User]Text[/quote] [code]Code[/code]
[list][*]Item 1[*]Item 2[/list]
[list=1][*]Item 1[*]Item 2[/list]
[table][tr][th]Head[/th][/tr][tr][td]Cell[/td][/tr][/table]
[hr]
[color=red]Text[/color] [size=16]Text[/size]
@Username fuer Erwahnungen';
    }

    // =========================================================================
    // CODE BLOCK EXTRACTION
    // =========================================================================

    /**
     * Replace [code] / [code=lang] blocks with unique placeholders so their
     * content is never touched by any other parser step.
     * Content is already htmlspecialchars-escaped — do NOT escape again.
     */
    private function extractCodeBlocks(string $text, array &$placeholders): string
    {
        $counter = 0;

        // [code=lang]...[/code]
        $text = preg_replace_callback(
            '/\[code=([^\]]*)\](.*?)\[\/code\]/si',
            function (array $m) use (&$placeholders, &$counter): string {
                $lang        = htmlspecialchars(strip_tags($m[1]), ENT_QUOTES, 'UTF-8');
                $content     = $m[2];
                $placeholder = "\x02CODE{$counter}\x03";
                $header      = $lang !== ''
                    ? '<div class="forum-code-header">' . $lang . '</div>'
                    : '';
                $placeholders[$placeholder] =
                    '<div class="forum-code">' . $header
                    . '<pre><code>' . $content . '</code></pre></div>';
                $counter++;
                return $placeholder;
            },
            $text
        ) ?? $text;

        // [code]...[/code]
        $text = preg_replace_callback(
            '/\[code\](.*?)\[\/code\]/si',
            function (array $m) use (&$placeholders, &$counter): string {
                $placeholder = "\x02CODE{$counter}\x03";
                $placeholders[$placeholder] =
                    '<div class="forum-code"><pre><code>' . $m[1] . '</code></pre></div>';
                $counter++;
                return $placeholder;
            },
            $text
        ) ?? $text;

        return $text;
    }

    private function restoreCodeBlocks(string $text, array $placeholders): string
    {
        return str_replace(array_keys($placeholders), array_values($placeholders), $text);
    }

    // =========================================================================
    // INDIVIDUAL TAG PARSERS
    // =========================================================================

    private function parseMentions(string $text): string
    {
        return preg_replace_callback(
            '/@([a-zA-Z0-9_-]+)/',
            function (array $m): string {
                $username = $m[1];
                $user = $this->db->selectSingle(
                    "SELECT id, username FROM %%USERS%% WHERE username = :name LIMIT 1",
                    [':name' => $username]
                );
                if ($user) {
                    return '<a href="game.php?page=profile&amp;id=' . (int)$user['id']
                         . '" class="forum-mention">@'
                         . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '</a>';
                }
                return '@' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
            },
            $text
        ) ?? $text;
    }

    private function parseBasicTags(string $text): string
    {
        $map = [
            '/\[b\](.*?)\[\/b\]/si'           => '<strong>$1</strong>',
            '/\[i\](.*?)\[\/i\]/si'           => '<em>$1</em>',
            '/\[u\](.*?)\[\/u\]/si'           => '<u>$1</u>',
            '/\[s\](.*?)\[\/s\]/si'           => '<del>$1</del>',
            '/\[center\](.*?)\[\/center\]/si' => '<div class="bb-center">$1</div>',
            '/\[right\](.*?)\[\/right\]/si'   => '<div class="bb-right">$1</div>',
        ];
        foreach ($map as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }
        return $text;
    }

    private function parseHr(string $text): string
    {
        return preg_replace('/\[hr\]/i', '<hr class="bb-hr">', $text) ?? $text;
    }

    /**
     * Validate a URL: only allow http/https/ftp and relative paths.
     * Returns '#' if a dangerous scheme is detected.
     */
    private function sanitizeUrl(string $url): string
    {
        $url     = trim($url);
        $decoded = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = preg_replace('/[\x00-\x1F\x7F]/u', '', $decoded) ?? $decoded;
        if (preg_match('/^(javascript|data|vbscript|mhtml)\s*:/i', $decoded)) {
            return '#';
        }
        return $url;
    }

    private function parseLinks(string $text): string
    {
        // [url=href]label[/url]
        $text = preg_replace_callback(
            '/\[url=([^\]]*)\](.*?)\[\/url\]/si',
            function (array $m): string {
                $href = $this->sanitizeUrl($m[1]);
                return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8')
                     . '" target="_blank" rel="noopener noreferrer" class="forum-link">'
                     . $m[2] . '</a>';
            },
            $text
        ) ?? $text;

        // [url]href[/url]
        $text = preg_replace_callback(
            '/\[url\](.*?)\[\/url\]/si',
            function (array $m): string {
                $href = $this->sanitizeUrl($m[1]);
                $safe = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
                return '<a href="' . $safe
                     . '" target="_blank" rel="noopener noreferrer" class="forum-link">'
                     . $safe . '</a>';
            },
            $text
        ) ?? $text;

        return $text;
    }

    private function parseImages(string $text): string
    {
        // [img=WxH]url[/img]
        $text = preg_replace_callback(
            '/\[img=(\d+)x(\d+)\](.*?)\[\/img\]/si',
            function (array $m): string {
                $src = $this->sanitizeUrl($m[3]);
                return '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"'
                     . ' width="' . (int)$m[1] . '" height="' . (int)$m[2] . '"'
                     . ' alt="" class="forum-image" loading="lazy">';
            },
            $text
        ) ?? $text;

        // [img]url[/img]
        $text = preg_replace_callback(
            '/\[img\](.*?)\[\/img\]/si',
            function (array $m): string {
                $src = $this->sanitizeUrl($m[1]);
                return '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"'
                     . ' alt="" class="forum-image" loading="lazy">';
            },
            $text
        ) ?? $text;

        return $text;
    }

    private function parseQuotes(string $text): string
    {
        // Up to 3 nesting levels
        for ($i = 0; $i < 3; $i++) {
            $new = preg_replace(
                '/\[quote=([^\]]*)\](.*?)\[\/quote\]/si',
                '<div class="forum-quote">'
                . '<div class="forum-quote-author">$1 schrieb:</div>'
                . '<div class="forum-quote-content">$2</div></div>',
                $text
            ) ?? $text;
            $new = preg_replace(
                '/\[quote\](.*?)\[\/quote\]/si',
                '<div class="forum-quote">'
                . '<div class="forum-quote-content">$1</div></div>',
                $new
            ) ?? $new;
            if ($new === $text) {
                break;
            }
            $text = $new;
        }
        return $text;
    }

    /**
     * Parse [list], [list=1], [list=a] with [*] items.
     *
     * Uses preg_split on [*] so the LAST item (before [/list]) is always
     * captured — the old lookahead regex missed it.
     * Stray [*] outside any list are stripped.
     */
    private function parseLists(string $text): string
    {
        $callback = function (array $m): string {
            $type    = strtolower(trim((string)($m[1] ?? '')));
            $content = $m[2];

            $parts = preg_split('/\[\*\]/i', $content) ?: [];
            $items = '';
            foreach ($parts as $idx => $part) {
                $part = trim($part);
                if ($idx === 0 && $part === '') {
                    continue;
                }
                if ($part === '') {
                    continue;
                }
                $items .= '<li>' . $part . '</li>';
            }

            if ($type === '1') {
                return '<ol class="bb-list">' . $items . '</ol>';
            }
            if ($type === 'a') {
                return '<ol class="bb-list" style="list-style-type:lower-alpha">'
                     . $items . '</ol>';
            }
            return '<ul class="bb-list">' . $items . '</ul>';
        };

        $text = preg_replace_callback(
            '/\[list(?:=([^\]]*))?\](.*?)\[\/list\]/si',
            $callback,
            $text
        ) ?? $text;

        // Strip stray [*] outside any list
        $text = preg_replace('/\[\*\]/i', '', $text) ?? $text;

        return $text;
    }

    /**
     * Parse [table][thead][tbody][tr][th][td] into safe HTML.
     */
    private function parseTables(string $text): string
    {
        $text = preg_replace('/\[th\](.*?)\[\/th\]/si', '<th class="bb-th">$1</th>', $text) ?? $text;
        $text = preg_replace('/\[td\](.*?)\[\/td\]/si', '<td class="bb-td">$1</td>', $text) ?? $text;
        $text = preg_replace('/\[tr\](.*?)\[\/tr\]/si', '<tr>$1</tr>',               $text) ?? $text;
        $text = preg_replace('/\[thead\](.*?)\[\/thead\]/si', '<thead>$1</thead>',   $text) ?? $text;
        $text = preg_replace('/\[tbody\](.*?)\[\/tbody\]/si', '<tbody>$1</tbody>',   $text) ?? $text;
        $text = preg_replace(
            '/\[table\](.*?)\[\/table\]/si',
            '<table class="bb-table">$1</table>',
            $text
        ) ?? $text;
        return $text;
    }

    private function parseColors(string $text): string
    {
        return preg_replace_callback(
            '/\[color=([^\]]*)\](.*?)\[\/color\]/si',
            function (array $m): string {
                $color = trim($m[1]);
                // Allow only safe CSS color values
                if (!preg_match(
                    '/^(#[0-9a-fA-F]{3,8}|[a-zA-Z]{1,30}|rgb\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*\))$/',
                    $color
                )) {
                    return $m[2];
                }
                return '<span style="color:'
                     . htmlspecialchars($color, ENT_QUOTES, 'UTF-8') . '">'
                     . $m[2] . '</span>';
            },
            $text
        ) ?? $text;
    }

    private function parseSize(string $text): string
    {
        return preg_replace_callback(
            '/\[size=(\d+)\](.*?)\[\/size\]/si',
            function (array $m): string {
                $px = max(8, min(72, (int)$m[1]));
                return '<span style="font-size:' . $px . 'px">' . $m[2] . '</span>';
            },
            $text
        ) ?? $text;
    }

    // =========================================================================
    // SELECTIVE NL2BR
    // =========================================================================

    /**
     * Apply nl2br only to text segments that are NOT inside block-level HTML
     * elements emitted by this parser (lists, tables, code, quotes, hr, divs).
     *
     * Splits the string on block-tag boundaries, tracks nesting depth, and
     * only converts newlines in segments at depth 0.
     */
    private function selectiveNl2br(string $text): string
    {
        $blockPattern = implode('|', array_map(
            static fn(string $t): string => preg_quote($t, '/'),
            self::BLOCK_TAGS
        ));

        $tokens = preg_split(
            '/(<\/?' . $blockPattern . '(?:\s[^>]*)?>)/i',
            $text,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        if ($tokens === false) {
            return nl2br($text, false);
        }

        $depth  = 0;
        $result = '';

        foreach ($tokens as $token) {
            if (preg_match('/^<(' . $blockPattern . ')(?:\s[^>]*)?>$/i', $token)) {
                $depth++;
                $result .= $token;
            } elseif (preg_match('/^<\/(' . $blockPattern . ')>$/i', $token)) {
                if ($depth > 0) {
                    $depth--;
                }
                $result .= $token;
            } else {
                // Text / inline HTML segment
                $result .= ($depth === 0) ? nl2br($token, false) : $token;
            }
        }

        return $result;
    }
}
