<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

namespace craftpulse\tailwind\models;

use Craft;
use Stringable;

/**
 * Immutable container for CSS custom properties (variables).
 *
 * Provides rendering as raw CSS (`:root { ... }`) or as an inline
 * `<style>` tag. Values are sanitized to prevent CSS injection.
 * Variable names without a `--` prefix are automatically prefixed.
 *
 * @author CraftPulse
 * @since 1.0.0
 */
class CssVariables implements Stringable
{
    // =========================================================================
    // = Const Properties
    // =========================================================================

    /**
     * Regex pattern for safe CSS custom property values.
     *
     * Allows letters, digits, hyphens, underscores, dots, hashes,
     * commas, parentheses, percent signs, forward slashes, spaces, and quotes.
     */
    private const SAFE_VALUE_PATTERN = '/^[a-zA-Z0-9\-_.\#,()%\/\s\'"]+$/';

    // =========================================================================
    // = Private Properties
    // =========================================================================

    /**
     * The normalized CSS custom properties.
     *
     * Keys are guaranteed to start with `--`.
     *
     * @var array<string, string>
     */
    private array $_variables;

    // =========================================================================
    // = Public Methods
    // =========================================================================

    /**
     * Constructor.
     *
     * Normalizes keys to ensure they start with `--` and filters out
     * entries with unsafe values.
     *
     * @param array<string, string> $variables CSS custom properties as key-value pairs.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function __construct(array $variables)
    {
        $this->_variables = $this->_normalize($variables);
    }

    /**
     * Converts the variables to a CSS `:root` block string.
     *
     * @return string The CSS string, or empty string if no variables.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function __toString(): string
    {
        return $this->asCss();
    }

    /**
     * Returns the variables as a CSS `:root` block.
     *
     * Produces output like: `:root { --color-brand: #222; --size-lg: 1.5rem; }`
     *
     * @return string The CSS string, or empty string if no variables.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function asCss(): string
    {
        if ($this->_variables === []) {
            return '';
        }

        $declarations = [];

        foreach ($this->_variables as $name => $value) {
            $declarations[] = sprintf('  %s: %s;', $name, $value);
        }

        return ':root {' . "\n" . implode("\n", $declarations) . "\n" . '}';
    }

    /**
     * Returns the variables wrapped in a `<style>` tag.
     *
     * @return string The style tag HTML, or empty string if no variables.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function asStyleTag(): string
    {
        $css = $this->asCss();

        if ($css === '') {
            return '';
        }

        return '<style>' . $css . '</style>';
    }

    /**
     * Gets the value of a single CSS variable by name.
     *
     * The name is auto-prefixed with `--` if missing.
     *
     * @param string $name The CSS variable name (with or without `--` prefix).
     *
     * @return ?string The variable value, or null if not found.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function get(string $name): ?string
    {
        $normalizedName = str_starts_with($name, '--') ? $name : '--' . $name;

        return $this->_variables[$normalizedName] ?? null;
    }

    /**
     * Checks whether a CSS variable exists by name.
     *
     * The name is auto-prefixed with `--` if missing.
     *
     * @param string $name The CSS variable name (with or without `--` prefix).
     *
     * @return bool Whether the variable exists.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function has(string $name): bool
    {
        $normalizedName = str_starts_with($name, '--') ? $name : '--' . $name;

        return isset($this->_variables[$normalizedName]);
    }

    /**
     * Returns all CSS variables as an associative array.
     *
     * @return array<string, string> All normalized CSS variable key-value pairs.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function all(): array
    {
        return $this->_variables;
    }

    /**
     * Checks whether the variable collection is empty.
     *
     * @return bool Whether there are no variables.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function isEmpty(): bool
    {
        return $this->_variables === [];
    }

    // =========================================================================
    // = Private Methods
    // =========================================================================

    /**
     * Normalizes the input array by prefixing keys and filtering unsafe values.
     *
     * Keys without a `--` prefix are silently prefixed. Values that fail
     * the safe-character whitelist check are silently dropped (with a
     * devMode log entry).
     *
     * @param array<string, string> $variables Raw input variables.
     *
     * @return array<string, string> The normalized, safe variables.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    private function _normalize(array $variables): array
    {
        $normalized = [];

        foreach ($variables as $key => $value) {
            $name = str_starts_with((string) $key, '--') ? (string) $key : '--' . $key;

            if (!is_string($value) || $value === '') {
                $this->_logUnsafeValue($name, 'empty or non-string value');
                continue;
            }

            if (!preg_match(self::SAFE_VALUE_PATTERN, $value)) {
                $this->_logUnsafeValue($name, 'contains unsafe characters');
                continue;
            }

            $normalized[$name] = $value;
        }

        return $normalized;
    }

    /**
     * Logs a warning about an unsafe CSS variable value in devMode.
     *
     * @param string $name The CSS variable name.
     * @param string $reason The reason the value was rejected.
     *
     * @return void
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    private function _logUnsafeValue(string $name, string $reason): void
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
                    sprintf('CSS variable "%s" skipped: %s.', $name, $reason),
                    'tailwind',
                );
            }
        } catch (\Throwable) {
            // Silently ignore — Craft may not be fully initialized.
        }
    }
}
