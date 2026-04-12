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
 * merge caching, and class prefix behavior.
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
        $rules[] = [['enableDevLogging'], 'boolean'];
        $rules[] = [['cacheSize'], 'integer', 'min' => 0, 'max' => 10000];
        $rules[] = [['prefix'], 'string'];

        return $rules;
    }
}
