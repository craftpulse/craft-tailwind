<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

namespace craftpulse\tailwind;

use Craft;
use craft\base\Plugin as BasePlugin;

use craftpulse\tailwind\base\PluginTrait;
use craftpulse\tailwind\models\Settings;
use craftpulse\tailwind\services\ServicesTrait;

/**
 * Tailwind plugin for Craft CMS 5.
 *
 * Provides Tailwind CSS class merging, named-slot class builders,
 * and automatic version detection for Craft CMS templates.
 *
 * @method Settings getSettings()
 *
 * @author CraftPulse
 * @since 5.0.0
 */
class Tailwind extends BasePlugin
{
    // Traits
    // =========================================================================

    use PluginTrait;
    use ServicesTrait;

    // Static Properties
    // =========================================================================

    /**
     * Static reference to the plugin instance.
     *
     * @var ?Tailwind
     */
    public static ?Tailwind $plugin = null;

    // Public Properties
    // =========================================================================

    /**
     * @var string The schema version for this plugin.
     */
    public string $schemaVersion = '1.0.0';

    /**
     * @var bool Whether the plugin has a settings page in the control panel.
     */
    public bool $hasCpSettings = true;

    /**
     * @var bool Whether the settings page is viewable when `allowAdminChanges`
     * is disabled. Matches Craft's first-party behavior for plugins that
     * surface settings — editors can still see what's configured in production
     * even though they cannot change it.
     */
    public bool $hasReadOnlyCpSettings = true;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     *
     * @return void
     *
     * @author CraftPulse
     * @since 5.0.0
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        Craft::setAlias('@craftpulse/tailwind', __DIR__);

        $this->_registerVariables();
        $this->_registerAutoInject();
        $this->_registerDebugPanel();
        $this->_registerCpUrlRules();
    }
}
