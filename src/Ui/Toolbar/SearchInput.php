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
            'icon'        => 'bi bi-search',
        ];
    }

    protected function template(): string
    {
        return '<div class="position-relative" style="width:200px">'
             . '<input type="text" id="{id}" class="{class}" placeholder="{placeholder}">'
             . '<i class="{icon} position-absolute text-muted" style="right:10px;top:50%;transform:translateY(-50%);font-size:12px"></i>'
             . '</div>';
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
