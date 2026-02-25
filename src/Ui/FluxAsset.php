<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui;

/**
 * Static registry that accumulates widget configuration and renders the
 * final <script> tag that boots FluxUI with selectors, templates,
 * plugin options, and event hooks.
 *
 * Each widget auto-registers during its render() call.
 * At the end of the page, call FluxAsset::init() to emit the <script>.
 *
 * Usage (in your view):
 *   <?= FluxBadge::widget([...]) ?>
 *   <?= FluxToolbar::widget([...]) ?>
 *   <?= FluxLogPanel::widget([...]) ?>
 *   ...
 *   <?= FluxAsset::init(['sseUrl' => '/sse.php?workflow=deploy']) ?>
 */
final class FluxAsset
{
    /** @var array<string, string> element ID selectors */
    private static array $selectors = [];

    /** @var array<string, array> namespaced plugin options */
    private static array $pluginOptions = [];

    /** @var array<string, string> JS event hooks: event name → JS function body */
    private static array $pluginEvents = [];

    /** @var array<string, string> JS template strings: key → HTML template */
    private static array $templates = [];

    /** @var string base path to Flux public assets (CSS/JS) */
    private static string $assetPath = '';

    // ── Registration API ────────────────────────────────────────────────────

    /**
     * Register a selector key → element ID mapping.
     */
    public static function register(string $key, string $id): void
    {
        self::$selectors[$key] = $id;
    }

    /**
     * Register plugin options under a namespace.
     * e.g. registerPluginOptions('logPanel', ['useAccordion' => true])
     */
    public static function registerPluginOptions(string $namespace, array $options): void
    {
        self::$pluginOptions[$namespace] = array_merge(
            self::$pluginOptions[$namespace] ?? [],
            $options
        );
    }

    /**
     * Register a JS event hook.
     * The handler should be a raw JS function body string.
     * e.g. registerEvent('workflow_complete', 'function() { location.reload(); }')
     */
    public static function registerEvent(string $event, string $jsHandler): void
    {
        self::$pluginEvents[$event] = $jsHandler;
    }

    /**
     * Register a JS HTML template string.
     * e.g. registerTemplate('step', '<div class="accordion-item">...</div>')
     */
    public static function registerTemplate(string $key, string $htmlTemplate): void
    {
        self::$templates[$key] = $htmlTemplate;
    }

    // ── Asset Rendering ─────────────────────────────────────────────────────

    /**
     * Set the base path for Flux CSS/JS assets.
     * e.g. '/vendor/entreya/flux/public/assets'
     */
    public static function setAssetPath(string $path): void
    {
        self::$assetPath = rtrim($path, '/');
    }

    /**
     * Render the CSS <link> tag.
     */
    public static function css(): string
    {
        $path = self::$assetPath ? self::$assetPath . '/css/flux.css' : 'assets/css/flux.css';
        return '<link rel="stylesheet" href="' . htmlspecialchars($path, ENT_QUOTES) . '">';
    }

    /**
     * Render the JS <script> tag (just the file, not the init call).
     */
    public static function js(): string
    {
        $path = self::$assetPath ? self::$assetPath . '/js/flux.js' : 'assets/js/flux.js';
        return '<script src="' . htmlspecialchars($path, ENT_QUOTES) . '"></script>';
    }

    /**
     * Render the final <script> block that initializes FluxUI
     * with all accumulated configuration.
     *
     * @param array $config  Base config keys: sseUrl, uploadUrl, ...
     */
    public static function init(array $config = []): string
    {
        // Merge selectors
        $config['sel'] = array_merge($config['sel'] ?? [], self::$selectors);

        // Merge plugin options
        if (!empty(self::$pluginOptions)) {
            $config['plugins'] = array_merge($config['plugins'] ?? [], self::$pluginOptions);
        }

        // Merge templates
        if (!empty(self::$templates)) {
            $config['templates'] = array_merge($config['templates'] ?? [], self::$templates);
        }

        // JSON-encode the safe parts (without event handlers)
        $json = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        // Event handlers must be injected as raw JS (not JSON strings)
        if (!empty(self::$pluginEvents)) {
            $eventParts = [];
            foreach (self::$pluginEvents as $event => $handler) {
                $eventParts[] = '    ' . json_encode($event) . ': ' . $handler;
            }
            $eventsJs = "{\n" . implode(",\n", $eventParts) . "\n  }";

            // Inject events object into the config JSON
            // Remove the closing } and append events
            $json = rtrim($json) . ",\n  \"events\": " . $eventsJs . "\n}";
        }

        return "<script>FluxUI.init({$json});</script>";
    }

    /**
     * Reset the registry (useful for testing or multi-render scenarios).
     */
    public static function reset(): void
    {
        self::$selectors = [];
        self::$pluginOptions = [];
        self::$pluginEvents = [];
        self::$templates = [];
        self::$assetPath = '';
    }
}
