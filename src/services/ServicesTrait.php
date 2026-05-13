<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

namespace craftpulse\tailwind\services;

use yii\base\InvalidConfigException;

/**
 * Service component registration and typed accessors for the Tailwind plugin.
 *
 * The static `config()` method is read by Craft's plugin loader on
 * instantiation; each entry under `components` is registered as a Yii
 * component on the plugin instance and built lazily on first access via
 * the typed getters below.
 *
 * @property TailwindService $tailwind The main Tailwind service.
 * @property VersionDetector $versionDetector The version detector service.
 *
 * @author CraftPulse
 * @since 5.0.0
 */
trait ServicesTrait
{
    // Public Methods
    // =========================================================================

    /**
     * Returns the dynamic plugin config consumed by Craft's plugin loader.
     *
     * @return array{components: array<string, class-string>}
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public static function config(): array
    {
        return [
            'components' => [
                'tailwind' => TailwindService::class,
                'versionDetector' => VersionDetector::class,
            ],
        ];
    }

    /**
     * Returns the Tailwind service.
     *
     * @return TailwindService
     *
     * @throws InvalidConfigException
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function getTailwind(): TailwindService
    {
        return $this->get('tailwind');
    }

    /**
     * Returns the version detector service.
     *
     * @return VersionDetector
     *
     * @throws InvalidConfigException
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function getVersionDetector(): VersionDetector
    {
        return $this->get('versionDetector');
    }
}
