# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
- `[FEAT]` Configurable `icon` prop on all Toolbar components (Heading, SearchInput, TimestampButton, ThemeButton, ExpandButton, CollapseButton, RerunButton).
- `[FEAT]` Configurable `jobItemTemplate` prop on `JobList` — sidebar job items are now template-driven (same pattern as `stepTpl` and `jobHeaderTpl`).
- `[FEAT]` Auto-wiring: `FluxUI.init()` now attaches click handlers to all toolbar buttons automatically via selectors.
- `[FEAT]` Closure slot overrides now receive the **child component's** resolved props (not the parent's) and child `registerSelectors()` is still called.
- `[FEAT]` `FluxComponent` — Vue-inspired base class with declarative props, template, style, script, and slots.
- `[FEAT]` `FluxRenderer` — Static asset collector. CSS deduped per class, JS per instance. `flush()` bundles only rendered components.
- `[FEAT]` 22 component files: Toolbar (8), Sidebar (3), Log (3), Badge (3), Progress (2), core (2), EmptySlot.
- `[FEAT]` 5 slot override types: string, array, Closure, false, class-string.
- `[FEAT]` `rawProps()` — HTML-containing props skip escaping during interpolation.
- `[FEAT]` `FluxComponentTest` — 39 tests covering props, slots, nesting, assets, dedup, all components.
- `[FEAT]` `ChannelInterface` — formal contract for all event transport channels.
- `[FEAT]` `RedisChannel` — Redis Streams (XADD/XREAD BLOCK) with heartbeat liveness + auto-expire.
- `[FEAT]` `DatabaseChannel` — MySQL/Postgres/SQLite channel with polling, heartbeat, auto-migrate, and `cleanup()`.
- `[FEAT]` `Pipeline::streamTo(ChannelInterface)` — stream to any channel via fluent API.
- `[FEAT]` `Pipeline::writeToFile()` — background mode execution to log file.
- `[FEAT]` `Pipeline::preStep()` / `postStep()` — three-phase job execution (pre → main → post).
- `[FEAT]` Matrix strategy expansion (`strategy.matrix` in YAML).
- `[FEAT]` `Ansi::link()` — OSC 8 clickable hyperlinks in terminal output.
- `[FEAT]` 256-color and true-color ANSI support in `AnsiConverter`.

### Changed
- `[REFACTOR]` **PHP 8.0 rewrite** — constructor property promotion, readonly properties, typed returns, match expressions, nullsafe operator, `JSON_THROW_ON_ERROR` across all modules.
- `[REFACTOR]` `flux.js` — template-driven step/jobHeader/jobItem creation, dual collapse (details/accordion), custom event hooks.
- `[REFACTOR]` `flux.css` reduced from 507 → ~230 lines.
- `[REFACTOR]` `SseChannel` and `FileChannel` implement `ChannelInterface`. `close()` → `complete()`.
- `[REFACTOR]` Centralized `set_time_limit(0)` into `Pipeline::stream()` and `Pipeline::writeToFile()`.
- `[RENAME]` `Flux::pipeline()` → `Flux::workflow()` — aligns with event names.
- `[PERF]` `CommandRunner`: fread buffer 8KB → 64KB, `stream_select` timeout 100ms → 200ms.
- `[PERF]` `WorkflowExecutor`: `time()` → `hrtime(true)` for nanosecond event timestamps.
- `[PERF]` `SseChannel`: cached `ob_get_level()`, combined echo calls, pre-encoded JSON flags.
- `[FIX]` `RateLimiter`: fixed TOCTOU race condition in APCu increment path.
- `[FIX]` `flux.js`: Bootstrap 4 compatibility via `bsCollapse()` helper (BS4 jQuery + BS5 vanilla).
- `[FIX]` `AnsiConverter`: restored OSC 8 hyperlink support, fixed 256-color/true-color parsing.
- `[DOCS]` Replaced all docs with comprehensive `docs/README.md` covering all 13 API sections with hidden APIs.
- `[DELETE]` Removed stale docs: `WIDGET_API.md`, `YII2_INTEGRATION.md`, `debug/PHPSTORM_HANG.md`.
