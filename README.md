# Tailwind for Craft CMS

Server-side Tailwind CSS class merging for Craft CMS 5 templates. Resolves conflicting utility classes, organizes styles into named slots, and injects CSS custom properties -- all without client-side JavaScript.

## What it solves

### Component overrides without conflicts

A button component defines default colors. A page template wants to override just the color, but concatenating classes produces `bg-brand-accent bg-red-500` -- both classes render, the cascade picks a winner unpredictably, and the source is impossible to reason about.

With craft-tailwind, the merge engine understands which utilities conflict and keeps only the last one per CSS property:

```twig
{# _components/button.twig #}
{% set classes = craft.tailwind.classes({
    layout: 'inline-flex items-center gap-2',
    color: 'bg-brand-accent text-white',
    radius: 'rounded-sm',
    spacing: 'py-2 px-4',
}) %}

{# Override just the color slot -- layout, radius, spacing stay intact #}
{% set classes = classes.override({ color: 'bg-red-600 text-white' }) %}

<button class="{{ classes }}">Submit</button>
{# Output: inline-flex items-center gap-2 bg-red-600 text-white rounded-sm py-2 px-4 #}
```

### Dynamic colors without safelisting

A CMS field lets editors pick a brand color. Tailwind tree-shakes unused classes at build time, so writing `bg-{{ color }}` produces nothing -- the class never made it into the build.

CSS custom properties sidestep this entirely. Define your palette once in plugin settings, reference the variables in your templates with arbitrary value syntax, and swap values at runtime:

```twig
{# Inject CSS variables into the page head -- no |raw needed #}
{{ craft.tailwind.include() }}

{# Use arbitrary value syntax -- these classes ARE in the build #}
<div class="bg-[var(--color-brand-primary)] text-[var(--color-brand-on-primary)]">
    Editor-chosen colors, no safelisting required.
</div>
```

### Form field modifiers

A form input has default styling. Error state needs a red border. Disabled state needs muted colors. Without merge, layering modifier classes means manually tracking which base classes to remove:

```twig
{% set base = 'border border-gray-300 rounded-md px-3 py-2 text-sm' %}
{% set error = 'border-red-500 ring-1 ring-red-500' %}
{% set disabled = 'bg-gray-100 text-gray-400 cursor-not-allowed' %}

{# Merge resolves border-gray-300 vs border-red-500 automatically #}
<input class="{{ base|twmerge(hasErrors ? error : '', isDisabled ? disabled : '') }}" />
```

## Installation

```bash
composer require craftpulse/craft-tailwind
```

Then install the plugin via the control panel under Settings > Plugins, or from the command line:

```bash
php craft plugin/install tailwind
```

## Configuration

Settings can be managed through the control panel (Settings > Plugins > Tailwind) or via a config file. File-based configuration takes precedence over CP settings.

### Config file

Create `config/tailwind.php`:

```php
<?php

return [
    // 'auto' | '3' | '4'
    'tailwindVersion' => 'auto',

    // Directory containing tailwind.config.* (v3 detection)
    'buildchainPath' => null,

    // Directory containing CSS entry file (v4 detection)
    'cssPath' => null,

    // CSS custom properties injected as :root variables
    'cssVariables' => [
        '--color-brand-primary' => '#3490dc',
        '--color-brand-on-primary' => '#ffffff',
    ],

    // LRU cache size for merge results (0-10000)
    'cacheSize' => 500,

    // Tailwind class prefix, matching your tailwind.config.js prefix option
    'prefix' => '',

    // Auto-inject CSS variables into every site page's <head>
    'autoInject' => false,

    // Attributes applied to the auto-injected <style> tag
    // (e.g. ['nonce' => '...', 'media' => 'screen'])
    'autoInjectAttributes' => [],
];
```

When a setting is defined in both the config file and the CP, the config file value wins. The CP settings page shows a warning on each overridden field.

### Multi-environment configuration

The config file supports Craft's standard multi-environment pattern:

```php
<?php

return [
    '*' => [
        'tailwindVersion' => 'auto',
        'autoInject' => false,
    ],
    'dev' => [
        'autoInject' => true,
    ],
];
```

## Usage

### Class merging

The `|twmerge` filter resolves conflicting Tailwind utilities. The last class per CSS property group wins:

```twig
{{ 'px-4 bg-red-500'|twmerge('bg-blue-500 mt-4') }}
{# Result: px-4 bg-blue-500 mt-4 #}
```

You can also merge via the template variable:

