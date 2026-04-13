<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

namespace craftpulse\tailwind\services;

use craft\base\Component;
use craftpulse\tailwind\models\ClassList;
use craftpulse\tailwind\models\CssVariables;
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

    /**
     * Recorded merge operations for the debug panel.
     *
     * Each entry is keyed by the merge input string for deduplication.
     *
     * @var array<string, array{input: string, output: string, resolved: bool, cacheHit: bool, template: ?string, line: ?int, count: int}>
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

        // Check LRU cache
        $cacheKey = $input;
        if (isset($this->_cache[$cacheKey])) {
            $this->_touchCacheKey($cacheKey);
            $result = $this->_cache[$cacheKey];

            if ($this->_recording) {
                $this->_recordMerge($input, $result, true);
            }

            return $result;
        }

        $result = $this->_getMerger()->merge($input);
        $this->_addToCache($cacheKey, $result);

        if ($this->_recording) {
            $this->_recordMerge($input, $result, false);
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
     * @return array<int, array{input: string, output: string, resolved: bool, cacheHit: bool, template: ?string, line: ?int, count: int}>
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
     * @since 5.0.0
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
     * @since 5.0.0
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
     * @since 5.0.0
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
        return Plugin::$plugin?->versionDetector;
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
     * @param bool $cacheHit Whether the result came from the cache.
     *
     * @return void
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    private function _recordMerge(string $input, string $output, bool $cacheHit): void
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
            'cacheHit' => $cacheHit,
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
     * Evicts the oldest entry when the cache exceeds the configured size.
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
     * @since 5.0.0
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
