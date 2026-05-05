<!-- craftcms-claude-skills -->
# Migrations

This plugin has no migrations directory and no `Install.php` today. The `schemaVersion` is `1.0.0` purely for plugin-installer bookkeeping. Rules below apply only if a feature legitimately requires schema changes.

- Scaffold with `ddev craft make migration --with-docblocks` from the playground, then move into `src/migrations/` and adjust namespace.
- `Install.php` for the full schema on fresh install. Numbered migrations for incremental changes. Bump `schemaVersion` in `Plugin.php` whenever a numbered migration is added.
- Idempotent: check if column/table exists before creating. `safeDown()` should reverse `safeUp()`.
- Foreign keys with explicit `CASCADE` or `SET NULL` behavior. Index columns used in queries.
- `Craft::$app->getProjectConfig()->muteEvents = true` when writing to project config in migrations — prevents infinite event loops.
- Content migrations (creating sections, fields, entry types) use `Craft::$app->getProjectConfig()->set()` — never direct DB inserts for managed content.
- After adding columns, update the Record class and the element query's `addSelect()`.
- Test cycle: `ddev craft migrate/up` succeeds → `ddev craft migrate/down` reverses cleanly → `ddev craft migrate/up` succeeds again. Run from the playground.
- Deploy: `ddev craft up` runs both migrations and project config apply. Never run `migrate/all` and `project-config/apply` separately.

## Before adding the first migration

Ask: does this need to live in the database, or can it be a Settings model field? Settings flow through the existing CP settings page and `config/tailwind.php` overrides without schema overhead. Database tables are appropriate for: per-element merge caches, recorded merges for analytics, anything multi-row that doesn't belong in project config. They are **not** appropriate for: configuration that belongs in `Settings`, single-row globals, or anything a sensible user would put in a config file.
