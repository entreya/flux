<?php

declare(strict_types=1);

namespace Entreya\Flux\Tests\Ui;

use Entreya\Flux\Ui\FluxComponent;
use Entreya\Flux\Ui\FluxRenderer;
use Entreya\Flux\Ui\Toolbar\Toolbar;
use Entreya\Flux\Ui\Toolbar\Heading;
use Entreya\Flux\Ui\Toolbar\SearchInput;
use Entreya\Flux\Ui\Toolbar\RerunButton;
use Entreya\Flux\Ui\Toolbar\ThemeButton;
use Entreya\Flux\Ui\Sidebar\Sidebar;
use Entreya\Flux\Ui\Log\LogPanel;
use Entreya\Flux\Ui\Badge;
use Entreya\Flux\Ui\Progress;
use PHPUnit\Framework\TestCase;

/**
 * Tests for FluxComponent base class and all concrete components.
 */
class FluxComponentTest extends TestCase
{
    protected function setUp(): void
    {
        FluxRenderer::reset();
    }

    // ── FluxComponent Core ──────────────────────────────────────────────────

    public function testSimpleComponentRenders(): void
    {
        $html = Heading::render();
        $this->assertStringContainsString('fx-toolbar-heading', $html);
        $this->assertStringContainsString('Initializing', $html);
    }

    public function testPropsOverride(): void
    {
        $html = Heading::render([
            'props' => ['text' => 'Custom Title', 'class' => 'fs-3'],
        ]);
        $this->assertStringContainsString('Custom Title', $html);
        $this->assertStringContainsString('fs-3', $html);
    }

