<?php

declare(strict_types=1);

namespace Entreya\Flux\Tests\Ui;

use Entreya\Flux\Ui\FluxAsset;
use Entreya\Flux\Ui\FluxBadge;
use Entreya\Flux\Ui\FluxLogPanel;
use Entreya\Flux\Ui\FluxProgress;
use Entreya\Flux\Ui\FluxSidebar;
use Entreya\Flux\Ui\FluxToolbar;
use PHPUnit\Framework\TestCase;

/**
 * Comprehensive tests for the closure-first FluxWidget system.
 *
 * Covers: widget() output, render() with closures, slot() overrides,
 * FluxAsset integration, FluxBadge fix, and beforeContent/afterContent.
 */
class FluxWidgetTest extends TestCase
{
    protected function setUp(): void
    {
        FluxAsset::reset();
    }

    // ── FluxToolbar ─────────────────────────────────────────────────────────

    public function testToolbarWidgetProducesValidHtml(): void
    {
        $html = FluxToolbar::widget(['id' => 'test-tb']);

        $this->assertStringContainsString('id="test-tb"', $html);
        $this->assertStringContainsString('id="test-tb-heading"', $html);
        $this->assertStringContainsString('id="test-tb-search"', $html);
        $this->assertStringContainsString('id="test-tb-rerun-btn"', $html);
        $this->assertStringContainsString('id="test-tb-ts-btn"', $html);
        $this->assertStringContainsString('id="test-tb-theme-icon"', $html);
        $this->assertStringContainsString('Initializing', $html);
    }

    public function testToolbarWidgetMatchesRenderWithNoClosure(): void
    {
        FluxAsset::reset();
        $widgetHtml = FluxToolbar::widget(['id' => 'tb-cmp']);

        FluxAsset::reset();
        $renderHtml = FluxToolbar::render(['id' => 'tb-cmp']);

        $this->assertSame($widgetHtml, $renderHtml);
    }

    public function testToolbarRenderWithClosure(): void
    {
        $html = FluxToolbar::render(['id' => 'tb-cls'], function (FluxToolbar $t) {
            echo $t->heading();
            echo $t->search();
        });

        // Root tag present
        $this->assertStringContainsString('id="tb-cls"', $html);
        // Heading and search present
        $this->assertStringContainsString('id="tb-cls-heading"', $html);
        $this->assertStringContainsString('id="tb-cls-search"', $html);
        // Rerun button NOT present (we didn't call it)
        $this->assertStringNotContainsString('rerun-btn', $html);
    }

    public function testToolbarShowFlags(): void
    {
        $html = FluxToolbar::widget([
            'id'              => 'tb-flags',
            'showSearch'      => false,
            'showRerun'       => false,
            'showThemeToggle' => false,
            'showTimestamps'  => false,
            'showExpand'      => false,
        ]);

        $this->assertStringNotContainsString('tb-flags-search', $html);
        $this->assertStringNotContainsString('tb-flags-rerun-btn', $html);
        $this->assertStringNotContainsString('tb-flags-theme-icon', $html);
        $this->assertStringNotContainsString('tb-flags-ts-btn', $html);
        $this->assertStringNotContainsString('expandAll', $html);
        $this->assertStringNotContainsString('collapseAll', $html);
    }

    public function testToolbarCustomHeadingText(): void
    {
        $html = FluxToolbar::widget([
            'id'          => 'tb-txt',
            'headingText' => 'Grace Marks Evaluation',
        ]);

        $this->assertStringContainsString('Grace Marks Evaluation', $html);
    }

    public function testToolbarSlotOverrideReplace(): void
    {
        $html = FluxToolbar::widget([
            'id'    => 'tb-slot',
            'slots' => [
                'btnRerun' => fn($w, $props, $default) =>
                    '<a href="/report" class="btn btn-sm">Report</a>',
            ],
        ]);

        $this->assertStringContainsString('<a href="/report"', $html);
        $this->assertStringNotContainsString('FluxUI.rerun()', $html);
    }

