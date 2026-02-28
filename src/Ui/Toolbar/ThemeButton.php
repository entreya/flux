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
            'id'    => 'fx-toolbar-theme-btn',
            'class' => 'btn btn-outline-secondary btn-sm',
            'title' => 'Toggle Dark Mode',
            'icon'  => 'bi bi-moon-stars',
        ];
    }

    protected function template(): string
    {
        return '<button type="button" id="{id}" class="{class}" title="{title}"><i class="{icon}"></i></button>';
    }

    protected function registerSelectors(): void
    {
        FluxRenderer::registerSelector('themeBtn', (string) $this->props['id']);
    }
}
