<!-- craftcms-claude-skills -->
# Security

This plugin has no controllers, no user input parsing, and no DB writes today, so most controller-side rules are dormant. Apply them if/when controllers are added.

## Active rules

- **No secrets in source.** API keys, webhook secrets, anything sensitive — `App::env()`, never hardcoded. The plugin currently has no secrets; keep it that way.
- **No secrets in CLAUDE.md or rules files.** No tokens in commit messages or log output.
- **CSP-friendly auto-inject.** The `autoInjectAttributes` setting must remain a string-keyed map of HTML attributes — do not inject `<style>` tags directly with `echo` or string concatenation. `View::registerCss()` is the only sanctioned path.
- **Twig API surface is read-only.** `craft.tailwind.*` returns merged class strings and CSS variable maps; it must never accept user input that mutates plugin state. If a "set" operation is ever needed, make it a controller action with full guards (next section).

## Known threat-model edges (admin-only)

- **`buildchainPath` / `cssPath` accept any string.** An admin can point version detection at any directory readable by the PHP process. Content is regex-scanned, never reflected to the response, and only used to decide v3 vs v4 — so the worst case is a misfired version detection, not data disclosure. We deliberately do not `realpath()`-gate to the project root because dev environments commonly use sibling buildchain folders. If this changes (e.g. detection ever starts emitting file contents to the response or to logs), revisit and add a project-root constraint.

## Dormant rules — apply if controllers are added

- `$this->requirePermission()` on every controller action that accesses protected resources.
- `$this->requirePostRequest()` on every mutating action.
- `$this->requireAdmin(requireAdminChanges: true)` for settings that modify project config.
- All user input through `Db::parseParam()` or `Db::parseDateParam()` — never raw SQL interpolation.
- Per-resource permission scoping: `"tailwind:action-name:{$entityUid}"`.
- Element authorization via `canView()`, `canSave()`, `canDelete()` on element classes (none exist today).
