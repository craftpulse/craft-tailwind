# Tailwind for Craft CMS

Tailwind CSS class merging and named-slot class builder for Craft CMS 5.

## Features

- **Class merging** — resolves conflicting Tailwind utilities server-side (last wins per CSS property)
- **Named-slot class builder** — organize classes by concern (layout, color, font) with an immutable API
- **Tailwind v3 + v4 support** — auto-detects your Tailwind version, or configure it explicitly
- **LRU cache** — merge results are cached in memory for performance
- **DevMode logging** — logs conflict resolutions when devMode is enabled

## Requirements

- Craft CMS 5.0 or later
- PHP 8.2 or later

## Installation

```bash
composer require craftpulse/craft-tailwind
```

Then install the plugin in the Craft control panel under Settings > Plugins, or via the CLI:

```bash
php craft plugin/install tailwind
```

## Usage

### Class Merging

Merge conflicting Tailwind utilities — the last class per CSS property wins:

```twig
{# Filter form #}
{{ 'px-4 bg-red-500'|twmerge('bg-blue-500 mt-4') }}
{# → 'px-4 bg-blue-500 mt-4' #}

{# Function form #}
{{ twmerge('px-4 bg-red-500', 'bg-blue-500 mt-4') }}
{# → 'px-4 bg-blue-500 mt-4' #}

{# Variable form #}
{{ craft.tailwind.merge('px-4 bg-red-500', 'bg-blue-500 mt-4') }}
{# → 'px-4 bg-blue-500 mt-4' #}
```

### Named-Slot Class Builder

Build class strings from named concerns. Each slot represents a style responsibility — overriding a slot replaces it entirely, preventing structural conflicts:

```twig
{% set classes = craft.tailwind.classes({
    layout: 'inline-flex items-center group w-fit',
    color: 'bg-brand-accent text-brand-on-accent',
    font: 'font-heading font-bold text-base',
    hover: 'hover:bg-brand-accent-hover',
    radius: 'rounded-sm rounded-br-2xl',
    spacing: 'py-2 px-4 gap-2',
    focus: 'focus:ring-2 focus:ring-brand-focus focus:ring-offset-1 focus:outline-none',
}) %}

{# Auto-casts to merged string #}
<button class="{{ classes }}">Click me</button>

{# Get a single slot #}
{{ classes.get('color') }}

{# Override a slot — returns new immutable instance #}
{{ classes.override({ color: 'bg-red-600 text-white' }) }}

{# Add a new slot #}
{{ classes.extend({ border: 'border border-brand-muted' }) }}

{# Remove slots #}
{{ classes.without('hover', 'focus') }}

{# Merge additional utilities on top #}
{{ classes.merge('mt-8') }}
```

The `ClassList` object is immutable — all methods return new instances:

| Method | Returns | Description |
|--------|---------|-------------|
| `__toString()` | `string` | All slots merged into a single class string |
| `get(slot)` | `?string` | Single slot value |
| `override(slots)` | `ClassList` | New instance with replaced slots |
| `extend(slots)` | `ClassList` | New instance with added/extended slots |
| `without(slot, ...)` | `ClassList` | New instance without named slots |
| `merge(additional)` | `string` | All slots + additional merged into a string |
| `toArray()` | `array` | Named slots as associative array |

### Tag Integration

Works with Craft's native `{% tag %}` seamlessly:

```twig
{% tag 'a' with {
    class: craft.tailwind.classes({
        layout: 'inline-flex items-center',
        color: 'bg-brand-accent text-brand-on-accent',
    }),
    href: url,
} %}
    Click me
{% endtag %}
```

### Version Detection

The plugin auto-detects your Tailwind version:

1. Checks for `tailwind.config.js` / `.ts` / `.cjs` / `.mjs` (project root or `buildchain/`) -> v3
2. Checks for `@theme` directives in `src/css/*.css` -> v4
3. Parses `tailwindcss` version from `package.json` -> semver major
4. Falls back to plugin settings

```twig
{{ craft.tailwind.version }} {# '3', '4', or 'unknown' #}
```

## Configuration

Create a `config/tailwind.php` file to override defaults:

```php
<?php

return [
    // 'auto' | '3' | '4'
    'tailwindVersion' => 'auto',

    // Log merge conflict resolutions in devMode
    'enableDevLogging' => true,

    // LRU cache size for merge results (0-10000)
    'cacheSize' => 500,

    // Tailwind class prefix (e.g., 'tw-')
    'prefix' => '',
];
```

## Twig API Reference

| API | Type | Description |
|-----|------|-------------|
| `'classes'\|twmerge('more')` | Filter | Merge classes, last wins per property |
| `twmerge('a', 'b', ...)` | Function | Merge multiple class strings |
| `twclasses({ slot: 'classes' })` | Function | Named-slot class builder |
| `craft.tailwind.merge(...)` | Variable | Merge class strings |
| `craft.tailwind.classes({...})` | Variable | Named-slot class builder |
| `craft.tailwind.version` | Variable | Detected Tailwind version |

## Credits

This plugin wraps two excellent PHP libraries:

- [gehrisandro/tailwind-merge-php](https://github.com/gehrisandro/tailwind-merge-php) (Tailwind v3)
- [tales-from-a-dev/tailwind-merge-php](https://github.com/tales-from-a-dev/tailwind-merge-php) (Tailwind v4)

Brought to you by [CraftPulse](https://craftpulse.com).
