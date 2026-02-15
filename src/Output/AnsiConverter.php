<?php

declare(strict_types=1);

namespace Entreya\Flux\Output;

class AnsiConverter
{
    private const COLORS = [
        // Text Colors
        30 => 'color: var(--flux-ansi-black);',
        31 => 'color: var(--flux-ansi-red);',
        32 => 'color: var(--flux-ansi-green);',
        33 => 'color: var(--flux-ansi-yellow);',
        34 => 'color: var(--flux-ansi-blue);',
        35 => 'color: var(--flux-ansi-magenta);',
        36 => 'color: var(--flux-ansi-cyan);',
        37 => 'color: var(--flux-ansi-white);',
        90 => 'color: var(--flux-ansi-bright-black);',
        91 => 'color: var(--flux-ansi-bright-red);',
        92 => 'color: var(--flux-ansi-bright-green);',
        93 => 'color: var(--flux-ansi-bright-yellow);',
        94 => 'color: var(--flux-ansi-bright-blue);',
        95 => 'color: var(--flux-ansi-bright-magenta);',
        96 => 'color: var(--flux-ansi-bright-cyan);',
        97 => 'color: var(--flux-ansi-bright-white);',
        
        // Background Colors
        40 => 'background-color: var(--flux-ansi-black);',
        41 => 'background-color: var(--flux-ansi-red);',
        42 => 'background-color: var(--flux-ansi-green);',
        43 => 'background-color: var(--flux-ansi-yellow);',
        44 => 'background-color: var(--flux-ansi-blue);',
        45 => 'background-color: var(--flux-ansi-magenta);',
        46 => 'background-color: var(--flux-ansi-cyan);',
        47 => 'background-color: var(--flux-ansi-white);',
    ];

    private const STYLES = [
        1 => 'font-weight: bold;',
        3 => 'font-style: italic;',
        4 => 'text-decoration: underline;',
    ];

    /**
     * Convert ANSI string to HTML
     */
    public function convert(string $text): string
    {
        $text = htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');

        // Pattern to match ANSI escape codes: \x1b, \033, or \e followed by [... and a letter
        // Supports m (SGR) and K (Erase in Line)
        $pattern = '/(?:\x1b|\\\\033|\\\\e)\[([0-9;]*?)([a-zA-Z])/';

        preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);
        
        if (count($matches[0]) === 0) {
            return $text;
        }
        
        $currentStyles = [];
        $lastOffset = 0;
        $html = '';
        
        foreach ($matches[0] as $i => $fullMatch) {
            $matchStr = $fullMatch[0];
            $matchOffset = $fullMatch[1];
            $paramsStr = $matches[1][$i][0]; // Parameters (e.g. "31;1")
            $command = $matches[2][$i][0];   // Command (e.g. "m", "K")
            
            // Append text before this code
            $segment = substr($text, $lastOffset, $matchOffset - $lastOffset);
            if ($segment !== '') {
                $html .= $this->wrap($segment, $currentStyles);
            }
            
            // Handle Command
            if ($command === 'm') {
                // SGR - Select Graphic Rendition
                $codes = $paramsStr === '' ? [] : explode(';', $paramsStr);
                if (empty($codes)) {
                    $currentStyles = []; // Reset
                } else {
                    foreach ($codes as $code) {
                        $c = (int)$code;
                        if ($c === 0) {
                            $currentStyles = [];
                        } elseif (isset(self::COLORS[$c]) || isset(self::STYLES[$c])) {
                           if (($c >= 30 && $c <= 37) || ($c >= 90 && $c <= 97)) {
                                $currentStyles = array_filter($currentStyles, fn($k) => !($k >= 30 && $k <= 37) && !($k >= 90 && $k <= 97), ARRAY_FILTER_USE_KEY);
                           }
                           if ($c >= 40 && $c <= 47) {
                                $currentStyles = array_filter($currentStyles, fn($k) => !($k >= 40 && $k <= 47), ARRAY_FILTER_USE_KEY);
                           }
                           $currentStyles[$c] = true;
                        }
                    }
                }
            } elseif ($command === 'K') {
                // Erase in Line - Ignore for now (strip)
                // TODO: Implement graphical line clearing if needed
            }
            
            $lastOffset = $matchOffset + strlen($matchStr);
        }
        
        // Remaining text
        $remaining = substr($text, $lastOffset);
        if ($remaining !== '') {
            $html .= $this->wrap($remaining, $currentStyles);
        }
        
        return $html;
    }
    
    private function wrap(string $text, array $styleCodes): string
    {
        if (empty($styleCodes)) {
            return $text;
        }
        
        $css = [];
        foreach (array_keys($styleCodes) as $code) {
             if (isset(self::COLORS[$code])) {
                 $css[] = self::COLORS[$code];
             }
             if (isset(self::STYLES[$code])) {
                 $css[] = self::STYLES[$code];
             }
        }
        
        if (empty($css)) {
            return $text;
        }
        
        return sprintf('<span style="%s">%s</span>', implode(' ', $css), $text);
    }
}
