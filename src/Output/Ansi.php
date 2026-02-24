<?php

declare(strict_types=1);

namespace Entreya\Flux\Output;

/**
 * Generates ANSI escape sequences for colored console output.
 *
 * The counterpart to AnsiConverter: where AnsiConverter decodes ANSI → HTML
 * for browser display, this class encodes PHP strings → ANSI for terminal output.
 *
 * All color/style methods return a string and are fully composable:
 *
 *   Ansi::green('Done');                         // "\e[32mDone\e[0m"
 *   Ansi::bold(Ansi::red('Error'));              // "\e[1m\e[31mError\e[0m\e[0m"
 *   Ansi::line(Ansi::green('✓ Tests passed'));   // writes to STDOUT with newline
 *
 * Color support is auto-detected. When output is not a TTY, the NO_COLOR env
 * var is set, or the process is not running in CLI, all escape codes are stripped
 * so logs stay clean. You can override this with Ansi::forceColor(true).
 */
final class Ansi
{
    // ── SGR codes ─────────────────────────────────────────────────────────────

    private const RESET     = 0;
    private const BOLD      = 1;
    private const DIM       = 2;
    private const ITALIC    = 3;
    private const UNDERLINE = 4;

    // Foreground colors (matches AnsiConverter::FG_COLORS keys)
    private const FG = [
        'black'      => 30, 'red'        => 31, 'green'   => 32, 'yellow'  => 33,
        'blue'       => 34, 'magenta'    => 35, 'cyan'    => 36, 'white'   => 37,
        'br-black'   => 90, 'br-red'     => 91, 'br-green'=> 92, 'br-yellow'=> 93,
        'br-blue'    => 94, 'br-magenta' => 95, 'br-cyan' => 96, 'br-white'=> 97,
    ];

    // Background colors (matches AnsiConverter::BG_COLORS keys)
    private const BG = [
        'black'      => 40, 'red'        => 41, 'green'   => 42, 'yellow'  => 43,
        'blue'       => 44, 'magenta'    => 45, 'cyan'    => 46, 'white'   => 47,
        'br-black'   => 100, 'br-red'    => 101, 'br-green'=> 102, 'br-yellow'=> 103,
        'br-blue'    => 104, 'br-magenta'=> 105, 'br-cyan' => 106, 'br-white'=> 107,
    ];

    /** Manual override: null = auto-detect, true = always on, false = always off. */
    private static ?bool $forceColor = null;

    private function __construct() {}

    // ── Color support ─────────────────────────────────────────────────────────

    /**
     * Override automatic color detection.
     *
     *   Ansi::forceColor(true);   // always emit ANSI codes
     *   Ansi::forceColor(false);  // always strip codes (plain text)
     *   Ansi::forceColor(null);   // revert to auto-detect (default)
     */
    public static function forceColor(?bool $enabled): void
    {
        self::$forceColor = $enabled;
    }

    /**
     * Returns true when ANSI color output is supported in the current environment.
     *
     * Detection rules (in priority order):
     *   1. Ansi::forceColor() override
     *   2. NO_COLOR env var (https://no-color.org)
     *   3. FORCE_COLOR env var (set by Flux's CommandRunner for child processes)
     *   4. Must be CLI SAPI
     *   5. STDOUT must be a TTY (stream_isatty)
     */
    public static function isColorSupported(): bool
    {
        if (self::$forceColor !== null) {
            return self::$forceColor;
        }

        // Honour the NO_COLOR standard (any value means disabled)
        if (getenv('NO_COLOR') !== false) {
            return false;
        }

        // Flux's CommandRunner sets FORCE_COLOR=1 in child process env
        if (getenv('FORCE_COLOR') === '1') {
            return true;
        }

        // Only emit codes in CLI
        if (PHP_SAPI !== 'cli') {
            return false;
        }

        // Check if STDOUT is a real TTY
        return defined('STDOUT') && function_exists('stream_isatty') && stream_isatty(STDOUT);
    }

