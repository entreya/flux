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

### Changed
- `[REFACTOR]` `SseChannel` and `FileChannel` now implement `ChannelInterface`. Renamed `close()` → `complete()`.
- `[REFACTOR]` `flux.js`: template-driven step creation via `cfg.templates.step`, dual collapse (details/accordion), custom event hooks via `cfg.events`.
- `[REFACTOR]` `flux.css` reduced from 507 → ~230 lines.
- `[REFACTOR]` `index.php` rewritten using widget system.
- `[REFACTOR]` Centralized `set_time_limit(0)` into `Pipeline::stream()` and `Pipeline::writeToFile()`, removed from channels.

