<?php

declare(strict_types=1);

namespace Entreya\Flux\Theme;

class ThemeManager
{
    private array $themes = [];
    private string $defaultTheme = 'dark';
    
    // GitHub Themes
    private const BUILTIN_THEMES = [
        'dark' => [
            'name' => 'GitHub Dark',
            'bg' => '#0d1117',
            'sidebar' => '#161b22',
            'text' => '#c9d1d9',
            'text-muted' => '#8b949e',
            'border' => '#30363d',
            'success' => '#3fb950',
            'error' => '#f85149',
            'warning' => '#d29922',
            'info' => '#58a6ff',
            'code-bg' => '#0d1117',
            'code-text' => '#c9d1d9',
            'header-bg' => '#161b22',
            'log-timestamp' => '#6e7681',
            // ANSI Colors
            'ansi-black' => '#484f58',
            'ansi-red' => '#ff7b72',
            'ansi-green' => '#3fb950',
            'ansi-yellow' => '#d29922',
            'ansi-blue' => '#58a6ff',
            'ansi-magenta' => '#bc8cff',
            'ansi-cyan' => '#39c5cf',
            'ansi-white' => '#b1bac4',
        ],
        'light' => [
            'name' => 'GitHub Light',
            'bg' => '#ffffff',
            'sidebar' => '#f6f8fa',
            'text' => '#24292f',
            'text-muted' => '#57606a',
            'border' => '#d0d7de',
            'success' => '#1a7f37',
            'error' => '#cf222e',
            'warning' => '#9a6700',
            'info' => '#0969da',
            'code-bg' => '#ffffff',
            'code-text' => '#24292f',
            'header-bg' => '#f6f8fa',
            'log-timestamp' => '#6e7681',
            // ANSI Colors
            'ansi-black' => '#24292f',
            'ansi-red' => '#cf222e',
            'ansi-green' => '#1a7f37',
            'ansi-yellow' => '#9a6700',
            'ansi-blue' => '#0969da',
            'ansi-magenta' => '#8250df',
            'ansi-cyan' => '#1b7c83',
            'ansi-white' => '#6e7781',
            // Bright variants
            'ansi-bright-black' => '#57606a',
            'ansi-bright-red' => '#a40e26',
            'ansi-bright-green' => '#116329',
            'ansi-bright-yellow' => '#4d2d00',
            'ansi-bright-blue' => '#0550ae',
            'ansi-bright-magenta' => '#6639ba',
            'ansi-bright-cyan' => '#0550ae',
            'ansi-bright-white' => '#8c959f',
        ],
        'high-contrast' => [
            'name' => 'Dark High Contrast',
            'bg' => '#0a0c10',
            'sidebar' => '#0a0c10',
            'text' => '#f0f6fc',
            'text-muted' => '#9198a1',
            'border' => '#7a828e',
            'success' => '#26a641',
            'error' => '#ff6a69',
            'warning' => '#d4a72c',
            'info' => '#409eff',
            'code-bg' => '#0a0c10',
            'code-text' => '#f0f6fc',
            'header-bg' => '#010409',
            'log-timestamp' => '#9198a1',
             // ANSI Colors
            'ansi-black' => '#f0f6fc', // Inverted for contrast
            'ansi-red' => '#ff6a69',
            'ansi-green' => '#26a641',
            'ansi-yellow' => '#d4a72c',
            'ansi-blue' => '#409eff',
            'ansi-magenta' => '#d2a8ff',
            'ansi-cyan' => '#39c5cf',
            'ansi-white' => '#f0f6fc',
        ]
    ];

    public function __construct(string $customThemesDir = null)
    {
        $this->themes = self::BUILTIN_THEMES;
        if ($customThemesDir && is_dir($customThemesDir)) {
            $this->loadCustomThemes($customThemesDir);
        }
    }

    private function loadCustomThemes(string $dir): void
    {
        $files = glob("$dir/*.json");
        foreach ($files as $file) {
            $name = basename($file, '.json');
            $content = file_get_contents($file);
            $data = json_decode($content, true);
            if (is_array($data)) {
                $this->themes[$name] = array_merge($this->themes['dark'], $data);
            }
        }
    }

    public function getTheme(string $name): array
    {
        return $this->themes[$name] ?? $this->themes[$this->defaultTheme];
    }
    
    public function getCssVariables(string $name): string
    {
        $theme = $this->getTheme($name);
        $css = ":root {\n";
        foreach ($theme as $key => $val) {
            if ($key === 'name') continue;
            $css .= "  --flux-$key: $val;\n";
        }
        $css .= "}\n";
        return $css;
    }
}
