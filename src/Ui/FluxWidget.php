<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui;

/**
 * Abstract base for all Flux UI widgets.
 *
 * Every widget accepts:
 *   - id       (string)  — the HTML element ID (also registered with FluxAsset)
 *   - options  (array)   — arbitrary HTML attributes (class, style, data-*, etc.)
 *
 * Usage:
 *   echo FluxBadge::widget(['id' => 'myBadge', 'options' => ['class' => 'ms-2']]);
 */
abstract class FluxWidget
{
    protected string $id;
    protected array $options;

    public function __construct(array $config = [])
    {
        $this->id = $config['id'] ?? $this->defaultId();
        $this->options = $config['options'] ?? [];

        // Register selectors with the asset accumulator
        foreach ($this->selectorMap() as $key => $elementId) {
            FluxAsset::register($key, $elementId);
        }
    }

    /**
     * Static factory — matches Yii2 widget() convention.
     */
    public static function widget(array $config = []): string
    {
        $widget = new static($config);
        return $widget->render();
    }

    /**
     * Render the widget HTML.
     */
    abstract public function render(): string;

    /**
     * Return the default ID if none is provided.
     */
    abstract protected function defaultId(): string;

    /**
     * Return the selector key → element ID mappings this widget registers.
     * Override in subclasses.
     *
     * @return array<string, string>
     */
    protected function selectorMap(): array
    {
        return [];
    }

    /**
     * Render an HTML attributes string from the options array.
     * Merges any extra attributes provided.
     */
    protected function renderAttributes(array $extra = []): string
    {
        $attrs = array_merge($this->options, $extra);
        $parts = [];

        foreach ($attrs as $key => $value) {
            if ($value === true) {
                $parts[] = htmlspecialchars($key, ENT_QUOTES);
            } elseif ($value !== false && $value !== null) {
                $parts[] = htmlspecialchars($key, ENT_QUOTES) . '="' . htmlspecialchars((string) $value, ENT_QUOTES) . '"';
            }
        }

        return $parts ? ' ' . implode(' ', $parts) : '';
    }

    /**
     * Merge CSS classes: base classes + user-provided class option.
     */
    protected function mergeClass(string $base): string
    {
        $userClass = $this->options['class'] ?? '';
        unset($this->options['class']);
        return trim($base . ($userClass ? ' ' . $userClass : ''));
    }
}
