<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

namespace craftpulse\tailwind\services;

use craft\base\Component;
use craftpulse\tailwind\models\ClassList;
use craftpulse\tailwind\models\CssVariables;
use craftpulse\tailwind\models\Settings;
use craftpulse\tailwind\Plugin;
use TailwindMerge\TailwindMerge as TailwindMergeV3;
use TalesFromADev\TailwindMerge\TailwindMerge as TailwindMergeV4;
use Twig\Template;

/**
 * Main Tailwind service providing class merging and named-slot class builders.
 *
 * Wraps the appropriate PHP merge engine based on the detected or configured
 * Tailwind version. Provides an LRU cache for merge results and records
 * every merge operation for the debug toolbar panel.
 *
 * @author CraftPulse
 * @since 5.0.0
 */
class TailwindService extends Component
{
    // =========================================================================
    // = Public Properties
    // =========================================================================

    /**
     * Optional Settings override.
     *
     * When set, takes precedence over `Plugin::$plugin->getSettings()`.
     * Provided as a Yii-component-style injection seam so unit tests can
     * exercise specific configurations (e.g. small `cacheSize`) without
     * bootstrapping the full plugin.
     *
     * @var ?Settings
     */
    public ?Settings $settings = null;

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
     * The prefix the v3 merger was last initialized with.
     *
     * Tracked so a settings change (e.g. via the `$settings` injection seam)
     * rebuilds the merger when the prefix differs, instead of silently
     * serving merges from a stale engine configured with the old prefix.
     *
     * @var ?string
     */
    private ?string $_mergerV3Prefix = null;

    /**
     * The initialized v4 merge engine instance.
     *
     * @var ?TailwindMergeV4
     */
    private ?TailwindMergeV4 $_mergerV4 = null;

    /**
     * LRU cache of merge results.
     *
     * Insertion order doubles as recency: a touched key is removed and
     * re-inserted, moving it to the end of the iteration order. The oldest
     * key is `array_key_first()`.
     *
     * @var array<string, string>
     */
    private array $_cache = [];

    /**
     * Total cache hits observed during the current request.
     *
     * @var int
     */
    private int $_cacheHitCount = 0;

    /**
     * Total cache misses observed during the current request.
     *
     * @var int
     */
    private int $_cacheMissCount = 0;

    /**
     * Memoized CSS variables container for the current request.
     *
     * @var ?CssVariables
     */
    private ?CssVariables $_cssVariables = null;

    /**
     * Recorded merge operations for the debug panel.
     *
     * Each entry is keyed by the merge input string for deduplication.
     *
     * @var array<string, array{input: string, output: string, resolved: bool, template: ?string, line: ?int, count: int}>
     */
    private array $_merges = [];

    /**
     * Whether to collect merge recordings for the debug panel.
     *
     * Enabled by the plugin only when the Yii debug module is loaded,
     * so production requests don't pay for backtrace resolution.
     *
     * @var bool
     */
    private bool $_recording = false;

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
     * @since 5.0.0
     */
    public function merge(string ...$classes): string
    {
        $input = implode(' ', $classes);
        $input = trim(preg_replace('/\s+/', ' ', $input) ?? $input);

        if ($input === '') {
            return '';
        }

        if (isset($this->_cache[$input])) {
            $result = $this->_cache[$input];

            // Move to end (LRU touch) — unset + set is O(1) and preserves
            // PHP's insertion-order array semantics.
            unset($this->_cache[$input]);
            $this->_cache[$input] = $result;

            $this->_cacheHitCount++;

            if ($this->_recording) {
                $this->_recordMerge($input, $result);
            }

            return $result;
        }

        $result = $this->_getMerger()->merge($input);
        $this->_addToCache($input, $result);
        $this->_cacheMissCount++;

        if ($this->_recording) {
            $this->_recordMerge($input, $result);
        }

        return $result;
    }

    /**
     * Enables merge recording for the debug panel.
     *
     * Called by the plugin when the debug module is detected. When disabled,
     * merges run with no recording overhead.
     *
     * @return void
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function enableRecording(): void
    {
        $this->_recording = true;
    }

    /**
     * Returns the recorded merge operations for the current request.
     *
     * Consumed by the debug toolbar panel. Each entry describes a unique
     * merge input, including how many times it was called, whether it
     * resolved a conflict, and the originating template and line.
     *
     * @return array<int, array{input: string, output: string, resolved: bool, template: ?string, line: ?int, count: int}>
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function getRecordedMerges(): array
    {
        return array_values($this->_merges);
    }

    /**
     * Returns the current cache entry count.
     *
     * @return int Number of entries currently held in the LRU cache.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function getCacheCount(): int
    {
        return count($this->_cache);
    }

    /**
     * Returns the total number of cache hits observed during this request.
     *
     * @return int The cache hit count.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function getCacheHitCount(): int
    {
        return $this->_cacheHitCount;
    }

    /**
     * Returns the total number of cache misses observed during this request.
     *
     * @return int The cache miss count.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function getCacheMissCount(): int
    {
        return $this->_cacheMissCount;
    }

    /**
     * Clears the request-scoped merge cache and memoized state.
     *
     * Useful in long-running runtimes (queue workers, Octane, RoadRunner)
     * where the service instance survives across requests. Resets the LRU
     * cache, hit/miss counters, recorded merges, and the memoized CSS
     * variables container. The recording-enabled flag is intentionally
     * preserved so the debug module's once-per-process registration stays
     * in effect across requests.
     *
     * @return void
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function clearCache(): void
    {
        $this->_cache = [];
        $this->_cacheHitCount = 0;
        $this->_cacheMissCount = 0;
        $this->_cssVariables = null;
        $this->_merges = [];
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
     * @since 5.0.0
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
     * @since 5.0.0
     */
    public function getVersion(): string
    {
        $settings = $this->_settings();

        return $this->_getVersionDetector()->detect(
            $settings?->tailwindVersion ?? 'auto',
            $settings?->buildchainPath,
            $settings?->cssPath,
        );
    }

