<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

namespace craftpulse\tailwind\twig;

use craftpulse\tailwind\Plugin;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Twig extension providing the Tailwind merge filter.
 *
 * Registers the `|twmerge` filter for use in Twig templates.
 * All other functionality is accessible via `craft.tailwind` variables.
 *
 * @author CraftPulse
 * @since 5.0.0
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
     * @since 5.0.0
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
     * @since 5.0.0
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
     * @since 5.0.0
     */
    public function getFunctions(): array
    {
        return [];
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
     * @since 5.0.0
     */
    public function mergeFilter(string $base, string ...$additional): string
    {
        return Plugin::$plugin?->tailwind->merge($base, ...$additional) ?? '';
    }
}
