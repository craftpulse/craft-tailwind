<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

namespace craftpulse\tailwind\services;

use Craft;
use craft\base\Component;

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
 * @since 1.0.0
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
     * @since 1.0.0
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
     * @since 1.0.0
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
     * @since 1.0.0
     */
    private function _getProjectRoot(): string
    {
        $alias = Craft::getAlias('@root', false);

        if ($alias !== false) {
            return $alias;
        }

        if (defined('CRAFT_BASE_PATH')) {
            return CRAFT_BASE_PATH;
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
     * @since 1.0.0
     */
    private function _detectFromCssFiles(string $searchPath): ?string
    {
        if (!is_dir($searchPath)) {
            return null;
        }

        $cssFiles = glob($searchPath . DIRECTORY_SEPARATOR . '*.{css,pcss}', GLOB_BRACE);

        if ($cssFiles === false || $cssFiles === []) {
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
     * @since 1.0.0
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
     * @since 1.0.0
     */
    private function _logDetectionFallback(): void
    {
        if (!class_exists(Craft::class)) {
            return;
        }

        try {
            /** @var \craft\web\Application $app */
            $app = Craft::$app;
            $config = $app->getConfig();

            if ($config->getGeneral()->devMode) {
                Craft::warning(
                    'Tailwind version auto-detection found no signals. '
                    . 'Falling back to v4. Set tailwindVersion explicitly in config/tailwind.php '
                    . 'or plugin settings to silence this warning.',
                    'tailwind',
                );
            }
        } catch (\Throwable) {
            // Silently ignore — Craft may not be fully initialized (e.g., in tests).
        }
    }
}
