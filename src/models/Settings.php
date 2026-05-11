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
    // =========================================================================
    // = Const Properties
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

    // =========================================================================
    // = Public Properties
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
     * Tailwind class prefix (e.g., 'tw-').
     *
     * Used when the project is configured with a custom prefix
     * in the Tailwind configuration.
     *
     * @var string
     */
    public string $prefix = '';

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

    // =========================================================================
    // = Public Methods
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
     * @author CraftPulse
     * @since 5.0.0
     */
    public function beforeValidate(): bool
    {
        $this->cssVariables = $this->_normalizeEditableTableShape($this->cssVariables);
        $this->autoInjectAttributes = $this->_normalizeEditableTableShape($this->autoInjectAttributes);

        return parent::beforeValidate();
    }

    // =========================================================================
    // = Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
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
        $rules[] = [['autoInject'], 'boolean'];
        $rules[] = [['cacheSize'], 'integer', 'min' => 0, 'max' => 10000];
        $rules[] = [['prefix'], 'string'];
        $rules[] = [['autoInjectAttributes'], 'validateAutoInjectAttributes'];
        $rules[] = [['cssVariables'], 'validateCssVariables'];

        return $rules;
    }

    // =========================================================================
    // = Private Methods
    // =========================================================================

    /**
     * Normalizes editable-table POST input to a flat `name => value` map.
     *
     * Craft's `forms.editableTableField` posts each cell as
     * `field[rowId][colId]=value`, so PHP delivers
     * `[rowId => ['name' => '...', 'value' => '...']]`. This method folds
     * those rows down to the flat associative shape the model declares,
     * while leaving inputs that are already flat (constructor injection,
     * `config/tailwind.php`) untouched.
     *
     * @param array<mixed> $value Raw input — either row format or flat map.
     *
     * @return array<string, string> Normalized flat map.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    private function _normalizeEditableTableShape(array $value): array
    {
        $normalized = [];

        foreach ($value as $key => $entry) {
            if (is_array($entry) && array_key_exists('name', $entry)) {
                $name = (string) $entry['name'];

                if ($name === '') {
                    continue;
                }

                $normalized[$name] = (string) ($entry['value'] ?? '');
                continue;
            }

            $normalized[(string) $key] = (string) $entry;
        }

        return $normalized;
    }
}
