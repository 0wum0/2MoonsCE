<?php

declare(strict_types=1);

/**
 * SmartMoons BBCode Parser
 * Unterstützt: Bold, Italic, Underline, Links, Images, Quotes, Code, Lists, Mentions
 */

class BBCode
{
    private Database $db;
    
    public function __construct()
    {
        $this->db = Database::get();
    }
    
    /**
     * Parse BBCode to HTML
     */
    public function parse(string $text, bool $allowHTML = false): string
    {
        if (!$allowHTML) {
            $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }
        
        // Parse in order
        $text = $this->parseMentions($text);
        $text = $this->parseBasicTags($text);
        $text = $this->parseLinks($text);
        $text = $this->parseImages($text);
        $text = $this->parseQuotes($text);
        $text = $this->parseCode($text);
        $text = $this->parseLists($text);
        $text = $this->parseColors($text);
        $text = $this->parseSize($text);
        $text = nl2br($text);
        
        return $text;
    }
    
    /**
     * Parse @mentions
     */
    private function parseMentions(string $text): string
    {
        // Match @Username
        $pattern = '/@([a-zA-Z0-9_-]+)/';
        
        return preg_replace_callback($pattern, function($matches) {
            $username = $matches[1];
            
            // Check if user exists
            $user = $this->db->selectSingle(
                "SELECT id, username FROM %%USERS%% WHERE username = :name LIMIT 1",
                [':name' => $username]
            );
            
            if ($user) {
                return '<a href="?page=profile&id=' . $user['id'] . '" class="forum-mention">@' . htmlspecialchars($username) . '</a>';
            }
            
            return '@' . htmlspecialchars($username);
        }, $text);
    }
    
    /**
     * Parse basic formatting
     */
    private function parseBasicTags(string $text): string
    {
        $tags = [
            '/\[b\](.*?)\[\/b\]/si' => '<strong>$1</strong>',
            '/\[i\](.*?)\[\/i\]/si' => '<em>$1</em>',
            '/\[u\](.*?)\[\/u\]/si' => '<u>$1</u>',
            '/\[s\](.*?)\[\/s\]/si' => '<del>$1</del>',
            '/\[center\](.*?)\[\/center\]/si' => '<div style="text-align:center">$1</div>',
            '/\[right\](.*?)\[\/right\]/si' => '<div style="text-align:right">$1</div>',
        ];
        
        foreach ($tags as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }
        
        return $text;
    }
    
    /**
     * Parse links
     */
    private function parseLinks(string $text): string
    {
        // [url=https://example.com]Link Text[/url]
        $text = preg_replace(
            '/\[url=(.*?)\](.*?)\[\/url\]/si',
            '<a href="$1" target="_blank" rel="noopener noreferrer" class="forum-link">$2</a>',
            $text
        );
        
        // [url]https://example.com[/url]
        $text = preg_replace(
            '/\[url\](.*?)\[\/url\]/si',
            '<a href="$1" target="_blank" rel="noopener noreferrer" class="forum-link">$1</a>',
            $text
        );
        
        return $text;
    }
    
    /**
     * Parse images
     */
    private function parseImages(string $text): string
    {
        // [img]url[/img]
        $text = preg_replace(
            '/\[img\](.*?)\[\/img\]/si',
            '<img src="$1" alt="Image" class="forum-image" loading="lazy">',
            $text
        );
        
        // [img=widthxheight]url[/img]
        $text = preg_replace(
            '/\[img=(\d+)x(\d+)\](.*?)\[\/img\]/si',
            '<img src="$3" width="$1" height="$2" alt="Image" class="forum-image" loading="lazy">',
            $text
        );
        
        return $text;
    }
    
    /**
     * Parse quotes
     */
    private function parseQuotes(string $text): string
    {
        // [quote=Username]text[/quote]
        $text = preg_replace(
            '/\[quote=(.*?)\](.*?)\[\/quote\]/si',
            '<div class="forum-quote"><div class="forum-quote-author">$1 schrieb:</div><div class="forum-quote-content">$2</div></div>',
            $text
        );
        
        // [quote]text[/quote]
        $text = preg_replace(
            '/\[quote\](.*?)\[\/quote\]/si',
            '<div class="forum-quote"><div class="forum-quote-content">$1</div></div>',
            $text
        );
        
        return $text;
    }
    
    /**
     * Parse code blocks
     */
    private function parseCode(string $text): string
    {
        // [code=language]code[/code]
        $text = preg_replace_callback(
            '/\[code=(.*?)\](.*?)\[\/code\]/si',
            function($matches) {
                $lang = htmlspecialchars($matches[1]);
                $code = htmlspecialchars($matches[2]);
                return '<div class="forum-code"><div class="forum-code-header">' . $lang . '</div><pre><code>' . $code . '</code></pre></div>';
            },
            $text
        );
        
        // [code]code[/code]
        $text = preg_replace_callback(
            '/\[code\](.*?)\[\/code\]/si',
            function($matches) {
                $code = htmlspecialchars($matches[1]);
                return '<div class="forum-code"><pre><code>' . $code . '</code></pre></div>';
            },
            $text
        );
        
        return $text;
    }
    
    /**
     * Parse lists
     */
    private function parseLists(string $text): string
    {
        // [list][*]item[/list]
        $text = preg_replace_callback(
            '/\[list\](.*?)\[\/list\]/si',
            function($matches) {
                $content = $matches[1];
                $items = preg_replace('/\[\*\](.*?)(?=\[\*\]|\[\/list\])/si', '<li>$1</li>', $content);
                return '<ul class="forum-list">' . $items . '</ul>';
            },
            $text
        );
        
        // [list=1][*]item[/list] (numbered)
        $text = preg_replace_callback(
            '/\[list=1\](.*?)\[\/list\]/si',
            function($matches) {
                $content = $matches[1];
                $items = preg_replace('/\[\*\](.*?)(?=\[\*\]|\[\/list\])/si', '<li>$1</li>', $content);
                return '<ol class="forum-list">' . $items . '</ol>';
            },
            $text
        );
        
        return $text;
    }
    
    /**
     * Parse colors
     */
    private function parseColors(string $text): string
    {
        // [color=red]text[/color]
        $text = preg_replace(
            '/\[color=(.*?)\](.*?)\[\/color\]/si',
            '<span style="color:$1">$2</span>',
            $text
        );
        
        return $text;
    }
    
    /**
     * Parse font size
     */
    private function parseSize(string $text): string
    {
        // [size=14]text[/size]
        $text = preg_replace(
            '/\[size=(\d+)\](.*?)\[\/size\]/si',
            '<span style="font-size:$1px">$2</span>',
            $text
        );
        
        return $text;
    }
    
    /**
     * Extract mentions from text
     */
    public function extractMentions(string $text): array
    {
        $pattern = '/@([a-zA-Z0-9_-]+)/';
        preg_match_all($pattern, $text, $matches);
        
        $usernames = array_unique($matches[1]);
        $userIds = [];
        
        foreach ($usernames as $username) {
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
    
    /**
     * Get BBCode help text
     */
    public static function getHelpText(): string
    {
        return '[b]Bold[/b] [i]Italic[/i] [u]Underline[/u] [s]Strike[/s]
[url=link]Text[/url] [img]url[/img]
[quote=User]Text[/quote] [code]Code[/code]
[list][*]Item 1[*]Item 2[/list]
[color=red]Text[/color] [size=16]Text[/size]
@Username für Erwähnungen';
    }
}