```twig
{{ craft.tailwind.merge('px-4 bg-red-500', 'bg-blue-500 mt-4') }}
```

Both forms accept multiple arguments. Each argument is a space-separated class string.

### Named-slot ClassList

When a component has many style concerns, a flat class string becomes hard to override selectively. The `ClassList` object splits classes into named slots, each representing a single responsibility:

```twig
{% set btn = craft.tailwind.classes({
    layout: 'inline-flex items-center group w-fit',
    color: 'bg-brand-accent text-brand-on-accent',
    font: 'font-heading font-bold text-base',
    hover: 'hover:bg-brand-accent-hover',
    radius: 'rounded-sm rounded-br-2xl',
    spacing: 'py-2 px-4 gap-2',
    focus: 'focus:ring-2 focus:ring-brand-focus focus:ring-offset-1 focus:outline-none',
}) %}

{# Renders all slots merged into a single class string #}
<button class="{{ btn }}">Click me</button>
```

The `ClassList` object is immutable. Every mutation returns a new instance:

```twig
{# Replace an entire slot #}
{% set danger = btn.override({ color: 'bg-red-600 text-white' }) %}

{# Append classes to an existing slot #}
{% set bordered = btn.extend({ border: 'border-2 border-brand-muted' }) %}

{# Remove slots entirely #}
{% set minimal = btn.without('hover', 'focus') %}

{# Read a single slot #}
{{ btn.get('color') }}
{# Result: bg-brand-accent text-brand-on-accent #}

{# Merge additional utilities on top of all slots #}
{{ btn.merge('mt-8 shadow-lg') }}
```

Works naturally with Craft's `{% tag %}` helper:

```twig
{% tag 'a' with {
    class: craft.tailwind.classes({
        layout: 'inline-flex items-center',
        color: 'bg-brand-accent text-brand-on-accent',
    }),
    href: entry.url,
} %}
    {{ entry.title }}
{% endtag %}
```

### CSS variables

Define CSS custom properties in plugin settings (or `config/tailwind.php`), then render them as a `<style>` tag in your layout. The `include()` method returns a `Twig\Markup` object, so you don't need the `|raw` filter:

```twig
{# In your layout's <head> #}
{{ craft.tailwind.include() }}
{# Renders: <style>:root { --color-brand-primary: #3490dc; ... }</style> #}
```

**CSP and subresource integrity**

Pass attributes to the `<style>` tag — useful for Content Security Policy nonces or other custom attributes:

```twig
{{ craft.tailwind.include({ nonce: cspNonce }) }}
{# Renders: <style nonce="abc123">:root { ... }</style> #}

{{ craft.tailwind.include({ media: 'screen' }) }}
```

The plugin doesn't assume a nonce source — wire it up to your CSP module's per-request nonce however you expose it (a variable, a service, a Twig global).

**Auto-inject**

If you don't want to think about where the tag goes, enable the **Auto-Inject** setting (CP or `config/tailwind.php`). The plugin will register the style block via Craft's `View::registerCss()` on every site request automatically:

```php
// config/tailwind.php
return [
    'autoInject' => true,
    'autoInjectAttributes' => [
        'nonce' => 'static-nonce-or-leave-out',
    ],
];
```

Auto-inject is skipped on console requests and CP requests. **If you use a dynamic per-request CSP nonce, keep auto-inject disabled** and call `{{ craft.tailwind.include({ nonce: cspNonce }) }}` in your layout so the nonce can be resolved at render time.

**Inspecting variables**

Use `craft.tailwind.cssVariables` when you need to look up or iterate variables instead of rendering them:

```twig
{% if craft.tailwind.cssVariables.has('color-brand-primary') %}
    Primary: {{ craft.tailwind.cssVariables.get('color-brand-primary') }}
{% endif %}

{# Raw CSS without a <style> wrapper #}
{{ craft.tailwind.cssVariables.asCss() }}
```

**Sanitization and naming**

The `CssVariables` object sanitizes values to prevent CSS injection. Values containing characters outside the safe set (letters, digits, hyphens, underscores, dots, hashes, commas, parentheses, percent signs, slashes, spaces, and quotes) are silently dropped. In devMode, dropped values are logged as warnings.

Variable names are auto-prefixed with `--` if missing, so both `color-brand` and `--color-brand` resolve to the same property.

### Version detection

The plugin auto-detects whether your project uses Tailwind v3 or v4 and selects the correct merge engine. Detection follows this priority:

