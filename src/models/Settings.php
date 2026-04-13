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
 * @since 1.0.0
 */
class Settings extends Model
{
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
     * Whether to log merge conflict resolutions in devMode.
     *
     * @var bool
     */
    public bool $enableDevLogging = true;

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

    // =========================================================================
    // = Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     *
     * @return array<int, array<mixed>>
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['tailwindVersion'], 'required'];
        $rules[] = [['tailwindVersion'], 'in', 'range' => ['auto', '3', '4']];
        $rules[] = [['buildchainPath', 'cssPath'], 'string'];
        $rules[] = [['enableDevLogging'], 'boolean'];
        $rules[] = [['cacheSize'], 'integer', 'min' => 0, 'max' => 10000];
        $rules[] = [['prefix'], 'string'];
        $rules[] = [['cssVariables'], function(string $attribute): void {
            if (!is_array($this->$attribute)) {
                $this->addError($attribute, 'CSS variables must be an array.');
                return;
            }

            foreach ($this->$attribute as $key => $value) {
                $normalizedKey = str_starts_with((string) $key, '--') ? $key : '--' . $key;

                if (!is_string($value) || $value === '') {
                    $this->addError(
                        $attribute,
                        sprintf('CSS variable "%s" must have a non-empty string value.', $normalizedKey),
                    );
                }
            }
        }];

        return $rules;
    }
}
