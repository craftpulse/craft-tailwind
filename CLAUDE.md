<!-- craftcms-claude-skills v1.3.0 -->
# Tailwind — Craft CMS 5 Plugin

@.claude/rules/coding-style.md
@.claude/rules/architecture.md
@.claude/rules/git-workflow.md
@.claude/rules/scaffolding.md
@.claude/rules/security.md
@.claude/rules/testing.md
@.claude/rules/migrations.md

## General

Be critical. We're equals — push back when something doesn't make sense.

Do not excessively use emojis. Do not include AI attribution in commits, PRs, issues, or comments. Output should be indistinguishable from human-authored work.

Do not include "Test plan" sections in PR descriptions.

## Plugin

- **Handle**: `tailwind`
- **Vendor namespace**: `craftpulse\tailwind`
- **Entry class**: `craftpulse\tailwind\Plugin`
- **Author**: CraftPulse
- **Edition target**: Solo (single edition — no edition gating)
- **Schema version**: `1.0.0`
- **Plugin version**: `5.0.0` (matches Craft major)

This is a backend-only plugin. No element types, no controllers, no migrations, no asset bundle, no front-end JS. Surface area: services + Twig extension + Twig variable + debug toolbar panel + CP settings page.

## Tools

Use `ddev` shorthand commands: `ddev composer`, `ddev craft`, `ddev npm`. Never run `php`, `composer`, or `npm` on the host.

Use `gh` for all GitHub operations.

The plugin has no DDEV environment of its own — it's symlinked into a Craft install for testing. The playground is at `/Users/michtio/dev/craft-plugin-playground/cms_v5/cms/` (DDEV project `plugin-playground-v5`). Run plugin-touching commands from there.

## Environment

Lints and tests run inside the playground's DDEV container via the playground Makefile, so the plugin doesn't need its own DDEV. Each target invokes the plugin's local `vendor/bin/<tool>` against its own `phpstan.neon` / `ecs.php`.

```bash
cd /Users/michtio/dev/craft-plugin-playground

make v5-phpstan PLUGIN=craft-tailwind   # PHPStan analysis (level 5)
make v5-ecs     PLUGIN=craft-tailwind   # ECS code style (CRAFT_CMS_4 set)
make v5-ecs-fix PLUGIN=craft-tailwind   # ECS auto-fix
make v5-pest    PLUGIN=craft-tailwind   # Pest unit suite
```

If `vendor/` is missing or built against a different PHP version, the targets auto-run `composer install` inside the container. If the lockfile is incompatible with the container's PHP version (because it was last updated on a different host), run `composer update` once via:

```bash
cd cms_v5 && ddev exec --dir=/Users/Shared/dev/craft-plugins/v5/craft-tailwind "composer update --no-interaction"
```

## Plugin Structure

```
src/
├── Plugin.php                   # Entry point — registers services, twig ext, variable, debug panel, auto-inject
├── debug/
│   └── TailwindPanel.php        # Yii debug toolbar panel
├── models/
│   ├── ClassList.php            # Named-slot class collection model
│   ├── CssVariables.php         # CSS custom property model (asCss())
│   └── Settings.php             # Plugin settings model
├── services/
│   ├── TailwindService.php      # Class merging, CSS variables, recording for debug panel
│   └── VersionDetector.php      # Detects Tailwind v3 vs v4 from project files
├── templates/
│   ├── settings.twig            # CP settings page
│   └── debug/                   # Debug toolbar panel templates
├── twig/
│   └── TailwindTwigExtension.php # Twig functions/filters
└── variables/
    └── TailwindVariable.php     # craft.tailwind.* template API
```

## Companion Skills

When working on this plugin, the following Claude skills carry the deep knowledge — load them as needed:

- **`craft-php-guidelines`** — PHP coding standards (PHPDocs, section headers, naming). Load on any PHP edit.
- **`craftcms`** — Craft 5 plugin extend surface (services, events, twig extensions, debug panels, project config).
- **`ddev`** — DDEV commands and config.
- **`tailwind-v3`** / **`tailwind-v4`** — relevant when reasoning about what classes the plugin merges (the plugin has to understand both v3 and v4 conflict resolution).

## Paths

- **Dev root**: `/Users/michtio/dev/` — parent folder for all projects. The planner clones public repos here for research/audits (`/Users/michtio/dev/research/`).
- **Test playground**: `/Users/michtio/dev/craft-plugin-playground/cms_v5/cms/` — DDEV project `plugin-playground-v5`. Plugin is symlinked here. Logs at `storage/logs/`.
- **Research folder**: `/Users/michtio/dev/research/` — ephemeral. Shallow clones for plugin audits and pattern research.

## Permissions

`.claude/settings.local.json` pre-approves DDEV, git, and `gh` commands so agents run without permission prompts. The file is gitignored — adjust locally as needed. If a command is being blocked, check this file first.

## Documentation

- Plugin development: https://craftcms.com/docs/5.x/extend/
- Class reference: https://docs.craftcms.com/api/v5/
- Tailwind v3 docs: https://v3.tailwindcss.com/docs
- Tailwind v4 docs: https://tailwindcss.com/docs
- Craft source: `vendor/craftcms/cms/src/`
