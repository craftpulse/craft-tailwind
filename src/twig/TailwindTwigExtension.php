<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

namespace craftpulse\tailwind\twig;

use craftpulse\tailwind\models\ClassList;
use craftpulse\tailwind\Plugin;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Twig extension providing Tailwind merge filters and functions.
 *
 * Registers the `twmerge` filter, `twmerge` function, and `twclasses` function
 * for use in Twig templates.
 *
 * @author CraftPulse
 * @since 1.0.0
 */
class TailwindTwigExtension extends AbstractExtension
{
    // =========================================================================
    // = Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     *
     * @return string The extension name.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function getName(): string
    {
        return 'Tailwind';
    }

    /**
     * @inheritdoc
     *
     * @return array<int, TwigFilter> The registered Twig filters.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('twmerge', [$this, 'mergeFilter'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * @inheritdoc
     *
     * @return array<int, TwigFunction> The registered Twig functions.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('twmerge', [$this, 'mergeFunction'], ['is_safe' => ['html']]),
            new TwigFunction('twclasses', [$this, 'classesFunction'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Twig filter callback for merging Tailwind classes.
     *
     * Usage: `{{ 'px-4 bg-red-500'|twmerge('bg-blue-500 mt-4') }}`
     *
     * @param string $base The base CSS class string.
     * @param string ...$additional Additional class strings to merge.
     *
     * @return string The merged CSS class string.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function mergeFilter(string $base, string ...$additional): string
    {
        return Plugin::$plugin?->tailwind->merge($base, ...$additional) ?? '';
    }

    /**
     * Twig function callback for merging Tailwind classes.
     *
     * Usage: `{{ twmerge('px-4 bg-red-500', 'bg-blue-500 mt-4') }}`
     *
     * @param string ...$classes One or more CSS class strings to merge.
     *
     * @return string The merged CSS class string.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function mergeFunction(string ...$classes): string
    {
        return Plugin::$plugin?->tailwind->merge(...$classes) ?? '';
    }

    /**
     * Twig function callback for creating a named-slot class builder.
     *
     * Usage: `{{ twclasses({ layout: 'flex gap-4', color: 'bg-blue-500' }) }}`
     *
     * @param array<string, string> $slots Named slots of CSS class strings.
     *
     * @return ClassList The class list builder instance.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function classesFunction(array $slots): ClassList
    {
        return Plugin::$plugin?->tailwind->classes($slots);
    }
}
