<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui\Toolbar;

use Entreya\Flux\Ui\FluxComponent;
use Entreya\Flux\Ui\FluxRenderer;

class ThemeButton extends FluxComponent
{
    protected function defaults(): array
    {
        return [
            'id'      => 'fx-toolbar-theme-btn',
            'class'   => 'btn btn-outline-secondary btn-sm',
            'icon_id' => 'fx-toolbar-theme-icon',
        ];
    }

    protected function template(): string
    {
        return '<button id="{id}" class="{class}" onclick="FluxUI.toggleTheme()" title="Toggle theme"><i id="{icon_id}" class="bi bi-moon-stars"></i></button>';
    }

    protected function registerSelectors(): void
    {
        FluxRenderer::registerSelector('themeIcon', $this->props['icon_id']);
    }
}