    /**
     * Returns the configured CSS variables as a CssVariables instance.
     *
     * Memoized for the request lifetime. Call `clearCache()` to invalidate
     * after settings changes in long-running runtimes.
     *
     * @return CssVariables The CSS variables container.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function cssVariables(): CssVariables
    {
        if ($this->_cssVariables === null) {
            $settings = $this->_settings();
            $this->_cssVariables = new CssVariables($settings?->cssVariables ?? []);
        }

        return $this->_cssVariables;
    }

    // =========================================================================
    // = Private Methods
    // =========================================================================

    /**
     * Resolves the settings instance to read configuration from.
     *
     * Falls back from the public `$settings` injection seam to the plugin
     * singleton so production code paths are unchanged while tests can
     * exercise specific configurations without bootstrapping the plugin.
     *
     * @return ?Settings The active settings, or null when neither source is set.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    private function _settings(): ?Settings
    {
        return $this->settings ?? Plugin::$plugin?->getSettings();
    }

    /**
     * Gets or creates the appropriate merge engine for the detected version.
     *
     * @return TailwindMergeV3|TailwindMergeV4 The merge engine instance.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    private function _getMerger(): TailwindMergeV3|TailwindMergeV4
    {
        $version = $this->getVersion();
        $prefix = $this->_settings()?->prefix ?? '';

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
     * @since 5.0.0
     */
    private function _getMergerV3(string $prefix = ''): TailwindMergeV3
    {
        if ($this->_mergerV3 !== null && $this->_mergerV3Prefix === $prefix) {
            return $this->_mergerV3;
        }

        $factory = TailwindMergeV3::factory();

        if ($prefix !== '') {
            $factory = $factory->withConfiguration([
                'prefix' => $prefix,
            ]);
        }

        $this->_mergerV3 = $factory->make();
        $this->_mergerV3Prefix = $prefix;

        return $this->_mergerV3;
    }

    /**
     * Gets or initializes the v4 merge engine.
     *
     * @return TailwindMergeV4 The v4 merge engine instance.
     *
     * @author CraftPulse
     * @since 5.0.0
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
     * @since 5.0.0
     */
    private function _getVersionDetector(): VersionDetector
    {
        return Plugin::$plugin?->versionDetector ?? new VersionDetector();
    }

    /**
     * Records a merge operation for the debug panel.
     *
     * Deduplicates by input string, incrementing a call counter on repeat
     * calls. Captures the calling template name by walking the backtrace
     * for a `Twig\Template` frame.
     *
     * @param string $input The normalized merge input string.
     * @param string $output The merge result.
     *
     * @return void
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    private function _recordMerge(string $input, string $output): void
    {
        if (isset($this->_merges[$input])) {
            $this->_merges[$input]['count']++;

            return;
        }

        [$template, $line] = $this->_resolveCallSite();

        $this->_merges[$input] = [
            'input' => $input,
            'output' => $output,
            'resolved' => $input !== $output,
            'template' => $template,
            'line' => $line,
            'count' => 1,
        ];
    }

    /**
     * Resolves the calling Twig template name and source line via the backtrace.
     *
     * Uses the same technique as `Twig\Error\Error::guessTemplateInfo()`:
     * finds the nearest Twig template on the stack, takes its compiled PHP
     * file path, then walks the trace for a frame whose file matches and uses
     * the template's `getDebugInfo()` map to translate the compiled PHP line
     * into the original template line.
     *
     * @return array{0: ?string, 1: ?int} Tuple of template name and source line.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    private function _resolveCallSite(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS | DEBUG_BACKTRACE_PROVIDE_OBJECT, 25);

        $template = null;
        foreach ($trace as $frame) {
            $object = $frame['object'] ?? null;

            if ($object instanceof Template) {
                $template = $object;
                break;
            }
        }

        if ($template === null) {
            return [null, null];
        }

        $compiledFile = (new \ReflectionObject($template))->getFileName();
        $name = $template->getTemplateName();

        if ($compiledFile === false) {
            return [$name, null];
        }

        foreach ($trace as $frame) {
            if (!isset($frame['file'], $frame['line']) || $frame['file'] !== $compiledFile) {
                continue;
            }

            foreach ($template->getDebugInfo() as $phpLine => $twigLine) {
                if ($phpLine <= $frame['line']) {
                    return [$name, $twigLine];
                }
            }

            break;
        }

        return [$name, null];
    }

    /**
     * Adds a merge result to the LRU cache.
     *
     * Evicts the oldest entries when the cache exceeds the configured size.
     * A `cacheSize` of `0` disables caching entirely.
     *
     * @param string $key The cache key (input string).
     * @param string $value The cache value (merged result).
     *
     * @return void
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    private function _addToCache(string $key, string $value): void
    {
        $maxSize = $this->_settings()?->cacheSize ?? 500;

        if ($maxSize <= 0) {
            return;
        }

        while (count($this->_cache) >= $maxSize) {
            // The `while` guard keeps the cache non-empty for as long as
            // we're inside the loop, so `array_key_first()` is never null.
            unset($this->_cache[array_key_first($this->_cache)]);
        }

        $this->_cache[$key] = $value;
    }
}