    // ── Low-level wrapper ─────────────────────────────────────────────────────

    /**
     * Wrap text in a single SGR code. Resets after the text.
     * If color is not supported, returns the text unchanged.
     */
    public static function sgr(string $text, int ...$codes): string
    {
        if (!self::isColorSupported() || empty($codes)) {
            return $text;
        }

        $open  = "\e[" . implode(';', $codes) . 'm';
        $close = "\e[" . self::RESET . 'm';

        return $open . $text . $close;
    }

    // ── Foreground colors ─────────────────────────────────────────────────────

    public static function black(string $text): string      { return self::sgr($text, self::FG['black']);       }
    public static function red(string $text): string        { return self::sgr($text, self::FG['red']);         }
    public static function green(string $text): string      { return self::sgr($text, self::FG['green']);       }
    public static function yellow(string $text): string     { return self::sgr($text, self::FG['yellow']);      }
    public static function blue(string $text): string       { return self::sgr($text, self::FG['blue']);        }
    public static function magenta(string $text): string    { return self::sgr($text, self::FG['magenta']);     }
    public static function cyan(string $text): string       { return self::sgr($text, self::FG['cyan']);        }
    public static function white(string $text): string      { return self::sgr($text, self::FG['white']);       }

    // Bright variants
    public static function gray(string $text): string       { return self::sgr($text, self::FG['br-black']);    }
    public static function brRed(string $text): string      { return self::sgr($text, self::FG['br-red']);      }
    public static function brGreen(string $text): string    { return self::sgr($text, self::FG['br-green']);    }
    public static function brYellow(string $text): string   { return self::sgr($text, self::FG['br-yellow']);   }
    public static function brBlue(string $text): string     { return self::sgr($text, self::FG['br-blue']);     }
    public static function brMagenta(string $text): string  { return self::sgr($text, self::FG['br-magenta']);  }
    public static function brCyan(string $text): string     { return self::sgr($text, self::FG['br-cyan']);     }
    public static function brWhite(string $text): string    { return self::sgr($text, self::FG['br-white']);    }

    // ── Background colors ─────────────────────────────────────────────────────

    public static function bgBlack(string $text): string    { return self::sgr($text, self::BG['black']);       }
    public static function bgRed(string $text): string      { return self::sgr($text, self::BG['red']);         }
    public static function bgGreen(string $text): string    { return self::sgr($text, self::BG['green']);       }
    public static function bgYellow(string $text): string   { return self::sgr($text, self::BG['yellow']);      }
    public static function bgBlue(string $text): string     { return self::sgr($text, self::BG['blue']);        }
    public static function bgMagenta(string $text): string  { return self::sgr($text, self::BG['magenta']);     }
    public static function bgCyan(string $text): string     { return self::sgr($text, self::BG['cyan']);        }
    public static function bgWhite(string $text): string    { return self::sgr($text, self::BG['white']);       }

    // ── Text styles ───────────────────────────────────────────────────────────

    public static function bold(string $text): string       { return self::sgr($text, self::BOLD);      }
    public static function dim(string $text): string        { return self::sgr($text, self::DIM);       }
    public static function italic(string $text): string     { return self::sgr($text, self::ITALIC);    }
    public static function underline(string $text): string  { return self::sgr($text, self::UNDERLINE); }

    // ── Multi-style shorthand ─────────────────────────────────────────────────