    public function testToolbarSlotOverrideWrap(): void
    {
        $html = FluxToolbar::widget([
            'id'    => 'tb-wrap',
            'slots' => [
                'search' => fn($w, $props, $default) =>
                    '<div class="glow-wrapper">' . $default() . '</div>',
            ],
        ]);

        $this->assertStringContainsString('class="glow-wrapper"', $html);
        $this->assertStringContainsString('id="tb-wrap-search"', $html);
    }

    public function testToolbarSlotPropsContainResolvedValues(): void
    {
        $capturedProps = null;

        FluxToolbar::widget([
            'id'              => 'tb-props',
            'searchPlaceholder' => 'Find stuff…',
            'slots'           => [
                'search' => function ($w, $props, $default) use (&$capturedProps) {
                    $capturedProps = $props;
                    return $default();
                },
            ],
        ]);

        $this->assertNotNull($capturedProps);
        $this->assertSame('tb-props-search', $capturedProps['id']);
        $this->assertSame('Find stuff…', $capturedProps['placeholder']);
        $this->assertStringContainsString('form-control', $capturedProps['class']);
    }

    public function testToolbarBeforeAfterContent(): void
    {
        $html = FluxToolbar::widget([
            'id'            => 'tb-ba',
            'beforeContent' => '<div class="before">',
            'afterContent'  => '</div><!-- after -->',
        ]);

        $this->assertStringContainsString('<div class="before">', $html);
        $this->assertStringContainsString('</div><!-- after -->', $html);
    }

    public function testToolbarAfterSearch(): void
    {
        $html = FluxToolbar::widget([
            'id'          => 'tb-as',
            'afterSearch' => '<span class="badge">BETA</span>',
        ]);

        $this->assertStringContainsString('<span class="badge">BETA</span>', $html);
    }

    // ── FluxBadge ───────────────────────────────────────────────────────────

    public function testBadgeWidgetProducesValidHtml(): void
    {
        $html = FluxBadge::widget(['id' => 'test-badge']);

        $this->assertStringContainsString('id="test-badge"', $html);
        $this->assertStringContainsString('id="test-badge-text"', $html);
        $this->assertStringContainsString('data-status="pending"', $html);
        $this->assertStringContainsString('flux-badge-dot', $html);
        $this->assertStringContainsString('Connecting', $html);
        // Root tag is <span>
        $this->assertStringStartsWith('<span', $html);
        $this->assertStringEndsWith('</span>', $html);
    }

    public function testBadgeRenderWithClosureWorks(): void
    {
        $html = FluxBadge::render(['id' => 'badge-cls'], function (FluxBadge $b) {
            echo $b->text();
            echo $b->dot();
        });

        // Reversed order: text first, then dot
        $textPos = strpos($html, 'badge-cls-text');
        $dotPos  = strpos($html, 'flux-badge-dot');
        $this->assertGreaterThan(0, $textPos);
        $this->assertGreaterThan($textPos, $dotPos);
    }

    public function testBadgeWidgetMatchesRenderWithNoClosure(): void
    {
        FluxAsset::reset();
        $widgetHtml = FluxBadge::widget(['id' => 'badge-cmp']);

        FluxAsset::reset();
        $renderHtml = FluxBadge::render(['id' => 'badge-cmp']);

        $this->assertSame($widgetHtml, $renderHtml);
    }

    public function testBadgeSlotOverrideDot(): void
    {
        $html = FluxBadge::widget([
            'id'    => 'badge-slot',
            'slots' => [
                'dot' => fn($w, $props, $default) =>
                    '<i class="bi bi-circle-fill text-success"></i>',
            ],
        ]);

        $this->assertStringContainsString('bi-circle-fill', $html);
        $this->assertStringNotContainsString('flux-badge-dot', $html);
    }

    public function testBadgeCustomInitialText(): void
    {
        $html = FluxBadge::widget([
            'id'          => 'badge-txt',
            'initialText' => 'Starting…',
        ]);

        $this->assertStringContainsString('Starting…', $html);
    }

    // ── FluxLogPanel ────────────────────────────────────────────────────────

    public function testLogPanelWidgetProducesValidHtml(): void
    {
        $html = FluxLogPanel::widget(['id' => 'test-lp']);

        $this->assertStringContainsString('id="test-lp"', $html);
        $this->assertStringContainsString('flex-grow-1', $html);
    }

