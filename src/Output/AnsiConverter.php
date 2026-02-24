<?php

declare(strict_types=1);

namespace Entreya\Flux\Output;

/**
 * Converts ANSI escape sequences to HTML <span> elements with CSS variables.
 *
 * Supports:
 *  - SGR color codes (30-37, 40-47, 90-97, 100-107)
 *  - Bold (1), italic (3), underline (4), dim (2)
 *  - Reset (0 / ESC[m)
 *  - 256-color foreground (38;5;n) and background (48;5;n)
 *  - Erase in Line K sequences (stripped)
 *  - Carriage return handling (overwrites line)
 */
class AnsiConverter
{
    private const FG_COLORS = [
        30 => 'var(--ansi-black)',       31 => 'var(--ansi-red)',
        32 => 'var(--ansi-green)',       33 => 'var(--ansi-yellow)',
        34 => 'var(--ansi-blue)',        35 => 'var(--ansi-magenta)',
        36 => 'var(--ansi-cyan)',        37 => 'var(--ansi-white)',
        90 => 'var(--ansi-br-black)',    91 => 'var(--ansi-br-red)',
        92 => 'var(--ansi-br-green)',    93 => 'var(--ansi-br-yellow)',
        94 => 'var(--ansi-br-blue)',     95 => 'var(--ansi-br-magenta)',
        96 => 'var(--ansi-br-cyan)',     97 => 'var(--ansi-br-white)',
    ];

    private const BG_COLORS = [
        40 => 'var(--ansi-black)',       41 => 'var(--ansi-red)',
        42 => 'var(--ansi-green)',       43 => 'var(--ansi-yellow)',
        44 => 'var(--ansi-blue)',        45 => 'var(--ansi-magenta)',
        46 => 'var(--ansi-cyan)',        47 => 'var(--ansi-white)',
        100 => 'var(--ansi-br-black)',   101 => 'var(--ansi-br-red)',
        102 => 'var(--ansi-br-green)',   103 => 'var(--ansi-br-yellow)',
        104 => 'var(--ansi-br-blue)',    105 => 'var(--ansi-br-magenta)',
        106 => 'var(--ansi-br-cyan)',    107 => 'var(--ansi-br-white)',
    ];

    // 256-color palette (xterm) — generated on first use
    private static ?array $palette256 = null;

