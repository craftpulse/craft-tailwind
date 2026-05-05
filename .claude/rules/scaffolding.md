<!-- craftcms-claude-skills -->
# Scaffolding

- Always use `ddev craft make <type> --with-docblocks` to scaffold new components. Run from the playground at `/Users/michtio/dev/craft-plugin-playground/cms_v5/cms/`, then move generated files into the plugin's `src/` tree and adjust the namespace from the playground module to `craftpulse\tailwind`.
- Never manually create boilerplate that the generator handles.
- After scaffolding, customize: add the `// =====...` section headers, `@author CraftPulse`, `@since 5.x.x`, `@throws` chains, and the project naming conventions (`_method` for private members).
- Available generators: `element-type`, `service`, `controller`, `command`, `queue-job`, `model`, `record`, `field-type`, `validator`, `widget-type`, `utility`, `asset-bundle`, `behavior`, `twig-extension`, `element-action`, `element-condition-rule`, `element-exporter`, `gql-directive`.
- Most relevant for this plugin: `service` (if splitting `TailwindService`), `model` (new settings shapes or value objects), `twig-extension` (additional filters/functions). Less likely but possible: `command` (CLI utilities for class-list dumping), `widget-type` (CP debug widget). Avoid generating element types, controllers, migrations, or asset bundles unless an actual feature requires one — see `architecture.md`.
