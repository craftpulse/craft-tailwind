<!-- craftcms-claude-skills -->
# Architecture

## Current shape

This plugin is a service-oriented Twig helper. It does **not** have controllers, element types, queue jobs, or migrations today. Most rules below are dormant — apply them only if/when those surfaces get added.

- **Services** registered via `setComponents()` in `Plugin::_registerServices()`. Both extend `yii\base\Component`. Access through `Plugin::$plugin->tailwind` or `Plugin::$plugin->versionDetector`, never via global service locator.
- **Twig extension** registered through `View::registerTwigExtension()` in `init()`. Filters and functions live in `TailwindTwigExtension` and delegate to the service.
- **Twig variable** registered via `CraftVariable::EVENT_INIT`. Exposed as `craft.tailwind.*`. Variable methods delegate to the service — never put logic in the variable.
- **Debug panel** registered on `Application::EVENT_BEFORE_REQUEST` so the debug module is available. Recording is opt-in: the service only records when the panel is registered (see `enableRecording()`).
- **Auto-inject** hooks `View::EVENT_BEFORE_RENDER_PAGE_TEMPLATE` and skips console + CP requests. Gate every event registration on a settings check, so disabling the setting fully no-ops the listener.
- **Settings page** rendered via `settingsHtml()` with both the persisted Settings model and the file-based config-from-file map (so the CP can show overridden values).

## General Craft 5 plugin rules

- Business logic in services. If a controller is added, keep it thin: validate, delegate, respond.
- Element operations through services, not controllers or helpers.
- Services extend `yii\base\Component`. Register through `setComponents()`, not `static config()` arrays.
- `MemoizableArray` for cached service lookups — always reset on data changes.
- Project config for entities that sync across environments. Runtime data stays in DB only. (This plugin currently has neither — keep it that way unless a real cross-environment need appears.)
- Events for extensibility: fire before/after events on significant operations. The merge service is the natural place if extension hooks are needed.
- `addSelect()` not `select()` in `beforePrepare()` on element queries — never wipe Craft's base columns. (No queries today; rule applies if added.)
- `site('*')` in queue workers — workers run in primary site context. (No workers today.)
- Scope element queries by owning context (site, section, owner) — never query globally without filters.

## Where extension hooks belong

If functionality grows, add hooks in this order:

1. **Twig API** — extend `TailwindTwigExtension` and `TailwindVariable`. Delegate to the service.
2. **Service events** — fire `EVENT_BEFORE_*` / `EVENT_AFTER_*` on `TailwindService` for class-merge interception, CSS variable resolution, etc. Use Craft's `Event` class with typed event objects in `src/events/`.
3. **Settings** — extend `Settings` model with new properties; surface in `templates/settings.twig`; document file-based overrides via `config/tailwind.php`.

Avoid: adding controllers, migrations, or element types until there's a concrete need. The plugin is intentionally lightweight.
