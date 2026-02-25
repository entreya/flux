<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui;

/**
 * Static registry that accumulates widget selectors and renders the
 * final <script> tag that boots FluxUI with the correct selector map.
 *
 * Usage:
 *   // Each widget auto-registers during render:
 *   FluxAsset::register('badge', 'myBadge');
 *   FluxAsset::register('steps', 'mySteps');
 *
 *   // At the end of the page:
 *   echo FluxAsset::init(['sseUrl' => '/sse.php?workflow=deploy']);
 */
final class FluxAsset
{
    /** @var array<string, string> accumulated selector map */
    private static array $selectors = [];

    /** @var string base path to Flux public assets (CSS/JS) */
    private static string $assetPath = '';

    /**
     * Register a selector key → element ID mapping.
     */
    public static function register(string $key, string $id): void
    {
        self::$selectors[$key] = $id;
    }

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
     * with the accumulated selector map and user-provided config.
     *
     * @param array $config  Keys: sseUrl, uploadUrl, theme, ...
     */
    public static function init(array $config = []): string
    {
        $config['sel'] = array_merge($config['sel'] ?? [], self::$selectors);

        $json = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return "<script>FluxUI.init({$json});</script>";
    }

    /**
     * Reset the registry (useful for testing or multi-render scenarios).
     */
    public static function reset(): void
    {
        self::$selectors = [];
        self::$assetPath = '';
    }
}