    public function testPropInterpolationEscapesHtml(): void
    {
        $html = Heading::render([
            'props' => ['text' => '<script>alert("xss")</script>'],
        ]);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testContentOverrideString(): void
    {
        $html = Heading::render([
            'content' => '<h1 id="{id}">Custom</h1>',
        ]);
        $this->assertStringContainsString('<h1 id="fx-toolbar-heading">Custom</h1>', $html);
    }

    public function testContentOverrideClosure(): void
    {
        $html = Heading::render([
            'content' => fn(array $props) => '<h1>' . $props['text'] . '</h1>',
        ]);
        $this->assertStringContainsString('<h1>Initializing', $html);
    }

    public function testWidgetIsAliasForRender(): void
    {
        $a = Heading::widget();
        $b = Heading::render();
        $this->assertSame($a, $b);
    }

    // ── Slots ───────────────────────────────────────────────────────────────

    public function testParentRendersChildSlots(): void
    {
        $html = Toolbar::render();
        // Should contain heading and search from sub-components
        $this->assertStringContainsString('fx-toolbar-heading', $html);
        $this->assertStringContainsString('fx-toolbar-search', $html);
    }

    public function testSlotOverrideString(): void
    {
        $html = Toolbar::render([
            'slots' => [
                'btnRerun' => '<a href="/report">Report</a>',
            ],
        ]);
        $this->assertStringContainsString('<a href="/report">Report</a>', $html);
        // Should NOT contain the default rerun button
        $this->assertStringNotContainsString('Re-run', $html);
    }

    public function testSlotOverrideArray(): void
    {
        $html = Toolbar::render([
            'slots' => [
                'search' => ['props' => ['placeholder' => 'Find grades…']],
            ],
        ]);
        $this->assertStringContainsString('Find grades', $html);
    }

    public function testSlotOverrideClosure(): void
    {
        $html = Toolbar::render([
            'slots' => [
                // Closure now receives the CHILD component's props (Heading defaults)
                'heading' => fn($childProps) =>
                    '<h1>Custom: ' . $childProps['id'] . '</h1>',
            ],
        ]);
        $this->assertStringContainsString('<h1>Custom: fx-toolbar-heading</h1>', $html);
    }

    public function testSlotOverrideFalse(): void
    {
        $html = Toolbar::render([
            'slots' => [
                'btnTheme' => false,
                'btnRerun' => false,
            ],
        ]);
        $this->assertStringNotContainsString('Re-run', $html);
        $this->assertStringNotContainsString('bi-moon-stars', $html);
    }

    public function testSlotFalseDoesNotRegisterAssets(): void
    {
        Toolbar::render([
            'slots' => ['search' => false],
        ]);
        $selectors = FluxRenderer::getSelectors();
        $this->assertArrayNotHasKey('search', $selectors);
    }

    public function testNestedComponentsInSlotClosure(): void
    {
        $html = Toolbar::render([
            'slots' => [
                'heading' => fn() =>
                    Badge::render(['props' => ['initialText' => 'OK']])
                    . Heading::render(['props' => ['text' => 'Grace']]),
            ],
        ]);
        $this->assertStringContainsString('fx-badge', $html);
        $this->assertStringContainsString('Grace', $html);
    }

    // ── FluxRenderer (Asset Collection) ─────────────────────────────────────

    public function testStyleRegisteredOnRender(): void
    {
        Badge::render();
        $styles = FluxRenderer::getStyles();
        $this->assertNotEmpty($styles);
        $found = false;
        foreach ($styles as $css) {
            if (str_contains($css, 'flux-badge-dot')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Badge CSS should be registered');
    }

    public function testStyleDeduplicatedByClass(): void
    {
        Badge::render(['props' => ['id' => 'badge-1']]);
        Badge::render(['props' => ['id' => 'badge-2']]);
        $styles = FluxRenderer::getStyles();
        // Count occurrences of Badge CSS key
        $count = 0;
        foreach ($styles as $key => $css) {
            if (str_contains($key, 'Badge') && str_contains($css, 'flux-badge-dot')) {
                $count++;
            }
        }
        $this->assertSame(1, $count, 'Same component class should not register CSS twice');
    }

    public function testScriptRegisteredPerInstance(): void
    {
        SearchInput::render(['props' => ['id' => 'search-1']]);
        SearchInput::render(['props' => ['id' => 'search-2']]);
        $scripts = FluxRenderer::getScripts();
        $foundSearch1 = false;
        $foundSearch2 = false;
        foreach ($scripts as $js) {
            if (str_contains($js, 'search-1')) {
                $foundSearch1 = true;
            }
            if (str_contains($js, 'search-2')) {
                $foundSearch2 = true;
            }
        }
        $this->assertTrue($foundSearch1, 'Script for search-1 should be registered');
        $this->assertTrue($foundSearch2, 'Script for search-2 should be registered');
    }

    public function testCustomStyleOverride(): void
    {
        Heading::render([
            'style' => '.custom-heading { color: red; }',
        ]);
        $styles = FluxRenderer::getStyles();
        $found = false;
        foreach ($styles as $css) {
            if (str_contains($css, '.custom-heading')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Custom style override should be registered');
    }

    public function testCustomScriptOverride(): void
    {
        Heading::render([
            'props'  => ['id' => 'test-h'],
            'script' => 'console.log("{id} ready");',
        ]);
        $scripts = FluxRenderer::getScripts();
        $found = false;
        foreach ($scripts as $js) {
            if (str_contains($js, 'test-h ready')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Custom script should be registered with interpolated props');
    }

    public function testSelectorRegistration(): void
    {
        Toolbar::render();
        $selectors = FluxRenderer::getSelectors();
        $this->assertSame('fx-toolbar-heading', $selectors['jobHeading']);
        $this->assertSame('fx-toolbar-search', $selectors['search']);
        $this->assertSame('fx-toolbar-rerun-btn', $selectors['rerunBtn']);
        $this->assertSame('fx-toolbar-ts-btn', $selectors['tsBtn']);
    }

    public function testRendererFlushProducesValidOutput(): void
    {
        Toolbar::render();
        Badge::render();
        $output = FluxRenderer::flush(['sseUrl' => '/sse.php']);
        $this->assertStringContainsString('<style>', $output);
        $this->assertStringContainsString('</style>', $output);
        $this->assertStringContainsString('<script>', $output);
        $this->assertStringContainsString('FluxUI.init(', $output);
        $this->assertStringContainsString('sseUrl', $output);
        $this->assertStringContainsString('"sel"', $output);
    }

    public function testRendererReset(): void
    {
        Badge::render();
        $this->assertNotEmpty(FluxRenderer::getStyles());
        FluxRenderer::reset();
        $this->assertEmpty(FluxRenderer::getStyles());
        $this->assertEmpty(FluxRenderer::getScripts());
        $this->assertEmpty(FluxRenderer::getSelectors());
    }

    // ── Specific Components ─────────────────────────────────────────────────

    public function testBadgeRendersWithDotAndText(): void
    {
        $html = Badge::render();
        $this->assertStringContainsString('flux-badge-dot', $html);
        $this->assertStringContainsString('Connecting', $html);
    }

    public function testBadgeCustomInitialText(): void
    {
        $html = Badge::render([
            'props' => ['initialText' => 'Starting…'],
        ]);
        $this->assertStringContainsString('Starting', $html);
    }

    public function testBadgeSlotOverrideDot(): void
    {
        $html = Badge::render([
            'slots' => [
                'dot' => '<i class="bi bi-circle-fill"></i>',
            ],
        ]);
        $this->assertStringContainsString('bi-circle-fill', $html);
        $this->assertStringNotContainsString('flux-badge-dot', $html);
    }

    public function testSidebarRendersWithJobListAndFooter(): void
    {
        $html = Sidebar::render();
        $this->assertStringContainsString('fx-sidebar', $html);
        $this->assertStringContainsString('fx-sidebar-job-list', $html);
        $this->assertStringContainsString('Trigger', $html);
    }

    public function testSidebarPassesWorkflowName(): void
    {
        $html = Sidebar::render([
            'props' => ['workflowName' => 'grace-marks'],
        ]);
        $this->assertStringContainsString('grace-marks', $html);
        $this->assertStringContainsString('Workflow', $html);
    }

    public function testSidebarHidesFooter(): void
    {
        $html = Sidebar::render([
            'slots' => ['footer' => false],
        ]);
        $this->assertStringNotContainsString('Trigger', $html);
        $this->assertStringNotContainsString('Runner', $html);
    }

    public function testLogPanelRendersClean(): void
    {
        $html = LogPanel::render();
        $this->assertStringContainsString('fx-log-panel', $html);
    }

    public function testLogPanelBeforeAfterSteps(): void
    {
        $html = LogPanel::render([
            'slots' => [
                'beforeSteps' => '<div class="before">Before</div>',
                'afterSteps'  => '<div class="after">After</div>',
            ],
        ]);
        $this->assertStringContainsString('<div class="before">Before</div>', $html);
        $this->assertStringContainsString('<div class="after">After</div>', $html);
    }

    public function testLogPanelRegistersStyle(): void
    {
        LogPanel::render();
        $styles = FluxRenderer::getStyles();
        $found = false;
        foreach ($styles as $css) {
            if (str_contains($css, 'flux-step')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'LogPanel CSS should be registered');
    }

    public function testProgressRendersBar(): void
    {
        $html = Progress::render();
        $this->assertStringContainsString('progress-bar', $html);
        $this->assertStringContainsString('role="progressbar"', $html);
    }

    public function testProgressCustomHeight(): void
    {
        $html = Progress::render([
            'props' => ['height' => '6px'],
        ]);
        $this->assertStringContainsString('height:6px', $html);
    }

    public function testProgressCustomBarClass(): void
    {
        $html = Progress::render([
            'props' => ['barClass' => 'bg-success'],
        ]);
        $this->assertStringContainsString('bg-success', $html);
    }

    // ── Event Registration ──────────────────────────────────────────────────

    public function testRendererEventsInFlush(): void
    {
        FluxRenderer::registerEvent('workflow_complete', 'function(){location.reload()}');
        $output = FluxRenderer::init();
        $this->assertStringContainsString('"events"', $output);
        $this->assertStringContainsString('workflow_complete', $output);
        $this->assertStringContainsString('location.reload', $output);
    }

    public function testRendererCssAndJs(): void
    {
        FluxRenderer::setAssetPath('/assets');
        $css = FluxRenderer::css();
        $js = FluxRenderer::js();
        $this->assertStringContainsString('/assets/css/flux.css', $css);
        $this->assertStringContainsString('/assets/js/flux.js', $js);
    }
}