    public function convert(string $text): string
    {
        // Handle carriage returns (progress bars): keep only last sub-line
        if (str_contains($text, "\r")) {
            $text = preg_replace('/[^\r\n]*\r/', '', $text);
        }

        // Extract OSC 8 links before htmlspecialchars modifies the escape codes
        $links = [];
        $text = preg_replace_callback(
            '/\x1b\]8;;(.*?)\x1b\\\\(.*?)\x1b\]8;;\x1b\\\\/s',
            function ($matches) use (&$links) {
                $placeholder = "\x00LINK_" . count($links) . "\x00";
                
                $url  = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
                // The inner text might have CSI color codes, so we just preserve it for now
                // but we HTML escape the plain text parts later.
                // Wait, if we preserve it, htmlspecialchars will escape the CSI codes.
                // It's safer to let htmlspecialchars run, THEN inject the <a> wrappers.
                $links[$placeholder] = [
                    'url'  => $url,
                    'text' => $matches[2]
                ];
                
                return $placeholder;
            },
            $text
        );

        // Escape standard HTML entities
        $text = htmlspecialchars($text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Restore links with proper <a> tags
        if (!empty($links)) {
            $text = strtr($text, array_map(function ($link) {
                // Make sure we escape the inner text of the link too, but leave nested CSI codes alone
                $inner = htmlspecialchars($link['text'], ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
                return "<a href=\"{$link['url']}\" target=\"_blank\" rel=\"noopener noreferrer\" class=\"flux-ansi-link\">{$inner}</a>";
            }, $links));
        }

        // Match all ANSI escape sequences (CSI)
        $pattern = '/\x1b\[([0-9;]*)([A-Za-z])/';

        $segments = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($segments === false || count($segments) === 1) {
            return $text;
        }

        $html    = '';
        $fg      = null;
        $bg      = null;
        $bold    = false;
        $italic  = false;
        $under   = false;
        $dim     = false;
        $openTag = false;

        // segments alternate: [text, params, command, text, params, command, ...]
        for ($i = 0, $c = count($segments); $i < $c; $i++) {
            if ($i % 3 === 0) {
                // Text segment
                if ($segments[$i] !== '') {
                    $html .= $this->buildSpan($segments[$i], $fg, $bg, $bold, $italic, $under, $dim);
                }
            } elseif ($i % 3 === 1) {
                $params  = $segments[$i];
                $command = $segments[$i + 1] ?? '';
                $i++;

                if ($command !== 'm') {
                    continue; // Only handle SGR; ignore cursor moves, erase etc.
                }

                $codes = ($params === '') ? [0] : array_map('intval', explode(';', $params));
                $j     = 0;

                while ($j < count($codes)) {
                    $c2 = $codes[$j];

                    if ($c2 === 0) {
                        $fg = $bg = null;
                        $bold = $italic = $under = $dim = false;
                    } elseif ($c2 === 1)  { $bold   = true; }
                    elseif ($c2 === 2)    { $dim    = true; }
                    elseif ($c2 === 3)    { $italic = true; }
                    elseif ($c2 === 4)    { $under  = true; }
                    elseif ($c2 === 22)   { $bold   = $dim = false; }
                    elseif ($c2 === 23)   { $italic = false; }
                    elseif ($c2 === 24)   { $under  = false; }
                    elseif ($c2 === 39)   { $fg     = null; }
                    elseif ($c2 === 49)   { $bg     = null; }
                    elseif (isset(self::FG_COLORS[$c2])) { $fg = self::FG_COLORS[$c2]; }
                    elseif (isset(self::BG_COLORS[$c2])) { $bg = self::BG_COLORS[$c2]; }
                    elseif ($c2 === 38 && ($codes[$j + 1] ?? null) === 5) {
                        // 256-color foreground: 38;5;n
                        $fg = $this->color256($codes[$j + 2] ?? 0);
                        $j += 2;
                    } elseif ($c2 === 48 && ($codes[$j + 1] ?? null) === 5) {
                        // 256-color background: 48;5;n
                        $bg = $this->color256($codes[$j + 2] ?? 0);
                        $j += 2;
                    } elseif ($c2 === 38 && ($codes[$j + 1] ?? null) === 2) {
                        // True-color foreground: 38;2;r;g;b
                        $r = $codes[$j + 2] ?? 0;
                        $g = $codes[$j + 3] ?? 0;
                        $b = $codes[$j + 4] ?? 0;
                        $fg = "rgb($r,$g,$b)";
                        $j += 4;
                    } elseif ($c2 === 48 && ($codes[$j + 1] ?? null) === 2) {
                        // True-color background: 48;2;r;g;b
                        $r = $codes[$j + 2] ?? 0;
                        $g = $codes[$j + 3] ?? 0;
                        $b = $codes[$j + 4] ?? 0;
                        $bg = "rgb($r,$g,$b)";
                        $j += 4;
                    }

                    $j++;
                }
            }
        }

        return $html;
    }

    private function buildSpan(
        string $text,
        ?string $fg, ?string $bg,
        bool $bold, bool $italic, bool $under, bool $dim
    ): string {
        $css = '';

        if ($fg !== null)  $css .= "color:$fg;";
        if ($bg !== null)  $css .= "background:$bg;";
        if ($bold)         $css .= 'font-weight:bold;';
        if ($italic)       $css .= 'font-style:italic;';
        if ($under)        $css .= 'text-decoration:underline;';
        if ($dim)          $css .= 'opacity:.6;';

        return $css !== ''
            ? "<span style=\"$css\">$text</span>"
            : $text;
    }

    private function color256(int $n): string
    {
        if (self::$palette256 === null) {
            self::$palette256 = $this->buildPalette256();
        }
        return self::$palette256[$n] ?? '#ffffff';
    }

    private function buildPalette256(): array
    {
        $p = [];
        // 16 system colors handled by CSS vars already (0-15)
        for ($i = 0; $i < 16; $i++) {
            $names = ['black','red','green','yellow','blue','magenta','cyan','white',
                      'br-black','br-red','br-green','br-yellow','br-blue','br-magenta','br-cyan','br-white'];
            $p[$i] = "var(--ansi-{$names[$i]})";
        }
        // 216-color cube (16-231)
        for ($i = 16; $i <= 231; $i++) {
            $idx = $i - 16;
            $b   = $idx % 6; $idx = intdiv($idx, 6);
            $g   = $idx % 6; $r   = intdiv($idx, 6);
            $to  = fn(int $v) => $v === 0 ? 0 : 55 + 40 * $v;
            $p[$i] = sprintf('rgb(%d,%d,%d)', $to($r), $to($g), $to($b));
        }
        // Grayscale (232-255)
        for ($i = 232; $i <= 255; $i++) {
            $v     = 8 + 10 * ($i - 232);
            $p[$i] = "rgb($v,$v,$v)";
        }
        return $p;
    }
}
