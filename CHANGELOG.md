# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
- `[FEAT]` Widget system: independent PHP component wrappers (`FluxBadge`, `FluxSidebar`, `FluxToolbar`, `FluxLogPanel`, `FluxProgress`) with configurable IDs and HTML attributes.
- `[FEAT]` `FluxAsset` static accumulator for dynamic JS selector binding.
- `[FEAT]` `FluxWidget` abstract base class with `::widget()` factory (Yii2-style API).
- `[FEAT]` Added `Ansi::link()` method to render clickable links in supported terminals.
- `[FEAT]` Added `Ansi` helper class in `src/Output/Ansi.php` for generating ANSI color codes to echo content with color.
- `[FEAT]` Integrated `Ansi` helper into UI and parsing layers.

### Changed
- `[REFACTOR]` `flux.js` is now fully selector-agnostic — reads IDs from `cfg.sel` map instead of hardcoded `fx-*` strings.
- `[REFACTOR]` `flux.css` reduced from 507 → ~230 lines. Removed all layout/button/sidebar/toolbar classes; only log-specific and ANSI styles remain.
- `[REFACTOR]` `index.php` rewritten to use widget system with Bootstrap 5 native components.
