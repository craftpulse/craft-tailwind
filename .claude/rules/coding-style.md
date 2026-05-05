<!-- craftcms-claude-skills -->
# Coding Style

- PHPDocs on every class, method, and property. No exceptions.
- `@author CraftPulse` and `@since` tags at the bottom of every method docblock, after a blank line. `@author` and `@since` on classes too.
- `@throws` chains: document every exception including those propagated from called methods.
- `@inheritdoc` only when the parent has a meaningful doc comment.
- Section headers with `// =========================================================================` on every class. Sections used in this plugin: `Static Properties`, `Public Properties`, `Public Methods`, `Protected Methods`, `Private Methods`. Match the existing convention in `src/Plugin.php`.
- Private methods/properties prefixed with underscore: `_myMethod()`, `$_myProp`.
- Imports alphabetical, grouped: PHP → Craft → Plugin → Third-party. One blank line between groups.
- `match` over `switch`. Early returns over `else`. Curly brackets always.
- `declare(strict_types=1)` is NOT used in this plugin's source files (consistent with Craft's own convention).
- Short nullable notation: `?string`, not `string|null`. Typed properties everywhere. `void` return types where applicable.
- `DateTimeHelper` for date parsing/formatting. `Carbon` only inside services that need arithmetic. Never mix in the same class.
- ECS rule set: `craft\ecs\SetList::CRAFT_CMS_4` — the latest set shipped by `craftcms/ecs` and the canonical choice for Craft 5 plugins. There is no `CRAFT_CMS_5` set and there isn't expected to be one.
- PHPStan level 5 (configured in `phpstan.neon`).
- Lints and tests run from the playground via the playground Makefile:
  ```bash
  cd /Users/michtio/dev/craft-plugin-playground
  make v5-phpstan PLUGIN=craft-tailwind
  make v5-ecs     PLUGIN=craft-tailwind
  make v5-ecs-fix PLUGIN=craft-tailwind
  make v5-pest    PLUGIN=craft-tailwind
  ```
  Run all three before commit.
