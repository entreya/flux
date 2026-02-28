<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui;

/**
 * Static asset collector for Flux components.
 *
 * Accumulates CSS, JS, selectors, templates, and events from all rendered
 * components. Outputs one <style> + one <script> block via flush().
 *
 * CSS is deduplicated by key (component class).
 * JS is per-instance (each component instance gets its own init code).
 * Selectors are accumulated for FluxUI.init().
 */
class FluxRenderer
{
    /** @var array<string, string> CSS blocks, keyed by component class */
    private static array $styles = [];

    /** @var array<string, string> JS blocks, keyed by instance */
    private static array $scripts = [];

    /** @var array<string, string> Element selectors: key → DOM id */
    private static array $selectors = [];

    /** @var array<string, string> JS templates (step, jobHeader, etc.) */
    private static array $templates = [];

    /** @var array<string, mixed> Plugin options */
    private static array $pluginOptions = [];

    /** @var array<string, string> Event handlers */
    private static array $events = [];

    /** @var string Base path for flux.css / flux.js */
    private static string $assetPath = '';

    // ── Registration ────────────────────────────────────────────────────────

    /**
     * Register CSS. Deduplicated by $key.
     * Use the component FQCN as key for class-level dedup.
     */
    public static function registerStyle(string $key, string $css): void
    {
        // Only register if not already present for this key
        if (!isset(self::$styles[$key])) {
            self::$styles[$key] = $css;
        }
    }

    /**
     * Register JS. Per-instance (not deduplicated).
     */
    public static function registerScript(string $key, string $js): void
    {
        self::$scripts[$key] = $js;
    }

    /**
     * Register a selector key → DOM element ID.
     */
    public static function registerSelector(string $key, string $elementId): void
    {
        self::$selectors[$key] = $elementId;
    }

    /**
     * Register a JS template (e.g., step accordion HTML).
     */
    public static function registerTemplate(string $name, string $html): void
    {
        self::$templates[$name] = $html;
    }

    /**
     * Register plugin options under a namespace.
     */
    public static function registerPluginOptions(string $namespace, array $options): void
    {
        self::$pluginOptions[$namespace] = array_merge(
            self::$pluginOptions[$namespace] ?? [],
            $options
        );
    }

    /**
     * Register a JS event handler.
     */
    public static function registerEvent(string $event, string $handler): void
    {
        self::$events[$event] = $handler;
    }

    /**
     * Set the base path for flux.css / flux.js.
     */
    public static function setAssetPath(string $path): void
    {
        self::$assetPath = rtrim($path, '/');
    }

    // ── Output ──────────────────────────────────────────────────────────────

    /**
     * Render <link> tag for flux.css.
     */
    public static function css(): string
    {
        $path = self::$assetPath !== '' ? self::$assetPath . '/css/flux.css' : 'flux.css';
        return '<link rel="stylesheet" href="' . htmlspecialchars($path, ENT_QUOTES) . '">';
    }

    /**
     * Render <script> tag for flux.js.
     */
    public static function js(): string
    {
        $path = self::$assetPath !== '' ? self::$assetPath . '/js/flux.js' : 'flux.js';
        return '<script src="' . htmlspecialchars($path, ENT_QUOTES) . '"></script>';
    }

    /**
     * Render collected <style> block.
     * Contains all CSS from rendered components — nothing from un-rendered ones.
     */
    public static function styles(): string
    {
        if (empty(self::$styles)) {
            return '';
        }

        $css = implode("\n", self::$styles);
        return '<style>' . $css . '</style>';
    }

    /**
     * Render the final <script> block: component scripts + FluxUI.init().
     *
     * This is the JS "bundler" — only scripts from actually rendered
     * components appear. Un-rendered components contribute nothing.
     *
     * @param array<string, mixed> $initConfig Config passed to FluxUI.init() (sseUrl, etc.)
     */
    public static function init(array $initConfig = []): string
    {
        $cfg = $initConfig;

        // Merge selectors (flux.js reads cfg.sel)
        if (self::$selectors !== []) {
            $cfg['sel'] = self::$selectors;
        }

        // Merge templates
        if (self::$templates !== []) {
            $cfg['templates'] = self::$templates;
        }

        // Merge plugin options (flux.js reads cfg.plugins.*)
        if (self::$pluginOptions !== []) {
            $cfg['plugins'] = self::$pluginOptions;
        }

        // Merge events
        $eventObj = '';
        if (self::$events !== []) {
            $pairs = [];
            foreach (self::$events as $event => $handler) {
                $pairs[] = json_encode($event, JSON_THROW_ON_ERROR) . ':' . $handler;
            }
            $eventObj = '{' . implode(',', $pairs) . '}';
        }

        // Build script
        $output = '<script>';
        $output .= 'document.addEventListener("DOMContentLoaded",function(){';

        // Component instance scripts
        foreach (self::$scripts as $js) {
            $output .= $js . "\n";
        }

        // FluxUI.init()
        if ($cfg !== [] || self::$events !== []) {
            $jsonCfg = json_encode($cfg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            // Inject events object into config (it contains raw JS functions, can't be JSON)
            if (self::$events !== []) {
                $jsonCfg = rtrim($jsonCfg, '}');
                if ($jsonCfg !== '{') {
                    $jsonCfg .= ',';
                }
                $jsonCfg .= '"events":' . $eventObj . '}';
            }

            $output .= 'FluxUI.init(' . $jsonCfg . ');';
        }

        $output .= '});';
        $output .= '</script>';

        return $output;
    }

    /**
     * Convenience: render styles() + js() + init() in one call.
     *
     * @param array<string, mixed> $initConfig
     */
    public static function flush(array $initConfig = []): string
    {
        return self::styles() . "\n" . self::js() . "\n" . self::init($initConfig);
    }

    // ── State Management ────────────────────────────────────────────────────

    /**
     * Clear all accumulated state. Use in tests or multi-render pages.
     */
    public static function reset(): void
    {
        self::$styles        = [];
        self::$scripts       = [];
        self::$selectors     = [];
        self::$templates     = [];
        self::$pluginOptions = [];
        self::$events        = [];
    }

    // ── Getters (for framework integration / testing) ───────────────────────

    /** @return array<string, string> */
    public static function getStyles(): array
    {
        return self::$styles;
    }

    /** @return array<string, string> */
    public static function getScripts(): array
    {
        return self::$scripts;
    }

    /** @return array<string, string> */
    public static function getSelectors(): array
    {
        return self::$selectors;
    }

    /** @return array<string, string> */
    public static function getTemplates(): array
    {
        return self::$templates;
    }

    /** @return array<string, mixed> */
    public static function getPluginOptions(): array
    {
        return self::$pluginOptions;
    }

    /** @return array<string, string> */
    public static function getEvents(): array
    {
        return self::$events;
    }
}