1. **CSS signals** -- scans the CSS path for `@import "tailwindcss"` or `@theme` directives (definitive v4 indicators)
2. **Config files** -- looks for `tailwind.config.{js,ts,cjs,mjs}` in the buildchain path (v3 indicator)
3. **Fallback** -- defaults to v4 with a devMode warning

Set `buildchainPath` and `cssPath` in settings to point detection at the right directories. When unset, both default to the project root.

```twig
{{ craft.tailwind.version }}
{# Result: '3' or '4' #}
```

To skip detection entirely, set `tailwindVersion` to `'3'` or `'4'` explicitly.

### Debug toolbar panel

When Craft's debug toolbar is enabled (devMode + a user with debug access), a **Tailwind** panel appears alongside the others. It records every merge operation during the current request and shows:

- **Total calls / unique inputs** — how many times `merge()` or `|twmerge` ran, and how many distinct input strings were seen
- **Cache stats** — hit count, hit rate, and current LRU entry count
- **Per-merge detail** — the input, the resolved output, whether a conflict was actually resolved (vs passthrough), the call count, and **the template that ran the merge**

Use it to answer questions like "why is `bg-red-500` not appearing on the page?" (find the merge input where it was overridden) or "is my cache actually helping?" (check the hit rate).

The panel has no overhead outside of debug-enabled requests — data collection only runs when the debug module is loaded.

## Typography plugin compatibility

The Tailwind Typography plugin (`@tailwindcss/typography`) works out of the box. Both underlying merge engines understand `prose` and its modifiers as first-class utilities, so size and theme conflicts resolve correctly:

```twig
{# Size modifiers resolve last-wins #}
{{ 'prose prose-sm'|twmerge('prose-lg') }}
{# Result: prose prose-lg #}

{# Light/dark variants resolve last-wins #}
{{ 'prose prose-slate'|twmerge('prose-invert') }}
{# Result: prose prose-invert #}
```

A typical rich-text area with editor-controlled size:

```twig
{% set proseSize = entry.proseSize.value ?? 'prose-base' %}

<article class="{{ 'prose prose-slate max-w-none'|twmerge(proseSize) }}">
    {{ entry.body|raw }}
</article>
```

No special configuration required — typography utilities follow the same merge rules as every other Tailwind utility.

## API reference

### Twig filter

| Signature | Description |
|-----------|-------------|
| `'classes'\|twmerge('more classes', ...)` | Merge class strings, last wins per CSS property |

### Template variables (`craft.tailwind`)

| Property / Method | Returns | Description |
|-------------------|---------|-------------|
| `.merge('a', 'b', ...)` | `string` | Merge multiple class strings |
| `.classes({ slot: 'classes' })` | `ClassList` | Named-slot class builder |
| `.version` | `string` | Detected Tailwind version (`'3'` or `'4'`) |
| `.cssVariables` | `CssVariables` | CSS custom properties container |
| `.include(attributes = {})` | `Twig\Markup` | Ready-to-render `<style>` tag — no `\|raw` required |

### ClassList methods

| Method | Returns | Description |
|--------|---------|-------------|
| `__toString()` | `string` | All slots merged into a single class string |
| `.get(slot)` | `?string` | Value of a single slot |
| `.override({ slot: '...' })` | `ClassList` | New instance with replaced slots |
| `.extend({ slot: '...' })` | `ClassList` | New instance with appended slot values |
| `.without('slot', ...)` | `ClassList` | New instance without named slots |
| `.merge('additional')` | `string` | All slots + additional merged to a string |
| `.toArray()` | `array` | Named slots as an associative array |

### CssVariables methods

Use these when you need introspection; for rendering prefer `craft.tailwind.include()` on the variable.

| Method | Returns | Description |
|--------|---------|-------------|
| `__toString()` | `string` | `:root { ... }` CSS block |
| `.asCss()` | `string` | `:root { ... }` CSS block (no `<style>` wrapper) |
| `.get(name)` | `?string` | Value of a single variable |
| `.has(name)` | `bool` | Whether a variable exists |
| `.all()` | `array` | All variables as key-value pairs |
| `.isEmpty()` | `bool` | Whether the collection is empty |

## Credits

This plugin wraps two PHP merge libraries:

- [gehrisandro/tailwind-merge-php](https://github.com/gehrisandro/tailwind-merge-php) (Tailwind v3)
- [tales-from-a-dev/tailwind-merge-php](https://github.com/tales-from-a-dev/tailwind-merge-php) (Tailwind v4)

Brought to you by [CraftPulse](https://craftpulse.com).
