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
 * 1. Look for `tailwind.config.js` / `tailwind.config.ts` → v3
 * 2. Look for `@theme` in CSS files → v4
 * 3. Check `package.json` for `tailwindcss` version → parse semver
 * 4. Fall back to plugin settings
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

    /**
     * Unknown version identifier.
     */
    public const VERSION_UNKNOWN = 'unknown';

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
     * @param ?string $rootPath Override the project root path (for testing).
     *
     * @return string The detected version: '3', '4', or 'unknown'.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function detect(string $configuredVersion = 'auto', ?string $rootPath = null): string
    {
        // If explicitly configured, return immediately
        if ($configuredVersion !== 'auto') {
            return $configuredVersion;
        }

        // Return cached result if available
        if ($this->_detectedVersion !== null) {
            return $this->_detectedVersion;
        }

        $root = $rootPath ?? $this->_getProjectRoot();

        $this->_detectedVersion = $this->_detectFromConfigFiles($root)
            ?? $this->_detectFromCssFiles($root)
            ?? $this->_detectFromPackageJson($root)
            ?? self::VERSION_UNKNOWN;

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
     * Detects v3 from the presence of Tailwind config files.
     *
     * Checks for `tailwind.config.js` or `tailwind.config.ts` in the
     * project root and `buildchain/` directory.
     *
     * @param string $root The project root path.
     *
     * @return ?string '3' if config files found, null otherwise.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    private function _detectFromConfigFiles(string $root): ?string
    {
        $configFiles = [
            'tailwind.config.js',
            'tailwind.config.ts',
            'tailwind.config.cjs',
            'tailwind.config.mjs',
        ];

        $searchDirs = [
            $root,
            $root . DIRECTORY_SEPARATOR . 'buildchain',
        ];

        foreach ($searchDirs as $dir) {
            foreach ($configFiles as $file) {
                if (file_exists($dir . DIRECTORY_SEPARATOR . $file)) {
                    return self::VERSION_3;
                }
            }
        }

        return null;
    }

    /**
     * Detects v4 from `@theme` directives in CSS files.
     *
     * Scans CSS files in the `src/css/` directory for the `@theme`
     * directive, which is a Tailwind v4 feature.
     *
     * @param string $root The project root path.
     *
     * @return ?string '4' if @theme directive found, null otherwise.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    private function _detectFromCssFiles(string $root): ?string
    {
        $cssDir = $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'css';

        if (!is_dir($cssDir)) {
            return null;
        }

        $cssFiles = glob($cssDir . DIRECTORY_SEPARATOR . '*.css');

        if ($cssFiles === false) {
            return null;
        }

        foreach ($cssFiles as $cssFile) {
            $contents = file_get_contents($cssFile);

            if ($contents !== false && preg_match('/@theme\b/', $contents)) {
                return self::VERSION_4;
            }
        }

        return null;
    }

    /**
     * Detects the Tailwind version from `package.json`.
     *
     * Parses the semver version of the `tailwindcss` dependency
     * to determine the major version.
     *
     * @param string $root The project root path.
     *
     * @return ?string The major version ('3' or '4'), or null if not found.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    private function _detectFromPackageJson(string $root): ?string
    {
        $packageJsonPath = $root . DIRECTORY_SEPARATOR . 'package.json';

        if (!file_exists($packageJsonPath)) {
            return null;
        }

        $contents = file_get_contents($packageJsonPath);

        if ($contents === false) {
            return null;
        }

        $json = json_decode($contents, true);

        if (!is_array($json)) {
            return null;
        }

        $version = $json['dependencies']['tailwindcss']
            ?? $json['devDependencies']['tailwindcss']
            ?? null;

        if ($version === null || !is_string($version)) {
            return null;
        }

        return $this->_parseMajorVersion($version);
    }

    /**
     * Parses the major version number from a semver string.
     *
     * Handles prefixes like `^`, `~`, `>=`, and exact versions.
     *
     * @param string $version The semver version string.
     *
     * @return ?string The major version ('3' or '4'), or null if unrecognizable.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    private function _parseMajorVersion(string $version): ?string
    {
        // Strip common semver prefixes
        $cleaned = ltrim($version, '^~>=<! ');

        if (preg_match('/^(\d+)/', $cleaned, $matches)) {
            return match ($matches[1]) {
                '3' => self::VERSION_3,
                '4' => self::VERSION_4,
                default => null,
            };
        }

        return null;
    }
}
