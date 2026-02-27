<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui\Toolbar;

use Entreya\Flux\Ui\FluxComponent;
use Entreya\Flux\Ui\FluxRenderer;

/**
 * Toolbar — heading, search, action buttons.
 *
 * Slots: heading, search, btnTimestamps, btnExpand, btnCollapse, btnRerun, btnTheme
 */
class Toolbar extends FluxComponent
{
    protected function defaults(): array
    {
        return [
            'id'    => 'fx-toolbar',
            'class' => 'd-flex align-items-center gap-2 px-3 py-2 border-bottom bg-body-tertiary',
        ];
    }

    protected function slots(): array
    {
        return [
            'heading'       => Heading::class,
            'search'        => SearchInput::class,
            'btnTimestamps' => TimestampButton::class,
            'btnExpand'     => ExpandButton::class,
            'btnCollapse'   => CollapseButton::class,
            'btnRerun'      => RerunButton::class,
            'btnTheme'      => ThemeButton::class,
        ];
    }

    protected function template(): string
    {
        return '<div id="{id}" class="{class}">'
             . '{slot:heading}'
             . '<div class="ms-auto d-flex align-items-center gap-2">'
             .   '{slot:search}'
             .   '<div class="btn-group btn-group-sm">{slot:btnTimestamps}{slot:btnTheme}</div>'
             .   '<div class="btn-group btn-group-sm">{slot:btnExpand}{slot:btnCollapse}</div>'
             .   '{slot:btnRerun}'
             . '</div>'
             . '</div>';
    }
}
