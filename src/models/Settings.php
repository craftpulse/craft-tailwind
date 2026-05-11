<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

namespace craftpulse\tailwind\models;

use craft\base\Model;

/**
 * Plugin settings model for the Tailwind plugin.
 *
 * Holds configuration values for Tailwind version detection,
 * path resolution, CSS variables, merge caching, and class prefix behavior.
 *
 * @author CraftPulse
 * @since 5.0.0
 */
class Settings extends Model
{
    // Const Properties
    // =========================================================================

    /**
     * HTML attributes the auto-inject `<style>` tag may carry.
     *
     * Restricted to a known-safe set so an admin cannot store arbitrary
     * attributes on a tag rendered into every page's `<head>`. Per-request
     * use cases (e.g. dynamic CSP nonces from a request scope) should
     * disable auto-inject and call `craft.tailwind.include()` manually,
     * which has no whitelist because it's developer-controlled.
     */
    public const ALLOWED_AUTO_INJECT_ATTRIBUTES = ['nonce', 'media', 'title'];

    /**
     * Shape allowed for Tailwind class prefixes and typography suffixes.
     *
     * Must start with a letter, then letters, digits, hyphens, or underscores.
     * Shared between {@see self::validatePrefix()} and
     * {@see self::validateTypographyExtras()} — the contract is identical
     * because both validate strings that ultimately participate in a
     * Tailwind class name.
     */
    private const CLASS_SUFFIX_PATTERN = '/^[a-zA-Z][a-zA-Z0-9_-]*$/';

    // Public Properties
    // =========================================================================

    /**
     * The Tailwind version to use for class merging.
     *
     * Accepts 'auto', '3', or '4'. When set to 'auto', the plugin
     * will attempt to detect the version from the project files.
     *
     * @var string
     */
    public string $tailwindVersion = 'auto';

    /**
     * Path to the directory containing `tailwind.config.*` files.
     *
     * Used for v3 detection (or v4 projects using a JS config).
     * When null, the project root is used as the search path.
     *
     * @var ?string
     */
    public ?string $buildchainPath = null;

    /**
     * Path to the directory containing the Tailwind CSS entry file.
     *
     * Used for v4 detection by scanning for `@import "tailwindcss"`
     * or `@theme` directives in CSS/PostCSS files.
     * When null, the project root is used as the search path.
     *
     * @var ?string
     */
    public ?string $cssPath = null;

    /**
     * CSS custom properties to inject as a `:root` style block.
     *
     * Keys must start with `--` (auto-prefixed if missing).
     * Values must be non-empty strings containing safe CSS characters.
     *
     * @var array<string, string>
     */
    public array $cssVariables = [];

    /**
     * Maximum number of merge results to cache in memory.
     *
     * @var int
     */
    public int $cacheSize = 500;

    /**
     * Tailwind class prefix, bare form (no trailing hyphen).
     *
     * The plugin adapts to each Tailwind version's native syntax: v3 emits
     * `{prefix}-{utility}` (e.g. `tw-px-4`) and v4 emits `{prefix}:{utility}`
     * (e.g. `tw:px-4`). Store the bare prefix here (`tw`) and the merge
     * service will append `-` for v3 or pass the bare form for v4 at
     * call time. Leave `null` if the project uses no prefix.
     *
     * Validation rejects a trailing hyphen only when `tailwindVersion` is
     * explicitly `'4'` (the v4 doc syntax forbids it). v3 and `'auto'`
     * stay permissive because `'tw-'` is doc-correct for v3.
     *
     * @var ?string
     */
    public ?string $prefix = null;

    /**
     * Whether to automatically inject CSS variables into the page `<head>`.
     *
     * When true, the plugin registers the CSS variables style block via
     * Craft's `View::registerCss()` on every site request — no manual
     * `{{ craft.tailwind.include() }}` call is needed in your layout.
     * Console requests and CP requests are never auto-injected.
     *
     * @var bool
     */
    public bool $autoInject = false;

    /**
     * Attributes to apply to the auto-injected `<style>` tag.
     *
     * Common keys: `nonce` (for CSP), `media` (e.g. 'print').
     * For per-request dynamic nonces, keep `autoInject` disabled and use
     * `{{ craft.tailwind.include({ nonce: cspNonce }) }}` manually.
     *
     * @var array<string, string>
     */
    public array $autoInjectAttributes = [];

    /**
     * Whether the merge engine resolves `@tailwindcss/typography` conflicts.
     *
     * When `true`, `prose-{size}` and `prose-{color}` classes are merged
     * as mutually-exclusive utilities — `prose prose-sm prose-lg` collapses
     * to `prose prose-lg`. Defaults to `false` because not every project
     * uses the typography plugin and silently resolving `prose-*` classes
     * would surprise users with their own `prose-*` naming conventions.
     *
     * @var bool
     */
    public bool $typography = false;