    /**
     * Apply multiple styles in a single escape sequence.
     *
     *   Ansi::format('Deployed', fg: 'green', bold: true)
     *   Ansi::format('FAILED',   fg: 'red',   bg: 'black', bold: true)
     *
     * @param string      $text       The string to style
     * @param string|null $fg         Foreground color name (e.g. 'green', 'br-red')
     * @param string|null $bg         Background color name (e.g. 'black', 'br-blue')
     * @param bool        $bold
     * @param bool        $dim
     * @param bool        $italic
     * @param bool        $underline
     */
    public static function format(
        string  $text,
        ?string $fg        = null,
        ?string $bg        = null,
        bool    $bold      = false,
        bool    $dim       = false,
        bool    $italic    = false,
        bool    $underline = false,
    ): string {
        $codes = [];

        if ($bold)      $codes[] = self::BOLD;
        if ($dim)       $codes[] = self::DIM;
        if ($italic)    $codes[] = self::ITALIC;
        if ($underline) $codes[] = self::UNDERLINE;

        if ($fg !== null) {
            $codes[] = self::FG[$fg] ?? throw new \InvalidArgumentException("Unknown foreground color: '$fg'");
        }

        if ($bg !== null) {
            $codes[] = self::BG[$bg] ?? throw new \InvalidArgumentException("Unknown background color: '$bg'");
        }

        return self::sgr($text, ...$codes);
    }

    // ── Semantic helpers ──────────────────────────────────────────────────────

    /**
     * Render a clickable link in supported terminals (OSC 8).
     *
     *   Ansi::link('https://entreya.com', 'Entreya');
     */
    public static function link(string $url, string $text): string
    {
        if (!self::isColorSupported()) {
            return $text;
        }
        return "\e]8;;{$url}\e\\" . $text . "\e]8;;\e\\";
    }

    /**
     * Convenience aliases for common workflow log patterns.
     * These mirror the visual language of the Flux browser UI.
     */
    public static function success(string $text): string  { return self::bold(self::green('✓ ' . $text));    }
    public static function failure(string $text): string  { return self::bold(self::red('✗ ' . $text));      }
    public static function warning(string $text): string  { return self::bold(self::yellow('⚠ ' . $text));   }
    public static function info(string $text): string     { return self::cyan('ℹ ' . $text);                  }
    public static function muted(string $text): string    { return self::dim(self::gray($text));              }

    // ── Output ────────────────────────────────────────────────────────────────

    /**
     * Write text to STDOUT without a trailing newline.
     */
    public static function write(string $text): void
    {
        fwrite(STDOUT, $text);
    }

    /**
     * Write text to STDOUT followed by a newline.
     */
    public static function line(string $text = ''): void
    {
        fwrite(STDOUT, $text . PHP_EOL);
    }

    /**
     * Write text to STDERR followed by a newline.
     * Useful for error/warning messages that should not pollute STDOUT.
     */
    public static function err(string $text): void
    {
        fwrite(STDERR, $text . PHP_EOL);
    }

    // ── Utilities ─────────────────────────────────────────────────────────────

    /**
     * Strip all ANSI escape sequences from a string.
     *
     * The counterpart to AnsiConverter::convert() — where that method converts
     * ANSI to HTML for browsers, this removes them entirely for plain-text logging.
     *
     *   Ansi::strip("\e[32mHello\e[0m") === 'Hello'
     */
    public static function strip(string $text): string
    {
        return (string) preg_replace('/\x1b\[[0-9;]*[A-Za-z]/', '', $text);
    }

    /**
     * Pad text to a fixed width, ignoring invisible ANSI escape sequences.
     * Useful for aligning columns in tables or status lines.
     *
     *   Ansi::pad(Ansi::green('Done'), 20)   // pads to 20 visible chars
     */
    public static function pad(string $text, int $width, string $align = STR_PAD_RIGHT): string
    {
        $visible = mb_strlen(self::strip($text));
        $padding = max(0, $width - $visible);

        return match ($align) {
            STR_PAD_LEFT  => str_repeat(' ', $padding) . $text,
            STR_PAD_BOTH  => str_repeat(' ', (int) floor($padding / 2)) . $text . str_repeat(' ', (int) ceil($padding / 2)),
            default        => $text . str_repeat(' ', $padding),
        };
    }
}
