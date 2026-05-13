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
use craftpulse\tailwind\models\Settings;
use craftpulse\tailwind\models\TypographyConfig;
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
    // Const Properties
    // =========================================================================

    /**
     * Maximum stack-frame count to scan when resolving a Twig template call site.
     *
     * The recording path only runs when the Yii debug module is loaded, so
     * the cost of the cap is paid only in dev. Raise this if a reported
     * case shows the originating template missing from the panel because
     * the Twig frame sits deeper than the current limit.
     */
    private const CALL_SITE_TRACE_DEPTH = 25;

    /**
     * Hard cap on unique merge inputs recorded for the debug panel.
     *
     * Recording is gated on the Yii debug module so this only matters in
     * dev. A page that triggers thousands of unique merge inputs is rare
     * but possible (rich CMS data with many editor-chosen colors, etc.);
     * the cap keeps memory bounded and keeps the panel table renderable.
     * Already-recorded inputs continue to increment their `count` past
     * the cap — only new unique inputs are dropped.
     */
    private const MAX_RECORDED_MERGES = 1000;

    // Public Properties
    // =========================================================================

    /**
     * Optional Settings override.
     *
     * When set, takes precedence over `Plugin::$plugin->getSettings()`.
     * Provided as a Yii-component-style injection seam so unit tests can
     * exercise specific configurations (e.g. small `cacheSize`) without
     * bootstrapping the full plugin.
     *
     * Reassigning this property after any merges have been cached will
     * leave stale entries in the LRU keyed against the previous
     * configuration. Call {@see self::clearCache()} immediately after
     * reassignment so subsequent merges don't serve results computed
     * against the old settings.
     *
     * @var ?Settings
     */
    public ?Settings $settings = null;

    // Private Properties
    // =========================================================================

    /**
     * The initialized v3 merge engine instance.
     *
     * @var ?TailwindMergeV3
     */
    private ?TailwindMergeV3 $_mergerV3 = null;

    /**
     * Signature describing the configuration the v3 merger was built with.
     *
     * Tracked so a settings change via the `$settings` injection seam (used
     * by the test suite) rebuilds the merger when its config inputs differ,
     * instead of silently serving merges from a stale engine.
     *
     * @var ?string
     */
    private ?string $_mergerV3Signature = null;

    /**
     * The initialized v4 merge engine instance.
     *
     * @var ?TailwindMergeV4
     */
    private ?TailwindMergeV4 $_mergerV4 = null;

    /**
     * Signature describing the configuration the v4 merger was built with.
     *
     * @var ?string
     */
    private ?string $_mergerV4Signature = null;

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
     * Memoized typography conflict-group config for the current request.
     *
     * Holds the cached `TypographyConfig` after the first call to
     * `_typographyConfig()`; `null` either means "not yet computed" or
     * "computed and resolved to null (feature disabled)". The
     * `$_typographyResolved` flag disambiguates the two.
     *
     * @var ?TypographyConfig
     */
    private ?TypographyConfig $_typography = null;

    /**
     * Whether the typography config has been resolved this request.
     *
     * @var bool
     */
    private bool $_typographyResolved = false;

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

    // Public Methods
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
     * Resets the request-scoped merge cache and memoized state.
     *
     * Internal test seam: the test suite calls this between scenarios
     * that mutate the `$settings` injection seam to force re-evaluation
     * against the new configuration. Production code in a standard Craft
     * deployment doesn't need to call this — the service is instantiated
     * fresh per request and discarded at the end. Resets the LRU cache,
     * hit/miss counters, recorded merges, and the memoized CSS variables
     * and typography config containers. The recording-enabled flag and
     * merger instances are intentionally preserved; the merger instances
     * are rebuilt lazily on the next `_getMergerVX()` call if their
     * stored signature no longer matches the current settings.
     *
     * @internal Exposed for the test suite, not for third-party callers.
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
        $this->_typography = null;
        $this->_typographyResolved = false;
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
     * Returns the resolved typography conflict-group configuration.
     *
     * Mirrors the value the merger getters use internally, exposed so
     * the debug toolbar panel can surface what conflict groups are
     * currently active. Returns `null` when the `typography` setting
     * is off.
     *
     * @return ?TypographyConfig The typography conflict groups, or null when disabled.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function typographyConfig(): ?TypographyConfig
    {
        return $this->_typographyConfig();
    }

    /**
     * Returns the configured CSS variables as a CssVariables instance.
     *
     * Memoized for the request lifetime.
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

    // Private Methods
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
        return match ($this->getVersion()) {
            VersionDetector::VERSION_4 => $this->_getMergerV4(),
            default => $this->_getMergerV3(),
        };
    }

    /**
     * Normalizes the configured prefix to its bare form.
     *
     * Strips a trailing hyphen so a v3 user who pasted `'tw-'` from the
     * v3 docs ends up with the same internal value as a v4 user who
     * typed `'tw'`. Returns an empty string when no prefix is configured.
     *
     * @return string The bare prefix, or '' when none is configured.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    private function _bareNormalizedPrefix(): string
    {
        $prefix = $this->_settings()?->prefix ?? '';

        return rtrim($prefix, '-');
    }

    /**
     * Returns the typography conflict-group config for the current request.
     *
     * Memoized for the request lifetime. `_getMergerV3()` and `_getMergerV4()`
     * invoke this on every cache miss to compute the merger signature;
     * the first call resolves from settings, subsequent calls return the
     * cached instance (or the cached `null` when typography is disabled).
     * Cache hits in the merge LRU never reach this method.
     *
     * @return ?TypographyConfig The configured typography conflict groups, or null when off.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    private function _typographyConfig(): ?TypographyConfig
    {
        if ($this->_typographyResolved) {
            return $this->_typography;
        }

        $settings = $this->_settings();

        if ($settings !== null && $settings->typography) {
            $this->_typography = new TypographyConfig(
                $settings->typographyExtraSizes,
                $settings->typographyExtraColors,
            );
        }

        $this->_typographyResolved = true;

        return $this->_typography;
    }

    /**
     * Gets or initializes the v3 merge engine.
     *
     * The v3 library expects `prefix` in the fused form (e.g. `'tw-'`),
     * which matches the v3 class syntax `tw-px-4`. The bare prefix from
     * settings is appended with `-` here before being passed to the engine.
     *
     * @return TailwindMergeV3 The v3 merge engine instance.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    private function _getMergerV3(): TailwindMergeV3
    {
        $prefix = $this->_bareNormalizedPrefix();
        $typography = $this->_typographyConfig();
        $signature = 'v3|prefix=' . $prefix . '|typography=' . ($typography?->signature() ?? '');

        if ($this->_mergerV3 !== null && $this->_mergerV3Signature === $signature) {
            return $this->_mergerV3;
        }

        $config = $this->_buildMergerConfig($prefix, $typography, fusedV3Prefix: true);
        $factory = TailwindMergeV3::factory();

        if ($config !== []) {
            $factory = $factory->withConfiguration($config);
        }

        // `Factory::make()` writes the merged config into a class-level static
        // on `TailwindMerge\Support\Config`, and the resulting merger reads
        // conflict-group data from that static on every `merge()` call (not
        // from the instance). One service per request keeps this safe; a
        // second `TailwindService` or a direct `TailwindMergeV3::factory()`
        // call elsewhere in the same request would stomp the static, leaving
        // any earlier merger reading the wrong groups. `clearCache()` plus
        // the signature-based rebuild keep this contained for the test suite.
        $this->_mergerV3 = $factory->make();
        $this->_mergerV3Signature = $signature;

        return $this->_mergerV3;
    }

    /**
     * Gets or initializes the v4 merge engine.
     *
     * The v4 library expects `prefix` in bare form (e.g. `'tw'`), which
     * matches the v4 class syntax `tw:px-4` — the library's class-name
     * parser appends the colon itself.
     *
     * @return TailwindMergeV4 The v4 merge engine instance.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    private function _getMergerV4(): TailwindMergeV4
    {
        $prefix = $this->_bareNormalizedPrefix();
        $typography = $this->_typographyConfig();
        $signature = 'v4|prefix=' . $prefix . '|typography=' . ($typography?->signature() ?? '');

        if ($this->_mergerV4 !== null && $this->_mergerV4Signature === $signature) {
            return $this->_mergerV4;
        }

        $config = $this->_buildMergerConfig($prefix, $typography, fusedV3Prefix: false);

        $this->_mergerV4 = new TailwindMergeV4($config);
        $this->_mergerV4Signature = $signature;

        return $this->_mergerV4;
    }

    /**
     * Builds the shared merge-engine configuration array.
     *
     * Both underlying libraries accept the same `['prefix' => ..., 'classGroups' => ...]`
     * shape; the only divergence is `prefix` value semantics (v3 wants the
     * fused trailing-hyphen form, v4 wants the bare form).
     *
     * @param string $prefix The bare prefix; '' when none is configured.
     * @param ?TypographyConfig $typography The typography conflict-group config, or null when disabled.
     * @param bool $fusedV3Prefix `true` to append `-` to the prefix for the v3 library.
     *
     * @return array<string, mixed> The config array, or `[]` when neither knob is engaged.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    private function _buildMergerConfig(string $prefix, ?TypographyConfig $typography, bool $fusedV3Prefix): array
    {
        $config = [];

        if ($prefix !== '') {
            $config['prefix'] = $fusedV3Prefix ? $prefix . '-' : $prefix;
        }

        // array_merge (not array_merge_recursive) — the two sources contribute
        // distinct top-level keys (prefix vs classGroups). Recursive merge
        // would convert any colliding scalar (e.g. a future prefix override)
        // into an array, which neither engine expects.
        if ($typography !== null) {
            $config = array_merge($config, $typography->toMergeConfig());
        }

        return $config;
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

        if (count($this->_merges) >= self::MAX_RECORDED_MERGES) {
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
     * Resolves the calling Twig template path and source line via the backtrace.
     *
     * Uses the same technique as `Twig\Error\Error::guessTemplateInfo()`:
     * finds the nearest Twig template on the stack, takes its compiled PHP
     * file path, then walks the trace for a frame whose file matches and uses
     * the template's `getDebugInfo()` map to translate the compiled PHP line
     * into the original template line.
     *
     * The returned path is project-relative when the template lives under
     * the project root (typically `templates/foo.twig` for site templates
     * or `vendor/.../templates/bar.twig` for plugin-supplied templates).
     * Falls back to the loader-relative template name when the source path
     * is unavailable (string templates) or sits outside the project root.
     *
     * @return array{0: ?string, 1: ?int} Tuple of source path and source line.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    private function _resolveCallSite(): array
    {
        $trace = debug_backtrace(
            DEBUG_BACKTRACE_IGNORE_ARGS | DEBUG_BACKTRACE_PROVIDE_OBJECT,
            self::CALL_SITE_TRACE_DEPTH,
        );

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
        $displayPath = $this->_displayTemplatePath($template);

        if ($compiledFile === false) {
            return [$displayPath, null];
        }

        foreach ($trace as $frame) {
            if (!isset($frame['file'], $frame['line']) || $frame['file'] !== $compiledFile) {
                continue;
            }

            foreach ($template->getDebugInfo() as $phpLine => $twigLine) {
                if ($phpLine <= $frame['line']) {
                    return [$displayPath, $twigLine];
                }
            }

            break;
        }

        return [$displayPath, null];
    }

    /**
     * Resolves a Twig template's display path for the debug panel.
     *
     * Prefers the source-context path made relative to the project root
     * (e.g. `templates/_components/button.twig`) so editors recognize the
     * file at a glance. Falls back to the loader-relative template name
     * for string templates or when the source path can't be resolved.
     *
     * @param Template $template The Twig template object.
     *
     * @return string The display path or template name.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    private function _displayTemplatePath(Template $template): string
    {
        $sourcePath = '';

        try {
            $sourcePath = $template->getSourceContext()->getPath();
        } catch (\Throwable) {
            $sourcePath = '';
        }

        if ($sourcePath === '') {
            return $template->getTemplateName();
        }

        // Prefer making the path relative to Craft's @templates alias — site
        // templates show as `atoms/button.twig` (no redundant `templates/`
        // prefix) since editors already know where their template root is.
        $templates = Craft::getAlias('@templates', false);

        if (is_string($templates) && $templates !== '' && str_starts_with($sourcePath, $templates . DIRECTORY_SEPARATOR)) {
            return substr($sourcePath, strlen($templates) + 1);
        }

        // Fall back to project-root-relative for plugin-supplied templates
        // (e.g. `vendor/foo/templates/bar.twig`) so the path still anchors
        // to a recognizable location.
        $root = Craft::getAlias('@root', false);

        if (is_string($root) && $root !== '' && str_starts_with($sourcePath, $root . DIRECTORY_SEPARATOR)) {
            return substr($sourcePath, strlen($root) + 1);
        }

        return $sourcePath;
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

        if (count($this->_cache) >= $maxSize) {
            unset($this->_cache[array_key_first($this->_cache)]);
        }

        $this->_cache[$key] = $value;
    }
}