    public function testLogPanelBeforeAfterSteps(): void
    {
        $html = FluxLogPanel::widget([
            'id'          => 'lp-ba',
            'beforeSteps' => '<div class="alert">Before</div>',
            'afterSteps'  => '<div class="footer">After</div>',
        ]);

        $this->assertStringContainsString('<div class="alert">Before</div>', $html);
        $this->assertStringContainsString('<div class="footer">After</div>', $html);
    }

    public function testLogPanelRenderWithClosure(): void
    {
        $html = FluxLogPanel::render(['id' => 'lp-cls'], function (FluxLogPanel $lp) {
            echo '<div class="custom-wrapper">';
            echo $lp->stepsContainer();
            echo '</div>';
        });

        $this->assertStringContainsString('id="lp-cls"', $html);
        $this->assertStringContainsString('class="custom-wrapper"', $html);
    }

    // ── FluxSidebar ─────────────────────────────────────────────────────────

    public function testSidebarWidgetProducesValidHtml(): void
    {
        $html = FluxSidebar::widget(['id' => 'test-sb']);

        $this->assertStringContainsString('id="test-sb"', $html);
        $this->assertStringContainsString('id="test-sb-job-list"', $html);
        $this->assertStringContainsString('<nav', $html);
        $this->assertStringContainsString('</nav>', $html);
    }

    public function testSidebarWithWorkflowName(): void
    {
        $html = FluxSidebar::widget([
            'id'           => 'sb-wf',
            'workflowName' => 'Grace Marks',
        ]);

        $this->assertStringContainsString('Grace Marks', $html);
        $this->assertStringContainsString('Workflow', $html);
    }

    public function testSidebarHideFooter(): void
    {
        $html = FluxSidebar::widget([
            'id'         => 'sb-nf',
            'showFooter' => false,
        ]);

        $this->assertStringNotContainsString('Runner', $html);
        $this->assertStringNotContainsString('Trigger', $html);
    }

    public function testSidebarRenderWithClosure(): void
    {
        $html = FluxSidebar::render(['id' => 'sb-cls'], function (FluxSidebar $s) {
            echo '<div class="custom">';
            echo $s->jobList();
            echo '</div>';
        });

        $this->assertStringContainsString('id="sb-cls"', $html);
        $this->assertStringContainsString('custom', $html);
        $this->assertStringContainsString('sb-cls-job-list', $html);
        // Footer NOT present
        $this->assertStringNotContainsString('Runner', $html);
    }

    public function testSidebarSlotOverrideFooter(): void
    {
        $html = FluxSidebar::widget([
            'id'    => 'sb-slot',
            'slots' => [
                'footer' => fn($w, $props, $default) =>
                    '<div class="my-footer">v2.0</div>',
            ],
        ]);

        $this->assertStringContainsString('my-footer', $html);
        $this->assertStringContainsString('v2.0', $html);
    }

    // ── FluxProgress ────────────────────────────────────────────────────────

    public function testProgressWidgetProducesValidHtml(): void
    {
        $html = FluxProgress::widget(['id' => 'test-pg']);

        $this->assertStringContainsString('id="test-pg"', $html);
        $this->assertStringContainsString('progress-bar', $html);
        $this->assertStringContainsString('role="progressbar"', $html);
    }

    public function testProgressCustomHeight(): void
    {
        $html = FluxProgress::widget([
            'id'     => 'pg-h',
            'height' => '6px',
        ]);

        $this->assertStringContainsString('height:6px', $html);
    }

    public function testProgressCustomBarClass(): void
    {
        $html = FluxProgress::widget([
            'id'       => 'pg-bc',
            'barClass' => 'bg-success',
        ]);

        $this->assertStringContainsString('bg-success', $html);
    }

    public function testProgressRenderWithClosure(): void
    {
        $html = FluxProgress::render(['id' => 'pg-cls'], function (FluxProgress $p) {
            echo $p->bar();
            echo '<span class="pct">0%</span>';
        });

        $this->assertStringContainsString('id="pg-cls"', $html);
        $this->assertStringContainsString('class="pct"', $html);
    }

