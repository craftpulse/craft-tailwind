<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

namespace craftpulse\tailwind\services;

use Craft;
use craft\base\Component;
use craft\console\Application as ConsoleApplication;
use craft\web\Application as WebApplication;

/**
 * Auto-detects the Tailwind CSS version used in the current project.
 *
 * Detection strategy (in order of priority):
 * 1. CSS signals: `@import "tailwindcss"` or `@theme` in CSS/PostCSS files -> v4
 * 2. Config files: `tailwind.config.{js,ts,cjs,mjs}` -> v3
 * 3. Fallback to v4 (the forward path) with a devMode warning
 *
 * Results are cached for the request lifecycle.
 *
 * @author CraftPulse
 * @since 5.0.0
 */
class VersionDetector extends Component
{
    // =========================================================================
    // = Const Properties
    // =========================================================================

    /**
     * Tailwind version 3 identifier.
     */
    public const VERSION_3 = '3';

    /**
     * Tailwind version 4 identifier.
     */
    public const VERSION_4 = '4';

    // =========================================================================
    // = Private Properties
    // =========================================================================

    /**
     * Cached detection result for the current request.
     *
     * @var ?string
     */
    private ?string $_detectedVersion = null;

    // =========================================================================
    // = Public Methods
    // =========================================================================

    /**
     * Detects the Tailwind version from the project files.
     *
     * Uses a priority-based detection strategy and caches the result
     * for the lifetime of the current request.
     *
     * @param string $configuredVersion The version from plugin settings ('auto', '3', '4').
     * @param ?string $buildchainPath Directory containing tailwind.config.* files.
     * @param ?string $cssPath Directory containing Tailwind CSS entry files.
     * @param ?string $rootPath Override the project root path (for testing).
     *
     * @return string The detected version: '3' or '4'.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function detect(
        string $configuredVersion = 'auto',
        ?string $buildchainPath = null,
        ?string $cssPath = null,
        ?string $rootPath = null,
    ): string {
        // If explicitly configured, return immediately
        if ($configuredVersion !== 'auto') {
            return $configuredVersion;
        }

        // Return cached result if available
        if ($this->_detectedVersion !== null) {
            return $this->_detectedVersion;
        }

        $root = $rootPath ?? $this->_getProjectRoot();
        $effectiveCssPath = $cssPath ?? $root;
        $effectiveBuildchainPath = $buildchainPath ?? $root;

        // Priority 1: CSS signals (v4 is definitively detected even if a legacy config exists)
        $cssResult = $this->_detectFromCssFiles($effectiveCssPath);

        if ($cssResult !== null) {
            $this->_detectedVersion = $cssResult;
            return $this->_detectedVersion;
        }

        // Priority 2: Config file signals
        $configResult = $this->_detectFromConfigFiles($effectiveBuildchainPath);

        if ($configResult !== null) {
            $this->_detectedVersion = $configResult;
            return $this->_detectedVersion;
        }

        // Fallback: v4 is the forward path
        $this->_logDetectionFallback();
        $this->_detectedVersion = self::VERSION_4;

        return $this->_detectedVersion;
    }

    /**
     * Clears the cached detection result.
     *
     * Useful for testing or when project files have changed.
     *
     * @return void
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function clearCache(): void
    {
        $this->_detectedVersion = null;
    }

    // =========================================================================
    // = Private Methods
    // =========================================================================

    /**
     * Resolves the project root directory.
     *
     * @return string The absolute path to the project root.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    private function _getProjectRoot(): string
    {
        $alias = Craft::getAlias('@root', false);

        if (is_string($alias)) {
            return $alias;
        }

        if (defined('CRAFT_BASE_PATH')) {
            return CRAFT_BASE_PATH;
        }

        $webroot = Craft::getAlias('@webroot', false);

        if (is_string($webroot)) {
            return dirname($webroot);
        }

        return getcwd() ?: '.';
    }

    /**
     * Detects v4 from CSS signal directives in CSS/PostCSS files.
     *
     * Scans the given directory (non-recursive) for `.css` and `.pcss` files
     * containing `@import "tailwindcss"` or `@theme` blocks.
     *
     * @param string $searchPath The directory to scan for CSS files.
     *
     * @return ?string '4' if v4 signals found, null otherwise.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    private function _detectFromCssFiles(string $searchPath): ?string
    {
        if (!is_dir($searchPath)) {
            return null;
        }

        // Two glob calls instead of `*.{css,pcss}` with GLOB_BRACE,
        // which is unavailable on some libcs (notably musl).
        $cssFiles = array_merge(
            glob($searchPath . DIRECTORY_SEPARATOR . '*.css') ?: [],
            glob($searchPath . DIRECTORY_SEPARATOR . '*.pcss') ?: [],
        );

        if ($cssFiles === []) {
            return null;
        }

        foreach ($cssFiles as $cssFile) {
            $contents = file_get_contents($cssFile);

            if ($contents === false) {
                continue;
            }

            if (preg_match('/@import\s+["\']tailwindcss["\']/', $contents)) {
                return self::VERSION_4;
            }

            if (preg_match('/@theme\b/', $contents)) {
                return self::VERSION_4;
            }
        }

        return null;
    }

    /**
     * Detects v3 from the presence of Tailwind config files.
     *
     * Checks for `tailwind.config.{js,ts,cjs,mjs}` in the given directory
     * (non-recursive, just that single directory).
     *
     * @param string $searchPath The directory to scan for config files.
     *
     * @return ?string '3' if config files found, null otherwise.
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    private function _detectFromConfigFiles(string $searchPath): ?string
    {
        if (!is_dir($searchPath)) {
            return null;
        }

        $configFiles = [
            'tailwind.config.js',
            'tailwind.config.ts',
            'tailwind.config.cjs',
            'tailwind.config.mjs',
        ];

        foreach ($configFiles as $file) {
            if (file_exists($searchPath . DIRECTORY_SEPARATOR . $file)) {
                return self::VERSION_3;
            }
        }

        return null;
    }

    /**
     * Logs a warning when auto-detection falls back to v4 without signals.
     *
     * Only logs in devMode to avoid noise in production.
     *
     * @return void
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    private function _logDetectionFallback(): void
    {
        // The global `Craft` class isn't in composer's PSR-4 autoload —
        // a real Craft request loads it via the framework bootstrap. The
        // `false` flag skips autoload so unit tests (which don't boot
        // Craft) silently no-op here without surfacing a class-not-found.
        if (!class_exists(Craft::class, false)) {
            return;
        }

        if (!Craft::$app instanceof WebApplication && !Craft::$app instanceof ConsoleApplication) {
            return;
        }

        if (!Craft::$app->getConfig()->getGeneral()->devMode) {
            return;
        }

        Craft::warning(
            'Tailwind version auto-detection found no signals. '
            . 'Falling back to v4. Set tailwindVersion explicitly in config/tailwind.php '
            . 'or plugin settings to silence this warning.',
            'tailwind',
        );
    }
}