    /**
     * Custom size suffixes appended to {@see TypographyConfig::DEFAULT_SIZES}.
     *
     * Add suffixes you've registered yourself (e.g. an `@utility prose-huge`
     * block on v4 or a `theme.extend.typography.huge` entry on v3) so the
     * merger treats them as size-group conflicts alongside the defaults.
     * Suffixes are stored without the `prose-` prefix.
     *
     * @var array<int, string>
     */
    public array $typographyExtraSizes = [];

    /**
     * Custom color/theme suffixes appended to {@see TypographyConfig::DEFAULT_COLORS}.
     *
     * Same shape and use as {@see self::$typographyExtraSizes}, but for
     * color/theme variants (e.g. `prose-mybrand`).
     *
     * @var array<int, string>
     */
    public array $typographyExtraColors = [];

    // Public Methods
    // =========================================================================

    /**
     * Validates the `autoInjectAttributes` map.
     *
     * Rejects non-string keys/values and any key outside
     * {@see self::ALLOWED_AUTO_INJECT_ATTRIBUTES}.
     *
     * @return void
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function validateAutoInjectAttributes(): void
    {
        foreach ($this->autoInjectAttributes as $key => $value) {
            if (!in_array($key, self::ALLOWED_AUTO_INJECT_ATTRIBUTES, true)) {
                $this->addError(
                    'autoInjectAttributes',
                    sprintf(
                        'Auto-inject attribute "%s" is not allowed. Allowed: %s.',
                        $key,
                        implode(', ', self::ALLOWED_AUTO_INJECT_ATTRIBUTES),
                    ),
                );
            }
        }
    }

    /**
     * Validates the `prefix` setting.
     *
     * Rejects values whose shape can't be a valid Tailwind prefix (must
     * start with a letter, then letters/digits/hyphens/underscores).
     *
     * Additionally rejects a trailing hyphen only when `tailwindVersion`
     * is explicitly `'4'`. Tailwind v4 expects `prefix(tw)` and emits
     * `tw:px-4`, so a `tw-` value is unambiguously wrong on v4. For v3
     * and `'auto'` we stay permissive — `'tw-'` is the v3-doc-canonical
     * shape, and the merge service strips the trailing hyphen before
     * feeding either engine.
     *
     * @return void
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function validatePrefix(): void
    {
        if ($this->prefix === null || $this->prefix === '') {
            return;
        }

        if (!preg_match(self::CLASS_SUFFIX_PATTERN, $this->prefix)) {
            $this->addError(
                'prefix',
                'Prefix must start with a letter and contain only letters, digits, hyphens, or underscores.',
            );

            return;
        }

        if ($this->tailwindVersion === '4' && str_ends_with($this->prefix, '-')) {
            $this->addError(
                'prefix',
                'Tailwind v4 prefixes use the bare form (no trailing hyphen). '
                . 'Type the prefix as `tw` rather than `tw-`. The plugin emits '
                . '`tw:px-4` for v4, matching the v4 `prefix(tw)` syntax.',
            );
        }
    }

    /**
     * Validates a typography extras list (`typographyExtraSizes` or
     * `typographyExtraColors`).
     *
     * Each entry must match the Tailwind class-suffix shape: start with
     * a letter, then letters, digits, hyphens, or underscores. Empty
     * entries are dropped silently to tolerate trailing blank rows from
     * the CP editable-table input.
     *
     * @param string $attribute The attribute name being validated.
     *
     * @return void
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function validateTypographyExtras(string $attribute): void
    {
        $list = $this->{$attribute};

        if (!is_array($list)) {
            $this->addError($attribute, sprintf('"%s" must be an array of class suffixes.', $attribute));

            return;
        }

        foreach ($list as $entry) {
            if (!is_string($entry) || $entry === '') {
                continue;
            }

            if (!preg_match(self::CLASS_SUFFIX_PATTERN, $entry)) {
                $this->addError(
                    $attribute,
                    sprintf(
                        '"%s" entry "%s" is not a valid class suffix. '
                        . 'Use letters, digits, hyphens, or underscores, starting with a letter.',
                        $attribute,
                        $entry,
                    ),
                );
            }
        }
    }

    /**
     * Validates the `cssVariables` map.
     *
     * Rejects non-string or empty values and values containing characters
     * outside {@see CssVariables::SAFE_VALUE_PATTERN}.
     *
     * @return void
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function validateCssVariables(): void
    {
        foreach ($this->cssVariables as $key => $value) {
            $normalizedKey = str_starts_with($key, '--') ? $key : '--' . $key;

            if ($value === '') {
                $this->addError(
                    'cssVariables',
                    sprintf('CSS variable "%s" must have a non-empty string value.', $normalizedKey),
                );
                continue;
            }

            if (!preg_match(CssVariables::SAFE_VALUE_PATTERN, $value)) {
                $this->addError(
                    'cssVariables',
                    sprintf(
                        'CSS variable "%s" contains unsafe characters. '
                        . 'Allowed: letters, digits, hyphens, underscores, dots, hashes, '
                        . 'commas, parentheses, percent signs, slashes, spaces, and quotes.',
                        $normalizedKey,
                    ),
                );
            }
        }
    }

    /**
     * @inheritdoc
     *
     * Normalizes the editable-table POST shape that Craft's CP delivers
     * for `cssVariables` and `autoInjectAttributes` into the flat
     * `name => value` map the rest of the plugin expects. See
     * {@see self::_normalizeEditableTableShape()} for the shape contract.
     *
     * Also collapses an empty-string `prefix` (the CP form posts `prefix=`
     * when blank) to `null` so storage stays consistent with the property
     * default.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function beforeValidate(): bool
    {
        $this->cssVariables = $this->_normalizeMap($this->cssVariables);
        $this->autoInjectAttributes = $this->_normalizeMap($this->autoInjectAttributes);
        $this->typographyExtraSizes = $this->_normalizeList($this->typographyExtraSizes);
        $this->typographyExtraColors = $this->_normalizeList($this->typographyExtraColors);

        if ($this->prefix === '') {
            $this->prefix = null;
        }

        return parent::beforeValidate();
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     *
     * @return array<int, array<int|string, mixed>>
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['tailwindVersion'], 'required'];
        $rules[] = [['tailwindVersion'], 'in', 'range' => ['auto', '3', '4']];
        $rules[] = [['buildchainPath', 'cssPath'], 'string'];
        $rules[] = [['autoInject', 'typography'], 'boolean'];
        $rules[] = [['cacheSize'], 'integer', 'min' => 0, 'max' => 10000];
        $rules[] = [['prefix'], 'validatePrefix'];
        $rules[] = [['autoInjectAttributes'], 'validateAutoInjectAttributes'];
        $rules[] = [['cssVariables'], 'validateCssVariables'];
        $rules[] = [['typographyExtraSizes', 'typographyExtraColors'], 'validateTypographyExtras'];

        return $rules;
    }

    // Private Methods
    // =========================================================================

    /**
     * Normalizes a two-column editable-table POST into a flat `name => value` map.
     *
     * Craft's `forms.editableTableField` posts each cell as
     * `field[rowId][colId]=value`, so PHP delivers
     * `[rowId => ['name' => ..., 'value' => ...]]`. This method folds those
     * rows into the flat associative shape the model declares, while leaving
     * inputs that are already flat (constructor injection, `config/tailwind.php`)
     * untouched. Rows whose `name` cell is empty are dropped silently —
     * editable tables always submit a trailing blank "add row" placeholder.
     *
     * @param array<mixed> $value Raw input — either row format or flat map.
     * @param string $keyCol The column key holding the map key.
     * @param string $valueCol The column key holding the map value.
     *
     * @return array<string, string> Normalized flat map.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    private function _normalizeMap(array $value, string $keyCol = 'name', string $valueCol = 'value'): array
    {
        $normalized = [];

        foreach ($value as $key => $entry) {
            if (is_array($entry) && array_key_exists($keyCol, $entry)) {
                $name = (string) $entry[$keyCol];

                if ($name === '') {
                    continue;
                }

                $normalized[$name] = (string) ($entry[$valueCol] ?? '');
                continue;
            }

            $normalized[(string) $key] = (string) $entry;
        }

        return $normalized;
    }

    /**
     * Normalizes a single-column editable-table POST into a numeric list.
     *
     * Craft's `forms.editableTableField` posts a single-column table as
     * `[rowId => [colKey => value]]`. This method folds those rows into a
     * flat numeric list, reading the cell at `$colKey` (default `'suffix'`,
     * matching the typography extras tables). Empty entries are dropped
     * (the editable table always submits a trailing blank "add row"
     * placeholder). Flat input from constructor injection or
     * `config/tailwind.php` passes through untouched.
     *
     * @param array<mixed> $value Raw input — either row format or flat list.
     * @param string $colKey The column key whose value to extract from each row.
     *
     * @return array<int, string> Numeric list of values.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    private function _normalizeList(array $value, string $colKey = 'suffix'): array
    {
        $normalized = [];

        foreach ($value as $entry) {
            if (is_array($entry)) {
                $cell = $entry[$colKey] ?? null;

                if (is_string($cell) && $cell !== '') {
                    $normalized[] = $cell;
                }

                continue;
            }

            if (is_string($entry) && $entry !== '') {
                $normalized[] = $entry;
            }
        }

        return $normalized;
    }
}