    // ── FluxAsset Integration ───────────────────────────────────────────────

    public function testFluxAssetAccumulatesSelectors(): void
    {
        FluxToolbar::widget(['id' => 'ast-tb']);

        $sel = FluxAsset::getSelectors();
        $this->assertSame('ast-tb-search', $sel['search']);
        $this->assertSame('ast-tb-rerun-btn', $sel['rerunBtn']);
        $this->assertSame('ast-tb-heading', $sel['jobHeading']);
    }

    public function testFluxAssetAccumulatesMultipleWidgets(): void
    {
        FluxToolbar::widget(['id' => 'multi-tb']);
        FluxSidebar::widget(['id' => 'multi-sb']);
        FluxBadge::widget(['id' => 'multi-badge']);

        $sel = FluxAsset::getSelectors();
        $this->assertArrayHasKey('search', $sel);
        $this->assertArrayHasKey('jobList', $sel);
        $this->assertArrayHasKey('badge', $sel);
        $this->assertArrayHasKey('badgeText', $sel);
    }

    public function testFluxAssetRegistersCss(): void
    {
        FluxBadge::widget(['id' => 'css-badge']);

        $css = FluxAsset::getCss();
        $this->assertArrayHasKey(FluxBadge::class, $css);
        $this->assertStringContainsString('flux-badge-dot', $css[FluxBadge::class]);
    }

    public function testFluxAssetRegistersTemplates(): void
    {
        FluxLogPanel::widget(['id' => 'tpl-lp']);

        $templates = FluxAsset::getTemplates();
        $this->assertArrayHasKey('step', $templates);
        $this->assertStringContainsString('flux-step', $templates['step']);
    }

    public function testFluxAssetRegistersPluginOptions(): void
    {
        FluxToolbar::widget([
            'id'            => 'po-tb',
            'pluginOptions' => ['autoScroll' => true],
        ]);

        $opts = FluxAsset::getPluginOptions();
        $this->assertArrayHasKey('fluxToolbar', $opts);
        $this->assertTrue($opts['fluxToolbar']['autoScroll']);
    }

    public function testFluxAssetRegistersEvents(): void
    {
        FluxLogPanel::widget([
            'id'           => 'ev-lp',
            'pluginEvents' => [
                'workflow_complete' => 'function() { location.reload(); }',
            ],
        ]);

        $events = FluxAsset::getPluginEvents();
        $this->assertArrayHasKey('workflow_complete', $events);
    }

    public function testFluxAssetInitProducesValidScript(): void
    {
        FluxToolbar::widget(['id' => 'init-tb']);
        FluxBadge::widget(['id' => 'init-badge']);

        $script = FluxAsset::init(['sseUrl' => '/sse.php']);

        $this->assertStringContainsString('<script>', $script);
        $this->assertStringContainsString('FluxUI.init(', $script);
        $this->assertStringContainsString('/sse.php', $script);
        $this->assertStringContainsString('init-tb-search', $script);
    }

    // ── CSS class merging ───────────────────────────────────────────────────

    public function testOptionsClassMerge(): void
    {
        $html = FluxToolbar::widget([
            'id'      => 'merge-tb',
            'options' => ['class' => 'sticky-top my-custom'],
        ]);

        // Both default classes and custom classes present
        $this->assertStringContainsString('d-flex', $html);
        $this->assertStringContainsString('sticky-top', $html);
        $this->assertStringContainsString('my-custom', $html);
    }

    // ── Selector API ────────────────────────────────────────────────────────

    public function testSelectorReturnCorrectIds(): void
    {
        $capturedId = '';
        FluxToolbar::render(['id' => 'sel-tb'], function (FluxToolbar $t) use (&$capturedId) {
            $capturedId = $t->selector('search');
        });

        $this->assertSame('sel-tb-search', $capturedId);
    }

    public function testSelectorReturnsEmptyForUnknownKey(): void
    {
        $capturedId = 'not-empty';
        FluxToolbar::render(['id' => 'sel-unk'], function (FluxToolbar $t) use (&$capturedId) {
            $capturedId = $t->selector('nonexistent');
        });

        $this->assertSame('', $capturedId);
    }
}
