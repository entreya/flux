# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
- **ThemeManager**: Support for GitHub Dark, Light, and High Contrast themes.
- **WorkflowExecutor**: Support for `jobs` and `needs` dependencies in workflows.
- **AnsiConverter**: Enhanced regex to support literal escape sequences (`\033`, `\e`) for robust color rendering.
- **Flux Console UI**: New 2-column layout with real-time log streaming and job status tracking.

### Changed
- **RateLimiter**: Increased default limit and improved error handling for SSE connections.
- **YamlParser**: Added support for multiline strings (`|`, `>`) and C-style escape sequences in double-quoted strings.
- **Flux.js**: Implemented theme persistence via local storage and fixed duplicate job rendering bugs.
- **Flux.php**: Optimized session handling to prevent locking during concurrent SSE requests.

### Fixed
- **Security**: Updated `complex-workflow.yaml` to remove chaining operators (`&&`) in compliance with strict validator rules.
- **Rendering**: Fixed ANSI color rendering in browser logs.
- **Stability**: Resolved infinite reconnection loops in SSE client when rate limited.
- **Compatibility**: Patched SSE logic to support PHP 8.2 (dynamic properties).
- **Paths**: Resolved path resolution issues for example workflows.

### Added (v2 Polish)
- **UI**: Added Drag-and-Drop support for workflow files with polished Dropzone.
- **Security**: Added backend request rate limiting (default 1000/hr).
- **Execution**: Injected PHP binary path into environment for robust execution.
- **Style**: Polished UI with rounded corners and scrollbar styling.
