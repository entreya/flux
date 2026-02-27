<?php

declare(strict_types=1);

namespace Entreya\Flux\Ui\Toolbar;

use Entreya\Flux\Ui\FluxComponent;

/**
 * Toolbar component — search, timestamps, expand/collapse, rerun, theme toggle.
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
        return <<<'HTML'
        <div id="{id}" class="{class}">
            {slot:heading}
            <div class="d-flex align-items-center gap-1 flex-shrink-0">
                {slot:search}
                {slot:btnTimestamps}
                {slot:btnExpand}
                {slot:btnCollapse}
                {slot:btnRerun}
                {slot:btnTheme}
            </div>
        </div>
        HTML;
    }
}
