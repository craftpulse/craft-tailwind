<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

namespace craftpulse\tailwind\variables;

use craftpulse\tailwind\models\ClassList;
use craftpulse\tailwind\models\CssVariables;
use craftpulse\tailwind\Plugin;

/**
 * Template variable class for the Tailwind plugin.
 *
 * Provides access to Tailwind merge functionality via `craft.tailwind`
 * in Twig templates.
 *
 * @author CraftPulse
 * @since 1.0.0
 */
class TailwindVariable
{
    // =========================================================================
    // = Public Methods
    // =========================================================================

    /**
     * Merges one or more Tailwind CSS class strings.
     *
     * Usage: `{{ craft.tailwind.merge('px-4 bg-red-500', 'bg-blue-500') }}`
     *
     * @param string ...$classes One or more CSS class strings to merge.
     *
     * @return string The merged CSS class string.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function merge(string ...$classes): string
    {
        return Plugin::$plugin?->tailwind->merge(...$classes) ?? '';
    }

    /**
     * Creates a named-slot class builder.
     *
     * Usage: `{{ craft.tailwind.classes({ layout: 'flex', color: 'bg-blue-500' }) }}`
     *
     * @param array<string, string> $slots Named slots of CSS class strings.
     *
     * @return ClassList The class list builder instance.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function classes(array $slots): ClassList
    {
        return Plugin::$plugin?->tailwind->classes($slots);
    }

    /**
     * Returns the detected Tailwind version.
     *
     * Usage: `{{ craft.tailwind.version }}`
     *
     * @return string The version: '3' or '4'.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function getVersion(): string
    {
        return Plugin::$plugin?->tailwind->getVersion() ?? '4';
    }

    /**
     * Returns the configured CSS variables container.
     *
     * Usage: `{{ craft.tailwind.cssVariables.asStyleTag() }}`
     *
     * @return CssVariables The CSS variables container.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function getCssVariables(): CssVariables
    {
        return Plugin::$plugin?->tailwind->cssVariables() ?? new CssVariables([]);
    }
}
