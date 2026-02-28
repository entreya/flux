<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui\Toolbar;

use Entreya\Flux\Ui\FluxComponent;
use Entreya\Flux\Ui\FluxRenderer;

class SearchInput extends FluxComponent
{
    protected function defaults(): array
    {
        return [
            'id'          => 'fx-toolbar-search',
            'class'       => 'form-control form-control-sm',
            'placeholder' => 'Search logs...',
        ];
    }

    protected function template(): string
    {
        return '<input type="text" id="{id}" class="{class}" placeholder="{placeholder}" style="width:200px">';
    }

    protected function script(): string
    {
        return '// Search input {id} ready';
    }

    protected function registerSelectors(): void
    {
        FluxRenderer::registerSelector('search', (string) $this->props['id']);
    }
}
