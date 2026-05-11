# Changelog

All notable changes to this project will be documented in this file. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [5.0.0] - 2026-05-11

Initial public release for Craft CMS 5.

### Added

- Server-side Tailwind CSS class merging via `craft.tailwind.merge()`, wrapping `gehrisandro/tailwind-merge-php` (v3) and `tales-from-a-dev/tailwind-merge-php` (v4).
- Named-slot `ClassList` builder via `craft.tailwind.classes({ slot: '...' })`, with immutable `.override()`, `.extend()`, `.without()`, `.merge()`, `.get()`, and `.toArray()`.
- Automatic Tailwind v3/v4 detection from project files. Scans for `@import "tailwindcss"` / `@theme` (v4) and `tailwind.config.{js,ts,cjs,mjs}` (v3). Configurable `buildchainPath` and `cssPath`; manual override via `tailwindVersion`. Exposed as `craft.tailwind.version`.
- CSS custom properties container via `craft.tailwind.cssVariables` (`.get()`, `.has()`, `.all()`, `.isEmpty()`, `.asCss()`), with save-time validation against a safe-character whitelist and render-time silent drop of unsafe values.
- `<style>` tag rendering via `craft.tailwind.include({ nonce, media, title })` — returns `Twig\Markup` so no `|raw` is required.
- Auto-inject mode (`autoInject` + `autoInjectAttributes`) that registers the CSS variables style block on every site request via `View::registerCss()`. Skips console and CP requests.
- O(1) LRU merge cache, request-scoped, with configurable `cacheSize` (set to `0` to disable). Hit/miss counters surfaced through the debug panel.
- `clearCache()` method on `TailwindService` for long-running runtimes (Octane, RoadRunner, queue workers).
- Yii debug toolbar panel showing total calls, unique inputs, cache hit rate, and per-merge detail (input, output, resolved/passthrough, count, originating template + line). Zero overhead when the debug module isn't loaded.
- CP settings page with editable tables for `cssVariables` and `autoInjectAttributes`, per-field override warnings when shadowed by `config/tailwind.php`, and a conditional CSS-path field shown only when version detection includes v4.
- Multi-environment configuration support in `config/tailwind.php` (Craft's standard `'*' + env` pattern).
- Tailwind class prefix support (bare-form value, e.g. `prefix: 'tw'`) wired into both v3 and v4 engines — v3 emits `tw-px-4`, v4 emits `tw:px-4`, matching each version's native prefix syntax. Validation rejects trailing-hyphen input on explicit v4 to surface the v3-vs-v4 syntax difference at save time.
- Opt-in `@tailwindcss/typography` conflict resolution. Enable the `typography` setting to have `prose-{size}` and `prose-{theme}` classes merge as mutually-exclusive utilities (sizes among themselves, colors among themselves, size and color stay orthogonal). Defaults cover the suffixes shipped by `@tailwindcss/typography` 0.5.x; `typographyExtraSizes` / `typographyExtraColors` register custom suffixes for your own `prose-*` themes.
- Debug toolbar row showing the active typography config — whether resolution is enabled and which custom suffixes are loaded.

[5.0.0]: https://github.com/craftpulse/craft-tailwind/releases/tag/5.0.0
