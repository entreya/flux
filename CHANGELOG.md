# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
- `[FEAT]` `ChannelInterface` — formal contract for all event transport channels.
- `[FEAT]` `RedisChannel` — Redis Streams (XADD/XREAD BLOCK) for multi-server/auto-scale with heartbeat liveness + auto-expire.
- `[FEAT]` `DatabaseChannel` — MySQL/Postgres channel with polling, heartbeat, auto-migrate schema, and cleanup utility.
- `[FEAT]` Kartik/GridView-style widget architecture with layout templates, named render methods, and per-sub-element options.
- `[FEAT]` `StepRendererInterface` + `DetailsStepRenderer` + `AccordionStepRenderer` — swappable step markup (Column pattern).
- `[FEAT]` `pluginOptions` and `pluginEvents` — JS behavior config and event hooks from PHP.
- `[FEAT]` `beforeContent` / `afterContent` hooks on every widget.
- `[FEAT]` Widget system: `FluxBadge`, `FluxSidebar`, `FluxToolbar`, `FluxLogPanel`, `FluxProgress`.
- `[FEAT]` `FluxAsset` static accumulator: selectors, templates, pluginOptions, pluginEvents.
- `[FEAT]` `FluxWidget` abstract base with `::widget()` factory, `renderSections()`, `configure()`.
- `[FEAT]` Added `Ansi::link()` and `Ansi` helper class.
- `[FEAT]` `FluxWidget::styles()` — per-widget CSS method with lazy registration via `FluxAsset::registerCss()`.
- `[FEAT]` `FluxAsset::styles()` — consolidated `<style>` block from all rendered widgets.
- `[FEAT]` `Pipeline::streamTo(ChannelInterface)` — stream to any channel via fluent API.

### Changed
- `[REFACTOR]` `SseChannel` and `FileChannel` now implement `ChannelInterface`. Renamed `close()` → `complete()`.
- `[REFACTOR]` `flux.js`: template-driven step creation via `cfg.templates.step`, dual collapse (details/accordion), custom event hooks via `cfg.events`.
- `[REFACTOR]` `flux.css` reduced from 507 → ~230 lines.
- `[REFACTOR]` `index.php` rewritten using widget system.
- `[REFACTOR]` Centralized `set_time_limit(0)` into `Pipeline::stream()` and `Pipeline::writeToFile()`, removed from channels.
- `[RENAME]` `Flux::pipeline()` → `Flux::workflow()` — aligns with event names (`workflow_start`, `workflow_complete`).
- `[PERF]` `CommandRunner`: fread buffer 8KB → 64KB, stream_select timeout 100ms → 200ms.
- `[PERF]` `WorkflowExecutor`: `time()` → `hrtime(true)` for nanosecond event timestamps; cached `buildBaseEnv()`.
- `[PERF]` `SseChannel`: cached `ob_get_level()`, combined echo calls, pre-encoded JSON flags.
- `[FIX]` `RateLimiter`: fixed TOCTOU race condition in APCu increment path.
- `[FIX]` `flux.js`: Bootstrap 4 compatibility via `bsCollapse()` helper (supports both BS4 jQuery and BS5 vanilla).
