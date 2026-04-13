<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

namespace craftpulse\tailwind\services;

use Craft;
use craft\base\Component;
use craftpulse\tailwind\models\ClassList;
use craftpulse\tailwind\models\CssVariables;
use craftpulse\tailwind\Plugin;
use TailwindMerge\TailwindMerge as TailwindMergeV3;
use TalesFromADev\TailwindMerge\TailwindMerge as TailwindMergeV4;

/**
 * Main Tailwind service providing class merging and named-slot class builders.
 *
 * Wraps the appropriate PHP merge engine based on the detected or configured
 * Tailwind version. Provides an LRU cache for merge results and integrates
 * with Craft's logging in devMode.
 *
 * @author CraftPulse
 * @since 1.0.0
 */
class TailwindService extends Component
{
    // =========================================================================
    // = Private Properties
    // =========================================================================

    /**
     * The initialized v3 merge engine instance.
     *
     * @var ?TailwindMergeV3
     */
    private ?TailwindMergeV3 $_mergerV3 = null;

    /**
     * The initialized v4 merge engine instance.
     *
     * @var ?TailwindMergeV4
     */
    private ?TailwindMergeV4 $_mergerV4 = null;

    /**
     * LRU cache of merge results.
     *
     * @var array<string, string>
     */
    private array $_cache = [];

    /**
     * Ordered list of cache keys for LRU eviction.
     *
     * @var array<int, string>
     */
    private array $_cacheKeys = [];

    // =========================================================================
    // = Public Methods
    // =========================================================================

    /**
     * Merges one or more Tailwind CSS class strings, resolving conflicts.
     *
     * The last occurrence of conflicting utility classes wins. Results
     * are cached in an LRU cache for performance.
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
        $input = implode(' ', $classes);
        $input = trim(preg_replace('/\s+/', ' ', $input) ?? $input);

        if ($input === '') {
            return '';
        }

        // Check LRU cache
        $cacheKey = $input;
        if (isset($this->_cache[$cacheKey])) {
            // Move to end (most recently used)
            $this->_touchCacheKey($cacheKey);

            return $this->_cache[$cacheKey];
        }

        $result = $this->_getMerger()->merge($input);

        // Log conflict resolution in devMode
        if ($this->_shouldLog() && $result !== $input) {
            Craft::info(
                sprintf('Tailwind merge: "%s" → "%s"', $input, $result),
                'tailwind',
            );
        }

        // Store in LRU cache
        $this->_addToCache($cacheKey, $result);

        return $result;
    }

    /**
     * Creates a new named-slot class builder.
     *
     * Each slot holds a named concern (layout, color, font, etc.).
     * The returned object is immutable — all mutating methods return new instances.
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
        return new ClassList(
            $slots,
            fn(string ...$args): string => $this->merge(...$args),
        );
    }

    /**
     * Returns the detected Tailwind version string.
     *
     * @return string The version: '3' or '4'.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function getVersion(): string
    {
        $settings = Plugin::$plugin?->getSettings();

        return $this->_getVersionDetector()->detect(
            $settings->tailwindVersion ?? 'auto',
            $settings->buildchainPath ?? null,
            $settings->cssPath ?? null,
        );
    }

    /**
     * Returns the configured CSS variables as a CssVariables instance.
     *
     * @return CssVariables The CSS variables container.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function cssVariables(): CssVariables
    {
        $settings = Plugin::$plugin?->getSettings();

        return new CssVariables($settings->cssVariables ?? []);
    }

    // =========================================================================
    // = Private Methods
    // =========================================================================

    /**
     * Gets or creates the appropriate merge engine for the detected version.
     *
     * @return TailwindMergeV3|TailwindMergeV4 The merge engine instance.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    private function _getMerger(): TailwindMergeV3|TailwindMergeV4
    {
        $version = $this->getVersion();
        $settings = Plugin::$plugin?->getSettings();
        $prefix = $settings->prefix ?? '';

        return match ($version) {
            VersionDetector::VERSION_4 => $this->_getMergerV4(),
            default => $this->_getMergerV3($prefix),
        };
    }

    /**
     * Gets or initializes the v3 merge engine.
     *
     * @param string $prefix The Tailwind class prefix.
     *
     * @return TailwindMergeV3 The v3 merge engine instance.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    private function _getMergerV3(string $prefix = ''): TailwindMergeV3
    {
        if ($this->_mergerV3 === null) {
            $factory = TailwindMergeV3::factory();

            if ($prefix !== '') {
                $factory = $factory->withConfiguration([
                    'prefix' => $prefix,
                ]);
            }

            $this->_mergerV3 = $factory->make();
        }

        return $this->_mergerV3;
    }

    /**
     * Gets or initializes the v4 merge engine.
     *
     * @return TailwindMergeV4 The v4 merge engine instance.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    private function _getMergerV4(): TailwindMergeV4
    {
        if ($this->_mergerV4 === null) {
            $this->_mergerV4 = new TailwindMergeV4();
        }

        return $this->_mergerV4;
    }

    /**
     * Gets the version detector service.
     *
     * @return VersionDetector The version detector instance.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    private function _getVersionDetector(): VersionDetector
    {
        return Plugin::$plugin?->versionDetector;
    }

    /**
     * Determines whether merge logging should be enabled.
     *
     * Logging is enabled when both devMode is active and the plugin
     * setting `enableDevLogging` is true.
     *
     * @return bool Whether to log merge results.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    private function _shouldLog(): bool
    {
        /** @var \craft\web\Application $app */
        $app = Craft::$app;
        $config = $app->getConfig();

        if (!$config->getGeneral()->devMode) {
            return false;
        }

        $settings = Plugin::$plugin?->getSettings();

        return $settings->enableDevLogging ?? false;
    }

    /**
     * Adds a merge result to the LRU cache.
     *
     * Evicts the oldest entry when the cache exceeds the configured size.
     *
     * @param string $key The cache key (input string).
     * @param string $value The cache value (merged result).
     *
     * @return void
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    private function _addToCache(string $key, string $value): void
    {
        $maxSize = Plugin::$plugin?->getSettings()->cacheSize ?? 500;

        // Evict oldest if at capacity
        if (count($this->_cache) >= $maxSize && !isset($this->_cache[$key])) {
            $oldestKey = array_shift($this->_cacheKeys);

            if ($oldestKey !== null) {
                unset($this->_cache[$oldestKey]);
            }
        }

        $this->_cache[$key] = $value;
        $this->_cacheKeys[] = $key;
    }

    /**
     * Moves a cache key to the end of the LRU queue.
     *
     * @param string $key The cache key to touch.
     *
     * @return void
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    private function _touchCacheKey(string $key): void
    {
        $index = array_search($key, $this->_cacheKeys, true);

        if ($index !== false) {
            unset($this->_cacheKeys[$index]);
            $this->_cacheKeys = array_values($this->_cacheKeys);
        }

        $this->_cacheKeys[] = $key;
    }
}
