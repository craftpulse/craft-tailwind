<!-- craftcms-claude-skills -->
# Testing

- **Pest 3** (`pestphp/pest`). Tests live in `tests/Unit/` with `tests/Pest.php` and `tests/TestCase.php` as bootstrap. Suite registered in `phpunit.xml` (`Unit` testsuite).
- **Run from the playground** because the plugin has no DDEV of its own:
  ```bash
  # from /Users/michtio/dev/craft-plugin-playground/cms_v5/cms/
  ddev exec vendor/bin/pest --filter=ClassListTest          # targeted
  ddev exec vendor/bin/pest                                 # full suite
  ```
  Do not run `vendor/bin/pest` on the host — it bypasses DDEV's PHP version.
- Write tests alongside each layer. Service tests with the service. Model tests with the model. The current suite is unit-only — keep it that way unless a real integration concern appears.
- Use `--filter=ClassName` for targeted runs during development. Full suite before committing.
- Test edge cases: empty class lists, conflicting Tailwind utilities, version-detection misses, settings with file-based overrides masking persisted values.
- Pure-function nature of class merging means most coverage can stay at the unit level. No factories, no fixtures, no DB needed for `TailwindService::merge()` and `ClassList`.
- For `VersionDetector`: stub the filesystem reads — don't hit real `package.json` or `tailwind.config.*` from tests. Use temp directories or in-memory paths.
- `Pest.php` and `TestCase.php` are the only places to add global test setup. Don't sprinkle `beforeEach` across files for the same setup.
