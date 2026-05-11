<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

namespace craftpulse\tailwind\models;

/**
 * Resolved typography conflict-group configuration.
 *
 * Holds the size and color suffixes the merge engine should treat as
 * mutually-exclusive `prose-{suffix}` utilities. Defaults match the
 * sizes and themes shipped by `@tailwindcss/typography` 0.5.x; users
 * can register additional suffixes via plugin settings or
 * `config/tailwind.php` to cover custom themes (e.g. a `prose-mybrand`
 * declared as an `@utility` block on v4 or under `theme.extend.typography`
 * in a v3 `tailwind.config.js`).
 *
 * Dropping a default isn't supported and isn't useful — Tailwind only
 * emits classes you actually reference, so an unused conflict-group
 * entry costs nothing.
 *
 * @author CraftPulse
 * @since 5.0.0
 */
class TypographyConfig
{
    // Const Properties
    // =========================================================================

    /**
     * Size suffixes shipped by `@tailwindcss/typography`.
     *
     * Each becomes a `prose-{suffix}` class that conflicts with the others
     * (so `prose-sm prose-lg` resolves to `prose-lg`).
     */
    public const DEFAULT_SIZES = ['sm', 'base', 'lg', 'xl', '2xl'];

    /**
     * Color/theme suffixes shipped by `@tailwindcss/typography`.
     *
     * Each becomes a `prose-{suffix}` class that conflicts with the others.
     * `invert` is included because it ships as a theme variant alongside
     * the color names.
     */
    public const DEFAULT_COLORS = ['gray', 'slate', 'zinc', 'neutral', 'stone', 'invert'];

    // Private Properties
    // =========================================================================

    /**
     * Additional size suffixes beyond {@see self::DEFAULT_SIZES}.
     *
     * @var array<int, string>
     */
    private array $_extraSizes;

    /**
     * Additional color/theme suffixes beyond {@see self::DEFAULT_COLORS}.
     *
     * @var array<int, string>
     */
    private array $_extraColors;

    /**
     * Resolved size list (defaults followed by deduped extras).
     *
     * Pre-computed in the constructor because the inputs are immutable
     * and the merger getters read this every cache miss.
     *
     * @var array<int, string>
     */
    private array $_sizes;

    /**
     * Resolved color list (defaults followed by deduped extras).
     *
     * @var array<int, string>
     */
    private array $_colors;

    /**
     * Cached signature for {@see self::signature()}.
     *
     * Computed lazily on first access and reused for the lifetime of the
     * instance, since the inputs are immutable.
     *
     * @var ?string
     */
    private ?string $_signature = null;

    // Public Methods
    // =========================================================================

    /**
     * Constructor.
     *
     * @param array<int, string> $extraSizes Additions to {@see self::DEFAULT_SIZES}.
     * @param array<int, string> $extraColors Additions to {@see self::DEFAULT_COLORS}.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function __construct(array $extraSizes = [], array $extraColors = [])
    {
        $this->_extraSizes = array_values(array_unique($extraSizes));
        $this->_extraColors = array_values(array_unique($extraColors));
        $this->_sizes = array_values(array_unique([...self::DEFAULT_SIZES, ...$this->_extraSizes]));
        $this->_colors = array_values(array_unique([...self::DEFAULT_COLORS, ...$this->_extraColors]));
    }

    /**
     * Returns the full size list (defaults followed by extras).
     *
     * @return array<int, string> The size suffixes.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function getSizes(): array
    {
        return $this->_sizes;
    }

    /**
     * Returns the full color list (defaults followed by extras).
     *
     * @return array<int, string> The color suffixes.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function getColors(): array
    {
        return $this->_colors;
    }

    /**
     * Returns the configured extra size suffixes.
     *
     * @return array<int, string> The user-added size suffixes (defaults excluded).
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function getExtraSizes(): array
    {
        return $this->_extraSizes;
    }

    /**
     * Returns the configured extra color suffixes.
     *
     * @return array<int, string> The user-added color suffixes (defaults excluded).
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function getExtraColors(): array
    {
        return $this->_extraColors;
    }

    /**
     * Returns the merge-engine configuration for typography conflict groups.
     *
     * The resulting array slots straight into either underlying merge
     * library (`TailwindMerge::factory()->withConfiguration(...)` for v3
     * or `new TailwindMerge(...)` for v4). Both libraries share the
     * `classGroups` shape — a list of `[prefix => [suffixes]]` entries
     * grouped by mutually-exclusive utility.
     *
     * Sizes and colors are intentionally separate groups: a size and a
     * color can coexist on the same element (e.g. `prose-lg prose-invert`).
     *
     * @return array<string, array<string, array<int, array<string, array<int, string>>>>>
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function toMergeConfig(): array
    {
        return [
            'classGroups' => [
                'prose-size' => [['prose' => $this->_sizes]],
                'prose-color' => [['prose' => $this->_colors]],
            ],
        ];
    }

    /**
     * Returns a deterministic signature for the current configuration.
     *
     * Consumed by `TailwindService` to detect when the merger needs to
     * rebuild — two `TypographyConfig` instances with the same extras
     * always produce the same signature, and any extras change produces
     * a different one. Both lists are sorted before hashing so any input
     * ordering produces the same signature: a conflict-group entry's
     * position carries no semantic meaning, so neither reordering rows
     * in the CP editable-table nor any future shuffle of the defaults
     * should trigger a rebuild.
     *
     * @return string Short opaque identifier suitable for cache-key use.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function signature(): string
    {
        if ($this->_signature !== null) {
            return $this->_signature;
        }

        $sizes = $this->_sizes;
        $colors = $this->_colors;
        sort($sizes);
        sort($colors);

        return $this->_signature = sha1(implode(',', $sizes) . '|' . implode(',', $colors));
    }

    /**
     * Returns a debug-panel snapshot of the resolved configuration.
     *
     * Bundles the full lists and the user-added extras into the shape
     * {@see \craftpulse\tailwind\debug\TailwindPanel::save()} embeds in
     * its panel data. Centralized here so the panel doesn't need to know
     * the internal structure.
     *
     * @return array{sizes: array<int, string>, colors: array<int, string>, extraSizes: array<int, string>, extraColors: array<int, string>}
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function toPanelData(): array
    {
        return [
            'sizes' => $this->_sizes,
            'colors' => $this->_colors,
            'extraSizes' => $this->_extraSizes,
            'extraColors' => $this->_extraColors,
        ];
    }
}
